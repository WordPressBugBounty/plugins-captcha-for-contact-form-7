<?php

namespace f12_cf7_captcha\core\protection\multiple_submission;


use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\timer\CaptchaTimer;
use f12_cf7_captcha\core\timer\CaptchaTimerCleaner;
use f12_cf7_captcha\core\timer\Timer_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multiple_Submission_Validator extends BaseProtection {

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		/*
		 * Load submodules
		 */
		new CaptchaTimerCleaner( $Controller );

		$this->set_message( 'multiple-submission-protection' );
	}

	/**
	 * Creates a new instance of the CaptchaTimer class.
	 *
	 * This method creates and returns a new instance of the CaptchaTimer class, which is used for managing captcha
	 * timers.
	 *
	 * @return CaptchaTimer A new instance of the CaptchaTimer class.
	 */
	public function factory(): CaptchaTimer {
		return new CaptchaTimer();
	}

	/**
	 * Checks if the protection for multiple submissions is enabled.
	 *
	 * This method retrieves the value of the "protection_multiple_submission_enable" setting from the global settings.
	 * It returns true if the value is equal to 1, indicating that the protection is enabled. Otherwise, it returns
	 * false.
	 *
	 * @return bool True if the protection for multiple submissions is enabled, false otherwise.
	 */
	protected function is_enabled(): bool {
		$is_enabled = (int) $this->Controller->get_settings( 'protection_multiple_submission_enable', 'global' ) === 1;

		return apply_filters('f12-cf7-captcha-skip-validation-multiple_submission', $is_enabled);
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

		$hash = sanitize_text_field( $array_post_data[ $field_name ] );

		/**
		 * Load the Validator
		 *
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_modul( 'timer' );

		/**
		 * Load the Timer
		 *
		 * @var CaptchaTimer $Timer
		 */
		$Timer = $Timer_Controller->get_timer( $hash );

		if ( ! $Timer ) {
			return true;
		}

		$time_in_ms = round( microtime( true ) * 1000 );

		$minimum_time_in_ms = $this->get_validation_time();
		#echo sprintf( "%s - %s = %s", $time_in_ms, $Timer->get_value(), $time_in_ms - (float) $Timer->get_value() ) . PHP_EOL;

		if ( ( $time_in_ms - (float) $Timer->get_value() ) < $minimum_time_in_ms ) {
			return true;
		}

		$Timer->delete();

		return false;
	}

	/**
	 * Retrieves the captcha HTML markup.
	 *
	 * This method generates and returns the HTML markup for the captcha field.
	 *
	 * @param mixed ...$args Optional arguments.
	 *
	 * @return string The HTML markup for the captcha field.
	 * @throws \Exception
	 */
	public function get_captcha( ...$args ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$field_name = $this->get_field_name();

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_modul('timer');

		$hash = $Timer_Controller->add_timer();

		$html = sprintf( '<div class="f12t"><input type="hidden" class="f12_timer" name="%s" value="%s"/></div>', esc_attr( $field_name ), esc_attr( $hash ) );

		return $html;
	}

	/**
	 * Retrieves the validation time.
	 *
	 * This method returns the length of time, in milliseconds, that is allowed for validation.
	 *
	 * @return int The validation time in milliseconds.
	 */
	protected function get_validation_time(): int {
		return 2000;
	}

	/**
	 * Retrieves the field name.
	 *
	 * This method returns the name of the field used for multiple submission protection.
	 *
	 * @return string The field name.
	 */
	protected function get_field_name() {
		return 'f12_multiple_submission_protection';
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