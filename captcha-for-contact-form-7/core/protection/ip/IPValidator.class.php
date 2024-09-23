<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\UserData;
use IPAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'IPBan.class.php' );
require_once( 'IPLog.class.php' );
require_once( 'Salt.class.php' );
require_once( 'IPBanCleaner.class.php' );
require_once( 'IPLogCleaner.class.php' );

/**
 * Class IPValidator
 */
class IPValidator extends BaseProtection {
	private IPBanCleaner $_IP_Ban_Cleaner;
	private IPLogCleaner $_IP_Log_Cleaner;

	/**
	 * Class constructor.
	 *
	 * @param CF7Captcha|null $Controller The CF7Captcha object. (optional)
	 *
	 * @return void
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->_IP_Ban_Cleaner = new IPBanCleaner( $Controller );
		$this->_IP_Log_Cleaner = new IPLogCleaner( $Controller );

		$this->Controller = $Controller;
	}

	/**
	 * Retrieves the IPLogCleaner object.
	 *
	 * @return IPLogCleaner The IPLogCleaner object.
	 */
	public function get_log_cleaner(): IPLogCleaner {
		return $this->_IP_Log_Cleaner;
	}

	/**
	 * Retrieves the IPBanCleaner object.
	 *
	 * @return IPBanCleaner The IPBanCleaner object.
	 */
	public function get_ban_cleaner(): IPBanCleaner {
		return $this->_IP_Ban_Cleaner;
	}

	/**
	 * Check if protection in the browser is enabled.
	 *
	 * @return bool Returns true if protection in the browser is enabled, false otherwise.
	 */
	protected function is_enabled(): bool {
		$is_enabled =  (int) $this->Controller->get_settings( 'protection_ip_enable', 'global' ) === 1;

		return apply_filters('f12-cf7-captcha-skip-validation-ip', $is_enabled);
	}


	/**
	 * Get the captcha string.
	 *
	 * @param mixed ...$args Additional arguments. (optional)
	 *
	 * @return string The captcha string.
	 */
	public function get_captcha( ...$args ): string {
		return '';
	}

	/**
	 * Handles form submission.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function do_handle_submit() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		/**
		 * @var UserData $User_Data
		 */
		$User_Data = $this->Controller->get_modul( 'user-data' );
		$ip        = $User_Data->get_ip_address();

		# Load the Salts
		$Salt          = new Salt();
		$hash_current  = $Salt->get_salted( $ip );
		$hash_previous = '';

		$Salt_Current = $Salt->get_one_salt_by_offset( 0 );
		if ( null !== $Salt_Current ) {
			$hash_current = $Salt_Current->get_salted( $ip );
		}

		$Salt_Previous = $Salt->get_one_salt_by_offset( 1 );
		if ( null !== $Salt_Previous ) {
			$hash_previous = $Salt_Previous->get_salted( $ip );
		}

		# Create a new IP Log Entry
		$IP_Log = new IPLog( [ 'hash' => $hash_current, 'submitted' => 1 ] );
		$IP_Log->save();

		# Remove failed submits
		$IP_Log->delete( $hash_current, $hash_previous, 0 );
	}

	public function success(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->do_handle_submit();
	}

	/**
	 * Validate method.
	 *
	 * Checks if the current request is valid based on IP address, previous IP addresses, and submission history.
	 *
	 * @return bool Returns true if the request is valid, false otherwise.
	 * @throws \Exception
	 */
	public function validate(): bool {
		/*
		 * Load settings
		 */
		$settings = $this->Controller->get_settings();

		/*
		 * Measure the period of time between those timestamps
		 */
		$allowed_time_between = $settings['global']['protection_ip_period_between_submits'];

		/*
		 * Max retries period
		 */
		$max_retry_period = time() - $settings['global']['protection_ip_max_retries_period'];

		/*
		 * Max retries
		 */
		$max_retries = $settings['global']['protection_ip_max_retries'];

		/*
		 * Block Time
		 */
		$block_time = $settings['global']['protection_ip_block_time'];

		/*
		 * Get User IP
		 */
		$User_Data = $this->Controller->get_modul( 'user-data' );
		$ip        = $User_Data->get_ip_address();

		/*
		 * Generate Salt
		 */
		$Salt_Current  = ( new Salt() )->get_last();
		$Salt_Previous = ( new Salt() )->get_one_salt_by_offset( 1 );

		/*
		 * Generate hash
		 */
		$hash_current  = $Salt_Current->get_salted( $ip );
		$hash_previous = $hash_current;

		if ( $Salt_Previous != null ) {
			$hash_previous = $Salt_Previous->get_salted( $ip );
		}

		// Check if the IP has been blocked
		if ( ( new IPBan() )->get_count( $hash_current, $hash_previous ) > 0 ) {
			return false;
		}

		/*
		 * Check for log entries to automatically ban the user if the limit is reached.
		 */
		$IP_Log_Last = ( new IPLog() )->get_last_entry_by_hash( $hash_current, $hash_previous );

		# skip if no entries has been found yet
		if ( null === $IP_Log_Last ) {

			# create a new log entry
			$IPLog = new IPLog( [ 'hash' => $hash_current, 'submitted' => 0 ] );
			$IPLog->save();

			return true;
		}

		/*
		 * Get the second last entry
		 */
		$IP_Log_Second_Last = ( new IPLog() )->get_last_entry_by_hash( $hash_current, $hash_previous, 1 );

		# skip if no entry has been found
		if ( null === $IP_Log_Second_Last ) {

			# create a new log entry
			$IPLog = new IPLog( [ 'hash' => $hash_current, 'submitted' => 0 ] );
			$IPLog->save();

			return true;
		}


		//error_log( sprintf( '%s - %s = %s > %s', $IP_Log_Last->get_submission_timestamp(), $IP_Log_Second_Last->get_submission_timestamp(), $IP_Log_Last->get_submission_timestamp() - $IP_Log_Second_Last->get_submission_timestamp(), $allowed_time_between ) );

		# skip if the time between two submissions was bigger then the minimum time required
		if ( $IP_Log_Last->get_submission_timestamp() - $IP_Log_Second_Last->get_submission_timestamp() > $allowed_time_between ) {
			return true;
		}

		# create a new log entry
		$IPLog = new IPLog( [ 'hash' => $hash_current, 'submitted' => 0 ] );
		$IPLog->save();

		// Check if there are 3+ entries for the given IP, if yes - block it
		if ( $IPLog->get_count( $hash_current, $hash_previous, 0, $max_retry_period ) >= $max_retries ) {

			# ban the ip address
			$IPBan = new IPBan( [ 'hash' => $hash_current, 'blockedtime' => $block_time ] );
			$IPBan->save();
		}

		$this->set_message( 'ip-protection' );

		return false;
	}

	/**
	 * Check if the submission is considered as spam.
	 *
	 * @return bool Returns true if the submission is considered as spam, false otherwise.
	 * @throws \Exception
	 */
	public function is_spam(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return ! $this->validate();
	}
}

//IPValidator::getInstance();