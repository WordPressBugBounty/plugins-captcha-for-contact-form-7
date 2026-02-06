<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPForms
 */
class ControllerWPForms extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'WPForms';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'wpforms';

	/**
	 * Check if the captcha is enabled for WooCommerce
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das WPForms-Modul aktiviert ist.');

		// Check if the WPForms plugin is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus des Moduls: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		// Get the global setting for WPForms protection.
		$setting_value = $this->Controller->get_settings('protection_wpforms_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_wpforms_enable": ' . $setting_value);

		// Determine if the module should be active.
		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_wpforms_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}
		$is_active = $is_installed && (int)$setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_wpforms', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}
	/**
	 * Check if WPForms plugin is installed
	 *
	 * @return bool True if WPForms is installed, false otherwise
	 */
	public function is_installed(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob WPForms installiert ist.');

		// Check if the 'WPForms' class exists, which is a reliable indicator of the plugin's presence.
		$is_installed = class_exists('WPForms');

		// Log the result of the check.
		if ($is_installed) {
			$this->get_logger()->info('WPForms wurde gefunden.');
		} else {
			$this->get_logger()->critical('WPForms wurde nicht gefunden. Das Modul kann nicht korrekt funktionieren.');
		}

		// Return the result.
		return $is_installed;
	}

	/**
	 * @private WordPress Hook
	 */
	public function on_init(): void
	{
		// Log the start of the initialization process for the WPForms module.
		$this->get_logger()->info('Starte die Initialisierung des WPForms-Moduls.');

		// Add an action to insert the captcha field into WPForms. The priority of 10 ensures
		// it runs after other default outputs, and it accepts 5 arguments.
		$this->get_logger()->debug('Füge die Aktion "wpforms_frontend_output" hinzu, um das Captcha-Feld anzuzeigen.');
		add_action('wpforms_frontend_output', array($this, 'wp_add_spam_protection'), 10, 5);

		// Add a filter to validate the form submission for spam. The priority of 10 ensures
		// it runs at a standard time during the validation process, and it accepts 2 arguments.
		$this->get_logger()->debug('Füge den Filter "wpforms_process_initial_errors" hinzu, um das Formular auf Spam zu prüfen.');
		add_filter('wpforms_process_initial_errors', array($this, 'wp_is_spam'), 10, 2);

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
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für WPForms.');

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
		// Log the start of the spam check for WPForms.
		$this->get_logger()->info('Starte Spam-Validierung für WPForms.');

		$errors = $args[0];
		$form_data = $args[1];

		// Get the POST data to check for spam.
		$array_post_data = $_POST;
		$this->get_logger()->debug('Überprüfe die folgenden POST-Daten auf Spam.', [
			'post_data_keys' => array_keys($array_post_data),
		]);

		// Check if the form ID is set.
		if (!isset($form_data['id'])) {
			$this->get_logger()->warning('Form-ID fehlt im Formulardaten-Array. Überspringe die Überprüfung.');
			return $errors;
		}

		$form_id = $form_data['id'];
		$this->get_logger()->debug('Formular-ID: ' . $form_id);

		// Get the protection module instance.
		$Protection = $this->Controller->get_modul('protection');

		// Perform the spam check.
		if ($Protection->is_spam($array_post_data)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt! Fehlermeldung: ' . $message);

			// Add the error message to the errors array, specifically for the form's footer.
			$errors[$form_id]['footer'] = sprintf(esc_html__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $message);
			$this->get_logger()->info('Fehlermeldung dem Formular-Footer hinzugefügt.');

			// Log the final outcome.
			$this->get_logger()->critical('Spam erkannt. Gebe Fehler-Array zurück, um die Übermittlung zu stoppen.');
		} else {
			// Log that no spam was detected.
			$this->get_logger()->info('Kein Spam erkannt. Die Übermittlung wird fortgesetzt.');
		}

		// Return the (potentially modified) errors array.
		return $errors;
	}
}