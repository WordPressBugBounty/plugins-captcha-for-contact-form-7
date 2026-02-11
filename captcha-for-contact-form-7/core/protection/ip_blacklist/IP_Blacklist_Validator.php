<?php

namespace f12_cf7_captcha\core\protection\ip_blacklist;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IP_Blacklist_Validator extends BaseProtection
{
	/**
	 * Private constructor for the class.
	 *
	 * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
	 * This hook is used to retrieve log data.
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Constructor started.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->get_logger()->info('Constructor completed.', [
			'class' => __CLASS__,
		]);
	}

	protected function is_enabled(): bool
	{
		$is_enabled = true;

		if ($is_enabled) {
			$this->get_logger()->info('IP blacklist is enabled.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		} else {
			$this->get_logger()->warning('IP blacklist is disabled.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-ip-blacklist', $is_enabled);

		if ($is_enabled && !$result) {
			$this->get_logger()->debug('IP blacklist skipped by filter.', [
				'filter' => 'f12-cf7-captcha-skip-validation-ip-blacklist',
				'original_state' => $is_enabled,
			]);
		}

		return $result;
	}

	/**
	 * Determines if the submitted form is considered spam.
	 *
	 * This method checks if the submitted form is spam based on certain criteria.
	 *
	 * @return bool Returns true if the form is considered spam, false otherwise.
	 */
	public function is_spam(): bool
	{
		$this->get_logger()->info('Performing spam check.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// If module is disabled → not spam
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam check skipped: IP blacklist protection is disabled.', [
				'class' => __CLASS__,
			]);
			return false;
		}

		// Get user IP
		$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Load blacklist entries from settings
		$settings          = get_option('f12-cf7-captcha-settings', []);
		$blacklist_raw     = $settings['global']['protection_blacklist_ips'] ?? '';
		$blacklisted_ips   = array_filter(array_map('trim', explode("\n", $blacklist_raw)));

		// Check if user IP is on blacklist
		if (!empty($user_ip) && in_array($user_ip, $blacklisted_ips, true)) {
			$this->get_logger()->warning('Form classified as spam: IP on blacklist.', [
				'class'   => __CLASS__,
				'user_ip' => $user_ip,
			]);
			$this->set_message(__('Your IP is blocked from submitting this form.', 'captcha-for-contact-form-7'));
			return true;
		}

		$this->get_logger()->info('Form classified as not spam.', [
			'ip' => $user_ip,
		]);

		return false;
	}


	public function success(): void
	{
		$this->get_logger()->info('Successful form submission detected.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Additional logic can be implemented here
		// to be executed on successful validation.
		// For example:
		// - Delete temporary data
		// - Send a notification
		// - Update counters

		// TODO: Implement success logic here.
	}
}