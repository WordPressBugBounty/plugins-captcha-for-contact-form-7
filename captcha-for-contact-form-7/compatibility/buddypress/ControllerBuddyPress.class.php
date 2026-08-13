<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyPress — the member registration form.
 *
 * Registration is the form worth protecting here: an open BuddyPress signup is a standing
 * invitation to fill a community with profile spam, and unlike a contact form the damage is
 * persistent and public.
 *
 * The form is plain `method="post"` with no JavaScript assembling the payload, so anything
 * rendered inside it is submitted with it.
 *
 * ## Aborting a signup, and the awkward bit about saying why
 *
 * `bp_signup_validate` runs immediately before the check that decides everything:
 *
 *     do_action( 'bp_signup_validate' );
 *     if ( ! empty( $bp->signup->errors ) ) { … display … } else { … create the account … }
 *
 * Any non-empty entry aborts, so stopping the signup is easy. Being *understood* is not:
 * BuddyPress renders errors only through per-field hooks — `bp_signup_username_errors`,
 * `bp_signup_email_errors` and so on — and there is no generic slot. Attaching a captcha
 * failure to the e-mail field would abort correctly and tell the visitor something false about
 * their address.
 *
 * So this does both, deliberately: the error goes under its own key, which aborts without
 * pretending a field was wrong, and the reason is added through `bp_core_add_message()`, the
 * notice area BuddyPress renders above the form for exactly this purpose.
 */
class ControllerBuddyPress extends BaseController {

	protected string $id = 'buddypress';
	protected string $settings_key = 'protection_buddypress_enable';

	protected array $hooks = [
		[ 'type' => 'action', 'hook' => 'bp_before_registration_submit_buttons', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 0 ],
		[ 'type' => 'action', 'hook' => 'bp_signup_validate', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 0 ],
	];

	public function get_name(): string {
		return __( 'BuddyPress (Registration)', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		$is_installed = class_exists( 'BuddyPress' );

		$this->get_logger()->debug( 'BuddyPress installed: ' . ( $is_installed ? 'Yes' : 'No' ) );

		return $is_installed;
	}

	/**
	 * Render the captcha into the registration form.
	 *
	 * @param mixed ...$args Unused; BuddyPress passes nothing to this hook.
	 */
	public function wp_add_spam_protection( ...$args ) {
		printf(
			'<div class="f12-captcha-wrapper submit">%s</div>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally.
			$this->get_captcha_html()
		);
	}

	/**
	 * Judge the submission and stop the signup.
	 *
	 * @param mixed ...$args Unused.
	 *
	 * @return void
	 */
	public function wp_is_spam( ...$args ) {
		$bp = function_exists( 'buddypress' ) ? buddypress() : null;

		if ( ! isset( $bp->signup ) ) {
			return;
		}

		// BuddyPress has already validated the account details by this point and recorded any
		// problem in the same bag. A submission that is going to be refused anyway must not
		// also spend a captcha session.
		if ( ! empty( $bp->signup->errors ) ) {
			return;
		}

		$message = $this->check_spam();

		if ( $message === null ) {
			return;
		}

		$this->get_logger()->warning( 'Spam detected in the BuddyPress registration form.', [
			'plugin' => 'f12-cf7-captcha',
		] );

		$formatted = $this->format_spam_message( $message );

		// Aborts the signup. The key is ours rather than a field's: any non-empty entry stops
		// the account being created, and naming a real field here would tell the visitor that
		// something was wrong with a value they entered correctly.
		$bp->signup->errors['signup_f12_captcha'] = $formatted;

		// …and this is what they actually read, because no template renders an error for a key
		// BuddyPress does not know.
		if ( function_exists( 'bp_core_add_message' ) ) {
			bp_core_add_message( $formatted, 'error' );
		}
	}
}
