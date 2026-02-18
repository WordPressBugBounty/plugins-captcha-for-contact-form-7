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

	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Constructor started.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// Load submodules
		$this->get_logger()->info('Loading submodule: CaptchaTimerCleaner.');
		new CaptchaTimerCleaner($Controller);

		$this->set_message(__('multiple-submission-protection', 'captcha-for-contact-form-7'));
		$this->get_logger()->debug('Message for multiple submission set.', [
			'message_key' => 'multiple-submission-protection',
		]);

		$this->get_logger()->info('Constructor completed.', [
			'class' => __CLASS__,
		]);
	}

	/**
	 * Creates a new instance of the CaptchaTimer class.
	 *
	 * This method creates and returns a new instance of the CaptchaTimer class, which is used for managing captcha
	 * timers.
	 *
	 * @return CaptchaTimer A new instance of the CaptchaTimer class.
	 */
	public function factory(): CaptchaTimer
	{
		$this->get_logger()->info('Creating new CaptchaTimer instance via factory method.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$captchaTimer = new CaptchaTimer($this->get_logger());

		$this->get_logger()->debug('New CaptchaTimer instance successfully created.');

		return $captchaTimer;
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
	protected function is_enabled(): bool
	{
		$is_enabled = $this->get_protection_setting('protection_multiple_submission_enable');

		if ($is_enabled === '' || $is_enabled === null) {
			// Default: active if not explicitly set
			$is_enabled = 1;
		}

		if ($is_enabled) {
			$this->get_logger()->info('Multiple submission protection is enabled.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		} else {
			$this->get_logger()->warning('Multiple submission protection is disabled.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-multiple_submission', $is_enabled);

		if ($is_enabled && !$result) {
			$this->get_logger()->debug('Protection skipped by filter.', [
				'filter' => 'f12-cf7-captcha-skip-validation-multiple_submission',
				'original_state' => $is_enabled,
			]);
		}

		return $result;
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
	public function is_spam(...$args): bool
	{
		$this->get_logger()->info('Performing spam check for multiple submission.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!isset($args[0])) {
			$this->get_logger()->warning('No post data available for checking.');
			return false;
		}

		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam check skipped because protection is disabled.');
			return false;
		}

		$array_post_data = $args[0];
		$field_name = $this->get_field_name();

		if (!isset($array_post_data[$field_name])) {
			$this->get_logger()->warning('Hash field missing in submitted data. Classified as spam.');
			return true;
		}

		$hash = sanitize_text_field($array_post_data[$field_name]);
		$this->get_logger()->debug('Retrieved hash value.', ['hash' => $hash]);

		/**
		 * Load the timer controller and the timer.
		 */
		$Timer_Controller = $this->Controller->get_module('timer');
		$Timer = $Timer_Controller->get_timer($hash);

		if (!$Timer) {
			$this->get_logger()->warning('No matching timer found for hash. Classified as spam.', ['hash' => $hash]);
			return true;
		}

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

		if ($time_passed < $minimum_time_in_ms) {
			$this->get_logger()->warning('Form submitted too quickly. Classified as spam.');
			return true;
		}

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
		$this->get_logger()->info('Generating captcha field for multiple submission protection.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha field not generated because protection is disabled.');
			return '';
		}

		$field_name = $this->get_field_name();

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_module('timer');

		if (!$Timer_Controller) {
			$this->get_logger()->error('Timer controller module not found.');
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
			'field_name' => $field_name,
		]);

		return $html;
	}

	/**
	 * Retrieves the validation time.
	 *
	 * This method returns the length of time, in milliseconds, that is allowed for validation.
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
	 * Retrieves the field name.
	 *
	 * This method returns the name of the field used for multiple submission protection.
	 *
	 * @return string The field name.
	 */
	protected function get_field_name()
	{
		$field_name = 'f12_multiple_submission_protection';

		$this->get_logger()->debug('Retrieving field name.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'field_name' => $field_name,
		]);

		return $field_name;
	}

	/**
	 * Initializes the method.
	 *
	 * This method is called to initialize the functionality of the code.
	 *
	 * @return void
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

	public function success(): void
	{
		$this->get_logger()->info('Successful multiple submission validation.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implement the logic for the success case here.
		// In this specific context, this could mean that no further actions are necessary,
		// since the check has already taken place in the is_spam() method.
		// If the timer should only be deleted here after successful validation,
		// the deletion logic would need to be moved from is_spam() to here.
	}
}