<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPForms
 */
class ControllerWPForms extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'WPForms';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'wpforms';

	/**
	 * Check if the captcha is enabled for WooCommerce
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool {
		return apply_filters( 'f12_cf7_captcha_is_installed_wpforms', $this->is_installed() && (int) $this->Controller->get_settings( 'protection_wpforms_enable', 'global' ) === 1 );
	}

	/**
	 * Check if WPForms plugin is installed
	 *
	 * @return bool True if WPForms is installed, false otherwise
	 */
	public function is_installed(): bool {
		return class_exists( 'WPForms' );
	}

	/**
	 * @private WordPress Hook
	 */
	public function on_init(): void {
		add_action( 'wpforms_frontend_output', array( $this, 'wp_add_spam_protection' ), 10, 5 );
		add_filter( 'wpforms_process_initial_errors', array( $this, 'wp_is_spam' ), 10, 2 );
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
	 * @param bool  $is_spam         Whether the post is considered as spam initially.
	 * @param array $array_post_data The array containing the POST data.
	 *
	 * @return bool Whether the post is considered as spam.
	 * @throws \Exception
	 */
	public function wp_is_spam( ...$args ) {
		$errors    = $args[0];
		$form_data = $args[1];

		$array_post_data = $_POST;

		if ( ! isset( $form_data['id'] ) ) {
			return $errors;
		}

		$form_id = $form_data['id'];

		$Protection = $this->Controller->get_modul( 'protection' );
		if ( $Protection->is_spam( $array_post_data ) ) {
			$errors[ $form_id ]['footer'] = sprintf( esc_html__( 'Captcha not correct: %s', 'captcha-for-contact-form-7' ), $Protection->get_message() );
		}

		return $errors;
	}
}