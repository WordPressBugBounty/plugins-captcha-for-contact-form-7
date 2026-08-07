<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Password reset request forms, for both WordPress and WooCommerce.
 *
 * Left unprotected, this form is a mail cannon: it takes an address, sends a message to it, and
 * repeats for as many addresses as anyone cares to submit. The victim is not the site owner but
 * the strangers who receive the mail, and the lasting damage is to the sending domain's
 * reputation — which then costs the site every other mail it tries to deliver. That the abuse
 * produces no visible spam anywhere on the site is exactly why it goes unnoticed.
 *
 * ## Why one module for two plugins
 *
 * WordPress and WooCommerce render separate forms — `wp-login.php?action=lostpassword` and the
 * My Account page — but both route the request through the same `lostpassword_post` hook, and
 * both abort when it produces an error: core checks `$errors->has_errors()`,
 * WC_Shortcode_My_Account::retrieve_password() checks `$errors->get_error_code()`.
 *
 * Splitting this into a `wordpress-` and a `woocommerce-` module the way login and registration
 * are split would mean two modules hooking one validation point, so every reset request would
 * be judged twice — burning two captcha sessions and logging two blocks for one submission.
 * One module renders into whichever form is present and judges once.
 */
class ControllerLostPassword extends BaseController {

	protected string $name = 'Password Reset (WordPress & WooCommerce)';
	protected string $id = 'lostpassword';
	protected string $settings_key = 'protection_lostpassword_enable';

	protected array $hooks = [
		// Two render hooks, one per form. The WooCommerce one simply never fires when
		// WooCommerce is absent.
		[ 'type' => 'action', 'hook' => 'lostpassword_form', 'method' => 'wp_add_spam_protection' ],
		[ 'type' => 'action', 'hook' => 'woocommerce_lostpassword_form', 'method' => 'wp_add_spam_protection' ],
		[ 'type' => 'action', 'hook' => 'lostpassword_post', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2 ],
	];

	public function is_installed(): bool {
		// Core WordPress: the form always exists.
		return true;
	}

	/**
	 * Judge a reset request.
	 *
	 * `lostpassword_post` is an action, not a filter, so nothing is returned. WP_Error is an
	 * object and both callers keep their own handle on it, which is how adding an error here
	 * reaches them.
	 *
	 * @param mixed ...$args WP_Error $errors, WP_User|false $user_data.
	 */
	public function wp_is_spam( ...$args ) {
		$errors = $args[0] ?? null;

		if ( ! $errors instanceof \WP_Error ) {
			return;
		}

		// Something else already refused this request; judging it again would consume a captcha
		// session for a submission that was never going to succeed.
		if ( $errors->has_errors() ) {
			return;
		}

		$message = $this->check_spam();

		if ( $message === null ) {
			return;
		}

		$errors->add( 'f12_captcha', $this->format_spam_message( $message ) );
	}
}
