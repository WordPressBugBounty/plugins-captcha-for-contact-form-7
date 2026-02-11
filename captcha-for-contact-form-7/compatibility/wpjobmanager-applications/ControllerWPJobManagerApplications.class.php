<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPJobManagerApplications
 */
class ControllerWPJobManagerApplications extends BaseController {
	protected string $name = 'WP Job Manager Application Forms';
	protected string $id = 'wpjobmanager_applications';
	protected string $settings_key = 'protection_wpjobmanager_applications_enable';

	protected array $hooks = [
		['type' => 'action', 'hook' => 'job_application_form_fields_end', 'method' => 'wp_add_spam_protection'],
		['type' => 'filter', 'hook' => 'application_form_validate_fields', 'method' => 'wp_is_spam'],
	];

	public function is_installed(): bool
	{
		$this->get_logger()->info('Starting check whether WP Job Manager plugin is installed and active.');

		if ( class_exists( 'WP_Job_Manager' ) || function_exists( 'WPJM' ) ) {
			$this->get_logger()->info('WP Job Manager class or function found.');
			return true;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			$plugin_file = 'wp-job-manager/wp-job-manager.php';
			if ( is_plugin_active( $plugin_file ) ) {
				$this->get_logger()->info('WP Job Manager is active in the plugin list.');
				return true;
			}
		}

		$this->get_logger()->warning('WP Job Manager was not found or is inactive.');
		return false;
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function wp_is_spam(...$args)
	{
		$this->get_logger()->info('Starting spam check for WP Job Manager Application Forms.');

		$validated = $args[0];

		if (is_wp_error($validated)) {
			$this->get_logger()->info('Form was already marked as invalid. Skipping spam check.');
			return $validated;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WP Job Manager
		$array_post_data = $_POST;

		$Protection = $this->Controller->get_module('protection');

		if ($Protection->is_spam($array_post_data)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam detected! Error message: ' . $message);

			return new \WP_Error('validation-error', sprintf(__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $message));
		}

		return $validated;
	}
}
