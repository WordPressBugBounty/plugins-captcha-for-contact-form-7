<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forminator Forms.
 *
 * The cheapest of the AJAX-submitting builders to support, for one reason: Forminator sends
 * `new FormData( form )` — the whole form element, serialised by the browser. Anything rendered
 * into the markup is therefore part of the submission automatically, so unlike Ninja Forms
 * this needs no JavaScript module and no payload translation. The fields arrive in `$_POST`
 * exactly as they would from a native submit.
 *
 * The captcha goes into the submit area rather than after the fields. Forminator hangs its own
 * honeypot off the same filter, which is a fair indication of where extra markup belongs.
 */
class ControllerForminator extends BaseController {

	protected string $id = 'forminator';
	protected string $settings_key = 'protection_forminator_enable';

	protected array $hooks = [
		[ 'type' => 'filter', 'hook' => 'forminator_render_form_submit_markup', 'method' => 'wp_add_spam_protection', 'priority' => 100, 'args' => 2 ],
		[ 'type' => 'filter', 'hook' => 'forminator_custom_form_submit_errors', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 3 ],
	];

	public function get_name(): string {
		return __( 'Forminator', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		return class_exists( 'Forminator' );
	}

	/**
	 * Put the captcha above the submit button.
	 *
	 * @param mixed ...$args string $html, int $form_id.
	 *
	 * @return string The submit-area markup with the captcha in front of it.
	 */
	public function wp_add_spam_protection( ...$args ) {
		$html    = (string) ( $args[0] ?? '' );
		$form_id = isset( $args[1] ) ? (string) $args[1] : null;

		return sprintf(
			'<div class="f12-captcha-wrapper forminator-row">%s</div>',
			$this->get_captcha_html( $form_id )
		) . $html;
	}

	/**
	 * Judge the submission.
	 *
	 * Forminator throws as soon as this filter returns a non-empty array, so adding an entry is
	 * enough to reject; the key only decides which field the message is shown against.
	 *
	 * @param mixed ...$args array $submit_errors, int $form_id, array $field_data_array.
	 *
	 * @return mixed
	 */
	public function wp_is_spam( ...$args ) {
		$errors = $args[0] ?? null;

		if ( ! is_array( $errors ) ) {
			return $errors;
		}

		// Forminator validates its own fields first and this filter fires either way. Judging a
		// submission that is already rejected would consume a captcha session for a request
		// that was never going to succeed.
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$form_id = isset( $args[1] ) ? (string) $args[1] : null;
		$message = $this->check_spam( null, $form_id );

		if ( $message === null ) {
			return $errors;
		}

		$field_id = $this->first_field_name( $args[2] ?? null );

		$errors[] = [ $field_id => $this->format_spam_message( $message ) ];

		return $errors;
	}

	/**
	 * The field the message is shown against — one the visitor can actually see.
	 *
	 * Forminator has no form-level error slot, so a form-wide rejection borrows a field. The
	 * first one is nearest the top, which is where a visitor looks. When the submission carried
	 * no fields at all there is nothing to borrow — the entry is still added under our own key,
	 * because a non-empty error array is what actually rejects the submission and losing that
	 * would let the spam through to keep the message tidy.
	 *
	 * "First" alone is not enough. A form whose first field is hidden — a tracking value, a
	 * pre-filled id — would have the refusal attached to something nobody can see: the visitor
	 * presses Send, no mail arrives, and nothing appears. That is the failure ControllerAvada
	 * was fixed for in 2.15.0, reached by a different route. Forminator cannot pick up a foreign
	 * plugin's honeypot the way Avada could, because this list comes from Forminator rather than
	 * from $_POST, but its own hidden fields are in it.
	 *
	 * Hidden-ness is judged from an explicit `type` where the payload carries one. The data
	 * Forminator hands this filter has only `name` and `value` as far as we have seen, so there
	 * is a second reading: element ids are `{type}-{n}`, which makes `hidden-1` recognisable
	 * without guessing at anything a third party might have injected. Neither test is trusted to
	 * exist — where nothing identifies a field as hidden, the old behaviour stands and the first
	 * field wins, which is no worse than before.
	 *
	 * @param mixed $field_data_array List of ['name' => id, 'value' => …] as Forminator builds it.
	 */
	private function first_field_name( $field_data_array ): string {
		if ( ! is_array( $field_data_array ) ) {
			return 'f12_captcha';
		}

		$first_named = null;

		foreach ( $field_data_array as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['name'] ) || ! is_string( $field['name'] ) || $field['name'] === '' ) {
				continue;
			}

			$name = $field['name'];

			if ( $first_named === null ) {
				$first_named = $name;
			}

			if ( self::looks_hidden( $field, $name ) ) {
				continue;
			}

			return $name;
		}

		// Every field looked hidden, or none carried a usable name. Falling back to the first
		// named field keeps the pre-2.15.2 behaviour rather than inventing a key Forminator
		// would not render at all.
		return $first_named ?? 'f12_captcha';
	}

	/**
	 * Whether a Forminator field is one the visitor cannot see.
	 *
	 * @param array  $field The field as posted.
	 * @param string $name  Its element id, e.g. `email-1` or `hidden-1`.
	 */
	private static function looks_hidden( array $field, string $name ): bool {
		if ( isset( $field['type'] ) && is_string( $field['type'] ) ) {
			return strtolower( $field['type'] ) === 'hidden';
		}

		return strpos( $name, 'hidden-' ) === 0;
	}
}
