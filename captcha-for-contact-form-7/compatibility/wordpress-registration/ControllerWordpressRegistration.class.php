<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPForms
 */
class ControllerWordpressRegistration extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'WordPress Registration';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'wordpress_registration';

	/**
	 * Check if the captcha is enabled for WooCommerce
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool {
		return apply_filters( 'f12_cf7_captcha_is_installed_wordpress_registration', (int) $this->Controller->get_settings( 'protection_wordpress_registration_enable', 'global' ) === 1 );
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
		$this->name = __('WordPress Registration', 'captcha-for-contact-form-7');

		add_action( 'register_form', array( $this, 'wp_add_spam_protection' ) );
		add_filter( 'registration_errors', array( $this, 'wp_is_spam' ), 10, 3 );
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
		/**
		 * @var \WP_Error $error
		 */
		$error = $args[0];

		$array_post_data = $_POST;

		if ( apply_filters( 'f12_cf7_captcha_login_login_validator', false ) ) {
			return $error;
		}

		$Protection = $this->Controller->get_modul( 'protection' );
		if ( $Protection->is_spam( $array_post_data ) ) {
			$error->add(500,  sprintf( 'Captcha not correct: %s', $Protection->get_message() ) );
		}
		return $error;
	}
}