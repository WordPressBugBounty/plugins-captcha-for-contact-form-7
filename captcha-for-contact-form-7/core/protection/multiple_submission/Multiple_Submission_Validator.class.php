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

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// Lade Submodule
		$this->get_logger()->info('Lade Submodul: CaptchaTimerCleaner.');
		new CaptchaTimerCleaner($Controller);

		$this->set_message(__('multiple-submission-protection', 'captcha-for-contact-form-7'));
		$this->get_logger()->debug('Nachricht für mehrfache Übermittlung gesetzt.', [
			'message_key' => 'multiple-submission-protection',
		]);

		$this->get_logger()->info('Konstruktor abgeschlossen.', [
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
		$this->get_logger()->info('Erzeuge eine neue Instanz von CaptchaTimer über die Factory-Methode.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$captchaTimer = new CaptchaTimer($this->get_logger());

		$this->get_logger()->debug('Neue CaptchaTimer-Instanz erfolgreich erstellt.');

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
		$is_enabled = $this->Controller->get_settings('protection_multiple_submission_enable', 'global');

		if ($is_enabled === '' || $is_enabled === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$is_enabled = 1;
		}

		if ($is_enabled) {
			$this->get_logger()->info('Schutz gegen mehrfache Übermittlungen ist aktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		} else {
			$this->get_logger()->warning('Schutz gegen mehrfache Übermittlungen ist deaktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-multiple_submission', $is_enabled);

		if ($is_enabled && !$result) {
			$this->get_logger()->debug('Schutz wird durch Filter übersprungen.', [
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
		$this->get_logger()->info('Führe Spam-Überprüfung für Mehrfachübermittlung durch.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!isset($args[0])) {
			$this->get_logger()->warning('Keine Post-Daten zum Überprüfen verfügbar.');
			return false;
		}

		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam-Überprüfung übersprungen, da der Schutz deaktiviert ist.');
			return false;
		}

		$array_post_data = $args[0];
		$field_name = $this->get_field_name();

		if (!isset($array_post_data[$field_name])) {
			$this->get_logger()->warning('Hash-Feld fehlt in den übermittelten Daten. Einstufung als Spam.');
			return true;
		}

		$hash = sanitize_text_field($array_post_data[$field_name]);
		$this->get_logger()->debug('Abgerufener Hash-Wert.', ['hash' => $hash]);

		/**
		 * Lade den Timer-Controller und den Timer.
		 */
		$Timer_Controller = $this->Controller->get_modul('timer');
		$Timer = $Timer_Controller->get_timer($hash);

		if (!$Timer) {
			$this->get_logger()->warning('Kein passender Timer für den Hash gefunden. Einstufung als Spam.', ['hash' => $hash]);
			return true;
		}

		$time_in_ms = round(microtime(true) * 1000);
		$minimum_time_in_ms = $this->get_validation_time();
		$start_time_ms = (float)$Timer->get_value();
		$time_passed = $time_in_ms - $start_time_ms;

		$this->get_logger()->debug("Zeitüberprüfung durchgeführt.", [
			'start_time_ms' => $start_time_ms,
			'end_time_ms' => $time_in_ms,
			'time_passed_ms' => $time_passed,
			'minimum_time_ms' => $minimum_time_in_ms,
		]);

		if ($time_passed < $minimum_time_in_ms) {
			$this->get_logger()->warning('Formular zu schnell übermittelt. Einstufung als Spam.');
			return true;
		}

		$this->get_logger()->info('Validierung erfolgreich. Lösche den Timer-Datensatz.', ['hash' => $hash]);
		$Timer->delete();

		$this->get_logger()->info('Formular als nicht-Spam eingestuft.');
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
		$this->get_logger()->info('Generiere Captcha-Feld für Mehrfachübermittlungsschutz.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha-Feld wird nicht generiert, da der Schutz deaktiviert ist.');
			return '';
		}

		$field_name = $this->get_field_name();

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_modul('timer');

		if (!$Timer_Controller) {
			$this->get_logger()->error('Timer-Controller-Modul nicht gefunden.');
			return '';
		}

		$hash = $Timer_Controller->add_timer();
		if (empty($hash)) {
			$this->get_logger()->error('Fehler beim Hinzufügen des Timers. Konnte keinen Hash generieren.');
			return '';
		}

		$this->get_logger()->debug('Neuer Timer-Hash erfolgreich generiert.', ['hash' => $hash]);

		$html = sprintf(
			'<div class="f12t"><input type="hidden" class="f12_timer" name="%s" value="%s"/></div>',
			esc_attr($field_name),
			esc_attr($hash)
		);

		$this->get_logger()->info('Verstecktes Captcha-Feld erfolgreich generiert.', [
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

		$this->get_logger()->debug('Rufe die minimale Validierungszeit ab.', [
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

		$this->get_logger()->debug('Rufe den Namen des Feldes ab.', [
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
		$this->get_logger()->info('on_init-Methode wird ausgeführt.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implementieren Sie hier die Initialisierungslogik.
		// Beispiel: Hinzufügen von Hooks, Registrieren von Shortcodes etc.

		$this->get_logger()->info('on_init-Methode abgeschlossen.');
	}

	public function success(): void
	{
		$this->get_logger()->info('Erfolgreiche Validierung der Mehrfachübermittlung.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implementieren Sie hier die Logik für den Erfolgsfall.
		// In diesem spezifischen Kontext könnte dies bedeuten, dass keine weiteren Aktionen notwendig sind,
		// da die Überprüfung bereits in der is_spam()-Methode stattgefunden hat.
		// Sollte der Timer nach erfolgreicher Validierung erst hier gelöscht werden,
		// müsste die Löschlogik von is_spam() hierher verschoben werden.
	}
}