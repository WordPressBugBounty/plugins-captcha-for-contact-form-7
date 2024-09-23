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
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		add_action( 'dailyCaptchaClear', array( $this, 'clean' ) );
	}

	/**
	 * Clean all expired Captchas
	 *
	 * This method deletes all Captchas that are older than 1 day.
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean() {
		$date_time = new \DateTime( "-1 Day" );

		return (int) ( new Captcha( '' ) )->delete_older_than( $date_time->format( 'Y-m-d H:i:s' ) );
	}

	public function reset_table(): int {
		return ( new Captcha( '' ) )->reset_table();
	}

	/**
	 * Clean all Captchas
	 *
	 * @return bool|int
	 * @deprecated
	 */
	public function resetTable() {
		return $this->reset_table();
	}

	/**
	 * Clean all Captchas
	 *
	 * @return bool|int
	 * @deprecated
	 */
	public function cleanValidated() {
		return $this->clean_validated();
	}

	/**
	 * Clean validated Captchas
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean_validated(): int {
		return ( new Captcha( '' ) )->delete_by_validate_status( 1 );
	}

	/**
	 * Cleans all non-validated captchas.
	 *
	 * @return int The number of captchas deleted.
	 */
	public function clean_non_validated(): int {
		return ( new Captcha( '' ) )->delete_by_validate_status( 0 );
	}

	/**
	 * Clean all Captchas
	 *
	 * @return bool|int
	 * @deprecated
	 */
	public function cleanNonValidated() {
		return $this->clean_non_validated();
	}
}