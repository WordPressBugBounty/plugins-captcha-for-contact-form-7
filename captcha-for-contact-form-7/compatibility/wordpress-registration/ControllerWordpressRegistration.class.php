<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPForms
 */
class ControllerWordpressRegistration extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'WordPress Registration';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'wordpress_registration';

	/**
	 * Check if the captcha is enabled for WooCommerce
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das WordPress-Registrierungs-Modul aktiviert ist.');

		// Get the global setting for WordPress registration protection.
		$setting_value = $this->Controller->get_settings('protection_wordpress_registration_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_wordpress_registration_enable": ' . $setting_value);

		// Determine if the module should be active.
		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_wordpress_registration_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}
		$is_active = (int)$setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_wordpress_registration', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

	/**
	 * Check if the software is installed
	 *
	 * @return bool True if the software is installed, false otherwise
	 */
	public function is_installed(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das WordPress-Modul installiert ist.');

		// Since this function always returns true, log the outcome directly.
		$is_installed = true;

		// Log the result of the check.
		$this->get_logger()->info('Das WordPress-Modul wird als installiert betrachtet. Gebe "true" zurück.');

		// Return the result.
		return $is_installed;
	}

	/**
	 * @private WordPress Hook
	 */
	public function on_init(): void
	{
		// Log the start of the initialization process for the WordPress Registration module.
		$this->get_logger()->info('Starte die Initialisierung des WordPress Registrierungs-Moduls.');

		// Set the module name.
		$this->name = __('WordPress Registration', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Add an action to insert the captcha field into the registration form.
		$this->get_logger()->debug('Füge die Aktion "register_form" hinzu, um das Captcha-Feld anzuzeigen.');
		add_action('register_form', array($this, 'wp_add_spam_protection'));

		// Add a filter to validate the registration form for spam.
		$this->get_logger()->debug('Füge den Filter "registration_errors" hinzu, um die Registrierung auf Spam zu prüfen.');
		add_filter('registration_errors', array($this, 'wp_is_spam'), 10, 3);

		// Log the successful completion of the initialization.
		$this->get_logger()->info('Initialisierung abgeschlossen.');
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
	public function wp_add_spam_protection(...$args)
	{
		// Log the beginning of the process.
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für die WordPress-Registrierung.');

		// Get the protection module instance.
		$Protection = $this->Controller->get_modul('protection');

		// Get the captcha HTML from the protection module.
		$captcha = $Protection->get_captcha();

		// Log the size of the retrieved captcha code.
		$this->get_logger()->debug('Captcha-Code wurde abgerufen. Größe: ' . strlen($captcha) . ' Zeichen.');

		// Check if the captcha code is empty.
		if (empty($captcha)) {
			// Log a warning if the code is empty, as it indicates a potential issue.
			$this->get_logger()->warning('Der Captcha-Code ist leer. Es wird kein HTML ausgegeben.');
		} else {
			// Log the successful output of the captcha.
			$this->get_logger()->info('Captcha-Code wird ausgegeben.');
		}

		// Echo the captcha code directly into the form.
		echo $captcha;

		// Log the end of the process.
		$this->get_logger()->info('Ausgabe des Captcha-Codes abgeschlossen.');
	}

	/**
	 * Check if a post is considered as spam
	 *
	 * @param array $array_post_data The array containing the POST data.
	 *
	 * @throws \Exception
	 */
	public function wp_is_spam(...$args)
	{
		// Log the start of the spam check for WordPress registration errors.
		$this->get_logger()->info('Starte Spam-Überprüfung für die WordPress-Registrierung.');

		/**
		 * @var \WP_Error $error
		 */
		$error = $args[0];

		// Get the POST data to check for spam.
		$array_post_data = $_POST;
		$this->get_logger()->debug('Überprüfe die folgenden POST-Daten auf Spam.', [
			'post_data_keys' => array_keys($array_post_data),
		]);

		// Apply a filter to check if the validation has already been performed by another module.
		if (apply_filters('f12_cf7_captcha_login_login_validator', false)) {
			$this->get_logger()->info('Überprüfung wurde bereits von einem anderen Modul durchgeführt. Überspringe die Ausführung.');
			return $error;
		}

		// Get the protection module instance.
		$Protection = $this->Controller->get_modul('protection');

		// Perform the spam check.
		if ($Protection->is_spam($array_post_data)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt! Fehlermeldung: ' . $message);

			// Add an error to the existing WP_Error object.
			$error->add(500, sprintf(__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $message));

			// Log the result.
			$this->get_logger()->critical('Spam erkannt. Fehlermeldung dem Fehlerobjekt hinzugefügt.');
		} else {
			// Log that no spam was detected.
			$this->get_logger()->info('Kein Spam erkannt. Die Überprüfung wird fortgesetzt.');
		}

		// Return the (potentially modified) WP_Error object.
		return $error;
	}
}