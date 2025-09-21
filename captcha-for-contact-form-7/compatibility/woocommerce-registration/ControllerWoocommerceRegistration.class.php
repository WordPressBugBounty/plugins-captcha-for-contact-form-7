<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerWoocommerce
 */
class ControllerWoocommerceRegistration extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'WooCommerce Registration';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'woocommerce_registration';

    /**
     * Check if the captcha is enabled for WooCommerce
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das WooCommerce-Registrierungs-Modul aktiviert ist.');

		// Check if the WooCommerce plugin is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus des Moduls: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		// Get the global setting for WooCommerce registration protection.
		$setting_value = $this->Controller->get_settings('protection_woocommerce_registration_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_woocommerce_registration_enable": ' . $setting_value);

		// Determine if the module should be active.
		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_woocommerce_registration_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}
		$is_active = $is_installed && $setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_woocommerce_registration', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

    /**
     * Check if WooCommerce plugin is installed.
     *
     * @return bool True if WooCommerce is installed, false otherwise.
     */
	public function is_installed(): bool
	{
		// Logge den Beginn der Überprüfung, ob WooCommerce installiert ist.
		$this->get_logger()->info('Starte Überprüfung, ob WooCommerce installiert ist.');

		// Prüfe, ob die Klasse 'WooCommerce' existiert, was ein zuverlässiger Indikator für die Installation ist.
		$is_installed = class_exists('WooCommerce');

		// Logge das Ergebnis der Überprüfung.
		if ($is_installed) {
			$this->get_logger()->info('WooCommerce wurde gefunden.');
		} else {
			$this->get_logger()->critical('WooCommerce wurde nicht gefunden. Das Modul kann nicht korrekt funktionieren.');
		}

		// Gib das Ergebnis zurück.
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Log the start of the initialization process for the WooCommerce Registration module.
		$this->get_logger()->info('Starte die Initialisierung des WooCommerce Registrierungs-Moduls.');

		// Set the module name.
		$this->name = __('WooCommerce Registration', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Add an action to insert the captcha field into the registration form.
		$this->get_logger()->debug('Füge die Aktion "woocommerce_register_form" hinzu, um das Captcha-Feld anzuzeigen.');
		add_action('woocommerce_register_form', array($this, 'wp_add_spam_protection'));

		// Add a filter to validate the registration form for spam.
		$this->get_logger()->debug('Füge den Filter "woocommerce_process_registration_errors" hinzu, um die Registrierung auf Spam zu prüfen.');
		add_filter('woocommerce_process_registration_errors', array($this, 'wp_is_spam'), 10, 4);

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
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für WooCommerce-Registrierung.');

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
     * @param bool  $is_spam         Whether the post is considered as spam initially.
     * @param array $array_post_data The array containing the POST data.
     *
     * @return bool Whether the post is considered as spam.
     * @throws \Exception
     */
	public function wp_is_spam(...$args)
	{
		// Logge den Beginn der Spam-Überprüfung für die WooCommerce-Registrierung.
		$this->get_logger()->info('Starte Spam-Überprüfung für die WooCommerce-Registrierung.');

		$errors = $args[0];

		// Hole die POST-Daten zur Überprüfung.
		$array_post_data = $_POST;
		$this->get_logger()->debug('Überprüfe die folgenden POST-Daten auf Spam.', [
			'post_data_keys' => array_keys($array_post_data),
		]);

		// Hole das Schutz-Modul.
		$Protection = $this->Controller->get_modul('protection');

		// Führe die Spam-Überprüfung durch.
		if ($Protection->is_spam($array_post_data)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt! Fehlermeldung: ' . $message);

			// Füge die Fehlermeldung dem Error-Objekt hinzu, falls es existiert.
			if (is_object($errors)) {
				$errors->add('spam', sprintf(__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $message));
				$this->get_logger()->info('Fehlermeldung dem Fehlerobjekt hinzugefügt.');
			} else {
				$this->get_logger()->warning('Fehlerobjekt nicht gefunden. Fehlermeldung kann nicht hinzugefügt werden.');
			}

			// Logge das Ergebnis der Überprüfung.
			$this->get_logger()->critical('Spam erkannt. Gebe das Fehlerobjekt zurück, um die Übermittlung zu stoppen.');
		} else {
			// Logge, dass kein Spam erkannt wurde.
			$this->get_logger()->info('Kein Spam erkannt. Die Überprüfung wird fortgesetzt.');
		}

		// Füge einen Filter hinzu, um eine doppelte Überprüfung zu verhindern.
		add_filter('f12_cf7_captcha_login_login_validator', '__return_true');
		$this->get_logger()->debug('Filter "f12_cf7_captcha_login_login_validator" wurde hinzugefügt.');

		// Gib das Fehlerobjekt zurück, unabhängig davon, ob Spam erkannt wurde.
		return $errors;
	}
}