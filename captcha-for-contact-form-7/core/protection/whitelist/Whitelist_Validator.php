<?php

namespace f12_cf7_captcha\core\protection\whitelist;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Whitelist_Validator extends BaseProtection {
	/**
	 * Private constructor for the class.
	 *
	 * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
	 * This hook is used to retrieve log data.
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->get_logger()->info( 'Constructor started.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$this->get_logger()->info( 'Constructor completed.', [
			'class' => __CLASS__,
		] );
	}

	protected function is_enabled(): bool {
		$is_enabled = true;

		if ( $is_enabled ) {
			$this->get_logger()->info( 'Whitelist is enabled.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
		} else {
			$this->get_logger()->warning( 'Whitelist is disabled.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
		}

		$result = apply_filters( 'f12-cf7-captcha-skip-validation-whitelist', $is_enabled );

		if ( $is_enabled && ! $result ) {
			$this->get_logger()->debug( 'Whitelist skipped by filter.', [
				'filter'         => 'f12-cf7-captcha-skip-validation-whitelist',
				'original_state' => $is_enabled,
			] );
		}

		return $result;
	}

	/**
	 * Always return false since this is about whitelist.
	 * @return bool
	 */
	public function is_spam():bool{
		return false;
	}

	/**
	 * Checks if the given email(s) are in the whitelist.
	 *
	 * This method verifies whether the provided argument, which can be either a single email
	 * address or an array of email addresses, exists in the specified whitelist of emails.
	 *
	 * @param mixed $arg                A single email address as a string or an array of email addresses.
	 * @param array $whitelisted_emails An optional array of whitelisted email addresses.
	 *
	 * @return bool Returns true if the provided email(s) are found in the whitelist, otherwise false.
	 */
	private function is_whitelisted_email( $arg, $whitelisted_emails = [] ): bool {
		if ( empty( $whitelisted_emails ) ) {
			$this->get_logger()->debug( "Whitelist check: no whitelist emails configured", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false;
		}

		if ( is_array( $arg ) ) {
			foreach ( $arg as $value ) {
				if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
					return true;
				}
			}

			$this->get_logger()->debug( "Whitelist check: array checked, no match found", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false; // If none of the email addresses are in the whitelist
		}

		// Sanitize and trim the current POST value
		$value = sanitize_text_field( trim( $arg ) );

		if ( empty( $value ) ) {
			$this->get_logger()->debug( "Whitelist check: value empty or invalid", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false;
		}

		// If any $_POST value matches a whitelisted email, skip protection
		if ( in_array( $value, $whitelisted_emails ) ) {
			$this->get_logger()->info( "Validation skipped: email is on whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'email'  => $value
			] );
		}


		$this->get_logger()->debug( "Whitelist check: email not in whitelist", [
			'plugin' => 'f12-cf7-captcha',
			'email'  => $value
		] );

		return false;
	}

	/**
	 * Checks if the current request is a known AJAX or REST endpoint
	 * that should be excluded from captcha protection.
	 *
	 * @return bool True if the request should be skipped.
	 */
	private function is_whitelisted_ajax_or_rest(): bool {
		// WooCommerce / PayPal AJAX
		$is_ajax_request = defined('DOING_AJAX') && DOING_AJAX;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified by the form plugin
		$wc_ajax_action  = isset($_REQUEST['wc-ajax']) ? sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';

		$ajax_whitelist = [
			// PayPal Payments (WooCommerce PayPal Payments)
			'ppc-create-order',
			'ppc-check-order-status',
			'ppc-capture-order',
			'ppc-add-payment-method',
			'ppc-get-funding-sources',

			// Stripe (WooCommerce Stripe Gateway, Blocks)
			'wc_stripe_create_order',
			'wc_stripe_verify_intent',
			'stripe_verify_payment_intent',
			'stripe_create_order',
			'wc_stripe_checkout',
			'wc_stripe_process_payment',

			// Klarna Payments / Checkout
			'kco_wc_payment',
			'kco_wc_push',
			'kco_wc_iframe',
			'kco_checkout',
			'klarna_checkout_update',
			'klarna_payments_session',

			// Mollie
			'mollie_create_order',
			'mollie_checkout',
			'mollie_return',
			'mollie_webhook',

			// Amazon Pay
			'amazon_payments_advanced_process_payment',
			'amazon_checkout_session',
			'amazon_pay_confirm_order',
			'amazon_pay_create_checkout_session',

			// WooCommerce Standard Requests
			//'checkout', -> FINAL SUBMIT
			'update_order_review',
			'apply_coupon',
			'remove_coupon',
			'get_refreshed_fragments',
			'update_shipping_method',
			'get_variation',
			'get_customer_location',

			// WooCommerce Payments
			'wc_payments_process_payment',
			'wc_payments_create_order',
			'wc_payments_verify_intent',
			'wc_payments_capture',

			// Giro / Sofort
			'wc_gateway_giropay_process',
			'wc_gateway_sofort_process',
			'wc_gateway_sepa_process',
			'wc_gateway_eps_process',
			'wc_gateway_ideal_process',
			'wc_gateway_skrill_process',
			'wc_gateway_unzer_process',
			'wc_gateway_novalnet_process',

			// Heidelpay
			'heidelpay_process_payment',
			'unzer_process_payment',
			'unzer_finalize_payment',

			// PayOne
			'payone_process_payment',
			'payone_ajax_checkout',
			'payone_ajax_finalize',

			// Sage Pay / Opayo
			'sagepay_process_payment',
			'sagepay_ajax_checkout',
			'opayo_process_payment',

			// Square
			'wc_square_process_payment',
			'wc_square_create_order',

			// Authorize Net
			'wc_authorize_net_process_payment',
			'wc_authorize_net_ajax_checkout',

			// Moneybrookers
			'wc_gateway_skrill_process',
			'skrill_process_payment',

			// Worldline (Six payment service, saferpay)
			'saferpay_process_payment',
			'saferpay_finalize_payment',

			// Paymill
			'paymill_process_payment',
			'wirecard_checkout',
			'nets_process_payment',
			'bambora_process_payment',

			// Afterpay, Rate Pay, BillPay
			'afterpay_process_payment',
			'ratepay_process_payment',
			'billpay_process_payment',
		];

		// Allow extension through filter
		$ajax_whitelist = apply_filters('f12_cf7_captcha_ajax_whitelist', $ajax_whitelist);

		if ($is_ajax_request && in_array($wc_ajax_action, $ajax_whitelist, true)) {
			$this->get_logger()->info('Whitelist: WooCommerce/PayPal-AJAX detected.', [
				'wc-ajax' => $wc_ajax_action,
			]);
			return true;
		}

		// WooCommerce Store API / REST API
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if (strpos($route, '/wc/store/') !== false || strpos($route, '/wc/v3/') !== false) {
				$this->get_logger()->info('Whitelist: WooCommerce REST-API detected.', [
					'route' => $route,
				]);
				return true;
			}
		}

		// No match -> no whitelist match
		return false;
	}

	/**
	 * Determines if the submitted form is considered spam.
	 *
	 * This method checks if the submitted form is spam based on certain criteria.
	 *
	 * @return bool Returns true if the form is considered spam, false otherwise.
	 */
	public function is_whitelisted($args): bool {
		$this->get_logger()->info( 'Performing whitelist check.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// 1. Check global whitelist for AJAX/REST requests
		if ($this->is_whitelisted_ajax_or_rest()) {
			$this->get_logger()->info('Whitelist: AJAX/REST request detected - captcha protection globally skipped.');
			return true;
		}

		// If module is disabled -> no spam
		if ( ! $this->is_enabled() ) {
			$this->get_logger()->debug( 'Whitelist check skipped: whitelist protection is disabled.', [
				'class' => __CLASS__,
			] );

			return false;
		}

		// Get the whitelist settings from the plugin options
		$settings               = get_option( 'f12-cf7-captcha-settings', [] );
		$whitelisted_emails     = isset( $settings['global']['protection_whitelist_emails'] ) ? explode( "\n", trim( $settings['global']['protection_whitelist_emails'] ) ) : [];
		$whitelisted_ips        = isset( $settings['global']['protection_whitelist_ips'] ) ? explode( "\n", $settings['global']['protection_whitelist_ips'] ) : [];
		$whitelisted_admin_role = isset( $settings['global']['protection_whitelist_role_admin'] ) ? (int) $settings['global']['protection_whitelist_role_admin'] : 0;
		$whitelisted_logged_in  = isset( $settings['global']['protection_whitelist_role_logged_in'] ) ? (int) $settings['global']['protection_whitelist_role_logged_in'] : 0;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw cookie value required for wp_validate_auth_cookie() HMAC verification
		$user_id = wp_validate_auth_cookie( isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) : '', 'logged_in' );
		if ( $user_id ) {
			wp_set_current_user( $user_id ); // Establish user context
		}

		$current_user = wp_get_current_user();


		$this->get_logger()->debug( "REST request detected", [
			'is_logged_in' => is_user_logged_in() ? 'yes' : 'no',
			'user'         => wp_get_current_user()->user_login ?: 'guest',
			'has_nonce'    => wp_verify_nonce( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '', 'wp_rest' ) ? 'valid' : 'missing/invalid',
		] );

		if ( $current_user->exists() && $whitelisted_logged_in ) {
			$this->get_logger()->info( "Validation skipped: user is logged in", [
				'plugin' => 'f12-cf7-captcha',
				'user'   => wp_get_current_user()->user_login ?? 'unknown'
			] );

			return true;
		}else {
			$this->get_logger()->info( "Validation not skipped: user not logged in or not on whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'user'   => is_user_logged_in() ? ( wp_get_current_user()->user_login ?? 'unknown' ) : 'guest',
				'whitelisted_logged_in' => $whitelisted_logged_in ? 'yes' : 'no',
			] );
		}

		if ( $current_user->exists() && $whitelisted_admin_role ) {

			// Check if the user has the 'administrator' role
			if ( in_array( 'administrator', (array)$current_user->roles ) ) {
				$this->get_logger()->info( "Validation skipped: user is administrator", [
					'plugin' => 'f12-cf7-captcha',
					'user'   => $current_user->user_login
				] );

				return true;
			} else {
				$this->get_logger()->debug( "User logged in but not admin - no exception", [
					'plugin' => 'f12-cf7-captcha',
					'user'   => $current_user->user_login
				] );

				return false;
			}
		} else {
			$this->get_logger()->info( "Validation not skipped: no user logged in or admin whitelist disabled", [
				'plugin'                => 'f12-cf7-captcha',
				'user'                  => is_user_logged_in() ? ( wp_get_current_user()->user_login ?? 'unknown' ) : 'guest',
				'whitelisted_admin_role' => $whitelisted_admin_role ? 'yes' : 'no',
			] );
		}


		// Get the current user's IP address
		$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Trim and clean whitelist values for comparison
		$whitelisted_emails = array_map( 'trim', $whitelisted_emails );
		$whitelisted_ips    = array_map( 'trim', $whitelisted_ips );

		$whitelisted_emails = array_filter( $whitelisted_emails );

		// Check if the user's IP is in the whitelist
		if ( in_array( $user_ip, $whitelisted_ips ) ) {
			$this->get_logger()->info( "Validation skipped: IP is on whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'ip'     => $user_ip
			] );

			return true;
		} else {
			$this->get_logger()->info( "Validation not skipped: IP not on whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'ip'     => $user_ip,
			] );
		}


		// Iterate through each $_POST variable to check if any match a whitelisted email
		foreach ( $args as $value ) {
			if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
				$this->get_logger()->info( "Validation skipped: email is on whitelist", [
					'plugin' => 'f12-cf7-captcha',
					'email'  => $value
				] );

				return true;
			}
		}

		$this->get_logger()->debug( "Validation not skipped", [
			'plugin' => 'f12-cf7-captcha',
			'ip'     => $user_ip,
			'args'   => $args
		] );

		return false;
	}


	public function success(): void {
		$this->get_logger()->info( 'Successful form submission detected.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Additional logic can be implemented here
		// that should be executed on successful validation.
		// For example:
		// - Deleting temporary data
		// - Sending a notification
		// - Updating counters

		// TODO: Implement the success logic here.
	}
}