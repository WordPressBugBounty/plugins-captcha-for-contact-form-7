<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class will handle the clean up of the database
 * as defined by the user settings.
 */
class CaptchaCleaner extends BaseModul {
	/**
	 * @param CF7Captcha $Controller
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		add_action('dailyCaptchaClear', [$this, 'clean']);

		$this->get_logger()->info(
			"__construct(): Cron-Job 'dailyCaptchaClear' registriert",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);
	}

	/**
	 * Clean all expired Captchas
	 *
	 * This method deletes all Captchas that are older than 1 day.
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean(): int
	{
		$date_time = new \DateTime("-1 Day");

		$cutoff = $date_time->format('Y-m-d H:i:s');

		$this->get_logger()->debug(
			"clean(): Starte Bereinigung alter Captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'cutoff' => $cutoff
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_older_than($cutoff);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean(): Alte Captchas gelöscht",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted,
					'cutoff'  => $cutoff
				]
			);
		} else {
			$this->get_logger()->warning(
				"clean(): Keine alten Captchas gefunden",
				[
					'plugin' => 'f12-cf7-captcha',
					'cutoff' => $cutoff
				]
			);
		}

		return $deleted;
	}


	public function reset_table(): int
	{
		$this->get_logger()->warning(
			"reset_table(): Starte Zurücksetzen der Captcha-Tabelle",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->get_logger(), '');
		$deleted = (int) $Captcha->reset_table();

		if ($deleted > 0) {
			$this->get_logger()->info(
				"reset_table(): Tabelle geleert",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"reset_table(): Keine Einträge in der Tabelle gefunden",
				[
					'plugin' => 'f12-cf7-captcha'
				]
			);
		}

		return $deleted;
	}

	/**
	 * Clean validated Captchas
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean_validated(): int
	{
		$this->get_logger()->debug(
			"clean_validated(): Starte Bereinigung validierter Captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_by_validate_status(1);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean_validated(): Validierte Captchas gelöscht",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"clean_validated(): Keine validierten Captchas zum Löschen gefunden",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $deleted;
	}


	/**
	 * Cleans all non-validated captchas.
	 *
	 * @return int The number of captchas deleted.
	 */
	public function clean_non_validated(): int
	{
		$this->get_logger()->debug(
			"clean_non_validated(): Starte Bereinigung nicht validierter Captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_by_validate_status(0);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean_non_validated(): Nicht validierte Captchas gelöscht",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"clean_non_validated(): Keine nicht validierten Captchas zum Löschen gefunden",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $deleted;
	}
}