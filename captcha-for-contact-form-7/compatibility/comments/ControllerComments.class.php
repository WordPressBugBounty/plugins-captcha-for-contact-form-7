<?php

namespace f12_cf7_captcha\compatibility;


use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerComments
 */
class ControllerComments extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'WordPress Comments';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'wordpress_comments';

    /**
     * Determines if the CF7 Captcha plugin is enabled.
     *
     * This method checks if the CF7 Captcha plugin is enabled by filtering the 'f12_cf7_captcha_is_installed_comments'
     * hook.
     *
     * @return bool Whether the CF7 Captcha plugin is enabled or not.
     */
	public function is_enabled(): bool
	{
		// Logge den Beginn der Überprüfung
		$this->get_logger()->info('Starte Überprüfung, ob die WordPress-Kommentar-Funktion aktiviert ist.');

		// Hole die Einstellung zur Aktivierung
		$setting_value = $this->Controller->get_settings('protection_wordpress_comments_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_wordpress_comments_enable": ' . $setting_value);

		$this->get_logger()->debug('Status von den Einstellungen: ' .$setting_value);
		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_wordpress_comments_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		$is_active = (int)$setting_value === 1;

		// Logge den Status vor dem Anwenden des Filters
		$this->get_logger()->debug('Status vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Wende den Filter an, um das endgültige Ergebnis zu erhalten
		$result = apply_filters('f12_cf7_captcha_is_installed_wordpress_comments', $is_active);

		// Logge das Endergebnis
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		// Gib das finale Ergebnis zurück
		return $result;
	}

    /**
     * Checks if the software is installed.
     *
     * @return bool Returns true if the software is installed, false otherwise.
     */
	public function is_installed(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starting a check to see if the module is installed.');

		// The core logic of the function, which always returns true.
		$is_installed = true;

		// Log the result of the check.
		if ($is_installed) {
			$this->get_logger()->info('The module is marked as installed. Returning true.');
		} else {
			// This log will technically never be reached with the current logic,
			// but it's good practice for when the logic might change.
			$this->get_logger()->warning('The module is not marked as installed. This may indicate a configuration issue.');
		}

		// Return the result.
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Logge den Beginn der Initialisierung
		$this->get_logger()->info('Starte die Initialisierung des WordPress Comments-Moduls.');

		// Setze den Namen
		$this->name = __('WordPress Comments', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Füge die Aktion für das Captcha im Kommentarformular hinzu
		$this->get_logger()->debug('Füge die Aktion "comment_form_after_fields" hinzu, um das Captcha anzuzeigen.');
		add_action('comment_form_after_fields', [$this, 'wp_add_spam_protection']);

		// Füge den Filter für die Spam-Überprüfung vor der Verarbeitung hinzu
		$this->get_logger()->debug('Füge den Filter "preprocess_comment" hinzu, um Kommentare auf Spam zu prüfen.');
		add_filter('preprocess_comment', [$this, 'wp_is_spam'], 1);

		// Logge den Abschluss der Initialisierung
		$this->get_logger()->info('Initialisierung abgeschlossen.');
	}

    /**
     * Adds spam protection to comment submission.
     *
     * This method is responsible for generating and displaying the captcha
     * field for spam protection when submitting a comment. The type of captcha
     * is determined by the settings from the controller.
     *
     * @param mixed ...$args Optional arguments to be passed to the method.
     *
     * @return void
     * @throws \Exception
     */
	public function wp_add_spam_protection(...$args)
	{
		// Logge den Beginn des Prozesses
		$this->get_logger()->info('Starte die Ausgabe des Captcha-Codes für WordPress-Kommentare.');

		// Hole den Captcha-Code vom Protection-Modul
		$captcha = $this->Controller->get_modul('protection')->get_captcha();

		// Logge die Größe des Captcha-Codes
		$this->get_logger()->debug('Captcha-Code wurde abgerufen. Größe: ' . strlen($captcha) . ' Zeichen.');

		// Überprüfe, ob der Captcha-Code leer ist
		if (empty($captcha)) {
			// Logge eine Warnung, wenn der Code leer ist
			$this->get_logger()->warning('Der Captcha-Code ist leer. Es wird kein HTML ausgegeben.');
		} else {
			// Logge die erfolgreiche Ausgabe
			$this->get_logger()->info('Captcha-Code wird ausgegeben.');
		}

		// Gib den Captcha-Code aus
		echo $captcha;

		// Logge das Ende des Prozesses
		$this->get_logger()->info('Ausgabe des Captcha-Codes abgeschlossen.');
	}

    /**
     * Determines if a comment is spam.
     *
     * This method checks if the given comment data is considered spam by
     * performing spam detection logic. If the comment is classified as spam,
     * appropriate action is taken, such as logging and displaying an error
     * message.
     *
     * @param mixed ...$args The comment data arguments.
     *
     * @return mixed The comment data.
     * @throws \Exception
     */
	public function wp_is_spam(...$args)
	{
		// Logge den Beginn der Spam-Überprüfung für Kommentare
		$this->get_logger()->info('Starte die Spam-Überprüfung für den übermittelten Kommentar.');

		$commentdata = $args[0];

		// Hole die Formulardaten aus dem POST-Array
		$formData = $_POST;

		// Logge die empfangenen Daten zur Fehlersuche
		$this->get_logger()->debug('Überprüfe die folgenden Daten auf Spam:', [
			'post_data_keys' => array_keys($formData),
		]);

		// Führe die eigentliche Spam-Überprüfung durch
		if ($this->Controller->get_modul('protection')->is_spam($formData)) {
			// Logge eine Warnung, wenn Spam erkannt wird
			$this->get_logger()->warning('Spam erkannt! Der Kommentar wird blockiert.');

			// Beende die Ausführung mit einer Fehlermeldung
			wp_die(__('Error: Spam', 'captcha-for-contact-form-7'));
		}

		// Logge den erfolgreichen Abschluss der Überprüfung
		$this->get_logger()->info('Kein Spam erkannt. Der Kommentar wird verarbeitet.');

		// Gib die Kommentardaten zurück
		return $commentdata;
	}
}