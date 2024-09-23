<?php

namespace f12_cf7_captcha\core\protection\time;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\timer\CaptchaTimer;
use f12_cf7_captcha\core\timer\Timer_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timer_Validator extends BaseProtection {

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->set_message( 'timer-protection' );
	}

    /**
	 * Checks if the provided input is considered spam.
	 *
	 * @param mixed $args The arguments to check for spam.
	 *
	 * @return bool True if the input is considered spam, false otherwise.
	 */
	public function is_spam( ...$args ): bool {
		if ( ! isset( $args[0] ) ) {
			return false;
		}

		if(!$this->is_enabled()){
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

		#echo sprintf("%s - %s = %s", $time_in_ms, $Timer->get_value(), $time_in_ms - (float)$Timer->get_value()) . PHP_EOL;
		$minimum_time_in_ms = $this->get_validation_time();

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
		if(!$this->is_enabled()){
			return '';
		}

		$field_name = $this->get_field_name();

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_modul( 'timer-validator' );

		$hash = $Timer_Controller->add_timer();

		$html = sprintf( '<div class="f12t"><input type="hidden" class="f12_timer" name="%s" value="%s"/></div>', esc_attr( $field_name ), esc_attr( $hash ) );

		return $html;
	}

	/**
	 * Returns the validation time in milliseconds.
	 *
	 * @return int The validation time in milliseconds.
	 */
	protected function get_validation_time(): int {
		return 2000;
	}

	/**
	 * Returns the name of the field.
	 *
	 * @return string The name of the field.
	 */
	protected function get_field_name() {
		return $this->Controller->get_settings( 'protection_time_field_name', 'global' );
	}

	/**
	 * Initializes the object.
	 *
	 * This method is called when the object is initialized and can be used to perform any necessary setup.
	 * It does not return any value and has no parameters.
	 */
	protected function on_init(): void {

	}

	/**
	 * Checks if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool {
		$is_enabled =  (int)$this->Controller->get_settings( 'protection_time_enable', 'global' ) === 1;

		return apply_filters('f12-cf7-captcha-skip-validation-timer', $is_enabled);
	}

	public function success(): void {
		// TODO: Implement success() method.
	}
}