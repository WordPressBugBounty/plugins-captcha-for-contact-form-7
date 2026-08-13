<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WooCommerce "Account details" form.
 *
 * Less obviously a spam target than a contact form, and worth protecting for a different
 * reason: it changes the e-mail address and password of an account that is already logged in.
 * A session hijacked through a stolen cookie is one submission away from becoming a permanent
 * takeover, and every captcha here is a step an automated script has to solve rather than
 * replay.
 *
 * Note that this only ever runs for logged-in visitors, and the plugin whitelists logged-in
 * users by default (`protection_whitelist_role_logged_in`). A site owner who wants this
 * protection has to turn that whitelist off — which is the correct default for both settings,
 * but it does mean enabling this module alone changes nothing.
 */
class ControllerWoocommerceEditAccount extends BaseController {

	protected string $id = 'woocommerce-edit-account';
	protected string $settings_key = 'protection_woocommerce-edit-account_enable';

	protected array $hooks = [
		[ 'type' => 'action', 'hook' => 'woocommerce_edit_account_form', 'method' => 'wp_add_spam_protection' ],
		[ 'type' => 'action', 'hook' => 'woocommerce_save_account_details_errors', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2 ],
	];

	public function get_name(): string {
		return __( 'WooCommerce Account Details', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Judge the change.
	 *
	 * The hook is fired with `do_action_ref_array`, so WooCommerce keeps its own handle on the
	 * WP_Error and reads it back: any message added here becomes a notice, and the save is
	 * guarded by `wc_notice_count( 'error' ) === 0`.
	 *
	 * @param mixed ...$args WP_Error $errors, \stdClass $user.
	 */
	public function wp_is_spam( ...$args ) {
		$errors = $args[0] ?? null;

		if ( ! $errors instanceof \WP_Error ) {
			return;
		}

		// WooCommerce validated the fields first. Judging a change that is already refused
		// would consume a captcha session for a submission that cannot succeed.
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
