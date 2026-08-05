<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\javascript\Javascript_Validator;
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
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller, 0);

		$this->get_logger()->debug(
			"__construct(): Initialization started",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$this->init();

		$this->get_logger()->info(
			"__construct(): Initialization completed",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);
	}


	/**
	 * Init the captcha
	 */
	private function init(): void
	{
		$this->_captcha = '';

		$this->get_logger()->debug(
			"init(): Captcha reset",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);
	}


	/**
	 * Get the Value of the captcha
	 *
	 * @return string
	 */
	public function get(): string
	{
		if (empty($this->_captcha)) {
			$this->get_logger()->warning(
				"get(): No captcha set",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__
				]
			);
			return '';
		}

		// Masking: only first and last position visible
		$length = strlen($this->_captcha);
		$masked = substr($this->_captcha, 0, 1)
		          . str_repeat('*', max(0, $length - 2))
		          . substr($this->_captcha, -1);

		$this->get_logger()->debug(
			"get(): Captcha returned",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'masked' => $masked,
				'length' => $length
			]
		);

		return $this->_captcha;
	}

	/**
	 * Checks if a given captcha code is valid.
	 *
	 * @param string $captcha_code The captcha code to check.
	 *
	 * @return bool Returns true if the captcha code is valid, false otherwise.
	 */
	public function is_valid(string $captcha_code, string $captcha_hash = ''): bool
	{
		// A trap field is valid exactly when it came back untouched. empty() was too generous
		// here: it also accepts "0", so a bot writing a zero into every input walked through.
		$result = trim($captcha_code) === '';

		// Masking: only 1st and last position visible
		$length = strlen($captcha_code);

		$this->get_logger()->debug(
			"is_valid(): Captcha validated",
			[
				'plugin'       => 'f12-cf7-captcha',
				'code'  => $captcha_code,
				'length'       => $length,
				'hash_present' => !empty($captcha_hash) ? 'yes' : 'no',
				'result'       => $result ? 'valid' : 'invalid'
			]
		);

		return $result;
	}

	/**
	 * Retrieves a form field for a given field name.
	 *
	 * @param string $field_name The name of the form field to generate.
	 *
	 * @return string The generated form field HTML.
	 */
	public function get_field( string $field_name, array $args = [] ): string {
		// Moved off-screen rather than hidden via `visibility:hidden`. That literal was a
		// reliable tell — a scraper only had to look for it in the style attribute to know
		// which input to leave alone. Positioning keeps it out of sight without the giveaway
		// and, unlike a CSS class, does not depend on a stylesheet having loaded (a honeypot
		// that becomes visible would get filled in by real people and block them).
		//
		// tabindex/aria-hidden keep keyboard and screen-reader users from ever reaching it;
		// autocomplete="off" plus the opaque rotated name keep browser autofill out.
		$captcha = sprintf(
			'<input id="%s" type="text" name="%s" value="" tabindex="-1" aria-hidden="true" autocomplete="off" style="position:absolute!important; left:-9999px!important; top:auto!important; height:1px!important; width:1px!important; overflow:hidden!important;"/>',
			esc_attr( $field_name ),
			esc_attr( $field_name )
		);

		$this->get_logger()->debug(
			"get_field(): Honeypot field generated",
			[
				'plugin'     => 'f12-cf7-captcha',
				'field_name' => $field_name
			]
		);

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
		$filtered = apply_filters( 'f12-cf7-captcha-get-form-field-honeypot', $captcha, $field_name );

		if ($filtered !== $captcha) {
			$this->get_logger()->info(
				"get_field(): Honeypot field modified by filter",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
		}

		return $filtered;
	}

	/**
	 * Retrieves the AJAX response as a string.
	 *
	 * @return string The AJAX response.
	 */
	public function get_ajax_response(): string
	{
		$response = '';

		$this->get_logger()->debug(
			"get_ajax_response(): Ajax response generated",
			[
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'response' => '(empty)'
			]
		);

		return $response;
	}

}