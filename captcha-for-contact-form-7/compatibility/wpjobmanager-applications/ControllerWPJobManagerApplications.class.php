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
	/**
	 * @var string
	 */
	protected string $name = 'WP Job Manager Application Forms';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'wpjobmanager_applications';

	/**
	 * Check if the captcha is enabled for WP Job Manager Applications
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool {
		return apply_filters( 'f12_cf7_captcha_is_installed_wpjobmanager_applciations', (int) $this->Controller->get_settings( 'protection_wpjobmanager_applications_enable', 'global' ) === 1 );
	}

	/**
	 * Check if the software is installed
	 *
	 * @return bool True if the software is installed, false otherwise
	 */
	public function is_installed(): bool {
		return true;
	}

	/**
	 * @private WordPress Hook
	 */
	public function on_init(): void {
		$this->name = __( 'WP Job Manager Application Forms', 'captcha-for-contact-form-7' );

		add_action( 'job_application_form_fields_end', array( $this, 'wp_add_spam_protection' ) );
		add_filter( 'application_form_validate_fields', array( $this, 'wp_is_spam' ) );
	}

	/**
	 * Add spam protection to the given content.
	 *
	 * This method adds spam protection to the given content by injecting a captcha field based on the specified
	 * validation method.
	 *
	 * @param mixed ...$args Any number of arguments.
	 *
	 *
	 * @throws \Exception
	 * @since 1.12.2
	 *
	 */
	public function wp_add_spam_protection( ...$args ) {
		$Protection = $this->Controller->get_modul( 'protection' );

		echo $Protection->get_captcha();
	}

	/**
	 * Check if a post is considered as spam
	 *
	 * @param array $array_post_data The array containing the POST data.
	 *
	 * @throws \Exception
	 */
	public function wp_is_spam( ...$args ) {
		$validated       = $args[0];
		$array_post_data = $_POST;

		$Protection = $this->Controller->get_modul( 'protection' );
		if ( $Protection->is_spam( $array_post_data ) ) {
			return new \WP_Error( 'validation-error', sprintf( __( 'Captcha not correct: %s', 'captcha-for-contact-form-7' ), $Protection->get_message() ) );
		}

		return $validated;
	}
}