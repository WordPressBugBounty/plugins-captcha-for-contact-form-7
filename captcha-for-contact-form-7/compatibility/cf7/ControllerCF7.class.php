<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\compatibility\cf7\Backend;
use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\Protection;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerCF7
 */
class ControllerCF7 extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Contact Forms 7';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'cf7';

    /**
     * Check if CF7 Captcha is enabled
     *
     * This method checks if the CF7 Captcha plugin is installed and enabled by using the
     * 'f12_cf7_captcha_is_installed_cf7' filter hook. It returns a boolean value indicating whether the plugin is
     * enabled or not.
     *
     * @return bool True if CF7 Captcha is enabled, false otherwise
     */
	public function is_enabled(): bool
	{
		// Logge den Beginn der Überprüfung
		$this->get_logger()->info('Starte Überprüfung, ob das Modul aktiviert ist.');

		// Überprüfe den Installationsstatus
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		// Hole die Einstellung zur Aktivierung
		$setting_value = $this->Controller->get_settings('protection_cf7_enable', 'global');
		$this->get_logger()->debug('Status von den Einstellungen: ' .$setting_value);
		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_cf7_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		$this->get_logger()->debug('Einstellung "protection_cf7_enable": ' . $setting_value);

		// Die Hauptlogik
		$is_active = $is_installed && (int)$setting_value === 1;

		// Logge das Ergebnis vor dem Filter
		$this->get_logger()->debug('Modulstatus vor dem Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		// Wende den Filter an
		$result = apply_filters('f12_cf7_captcha_is_installed_cf7', $is_active);

		// Logge das Endergebnis
		$this->get_logger()->info('Endgültiger Status nach Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		// Gebe das finale Ergebnis zurück
		return $result;
	}

    /**
     * Checks if the "wpcf7" function is available, indicating whether the WPCF7 plugin is installed.
     *
     * @return bool True if the WPCF7 plugin is installed; otherwise, false.
     */
	public function is_installed(): bool
	{
		// Logge den Beginn der Überprüfung
		$this->get_logger()->info('Starte Überprüfung, ob Contact Form 7 installiert ist.');

		// Prüfe, ob die Funktion 'wpcf7' existiert
		$is_installed = function_exists('wpcf7');

		// Logge das Ergebnis der Prüfung
		if ($is_installed) {
			$this->get_logger()->info('Contact Form 7 wurde gefunden.');
		} else {
			$this->get_logger()->critical('Contact Form 7 wurde nicht gefunden! Modul kann nicht korrekt funktionieren.');
		}

		// Gib das Ergebnis zurück
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Logge den Start der Initialisierung
		$this->get_logger()->info('Starte die Initialisierung des Contact Form 7-Moduls.');

		// Setze den Namen
		$this->name = __('Contact Forms 7', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Lade die Backend-Klasse
		//$this->get_logger()->info('Lade Backend-Klasse.');
		//require_once('Backend.class.php');
		//$Backend = new Backend();

		// Logge die Hinzufügung der Filter
		$this->get_logger()->debug('Füge Filter für die Spam-Überprüfung hinzu.');

		// Füge Filter hinzu
		add_filter('wpcf7_form_elements', array($this, 'wp_add_spam_protection'), 100, 1);
		add_filter('wpcf7_spam', array($this, 'wp_is_spam'), 100, 2);

		// Logge den Abschluss der Initialisierung
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
     * @return mixed The content with spam protection added.
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
	public function wp_add_spam_protection(...$args)
	{
		// Logge den Beginn der Captcha-Einfügung
		$this->get_logger()->info('Starte die Einfügung des Captcha-Codes in das Formular.');

		$content = $args[0];

		// Hole den Captcha-Code vom Protection-Modul
		$captcha = $this->Controller->get_modul('protection')->get_captcha();
		$this->get_logger()->debug('Captcha-Code erhalten. Größe: ' . strlen($captcha) . ' Zeichen.');

		// Kapsle den Captcha-Code in HTML
		$captcha = sprintf('<p><span class="wpcf7-form-control-wrap">%s</span></p>', $captcha);
		$this->get_logger()->debug('Captcha-Code für die Einfügung vorbereitet.');

		// Versuche, den Captcha-Code vor dem Submit-Button einzufügen
		if (preg_match('!<input(.*)type="submit"!', $content, $matches)) {
			$content = str_replace($matches[0], $captcha . $matches[0], $content);
			$this->get_logger()->info('Captcha wurde erfolgreich vor dem Submit-Button eingefügt.');
		} else {
			// Füge den Captcha-Code am Ende des Formulars ein
			$content .= $captcha;
			$this->get_logger()->warning('Kein Submit-Button gefunden. Captcha wurde am Ende des Formulars angehängt.');
		}

		// Logge den erfolgreichen Abschluss
		$this->get_logger()->info('Einfügung des Captcha-Codes abgeschlossen.');

		return $content;
	}

    /**
     * Determines if the given submission is spam.
     *
     * This method checks if the submission is marked as spam and logs it if necessary.
     *
     * @param mixed ...$args Any number of arguments. The first argument must be the spam indicator and the second
     *                       argument must be the submission.
     *
     * @return bool|int If the submission is identified as spam, it returns true. If not, it returns the spam indicator
     *                  value provided.
     *
     * @since 1.0.0
     */
	public function wp_is_spam(...$args)
	{
		// Logge den Beginn der Spam-Überprüfung
		$this->get_logger()->info('Starte die Spam-Überprüfung für das Formular.');

		$spam = $args[0];
		$submission = $args[1];

		// Wir gehen davon aus, dass wir alle POST-Daten überprüfen.
		$array_post_data = $_POST;

		/**
		 * @var Protection $Protection
		 */
		$Protection = $this->Controller->get_modul('protection');

		// Logge, bevor die is_spam-Methode aufgerufen wird
		$this->get_logger()->debug('Prüfe die übermittelten Daten auf Spam.', [
			'post_data_keys' => array_keys($array_post_data),
		]);

		// Führe die eigentliche Spam-Überprüfung durch
		if ($Protection->is_spam($array_post_data)) {
			// Logge, wenn Spam erkannt wurde
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt. Die Meldung lautet: ' . $message);

			// Füge den Filter hinzu, um die Spam-Nachricht anzupassen
			add_filter('wpcf7_display_message', function ($message, $status) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = $this->Controller->get_modul('protection');

				// Überprüfe den Status, um sicherzustellen, dass es sich um Spam handelt
				if ($status == 'spam') {
					$message = $Protection->get_message();
				}

				return $message;
			}, 10, 2);

			// Logge, dass der Filter hinzugefügt und 'true' zurückgegeben wird
			$this->get_logger()->critical('Spam erkannt. Modul gibt "true" zurück, um die Übermittlung als Spam zu markieren.');

			return true;
		}

		// Logge, wenn kein Spam erkannt wurde
		$this->get_logger()->info('Kein Spam erkannt. Die Übermittlung wird fortgesetzt.');

		// Gebe den ursprünglichen Wert zurück
		return $spam;
	}
}