<?php

namespace f12_cf7_captcha\core\timer;
use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CaptchaTimerCleaner
 *
 * This class is responsible for cleaning captchas older than 1 day and resetting the table of captchas.
 * It extends the BaseModul class.
 */
class CaptchaTimerCleaner extends BaseModul
{
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);
		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		add_action('dailyCaptchaTimerClear', array($this, 'clean'));
		$this->get_logger()->debug('Hook "dailyCaptchaTimerClear" für die Methode "clean" hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}
    /**
     * Clean all captchas older than 1 day
     *
     * @return int The number of captchas deleted
     */
	public function clean(): int
	{
		$this->get_logger()->info('Starte den täglichen Bereinigungsjob für abgelaufene Timer-Einträge.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			// Erzeuge ein DateTime-Objekt, das 24 Stunden in der Vergangenheit liegt
			$date_time = new \DateTime('-1 Day');
			$date_time_formatted = $date_time->format('Y-m-d H:i:s');
			$this->get_logger()->debug('Zeitstempel für die Bereinigung: ' . $date_time_formatted);
		} catch (\Exception $e) {
			$this->get_logger()->error('Fehler beim Erstellen des DateTime-Objekts. Bereinigung abgebrochen.', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}

		// Instanziiere ein neues CaptchaTimer-Objekt und rufe die Löschfunktion auf
		$timer_handler = new CaptchaTimer($this->get_logger());
		$rows_deleted = $timer_handler->delete_older_than($date_time_formatted);

		$this->get_logger()->info('Bereinigung abgeschlossen.', [
			'rows_deleted' => $rows_deleted,
		]);

		return $rows_deleted;
	}

    /**
     * Reset the table of Captchas
     *
     * @return int The number of rows affected by the reset operation
     */
	public function reset_table(): int
	{
		$this->get_logger()->info('Starte den Vorgang, um die gesamte Tabelle über den Haupt-Controller zurückzusetzen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instanziiere ein neues CaptchaTimer-Objekt, um auf die reset_table()-Methode zuzugreifen
		$timer_handler = new CaptchaTimer($this->get_logger());

		// Rufe die Methode auf und speichere das Ergebnis
		$rows_deleted = $timer_handler->reset_table();

		$this->get_logger()->info('Zurücksetzen der Tabelle abgeschlossen.', [
			'rows_deleted' => $rows_deleted,
		]);

		return $rows_deleted;
	}
}