<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\UserData;
use IPAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->_IP_Ban_Cleaner = new IPBanCleaner($Controller);
		$this->_IP_Log_Cleaner = new IPLogCleaner($Controller);

		$this->Controller = $Controller;

		$this->get_logger()->info('Instance created and cleaners initialized', [
			'cleaner_classes' => [
				IPBanCleaner::class,
				IPLogCleaner::class,
			],
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}


	/**
	 * Retrieves the IPLogCleaner object.
	 *
	 * @return IPLogCleaner The IPLogCleaner object.
	 */
	public function get_log_cleaner(): IPLogCleaner
	{
		$this->get_logger()->debug('Log cleaner retrieved', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return $this->_IP_Log_Cleaner;
	}


	/**
	 * Retrieves the IPBanCleaner object.
	 *
	 * @return IPBanCleaner The IPBanCleaner object.
	 */
	public function get_ban_cleaner(): IPBanCleaner {
		$this->get_logger()->debug('IP ban cleaner retrieved', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
		return $this->_IP_Ban_Cleaner;
	}

	/**
	 * Create a new Salt instance.
	 *
	 * @return Salt
	 */
	protected function create_salt(): Salt {
		return new Salt($this->get_logger());
	}

	/**
	 * Create a new IPLog instance.
	 *
	 * @param array $params Optional parameters.
	 *
	 * @return IPLog
	 */
	protected function create_ip_log(array $params = []): IPLog {
		return new IPLog($this->get_logger(), $params);
	}

	/**
	 * Create a new IPBan instance.
	 *
	 * @param array $params Optional parameters.
	 *
	 * @return IPBan
	 */
	protected function create_ip_ban(array $params = []): IPBan {
		return new IPBan($this->get_logger(), $params);
	}

	/**
	 * Check if protection in the browser is enabled.
	 *
	 * @return bool Returns true if protection in the browser is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = (int) $this->Controller->get_settings('protection_ip_enable', 'global') === 1;

		$this->get_logger()->debug('Checking if IP protection is enabled', [
			'is_enabled_raw' => $is_enabled,
			'class'          => __CLASS__,
			'method'         => __METHOD__,
		]);

		$filtered = apply_filters('f12-cf7-captcha-skip-validation-ip', $is_enabled);

		$this->get_logger()->debug('Filter result for IP protection', [
			'is_enabled_filtered' => $filtered,
			'class'               => __CLASS__,
			'method'              => __METHOD__,
		]);

		return $filtered;
	}

	/**
	 * Get the captcha string.
	 *
	 * @param mixed ...$args Additional arguments. (optional)
	 *
	 * @return string The captcha string.
	 */
	public function get_captcha(...$args): string
	{
		$this->get_logger()->debug('Captcha retrieved', [
			'args'   => $args,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return '';
	}

	/**
	 * Handles form submission.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function do_handle_submit()
	{
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Handle submit skipped: IP protection disabled', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			return;
		}

		/** @var UserData $User_Data */
		$User_Data = $this->Controller->get_module('user-data');
		$ip        = $User_Data->get_ip_address();

		$this->get_logger()->debug('Processing submit', [
			'ip'     => $ip,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// Load the Salts
		$Salt          = $this->create_salt();
		$hash_current  = $Salt->get_salted($ip);
		$hash_previous = '';

		$Salt_Current = $Salt->get_one_salt_by_offset(0);
		if (null !== $Salt_Current) {
			$hash_current = $Salt_Current->get_salted($ip);
		}

		$Salt_Previous = $Salt->get_one_salt_by_offset(1);
		if (null !== $Salt_Previous) {
			$hash_previous = $Salt_Previous->get_salted($ip);
		}

		$this->get_logger()->debug('Salts calculated', [
			'hash_current'  => $hash_current,
			'hash_previous' => $hash_previous,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		// Create a new IP Log Entry
		$IP_Log = $this->create_ip_log([
			'hash'      => $hash_current,
			'submitted' => 1,
		]);
		$IP_Log->save();

		$this->get_logger()->info('New IP log entry created', [
			'hash'     => $hash_current,
			'ip'       => $ip,
			'class'    => __CLASS__,
			'method'   => __METHOD__,
		]);

		// Remove failed submits
		$deleted = $IP_Log->delete($hash_current, $hash_previous, 0);

		$this->get_logger()->info('Failed submits deleted', [
			'hash_current'  => $hash_current,
			'hash_previous' => $hash_previous,
			'deleted_rows'  => $deleted,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);
	}

	public function success(): void
	{
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Success handler skipped: IP protection disabled', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			return;
		}

		$this->get_logger()->info('Success handler started', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->do_handle_submit();

		$this->get_logger()->info('Success handler completed', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}


	/**
	 * Validate method.
	 *
	 * Checks if the current request is valid based on IP address, previous IP addresses, and submission history.
	 *
	 * @return bool Returns true if the request is valid, false otherwise.
	 * @throws \Exception
	 */
	public function validate(): bool
	{
		// Load settings
		$settings = $this->Controller->get_settings();

		// Measure the period of time between those timestamps
		$allowed_time_between = (int) $settings['global']['protection_ip_period_between_submits'];

		// Max retries period
		$max_retry_period = time() - (int) $settings['global']['protection_ip_max_retries_period'];

		// Max retries
		$max_retries = (int) $settings['global']['protection_ip_max_retries'];

		// Block Time
		$block_time = (int) $settings['global']['protection_ip_block_time'];

		// Get User IP
		/** @var UserData $User_Data */
		$User_Data = $this->Controller->get_module('user-data');
		$ip        = $User_Data->get_ip_address();

		$this->get_logger()->debug('Starting IP validation', [
			'allowed_time_between' => $allowed_time_between,
			'max_retry_period'     => $max_retry_period,
			'max_retries'          => $max_retries,
			'block_time'           => $block_time,
			'ip'                   => $ip,
			'class'                => __CLASS__,
			'method'               => __METHOD__,
		]);

		// Generate Salt
		$Salt_Current  = $this->create_salt()->get_last();
		$Salt_Previous = $this->create_salt()->get_one_salt_by_offset(1);

		// Generate hash
		$hash_current  = $Salt_Current->get_salted($ip);
		$hash_previous = $hash_current;

		if ($Salt_Previous !== null) {
			$hash_previous = $Salt_Previous->get_salted($ip);
		}

		$this->get_logger()->debug('Hashes generated', [
			'hash_current'  => $hash_current,
			'hash_previous' => $hash_previous,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		// Check if the IP has been blocked
		if ($this->create_ip_ban()->get_count($hash_current, $hash_previous) > 0) {
			$this->get_logger()->info('IP is blocked', [
				'hash_current'  => $hash_current,
				'hash_previous' => $hash_previous,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);
			return false;
		}

		// Check for log entries to automatically ban the user if the limit is reached.
		$IP_Log_Last = $this->create_ip_log()->get_last_entry_by_hash($hash_current, $hash_previous);

		// skip if no entries has been found yet
		if (null === $IP_Log_Last) {
			$this->get_logger()->debug('No last log entry found - creating first one', [
				'hash_current'  => $hash_current,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);

			// create a new log entry
			$IPLog = $this->create_ip_log(['hash' => $hash_current, 'submitted' => 0]);
			$IPLog->save();

			return true;
		}

		// Get the second last entry
		$IP_Log_Second_Last = $this->create_ip_log()->get_last_entry_by_hash($hash_current, $hash_previous, 1);

		// skip if no entry has been found
		if (null === $IP_Log_Second_Last) {
			$this->get_logger()->debug('No second-to-last log entry found - creating new one', [
				'hash_current'  => $hash_current,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);

			// create a new log entry
			$IPLog = $this->create_ip_log(['hash' => $hash_current, 'submitted' => 0]);
			$IPLog->save();

			return true;
		}

		$diff = $IP_Log_Last->get_submission_timestamp() - $IP_Log_Second_Last->get_submission_timestamp();

		$this->get_logger()->debug('Time difference between last submits', [
			'last_ts'         => $IP_Log_Last->get_submission_timestamp(),
			'second_last_ts'  => $IP_Log_Second_Last->get_submission_timestamp(),
			'diff'            => $diff,
			'allowed'         => $allowed_time_between,
			'class'           => __CLASS__,
			'method'          => __METHOD__,
		]);

		// skip if the time between two submissions was bigger then the minimum time required
		if ($diff > $allowed_time_between) {
			$this->get_logger()->debug('Time difference OK - validation passed', [
				'diff'    => $diff,
				'allowed' => $allowed_time_between,
				'class'   => __CLASS__,
				'method'  => __METHOD__,
			]);
			return true;
		}

		// create a new log entry
		$IPLog = $this->create_ip_log(['hash' => $hash_current, 'submitted' => 0]);
		$IPLog->save();

		// Check if there are >= max_retries entries for the given IP within retry period, if yes - block it
		$count_in_period = $IPLog->get_count($hash_current, $hash_previous, 0, $max_retry_period);

		$this->get_logger()->debug('Number of failed attempts in period', [
			'count'          => $count_in_period,
			'max_retries'    => $max_retries,
			'retry_period_s' => $max_retry_period,
			'class'          => __CLASS__,
			'method'         => __METHOD__,
		]);

		if ($count_in_period >= $max_retries) {
			$this->get_logger()->warning('Maximum failed attempts reached - IP will be blocked', [
				'count'          => $count_in_period,
				'max_retries'    => $max_retries,
				'block_time_s'   => $block_time,
				'hash_current'   => $hash_current,
				'class'          => __CLASS__,
				'method'         => __METHOD__,
			]);

			// ban the ip address
			$IPBan = $this->create_ip_ban(['hash' => $hash_current, 'blockedtime' => $block_time]);
			$IPBan->save();
		}

		$this->set_message(__('ip-protection', 'captcha-for-contact-form-7'));

		$this->get_logger()->info('Validation failed - message set', [
			'message' => 'ip-protection',
			'class'   => __CLASS__,
			'method'  => __METHOD__,
		]);

		return false;
	}


	/**
	 * Check if the submission is considered as spam.
	 *
	 * @return bool Returns true if the submission is considered as spam, false otherwise.
	 * @throws \Exception
	 */
	public function is_spam(): bool
	{
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam check skipped: IP protection disabled', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			return false;
		}

		$result = !$this->validate();

		$this->get_logger()->info('Spam check performed', [
			'is_spam' => $result,
			'class'   => __CLASS__,
			'method'  => __METHOD__,
		]);

		return $result;
	}

}

//IPValidator::getInstance();