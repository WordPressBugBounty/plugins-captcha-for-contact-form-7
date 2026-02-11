<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class will handle the clean up of the database
 * as defined by the user settings.
 */
class IPBanCleaner extends BaseModul {
	/**
	 * Constructs a new instance of the class.
	 *
	 * This method sets up an action hook with the 'weeklyIPClear' name and assigns the 'clean' method of the current
	 * object as the callback function.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		add_action('weeklyIPClear', [$this, 'clean']);

		$this->get_logger()->info('Instance created and hook registered', [
			'hook'   => 'weeklyIPClear',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}


	/**
	 * Cleans IP bans.
	 *
	 * This method retrieves the date and time three weeks ago, formats it to 'Y-m-d H:i:s' format,
	 * and deletes IP bans older than the specified date and time using the delete_older_than method
	 * of the IPBan class.
	 *
	 * @return bool Returns true if the IP bans were successfully cleaned, false otherwise.
	 */
	public function clean()
	{
		$date_time = new \DateTime('-3 Weeks');
		$date_time_formatted = $date_time->format('Y-m-d H:i:s');

		$this->get_logger()->info('Starting cleanup of older IP bans', [
			'threshold' => $date_time_formatted,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);

		$ipBan = new IPBan($this->get_logger());
		$deleted = $ipBan->delete_older_than($date_time_formatted);

		$this->get_logger()->info('Cleanup completed', [
			'deleted_rows' => $deleted,
			'threshold'    => $date_time_formatted,
			'class'        => __CLASS__,
			'method'       => __METHOD__,
		]);

		return $deleted;
	}


	/**
	 * Resets the table in the IPBan class.
	 *
	 * @return int The number of rows affected by the table reset.
	 */
	public function reset_table(): int
	{
		$this->get_logger()->warning('Starting reset of IPBan table', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$ipBan = new IPBan($this->get_logger());
		$result = $ipBan->reset_table();

		$this->get_logger()->info('Reset of IPBan table completed', [
			'affected_rows' => $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return $result;
	}


	/**
	 * @return int
	 * @deprecated
	 */
	public function resetTable()
	{
		$this->get_logger()->debug('resetTable() called (alias for reset_table)', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return $this->reset_table();
	}

}