<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ninja Forms.
 *
 * Ninja Forms renders through a Backbone application rather than as server-side HTML: the form
 * model is serialised into the page as JSON and drawn client-side from an Underscore template.
 * Two consequences shape this integration.
 *
 * **The captcha travels as a string inside that model.** `ninja_forms_display_after_fields`
 * supplies the `afterFields` setting, which the template prints inside the form element. The
 * markup is re-generated on every page load — verified by probing the filter across three
 * consecutive requests and getting three different values — so the captcha does not freeze the
 * way it once did behind Elementor's element cache.
 *
 * **Our fields are not part of the submission by default.** Ninja Forms does not post the form
 * element; it builds its own payload from the Backbone models and sends that. Inputs injected
 * into the markup are simply not in it. The companion JavaScript module copies their values
 * into the model's `extra` bag on `before:submit`, which is what arrives here as
 * `$form_data['extra']`.
 *
 * @see assets/src/compatibility/NinjaForms.js
 */
class ControllerNinjaForms extends BaseController {

	protected string $name = 'Ninja Forms';
	protected string $id = 'ninjaforms';
	protected string $settings_key = 'protection_ninjaforms_enable';

	protected array $hooks = [
		[ 'type' => 'filter', 'hook' => 'ninja_forms_display_after_fields', 'method' => 'wp_add_spam_protection', 'priority' => 100, 'args' => 2 ],
		[ 'type' => 'filter', 'hook' => 'ninja_forms_submit_data', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 1 ],
	];

	public function is_installed(): bool {
		return class_exists( 'Ninja_Forms' );
	}

	/**
	 * Supply the captcha as the form's `afterFields` markup.
	 *
	 * @param mixed ...$args string $html, int $form_id.
	 *
	 * @return string
	 */
	public function wp_add_spam_protection( ...$args ) {
		$html    = (string) ( $args[0] ?? '' );
		$form_id = isset( $args[1] ) ? (string) $args[1] : null;

		return $html . sprintf(
			'<div class="f12-captcha-wrapper nf-field">%s</div>',
			$this->get_captcha_html( $form_id )
		);
	}

	/**
	 * Judge the submission.
	 *
	 * @param mixed ...$args array $form_data.
	 *
	 * @return mixed The payload, unchanged unless the submission is rejected.
	 */
	public function wp_is_spam( ...$args ) {
		$form_data = $args[0] ?? null;

		// Ninja Forms always passes an array, but the filter is public and other plugins hook
		// it too. Handing back whatever arrived beats replacing someone else's payload.
		if ( ! is_array( $form_data ) ) {
			return $form_data;
		}

		$post_data = $this->collect_submitted_fields( $form_data );

		// Nothing of ours came through. That is either a form rendered before this integration
		// was enabled or a client that never ran our JavaScript; either way the protection
		// modules decide, working from an empty set — the missing captcha is itself the signal.
		$form_id = isset( $form_data['id'] ) ? (string) $form_data['id'] : null;
		$message = $this->check_spam( $post_data, $form_id );

		if ( $message === null ) {
			return $form_data;
		}

		$field_id = $this->first_field_id( $form_data );

		if ( $field_id === null ) {
			// No field to hang the error on. Ninja Forms offers no form-level error to a filter,
			// so rather than fail open silently, leave a trace for whoever debugs this.
			$this->get_logger()->error( 'Ninja Forms submission is spam but has no field to report it on.', [
				'plugin'  => 'f12-cf7-captcha',
				'form_id' => $form_id,
			] );

			return $form_data;
		}

		// Ninja_Forms\AJAX\Controllers\Submission reads this back while processing each field
		// and responds immediately when it finds one, which aborts the submission.
		$form_data['errors']['fields'][ $field_id ] = $this->format_spam_message( $message );

		return $form_data;
	}

	/**
	 * Rebuild a conventional POST array from the Backbone payload.
	 *
	 * The protection modules expect field name => value. Ninja Forms delivers our hidden fields
	 * under `extra` (put there by the JS module) and the visitor's own answers under `fields`,
	 * keyed by numeric field ID with the value one level down.
	 *
	 * @param array $form_data
	 *
	 * @return array<string, mixed>
	 */
	private function collect_submitted_fields( array $form_data ): array {
		$post_data = [];

		if ( isset( $form_data['extra'] ) && is_array( $form_data['extra'] ) ) {
			foreach ( $form_data['extra'] as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$post_data[ (string) $key ] = $value;
				}
			}
		}

		if ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
			foreach ( $form_data['fields'] as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['value'] ) || ! is_scalar( $field['value'] ) ) {
					continue;
				}

				// Prefer the author-defined key ("email", "message") over the numeric ID: the
				// content heuristics report field names, and a number says nothing.
				$name = isset( $field['key'] ) && is_string( $field['key'] ) && $field['key'] !== ''
					? $field['key']
					: (string) ( $field['id'] ?? count( $post_data ) );

				$post_data[ $name ] = $field['value'];
			}
		}

		return $post_data;
	}

	/**
	 * The field an error can be attached to.
	 *
	 * Ninja Forms only surfaces per-field errors to this filter, so a form-wide rejection has to
	 * borrow one. The first field is the one nearest the top of the form, which is where a
	 * visitor will look.
	 *
	 * @param array $form_data
	 *
	 * @return int|string|null
	 */
	private function first_field_id( array $form_data ) {
		if ( ! isset( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) || empty( $form_data['fields'] ) ) {
			return null;
		}

		$ids = array_keys( $form_data['fields'] );

		return $ids[0];
	}
}
