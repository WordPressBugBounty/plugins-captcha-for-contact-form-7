<?php

namespace f12_cf7_captcha\core\protection\time;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\timer\CaptchaTimer;
use f12_cf7_captcha\core\timer\Timer_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timer_Validator extends BaseProtection {

	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->set_message(__('timer-protection', 'captcha-for-contact-form-7'));
		$this->get_logger()->debug('Nachricht für den Timer-Schutz gesetzt.', [
			'message_key' => 'timer-protection',
		]);

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

	/**
	 * Checks if the provided input is considered spam.
	 *
	 * @param mixed $args The arguments to check for spam.
	 *
	 * @return bool True if the input is considered spam, false otherwise.
	 */
	public function is_spam(...$args): bool
	{
		$this->get_logger()->info('Führe Spam-Überprüfung für Timer-Schutz durch.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Wenn keine Argumente übergeben wurden, kann es kein Spam sein.
		if (!isset($args[0])) {
			$this->get_logger()->warning('Keine Post-Daten zum Überprüfen verfügbar.');
			return false;
		}

		// Wenn der Timer-Schutz deaktiviert ist, überspringe die Überprüfung.
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam-Überprüfung übersprungen, da der Timer-Schutz deaktiviert ist.');
			return false;
		}

		$array_post_data = $args[0];
		$field_name = $this->get_field_name();

		// Wenn das spezielle Feld für den Timer-Schutz fehlt, ist es wahrscheinlich ein Bot.
		if (!isset($array_post_data[$field_name])) {
			$this->get_logger()->warning('Timer-Feld fehlt in den übermittelten Daten. Einstufung als Spam.');
			return true;
		}

		$hash = sanitize_text_field($array_post_data[$field_name]);
		$this->get_logger()->debug('Abgerufener Hash-Wert: ' . $hash);

		/**
		 * Lade den Timer-Controller und den spezifischen Timer.
		 */
		$Timer_Controller = $this->Controller->get_modul('timer');
		$Timer = $Timer_Controller->get_timer($hash);

		// Wenn der Timer nicht gefunden wird, ist der Hash ungültig oder abgelaufen.
		if (!$Timer) {
			$this->get_logger()->warning('Kein passender Timer für den Hash gefunden. Einstufung als Spam.', ['hash' => $hash]);
			return true;
		}

		// Berechne die verstrichene Zeit
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

		// Wenn die verstrichene Zeit unter dem Mindestwert liegt, ist es wahrscheinlich ein Bot.
		if ($time_passed < $minimum_time_in_ms) {
			$this->get_logger()->warning('Formular zu schnell übermittelt. Einstufung als Spam.');
			return true;
		}

		// Die Überprüfung war erfolgreich, lösche den Timer, um Mehrfachverwendung zu verhindern.
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
		$this->get_logger()->info('Generiere das Captcha-Feld für den Timer-Schutz.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha-Feld wird nicht generiert, da der Timer-Schutz deaktiviert ist.');
			return '';
		}

		$field_name = $this->get_field_name();
		$this->get_logger()->debug('Name des Feldes: ' . $field_name);

		/**
		 * @var Timer_Controller $Timer_Controller
		 */
		$Timer_Controller = $this->Controller->get_modul('timer');

		if (!$Timer_Controller) {
			$this->get_logger()->error('Das Modul "timer" konnte nicht geladen werden.');
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
			'html_length' => strlen($html),
		]);

		return $html;
	}

	/**
	 * Returns the validation time in milliseconds.
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
	 * Returns the name of the field.
	 *
	 * @return string The name of the field.
	 */
	protected function get_field_name()
	{
		$field_name = $this->Controller->get_settings('protection_time_field_name', 'global');

		$this->get_logger()->debug('Rufe den Namen des Formularfelds für den Timerschutz ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'field_name' => $field_name,
		]);

		return $field_name;
	}
	/**
	 * Initializes the object.
	 *
	 * This method is called when the object is initialized and can be used to perform any necessary setup.
	 * It does not return any value and has no parameters.
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

	/**
	 * Checks if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = (int)$this->Controller->get_settings('protection_time_enable', 'global') === 1;

		if ($is_enabled) {
			$this->get_logger()->info('Timer-Schutz ist global aktiviert.');
		} else {
			$this->get_logger()->warning('Timer-Schutz ist global deaktiviert. Validierung wird übersprungen.');
		}

		$filtered_state = apply_filters('f12-cf7-captcha-skip-validation-timer', $is_enabled);

		if ($is_enabled && !$filtered_state) {
			$this->get_logger()->debug('Der Timer-Schutz wurde durch einen externen Filter deaktiviert.', [
				'filter_name' => 'f12-cf7-captcha-skip-validation-timer',
			]);
		}

		return $filtered_state;
	}

	public function success(): void
	{
		$this->get_logger()->info('Erfolgreiche Validierung der Timer-Überprüfung.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implementieren Sie hier die Logik, die nach einer erfolgreichen Überprüfung ausgeführt werden soll.
		// In diesem Kontext ist es unwahrscheinlich, dass zusätzliche Aktionen erforderlich sind,
		// da die Überprüfung primär in der is_spam()-Methode stattfindet.
	}
}