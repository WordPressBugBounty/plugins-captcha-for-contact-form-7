<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formidable Forms.
 *
 * The one form plugin every competing captcha plugin supports and this one did not — all five
 * compared (hCaptcha, Cloudflare Turnstile, CleanTalk, Google Captcha, Friendly Captcha) list
 * it, which made it the clearest gap in the integration set.
 *
 * Mercifully ordinary to integrate. `frm_entry_form` fires inside the form, after Formidable's
 * own honeypot and state fields and before the submit button, so the captcha lands in the right
 * place without any markup surgery. Formidable submits the form element itself, so the fields
 * arrive in `$_POST` and no JavaScript module is needed.
 */
class ControllerFormidable extends BaseController {

	protected string $name = 'Formidable Forms';
	protected string $id = 'formidable';
	protected string $settings_key = 'protection_formidable_enable';

	/**
	 * Key the rejection is filed under.
	 *
	 * Formidable keys field errors as `field{id}`; anything else lands in the form's general
	 * error area, which is where a form-wide rejection belongs — it is not the fault of any one
	 * field the visitor filled in.
	 */
	private const ERROR_KEY = 'f12_captcha';

	protected array $hooks = [
		[ 'type' => 'action', 'hook' => 'frm_entry_form', 'method' => 'wp_add_spam_protection', 'priority' => 100, 'args' => 3 ],
		[ 'type' => 'filter', 'hook' => 'frm_validate_entry', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 2 ],
	];

	public function is_installed(): bool {
		return class_exists( 'FrmAppHelper' );
	}

	/**
	 * Render the captcha into the form.
	 *
	 * An action, not a filter: Formidable prints whatever is echoed at this point.
	 *
	 * @param mixed ...$args object $form, string $form_action, array $errors.
	 */
	public function wp_add_spam_protection( ...$args ) {
		$form    = $args[0] ?? null;
		$form_id = is_object( $form ) && isset( $form->id ) ? (string) $form->id : null;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
		echo sprintf(
			'<div class="f12-captcha-wrapper frm_form_field frm_top_container">%s</div>',
			$this->get_captcha_html( $form_id )
		);
	}

	/**
	 * Judge the entry.
	 *
	 * Formidable insists on an array coming back — it calls `_doing_it_wrong()` otherwise — and
	 * treats any non-empty result as a failed submission.
	 *
	 * @param mixed ...$args array $errors, array $values.
	 *
	 * @return mixed
	 */
	public function wp_is_spam( ...$args ) {
		$errors = $args[0] ?? null;

		if ( ! is_array( $errors ) ) {
			return $errors;
		}

		// Formidable validated its own fields first and this filter runs either way. Judging an
		// entry that is already refused would consume a captcha session for a submission that
		// was never going to succeed.
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$values  = $args[1] ?? null;
		$form_id = is_array( $values ) && isset( $values['form_id'] ) ? (string) $values['form_id'] : null;

		$message = $this->check_spam( null, $form_id );

		if ( $message === null ) {
			return $errors;
		}

		$errors[ self::ERROR_KEY ] = $this->format_spam_message( $message );

		return $errors;
	}
}
