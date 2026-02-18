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

	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->set_message(__('timer-protection', 'captcha-for-contact-form-7'));
		$this->get_logger()->debug('Message for timer protection set.', [
			'message_key' => 'timer-protection',
		]);

		$this->get_logger()->info('Constructor completed.');
	}

	/**
	 * Checks if the provided input is considered spam.
	 *
	 * @param mixed $args The arguments to check for spam.
	 *
	 * @return bool True if the input is considered spam, false otherwise.
	 */
	public function is_spam(...$args): bool
	{
		$this->get_logger()->info('Performing spam check for timer protection.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// If no arguments were passed, it cannot be spam.
		if (!isset($args[0])) {
			$this->get_logger()->warning('No post data available for checking.');
			return false;
		}

		// If timer protection is disabled, skip the check.
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam check skipped because timer protection is disabled.');
			return false;
		}

		$array_post_data = $args[0];
		$field_name = $this->get_field_name();

		// If the special field for timer protection is missing, it is probably a bot.
		if (!isset($array_post_data[$field_name])) {
			$this->get_logger()->warning('Timer field missing in submitted data. Classified as spam.');
			return true;
		}

		$hash = sanitize_text_field($array_post_data[$field_name]);
		$this->get_logger()->debug('Retrieved hash value: ' . $hash);

		/**
		 * Load the timer controller and the specific timer.
		 */
		$Timer_Controller = $this->Controller->get_module('timer');
		$Timer = $Timer_Controller->get_timer($hash);

		// If the timer is not found, the hash is invalid or expired.
		if (!$Timer) {
			$this->get_logger()->warning('No matching timer found for hash. Classified as spam.', ['hash' => $hash]);
			return true;
		}

		// Calculate the elapsed time
		$time_in_ms = round(microtime(true) * 1000);
		$minimum_time_in_ms = $this->get_validation_time();
		$start_time_ms = (float)$Timer->get_value();
		$time_passed = $time_in_ms - $start_time_ms;

		$this->get_logger()->debug("Time check performed.", [
			'start_time_ms' => $start_time_ms,
			'end_time_ms' => $time_in_ms,
			'time_passed_ms' => $time_passed,
			'minimum_time_ms' => $minimum_time_in_ms,
		]);

		// If the elapsed time is below the minimum value, it is probably a bot.
		if ($time_passed < $minimum_time_in_ms) {
			$this->get_logger()->warning('Form submitted too quickly. Classified as spam.');
			return true;
		}

		// The check was successful, delete the timer to prevent multiple use.
		$this->get_logger()->info('Validation successful. Deleting timer record.', ['hash' => $hash]);
		$Timer->delete();

		$this->get_logger()->info('Form classified as not spam.');
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
	public function get_captcha(...$args): string
	{
		$this->get_logger()->info('Generating captcha field for timer protection.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha field not generated because timer protection is disabled.');
			return '';
		}

		$field_name = $this->get_field_name();
		$this->get_logger()->debug('Field name: ' . $field_name);

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_module('timer');

		if (!$Timer_Controller) {
			$this->get_logger()->error('The "timer" module could not be loaded.');
			return '';
		}

		$hash = $Timer_Controller->add_timer();

		if (empty($hash)) {
			$this->get_logger()->error('Error adding timer. Could not generate hash.');
			return '';
		}

		$this->get_logger()->debug('New timer hash successfully generated.', ['hash' => $hash]);

		$html = sprintf(
			'<div class="f12t"><input type="hidden" class="f12_timer" name="%s" value="%s"/></div>',
			esc_attr($field_name),
			esc_attr($hash)
		);

		$this->get_logger()->info('Hidden captcha field successfully generated.', [
			'html_length' => strlen($html),
		]);

		return $html;
	}

	/**
	 * Returns the validation time in milliseconds.
	 *
	 * @return int The validation time in milliseconds.
	 */
	protected function get_validation_time(): int
	{
		$validation_time = 2000;

		$this->get_logger()->debug('Retrieving minimum validation time.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'time_in_ms' => $validation_time,
		]);

		return $validation_time;
	}

	/**
	 * Returns the name of the field.
	 *
	 * @return string The name of the field.
	 */
	protected function get_field_name()
	{
		$field_name = $this->Controller->get_settings('protection_time_field_name', 'global');

		$this->get_logger()->debug('Retrieving form field name for timer protection.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'field_name' => $field_name,
		]);

		return $field_name;
	}
	/**
	 * Initializes the object.
	 *
	 * This method is called when the object is initialized and can be used to perform any necessary setup.
	 * It does not return any value and has no parameters.
	 */
	protected function on_init(): void
	{
		$this->get_logger()->info('on_init method executing.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implement the initialization logic here.
		// Example: Adding hooks, registering shortcodes, etc.

		$this->get_logger()->info('on_init method completed.');
	}

	/**
	 * Checks if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = (int)$this->get_protection_setting('protection_time_enable') === 1;

		if ($is_enabled) {
			$this->get_logger()->info('Timer protection is globally enabled.');
		} else {
			$this->get_logger()->warning('Timer protection is globally disabled. Validation will be skipped.');
		}

		$filtered_state = apply_filters('f12-cf7-captcha-skip-validation-timer', $is_enabled);

		if ($is_enabled && !$filtered_state) {
			$this->get_logger()->debug('Timer protection was disabled by an external filter.', [
				'filter_name' => 'f12-cf7-captcha-skip-validation-timer',
			]);
		}

		return $filtered_state;
	}

	public function success(): void
	{
		$this->get_logger()->info('Successful timer validation.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implement the logic here that should be executed after a successful check.
		// In this context, it is unlikely that additional actions are required,
		// since the check primarily takes place in the is_spam() method.
	}
}