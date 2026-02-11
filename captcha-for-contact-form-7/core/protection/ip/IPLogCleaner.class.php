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
class IPLogCleaner extends BaseModul {
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
	 * Clean the IP log by deleting records older than 3 weeks.
	 *
	 * @return int The number of records deleted.
	 */
	public function clean(): int
	{
		$date_time = new \DateTime('-3 Weeks');
		$date_time_formatted = $date_time->format('Y-m-d H:i:s');

		$this->get_logger()->info('Starting cleanup of older logs', [
			'threshold' => $date_time_formatted,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);

		$deleted = (new IPLog($this->Controller->get_logger()))
			->delete_older_than($date_time_formatted);

		$this->get_logger()->info('Cleanup completed', [
			'threshold'     => $date_time_formatted,
			'deleted_rows'  => $deleted,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return $deleted;
	}


	/**
	 * Reset the table for IPLog records.
	 *
	 * This method is deprecated since it directly calls the `reset_table` method on the `IPLog` class.
	 * It returns the result of the `reset_table` method, which is an integer indicating the number of affected rows.
	 *
	 * @return int The number of affected rows after resetting the table.
	 */
	public function reset_table(): int
	{
		$this->get_logger()->warning('Starting reset of IPLog table', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$result = (new IPLog($this->Controller->get_logger()))->reset_table();

		$this->get_logger()->info('Reset of IPLog table completed', [
			'affected_rows' => $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return $result;
	}
}