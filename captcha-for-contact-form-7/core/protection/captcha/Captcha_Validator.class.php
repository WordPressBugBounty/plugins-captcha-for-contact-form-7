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
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info(
			"__construct(): CF7Captcha Controller initialisiert",
			[
				'plugin'    => 'f12-cf7-captcha',
				'class'     => __CLASS__
			]
		);

		// Submodules laden
		try {
			new CaptchaAjax($Controller);
			$this->get_logger()->debug(
				"__construct(): Submodul CaptchaAjax geladen",
				['plugin' => 'f12-cf7-captcha']
			);

			$this->_Captcha_Cleaner = new CaptchaCleaner($Controller);
			$this->get_logger()->debug(
				"__construct(): Submodul CaptchaCleaner geladen",
				['plugin' => 'f12-cf7-captcha']
			);
		} catch (\Throwable $e) {
			$this->get_logger()->error(
				"__construct(): Fehler beim Laden der Submodule",
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage()
				]
			);
			throw $e;
		}

		$this->set_message(__('captcha-protection', 'captcha-for-contact-form-7'));

		$this->get_logger()->info(
			"__construct(): Initialisierung abgeschlossen",
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
		$User_Data = $this->Controller->get_modul('user-data');
		$ipAddress = $User_Data->get_ip_address();

		$this->get_logger()->debug(
			"factory(): Erzeuge neues Captcha-Objekt",
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
			"factory(): Neues Captcha-Objekt erstellt",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => Captcha::class
			]
		);

		return $captcha;
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
		if ($this->_Captcha_Cleaner instanceof CaptchaCleaner) {
			$this->get_logger()->debug(
				"get_captcha_cleaner(): Instanz von CaptchaCleaner zurückgegeben",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => get_class($this->_Captcha_Cleaner)
				]
			);
		} else {
			$this->get_logger()->warning(
				"get_captcha_cleaner(): Keine gültige Instanz von CaptchaCleaner vorhanden",
				['plugin' => 'f12-cf7-captcha']
			);
		}

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
		$is_enabled = (int) $this->Controller->get_settings('protection_captcha_enable', 'global') === 1;

		$this->get_logger()->debug(
			"is_enabled(): Einstellung aus get_settings() ermittelt",
			[
				'plugin'   => 'f12-cf7-captcha',
				'enabled'  => $is_enabled ? 'ja' : 'nein'
			]
		);

		$filtered = apply_filters('f12-cf7-captcha-skip-validation-captcha', $is_enabled);

		if ($filtered !== $is_enabled) {
			$this->get_logger()->info(
				"is_enabled(): Ergebnis durch Filter überschrieben",
				[
					'plugin'        => 'f12-cf7-captcha',
					'original'      => $is_enabled ? 'ja' : 'nein',
					'nach_filter'   => $filtered ? 'ja' : 'nein'
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
		if (!isset($args[0])) {
			$this->get_logger()->warning(
				"is_spam(): Keine Post-Daten übergeben",
				['plugin' => 'f12-cf7-captcha']
			);
			return false;
		}

		if (!$this->is_enabled()) {
			$this->get_logger()->debug(
				"is_spam(): Captcha ist deaktiviert → kein Spam-Check",
				['plugin' => 'f12-cf7-captcha']
			);
			return false;
		}

		$array_post_data = $args[0];
		$field_name      = $this->get_field_name();

		if (!isset($array_post_data[$field_name])) {
			$this->get_logger()->info(
				"is_spam(): Feld nicht vorhanden → Spam erkannt",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
			return true;
		}

		$validation_method = $this->get_validation_method();

		// Honeypot → kein Hash erwartet
		if ($validation_method !== 'honey' && !isset($array_post_data[$field_name . '_hash'])) {
			$this->get_logger()->info(
				"is_spam(): Hash fehlt bei Methode '{$validation_method}' → Spam erkannt",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
			return true;
		}

		$hash = '';
		if ($validation_method !== 'honey') {
			$hash = $array_post_data[$field_name . '_hash'];
		}

		// Generator validieren
		$Generator = $this->get_generator($validation_method);

		if ($Generator->is_valid($array_post_data[$field_name], $hash)) {
			$this->get_logger()->debug(
				"is_spam(): Captcha gültig → kein Spam",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name,
					'method'     => $validation_method
				]
			);
			return false;
		}

		$this->get_logger()->warning(
			"is_spam(): Captcha-Validierung fehlgeschlagen → Spam erkannt",
			[
				'plugin'     => 'f12-cf7-captcha',
				'field_name' => $field_name,
				'method'     => $validation_method
			]
		);

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
				"get_captcha(): Captcha ist deaktiviert – kein Feld generiert",
				['plugin' => 'f12-cf7-captcha']
			);
			return '';
		}

		$field_name = $this->get_field_name();
		$generator  = $this->get_generator();

		$this->get_logger()->info(
			"get_captcha(): Captcha-Feld wird generiert",
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
		// Fallback auf Default-Methode
		if (empty($validation_method)) {
			$validation_method = $this->get_validation_method();
			$this->get_logger()->debug(
				"get_generator(): Kein Parameter übergeben, nehme Standard-Validation-Methode",
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
					"get_generator(): Math-Generator instanziiert",
					['plugin' => 'f12-cf7-captcha']
				);
				break;

			case 'image':
				$Captcha_Generator = new CaptchaImageGenerator($this->Controller);
				$this->get_logger()->info(
					"get_generator(): Image-Generator instanziiert",
					['plugin' => 'f12-cf7-captcha']
				);
				break;

			default:
				$Captcha_Generator = new CaptchaHoneypotGenerator($this->Controller);
				$this->get_logger()->info(
					"get_generator(): Honeypot-Generator instanziiert (Fallback)",
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
		$method = $this->Controller->get_settings('protection_captcha_method', 'global');

		if (empty($method)) {
			$this->get_logger()->warning(
				"get_validation_method(): Keine Methode in den Settings gefunden, Fallback auf 'honey'",
				['plugin' => 'f12-cf7-captcha']
			);
			$method = 'honey'; // sinnvoller Fallback
		} else {
			$this->get_logger()->debug(
				"get_validation_method(): Methode ermittelt",
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
				"get_field_name(): Kein Feldname in den Settings gefunden – Fallback gesetzt",
				['plugin' => 'f12-cf7-captcha']
			);

			// sinnvoller Fallback
			$field_name = 'captcha_field';
		} else {
			$this->get_logger()->debug(
				"get_field_name(): Feldname ermittelt",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
		}

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
		$this->get_logger()->info(
			"on_init(): Initialisierung des Captcha-Moduls gestartet",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		// Hier könntest du später Hooks/Filter registrieren, z. B.:
		// add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

		$this->get_logger()->debug(
			"on_init(): Initialisierung abgeschlossen",
			[
				'plugin' => 'f12-cf7-captcha'
			]
		);
	}

	public function success(): void
	{
		$this->get_logger()->info(
			"success(): Erfolgreiche Captcha-Validierung ausgeführt",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		// Späterer Erweiterungspunkt:
		// - Erfolgsmeldung setzen
		// - Analytics/Event-Tracking
		// - Weiterleitung/Custom Action
	}

}