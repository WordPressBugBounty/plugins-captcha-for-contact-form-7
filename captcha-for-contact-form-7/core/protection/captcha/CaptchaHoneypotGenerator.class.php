<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\javascript\JavascriptValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CaptchaHoneypotGenerator
 * Generate the custom captcha as an honeypot
 *
 * @package forge12\contactform7
 */
class CaptchaHoneypotGenerator extends CaptchaGenerator {
	/**
	 * constructor.
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller, 0 );

		$this->init();
	}

	/**
	 * Init the captcha
	 */
	private function init() {
		$this->_captcha = '';
	}

	/**
	 * Get the Value of the captcha
	 *
	 * @return string|void
	 */
	public function get() {
		return $this->_captcha;
	}

	/**
	 * @param $captcha_code
	 *
	 * @return bool
	 * @deprecated
	 */
	public static function validate( $captcha_code ) {
		$Timer    = JavascriptValidator::get_instance();
		$Honeypot = new CaptchaHoneypotGenerator( $Timer );

		return $Honeypot->is_valid( $captcha_code );
	}

	/**
	 * Checks if a given captcha code is valid.
	 *
	 * @param string $captcha_code The captcha code to check.
	 *
	 * @return bool Returns true if the captcha code is valid, false otherwise.
	 */
	public function is_valid( string $captcha_code, string $captcha_hash = '' ): bool {
		if ( ! empty( $captcha_code ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Retrieves a form field for a given field name.
	 *
	 * @param string $fieldname The name of the form field to generate.
	 *
	 * @return string The generated form field HTML.
	 * @deprecated
	 */
	public static function get_form_field( $fieldname ) {
		$Timer    = JavascriptValidator::get_instance();
		$Honeypot = new CaptchaHoneypotGenerator( $Timer );

		return $Honeypot->get_field( $fieldname );
	}

	/**
	 * Retrieves a form field for a given field name.
	 *
	 * @param string $field_name The name of the form field to generate.
	 *
	 * @return string The generated form field HTML.
	 */
	public function get_field( string $field_name ): string {
		$captcha = sprintf( '<input id="%s" type="text" style="visibility:hidden!important; opacity:1!important; height:0!important; width:0!important; margin:0!important; padding:0!important;" name="%s" value=""/>', esc_attr( $field_name ), esc_attr( $field_name ) );

		/**
		 * Update Honeypot Field before output
		 *
		 * The filter allows developers to customize the form field for the honeypot before returning.
		 *
		 * @param string $captcha    The HTML content of the form input field used as honeypot.
		 * @param string $field_name The Name of the field used as id and name for the input.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'f12-cf7-captcha-get-form-field-honeypot', $captcha, $field_name );
	}

	/**
	 * Retrieves the AJAX response as a string.
	 *
	 * @return string The AJAX response.
	 */
	function get_ajax_response(): string {
		return '';
	}
}