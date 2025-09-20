<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerFluentForm
 */
class ControllerFluentform extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Fluentform';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'fluentform';

    /**
     * Check if the captcha is enabled for Fluent Forms
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starte Überprüfung, ob das FluentForm-Modul aktiviert ist.');

		// Check if the FluentForm plugin is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus des Moduls: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		// Get the global setting for FluentForm protection.
		$setting_value = $this->Controller->get_settings('protection_fluentform_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_fluentform_enable": ' . $setting_value);

		// Determine if the module should be active.
		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
		}
		$is_active = $is_installed && $setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_fluentform', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

	/**
	 * Add assets for FluentForms form
	 */
	public function wp_add_assets() {
		// Logge den Beginn des Prozesses
		$this->get_logger()->info( 'Starte das Einreihen von Skripten.' );

		// Pfad zum Skript
		$script_url = plugin_dir_url( __FILE__ ) . 'assets/f12-cf7-captcha-fluentforms.js';

		// Logge die Details zum Skript
		$this->get_logger()->debug( 'Skript wird geladen.', [
			'handle'       => 'f12-cf7-captcha-fluentforms',
			'url'          => $script_url,
			'dependencies' => [ 'jquery' ],
		] );

		// Lade das Skript
		wp_enqueue_script( 'f12-cf7-captcha-fluentforms', $script_url, array( 'jquery' ) );

		// Die Daten für die Lokalisierung
		$localization_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		];

		// Logge die Lokalisierungsdaten
		$this->get_logger()->debug( 'Skript wird lokalisiert.', [
			'handle' => 'f12-cf7-captcha-fluentforms',
			'data'   => $localization_data,
		] );

		// Lokalisiere das Skript
		wp_localize_script( 'f12-cf7-captcha-fluentforms', 'f12_cf7_captcha_fluentforms', $localization_data );

		// Logge den erfolgreichen Abschluss
		$this->get_logger()->info( 'Skripte erfolgreich eingereiht und lokalisiert.' );
	}

    /**
     * Check if the Fluent Forms plugin is installed
     *
     * @return bool Returns true if the Fluent Forms plugin is installed, false otherwise
     */
	public function is_installed(): bool
	{
		// Logge den Beginn der Überprüfung, ob das Plugin installiert ist.
		$this->get_logger()->info('Starte Überprüfung, ob FluentForm installiert ist.');

		// Prüfe, ob die Konstante 'FLUENTFORM' existiert.
		$is_installed = defined('FLUENTFORM');

		// Logge das Ergebnis der Überprüfung.
		if ($is_installed) {
			$this->get_logger()->info('FluentForm wurde gefunden. Das Modul kann gestartet werden.');
		} else {
			$this->get_logger()->critical('FluentForm wurde nicht gefunden. Dieses Modul kann nicht korrekt funktionieren.');
		}

		// Gib das Ergebnis zurück.
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Log the start of the initialization process for the Fluent Forms module.
		$this->get_logger()->info('Starte die Initialisierung des Fluent Forms-Moduls.');

		// Set the module name.
		$this->name = __('Fluent Forms', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Add the action to insert the captcha before the submit button.
		$this->get_logger()->debug('Füge die Aktion "fluentform/render_item_submit_button" hinzu, um den Spamschutz vor dem Senden-Button anzuzeigen.');
		add_action('fluentform/render_item_submit_button', array($this, 'wp_add_spam_protection'), 5, 2);

		// Add the filter for spam validation.
		$this->get_logger()->debug('Füge den Filter "fluentform/validation_errors" hinzu, um das Formular auf Spam zu prüfen.');
		add_filter('fluentform/validation_errors', array($this, 'wp_is_spam'), 10, 4);

		// Adds an action to enqueue the necessary scripts and styles for the spam protection.
		// This hook is used to add assets to the front-end of the website.
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_add_assets' ) );
		$this->get_logger()->debug( 'Action "wp_enqueue_scripts" zum Laden der Assets registriert.' );

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
     * @return void The content with spam protection added.
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
	public function wp_add_spam_protection(...$args)
	{
		// Log the beginning of the process.
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für Fluent Forms.');

		// Get the captcha code from the protection module.
		$captcha = $this->Controller->get_modul('protection')->get_captcha();

		// Log the size of the retrieved captcha code for debugging.
		$this->get_logger()->debug('Captcha-Code wurde abgerufen. Größe: ' . strlen($captcha) . ' Zeichen.');

		// Check if the captcha code is empty.
		if (empty($captcha)) {
			// Log a warning if the code is empty.
			$this->get_logger()->warning('Der Captcha-Code ist leer. Es wird kein HTML ausgegeben.');
		} else {
			// Log the successful output of the captcha.
			$this->get_logger()->info('Captcha-Code wird ausgegeben.');
		}

		// Echo the captcha code.
		echo $captcha;
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
		// Logge den Beginn der Spam-Überprüfung für Fluent Forms.
		$this->get_logger()->info('Starte Spam-Validierung für ein FluentForm-Formular.');

		$errors = $args[0];

		// Hole die Formulardaten aus dem Ajax-Aufruf.
		$formData = $_POST['data'];
		$this->get_logger()->debug('Empfangene Rohdaten des Formulars.', ['data_length' => strlen($formData)]);

		// Dekodiere die Daten und konvertiere sie in ein Array.
		$decodedFormData = urldecode($formData);
		parse_str($decodedFormData, $array_post_data);
		$this->get_logger()->debug('Formulardaten erfolgreich in ein Array konvertiert.', ['keys' => array_keys($array_post_data)]);

		// Hole das Schutz-Modul.
		$Protection = $this->Controller->get_modul('protection');

		// Führe die Spam-Überprüfung durch.
		if ($Protection->is_spam($array_post_data)) {
			// Logge eine Warnung, wenn Spam erkannt wird.
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt. Sende JSON-Fehlermeldung.', ['spam_message' => $message]);

			// Sende eine JSON-Antwort mit einer Fehlermeldung und einem 422-Statuscode.
			wp_send_json(
				[
					'errors' => [
						'captcha-response' => [
							sprintf(__('Captcha verification failed: %s', 'captcha-for-contact-form-7'), $message),
						],
					],
				],
				422
			);

			// Da wp_send_json die Ausführung beendet, wird hier kein Code mehr ausgeführt.
		}

		// Logge, dass kein Spam erkannt wurde und die Übermittlung fortgesetzt wird.
		$this->get_logger()->info('Kein Spam erkannt. Die Validierung wird fortgesetzt.');

		// Gib die ursprünglichen Fehler zurück.
		return $errors;
	}
}