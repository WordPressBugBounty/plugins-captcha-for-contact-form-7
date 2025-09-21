<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerUltimateMember
 */
class ControllerUltimateMember extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Ultimate Member';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'ultimatemember';

    /**
     * Check if the captcha is enabled for ultimatemember
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das Ultimate Member-Modul aktiviert ist.');

		// Check if the Ultimate Member plugin is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus des Moduls: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		// Get the global setting for Ultimate Member protection.
		$setting_value = $this->Controller->get_settings('protection_ultimatemember_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_ultimatemember_enable": ' . $setting_value);

		// Determine if the module should be active.
		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_ultimatemember_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}
		$is_active = $is_installed && $setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_ultimatemember', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

    /**
     * Check if the Ultimate Member plugin is installed
     *
     * This method checks if the "UM_Functions" class exists, which indicates that the Ultimate Member plugin is
     * installed.
     *
     * @return bool Returns true if the Ultimate Member plugin is installed, false otherwise.
     */
	public function is_installed(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob Ultimate Member installiert ist.');

		// Check if the 'UM_Functions' class exists, which is a reliable indicator of Ultimate Member.
		$is_installed = class_exists('UM_Functions');

		// Log the result of the check.
		if ($is_installed) {
			$this->get_logger()->info('Ultimate Member wurde gefunden.');
		} else {
			$this->get_logger()->critical('Ultimate Member wurde nicht gefunden. Das Modul kann nicht korrekt funktionieren.');
		}

		// Return the result.
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Log the start of the initialization process for the Ultimate Member module.
		$this->get_logger()->info('Starte die Initialisierung des Ultimate Member-Moduls.');

		// Set the module name.
		$this->name = __('Ultimate Member', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Add actions to insert the captcha on login and registration forms.
		$this->get_logger()->debug('Füge die Aktionen "um_after_login_fields" und "um_after_register_fields" hinzu, um das Captcha anzuzeigen.');
		add_action('um_after_login_fields', [$this, 'wp_add_spam_protection']);
		add_action('um_after_register_fields', [$this, 'wp_add_spam_protection']);

		// Add actions for spam validation on both login and registration forms.
		$this->get_logger()->debug('Füge die Aktionen für die Spam-Überprüfung "um_submit_form_errors_hook_login" und "um_submit_form_errors_hook__registration" hinzu.');
		add_action('um_submit_form_errors_hook_login', [$this, 'wp_is_spam'], 5, 1);
		add_action('um_submit_form_errors_hook__registration', [$this, 'wp_is_spam'], 5, 1);

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
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für Ultimate Member Formulare.');

		$Protection = $this->Controller->get_modul('protection');

		// Get the captcha HTML from the protection module.
		$captcha = $Protection->get_captcha();
		$this->get_logger()->debug('Captcha-Code wurde abgerufen. Größe: ' . strlen($captcha) . ' Zeichen.');

		// Echo the captcha HTML.
		echo $captcha;
		$this->get_logger()->info('Captcha-Code wurde erfolgreich in das Formular ausgegeben.');

		// Check if a captcha-related error message exists and the form was submitted.
		if (!empty($Protection->get_message()) && !empty($_POST)) {
			// Log the fact that an error message will be displayed.
			$this->get_logger()->warning('Eine Fehlermeldung wird ausgegeben, da der Captcha-Test fehlgeschlagen ist.');

			// Output the error message.
			echo '<div class="um-field-error">' . sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $Protection->get_message()) . '</div>';
		}

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
		// Logge den Beginn der Spam-Überprüfung für Ultimate Member.
		$this->get_logger()->info('Starte die Spam-Überprüfung für Ultimate Member.');

		$parameter = $args[0];

		$array_post_data = $_POST;
		$this->get_logger()->debug('Überprüfe die folgenden Daten auf Spam:', ['post_data_keys' => array_keys($array_post_data)]);

		$Protection = $this->Controller->get_modul('protection');

		if ($Protection->is_spam($array_post_data)) {
			// Logge eine Warnung, wenn Spam erkannt wurde.
			$this->get_logger()->warning('Spam erkannt! Markiere die Formularübermittlung als ungültig.');
			$this->is_valid = false;

			// Füge die Fehlermeldung zum Formular hinzu, falls die UM-Funktion existiert.
			if (function_exists('UM')) {
				$message = $Protection->get_message();
				$this->get_logger()->info('Füge die Fehlermeldung zum UM-Formular hinzu.', ['message' => $message]);
				UM()->form()->add_error('f12_captcha', sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $message));
			} else {
				$this->get_logger()->warning('UM-Funktion nicht gefunden. Fehlermeldung kann nicht hinzugefügt werden.');
			}

			// Logge das Ergebnis der Funktion.
			$this->get_logger()->critical('Spam erkannt. Gebe "true" zurück, um die Übermittlung zu stoppen.');
			return true;
		}

		// Logge, wenn kein Spam erkannt wurde.
		$this->get_logger()->info('Kein Spam erkannt. Die Überprüfung wird fortgesetzt.');

		// Verhindere eine doppelte Überprüfung, falls andere Module aktiv sind.
		add_filter('f12_cf7_captcha_login_login_validator', '__return_true');
		$this->get_logger()->debug('Filter "f12_cf7_captcha_login_login_validator" wurde hinzugefügt, um eine doppelte Überprüfung zu verhindern.');

		// Gebe das Ergebnis zurück.
		return false;
	}
}