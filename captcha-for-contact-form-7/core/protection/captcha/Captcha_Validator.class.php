<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\protection\Field_Token;
use f12_cf7_captcha\core\timer\CaptchaTimerCleaner;
use f12_cf7_captcha\core\UserData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Captcha_Validator extends BaseProtection {

	private CaptchaCleaner $_Captcha_Cleaner;
	private CaptchaAjax $_Captcha_Ajax;

	/**
	 * Constructor method for the class.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha controller object.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info(
			"__construct(): CF7Captcha controller initialized",
			[
				'plugin'    => 'f12-cf7-captcha',
				'class'     => __CLASS__
			]
		);

		// Submodules laden
		try {
			$this->_Captcha_Ajax = new CaptchaAjax($Controller);
			$this->get_logger()->debug(
				"__construct(): Submodule CaptchaAjax loaded",
				['plugin' => 'f12-cf7-captcha']
			);

			$this->_Captcha_Cleaner = new CaptchaCleaner($Controller);
			$this->get_logger()->debug(
				"__construct(): Submodule CaptchaCleaner loaded",
				['plugin' => 'f12-cf7-captcha']
			);
		} catch (\Throwable $e) {
			$this->get_logger()->error(
				"__construct(): Error loading submodules",
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage()
				]
			);
			throw $e;
		}

		$this->set_message_on_init(function () {
			return __('captcha-protection', 'captcha-for-contact-form-7');
		});

		$this->get_logger()->info(
			"__construct(): Initialization completed",
			['plugin' => 'f12-cf7-captcha']
		);
	}


	/**
	 * Create and get a Captcha object.
	 *
	 * This method creates a new Captcha object using the IP address obtained from the User_Data module.
	 *
	 * @return Captcha The newly created Captcha object.
	 * @throws \Exception
	 */
	public function factory(): Captcha
	{
		/**
		 * @var UserData $User_Data
		 */
		$User_Data = $this->Controller->get_module('user-data');
		$ipAddress = $User_Data->get_ip_address();

		$this->get_logger()->debug(
			"factory(): Creating new captcha object",
			[
				'plugin'     => 'f12-cf7-captcha',
				'ip_address' => $ipAddress
			]
		);

		$captcha = new Captcha(
			$this->Controller->get_logger(),
			$ipAddress
		);

		$this->get_logger()->info(
			"factory(): New captcha object created",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => Captcha::class
			]
		);

		return $captcha;
	}


	/**
	 * Retrieves the instance of the CaptchaAjax submodule.
	 *
	 * @return CaptchaAjax The instance of the CaptchaAjax.
	 */
	public function get_captcha_ajax(): CaptchaAjax
	{
		return $this->_Captcha_Ajax;
	}

	/**
	 * Retrieves the instance of the CaptchaCleaner.
	 *
	 * This method returns the instance of the CaptchaCleaner class that
	 * is held by the current object.
	 *
	 * @return CaptchaCleaner The instance of the CaptchaCleaner.
	 */
	public function get_captcha_cleaner(): CaptchaCleaner
	{
		// The property is typed and set in the constructor, so there is no "not available"
		// case to warn about.
		$this->get_logger()->debug(
			"get_captcha_cleaner(): CaptchaCleaner instance returned",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => get_class($this->_Captcha_Cleaner)
			]
		);

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
	protected function is_enabled(): bool
	{
		$is_enabled = (int) $this->get_protection_setting('protection_captcha_enable') === 1;

		if (f12_is_debug()) {
			$this->get_logger()->debug(
				"is_enabled(): Setting retrieved from get_settings()",
				[
					'plugin'   => 'f12-cf7-captcha',
					'enabled'  => $is_enabled ? 'yes' : 'no'
				]
			);
		}

		$filtered = apply_filters('f12-cf7-captcha-skip-validation-captcha', $is_enabled);

		if (f12_is_debug() && $filtered !== $is_enabled) {
			$this->get_logger()->info(
				"is_enabled(): Result overridden by filter",
				[
					'plugin'        => 'f12-cf7-captcha',
					'original'      => $is_enabled ? 'yes' : 'no',
					'after_filter'  => $filtered ? 'yes' : 'no'
				]
			);
		}

		return $filtered;
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
		$debug = f12_is_debug();

		if (!isset($args[0])) {
			if ($debug) {
				$this->get_logger()->warning(
					"is_spam(): No post data provided",
					['plugin' => 'f12-cf7-captcha']
				);
			}
			return false;
		}

		if (!$this->is_enabled()) {
			if ($debug) {
				$this->get_logger()->debug(
					"is_spam(): Captcha is disabled - no spam check",
					['plugin' => 'f12-cf7-captcha']
				);
			}
			return false;
		}

		$array_post_data   = $args[0];
		$validation_method = $this->get_validation_method();
		$field_name        = $this->resolve_submitted_field_name($array_post_data, $validation_method);

		if ($field_name === null) {
			if ($debug) {
				$this->get_logger()->info(
					"is_spam(): Field not present - spam detected",
					[
						'plugin'     => 'f12-cf7-captcha',
						'field_name' => $this->get_field_name()
					]
				);
			}
			return true;
		}

		// Honeypot -> no hash expected
		if ($validation_method !== 'honey' && !isset($array_post_data[$field_name . '_hash'])) {
			if ($debug) {
				$this->get_logger()->info(
					"is_spam(): Hash missing for method '{$validation_method}' - spam detected",
					[
						'plugin'     => 'f12-cf7-captcha',
						'field_name' => $field_name
					]
				);
			}
			return true;
		}

		$hash = '';
		if ($validation_method !== 'honey') {
			$hash = $array_post_data[$field_name . '_hash'];
		}

		// Generator validieren
		$Generator = $this->get_generator($validation_method);

		if ($Generator->is_valid($array_post_data[$field_name], $hash)) {
			if ($debug) {
				$this->get_logger()->debug(
					"is_spam(): Captcha valid - not spam",
					[
						'plugin'     => 'f12-cf7-captcha',
						'field_name' => $field_name,
						'method'     => $validation_method
					]
				);
			}
			return false;
		}

		if ($debug) {
			$this->get_logger()->warning(
				"is_spam(): Captcha validation failed - spam detected",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name,
					'method'     => $validation_method
				]
			);
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
	 * @throws \Exception
	 */
	public function get_captcha(...$args): string
	{
		if (!$this->is_enabled()) {
			$this->get_logger()->debug(
				"get_captcha(): Captcha is disabled - no field generated",
				['plugin' => 'f12-cf7-captcha']
			);
			return '';
		}

		$validation_method = $this->get_validation_method();
		$field_name        = $this->get_render_field_name($validation_method);
		$generator         = $this->get_generator($validation_method);

		$this->get_logger()->info(
			"get_captcha(): Captcha field being generated",
			[
				'plugin'     => 'f12-cf7-captcha',
				'field_name' => $field_name,
				'generator'  => get_class($generator)
			]
		);

		return $generator->get_field($field_name);
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
	public function get_generator(string $validation_method = ''): CaptchaGenerator
	{
		// Fallback to default method
		if (empty($validation_method)) {
			$validation_method = $this->get_validation_method();
			$this->get_logger()->debug(
				"get_generator(): No parameter provided, using default validation method",
				[
					'plugin' => 'f12-cf7-captcha',
					'method' => $validation_method
				]
			);
		}

		switch ($validation_method) {
			case 'math':
				$Captcha_Generator = new CaptchaMathGenerator($this->Controller);
				$this->get_logger()->info(
					"get_generator(): Math generator instantiated",
					['plugin' => 'f12-cf7-captcha']
				);
				break;

			case 'image':
				$Captcha_Generator = new CaptchaImageGenerator($this->Controller);
				$this->get_logger()->info(
					"get_generator(): Image generator instantiated",
					['plugin' => 'f12-cf7-captcha']
				);
				break;

			default:
				$Captcha_Generator = new CaptchaHoneypotGenerator($this->Controller);
				$this->get_logger()->info(
					"get_generator(): Honeypot generator instantiated (fallback)",
					['plugin' => 'f12-cf7-captcha']
				);
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
	protected function get_validation_method(): string
	{
		$method = $this->get_protection_setting('protection_captcha_method');

		if (empty($method)) {
			$this->get_logger()->warning(
				"get_validation_method(): No method found in settings, fallback to 'honey'",
				['plugin' => 'f12-cf7-captcha']
			);
			$method = 'honey'; // sensible fallback
		} else {
			$this->get_logger()->debug(
				"get_validation_method(): Method determined",
				[
					'plugin' => 'f12-cf7-captcha',
					'method' => $method
				]
			);
		}

		return $method;
	}


	/**
	 * Retrieves the field name.
	 *
	 * This method returns the name of the field used for multiple submission protection.
	 *
	 * @return string The field name.
	 */
	protected function get_field_name(): string
	{
		$field_name = $this->Controller->get_settings('protection_captcha_field_name', 'global');

		if (empty($field_name)) {
			$this->get_logger()->warning(
				"get_field_name(): No field name found in settings - fallback set",
				['plugin' => 'f12-cf7-captcha']
			);

			// sensible fallback
			$field_name = 'captcha_field';
		} else {
			$this->get_logger()->debug(
				"get_field_name(): Field name determined",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
		}

		return $field_name;
	}


	/**
	 * The field name to render the captcha input under.
	 *
	 * Only the honeypot rotates. The math and image captchas carry a companion `_hash` field
	 * and are reloaded over AJAX by name, so their naming stays under the operator's control
	 * via the settings — and unlike the honeypot they are a real challenge, which a
	 * predictable name does not weaken.
	 *
	 * @param string $validation_method honey, math or image.
	 */
	protected function get_render_field_name(string $validation_method): string
	{
		if ($validation_method !== 'honey') {
			return $this->get_field_name();
		}

		return Field_Token::current()->field_name(Field_Token::SLOT_HONEYPOT);
	}


	/**
	 * Find the submitted captcha field, whether it arrived under the rotated or legacy name.
	 *
	 * Returns null when it is absent under both, which the caller treats as spam. The legacy
	 * fallback exists so that form HTML rendered before this version — or served from a
	 * full-page cache — keeps validating instead of blocking real visitors after an update.
	 *
	 * @param array  $array_post_data   The submitted data.
	 * @param string $validation_method honey, math or image.
	 *
	 * @return string|null The name the field actually arrived under.
	 */
	protected function resolve_submitted_field_name(array $array_post_data, string $validation_method): ?string
	{
		if ($validation_method === 'honey') {
			$Token = Field_Token::from_request($array_post_data);

			if ($Token !== null) {
				$rotated = $Token->field_name(Field_Token::SLOT_HONEYPOT);

				if (isset($array_post_data[$rotated])) {
					return $rotated;
				}
			}
		}

		$legacy = $this->get_field_name();

		return isset($array_post_data[$legacy]) ? $legacy : null;
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
		$this->get_logger()->info(
			"on_init(): Captcha module initialization started",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		// Later you could register hooks/filters here, e.g.:
		// add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

		$this->get_logger()->debug(
			"on_init(): Initialization completed",
			[
				'plugin' => 'f12-cf7-captcha'
			]
		);
	}

	public function success(): void
	{
		$this->get_logger()->info(
			"success(): Captcha validation completed successfully",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		// Future extension point:
		// - Set success message
		// - Analytics/Event tracking
		// - Redirect/Custom action
	}

}