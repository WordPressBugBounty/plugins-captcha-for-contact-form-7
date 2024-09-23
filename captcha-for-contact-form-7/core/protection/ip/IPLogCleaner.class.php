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
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		add_action( 'weeklyIPClear', array( $this, 'clean' ) );
	}


	/**
	 * Clean the IP log by deleting records older than 3 weeks.
	 *
	 * @return int The number of records deleted.
	 */
	public function clean(): int {
		$date_time           = new \DateTime( '-3 Weeks' );
		$date_time_formatted = $date_time->format( 'Y-m-d H:i:s' );

		return ( new IPLog() )->delete_older_than( $date_time_formatted );
	}

	/**
	 * @return bool|int
	 * @deprecated
	 */
	public function resetTable() {
		return $this->reset_table();
	}

	/**
	 * Reset the table for IPLog records.
	 *
	 * This method is deprecated since it directly calls the `reset_table` method on the `IPLog` class.
	 * It returns the result of the `reset_table` method, which is an integer indicating the number of affected rows.
	 *
	 * @return int The number of affected rows after resetting the table.
	 */
	public function reset_table(): int {
		return ( new IPLog() )->reset_table();
	}

}