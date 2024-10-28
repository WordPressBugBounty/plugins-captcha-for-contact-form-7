<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\timer\CaptchaTimerCleaner;
use f12_cf7_captcha\core\UserData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'Captcha.class.php' );
require_once( 'CaptchaAjax.class.php' );
require_once( 'CaptchaCleaner.class.php' );
require_once( 'CaptchaGenerator.class.php' );
require_once( 'CaptchaHoneypotGenerator.class.php' );
require_once( 'CaptchaMathGenerator.class.php' );
require_once( 'CaptchaImageGenerator.class.php' );

class Captcha_Validator extends BaseProtection {

	private CaptchaCleaner $_Captcha_Cleaner;

	/**
	 * Constructor method for the class.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha controller object.
	 *
	 * @return void
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		/**
		 * Load submoduls
		 */
		new CaptchaAjax( $Controller );

		$this->_Captcha_Cleaner = new CaptchaCleaner( $Controller );

		$this->set_message( __( 'captcha-protection', 'captcha-for-contact-form-7' ));
	}

	/**
	 * Create and get a Captcha object.
	 *
	 * This method creates a new Captcha object using the IP address obtained from the User_Data module.
	 *
	 * @return Captcha The newly created Captcha object.
	 * @throws \Exception
	 */
	public function factory(): Captcha {
		/**
		 * @var UserData $User_Data
		 */
		$User_Data = $this->Controller->get_modul( 'user-data' );

		return new Captcha( $User_Data->get_ip_address() );
	}

	/**
	 * Retrieves the instance of the CaptchaCleaner.
	 *
	 * This method returns the instance of the CaptchaCleaner class that
	 * is held by the current object.
	 *
	 * @return CaptchaCleaner The instance of the CaptchaCleaner.
	 */
	public function get_captcha_cleaner(): CaptchaCleaner {
		return $this->_Captcha_Cleaner;
	}


	/**
	 * Checks if the functionality is enabled.
	 *
	 * This method is used to check if the functionality of the code is enabled. It retrieves the value of the
	 * 'protection_captcha_enable' setting from the Controller, and compares it with 1. If the values are equal, it
	 * returns true; otherwise, it returns false.
	 *
	 * @return bool True if the functionality is enabled, false otherwise.
	 */
	protected function is_enabled(): bool {
		$is_enabled = (int) $this->Controller->get_settings( 'protection_captcha_enable', 'global' ) === 1;

		return apply_filters( 'f12-cf7-captcha-skip-validation-captcha', $is_enabled );
	}

	/**
	 * Check if the provided data is considered as spam.
	 *
	 * @param mixed ...$args The arguments passed to the method.
	 *                       - $args[0] (array) The array of post data.
	 *
	 * @return bool Returns true if the data is considered as spam, otherwise returns false.
	 * @throws \Exception
	 */
	public function is_spam( ...$args ): bool {
		if ( ! isset( $args[0] ) ) {
			return false;
		}

		if ( ! $this->is_enabled() ) {
			return false;
		}

		$array_post_data = $args[0];

		$field_name = $this->get_field_name();

		if ( ! isset( $array_post_data[ $field_name ] ) ) {
			return true;
		}

		$validation_method = $this->get_validation_method();

		/*
		 * Exception for honey - honeys dont have hash values
		 */
		if ( $validation_method != 'honey' && ! isset( $array_post_data[ $field_name . '_hash' ] ) ) {
			return true;
		}

		$hash = '';

		if ( $validation_method != 'honey' ) {
			$hash = $array_post_data[ $field_name . '_hash' ];
		}

		/*
		* Check if captcha is valid
		*/
		$Generator = $this->get_generator( $validation_method );

		if ( $Generator->is_valid( $array_post_data[ $field_name ], $hash ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the captcha value.
	 *
	 * This method retrieves the captcha value and returns it as a string. If the captcha
	 * functionality is not enabled, an empty string is returned.
	 *
	 * @param mixed ...$args Optional arguments that can be passed to the method.
	 *                       These arguments are ignored in the implementation.
	 *
	 * @return string The captcha value as a string.
	 */
	public function get_captcha( ...$args ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		/*
		 * Load the field name
		 */
		$field_name = $this->get_field_name();

		return $this->get_generator()->get_field( $field_name );
	}

	/**
	 * Retrieves the generator module based on the specified validation method.
	 *
	 * This method loads the appropriate validation method and returns the corresponding generator module.
	 *
	 * @param string $validation_method The validation method to use. Defaults to an empty string if not provided.
	 *
	 * @return CaptchaGenerator The generator module instance.
	 * @throws \Exception
	 */
	public function get_generator( string $validation_method = '' ): CaptchaGenerator {
		/*
		 * Load the validation method
		 */
		if ( empty( $validation_method ) ) {
			$validation_method = $this->get_validation_method();
		}

		switch ( $validation_method ) {
			case 'math':
				/**
				 * @var CaptchaMathGenerator $Captcha_Generator
				 */
				$Captcha_Generator = new CaptchaMathGenerator( $this->Controller );
				break;
			case 'image':
				/**
				 * @var CaptchaImageGenerator $Captcha_Generator
				 */
				$Captcha_Generator = new CaptchaImageGenerator( $this->Controller );
				break;
			default:
				/**
				 * @var CaptchaHoneypotGenerator $Captcha_Generator
				 */
				$Captcha_Generator = new CaptchaHoneypotGenerator( $this->Controller );
				break;
		}

		return $Captcha_Generator;
	}

	/**
	 * Retrieves the validation method.
	 *
	 * This method is used to retrieve the validation method for the code.
	 *
	 * @return string The validation method. Possible Values:  honeypot, math, image
	 */
	protected function get_validation_method(): string {
		return $this->Controller->get_settings( 'protection_captcha_method', 'global' );
	}

	/**
	 * Retrieves the field name.
	 *
	 * This method returns the name of the field used for multiple submission protection.
	 *
	 * @return string The field name.
	 */
	protected function get_field_name() {
		return $this->Controller->get_settings( 'protection_captcha_field_name', 'global' );
	}

	/**
	 * Initializes the method.
	 *
	 * This method is called to initialize the functionality of the code.
	 *
	 * @return void
	 */
	protected function on_init(): void {

	}

	public function success(): void {
		// TODO: Implement success() method.
	}
}