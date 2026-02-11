<?php

namespace f12_cf7_captcha\core\timer;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Timer_Controller
 * Enables the validation of forms / comments by submit time
 */
class Timer_Controller extends BaseModul {
	private string $createtime = '';

	private ?CaptchaTimer $Latest_Timer = null;

	private ?CaptchaTimerCleaner $Captcha_Timer_Cleaner = null;

	/**
	 * Constructor
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);
		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instantiate the CaptchaTimerCleaner
		$this->Captcha_Timer_Cleaner = new CaptchaTimerCleaner($Controller);
		$this->get_logger()->debug('CaptchaTimerCleaner instance created.');

		// Add the '_init' hook
		add_action('init', array($this, '_init'));
		$this->get_logger()->debug('Hook "init" added for the "_init" method.');

		$this->get_logger()->info('Constructor completed.');
	}

	/**
	 * Retrieves the CaptchaTimerCleaner instance associated with this system.
	 *
	 * This method returns the instance of the CaptchaTimerCleaner class that is responsible for managing
	 * and cleaning up the timers in the system.
	 *
	 * @return CaptchaTimerCleaner The CaptchaTimerCleaner instance associated with this system.
	 */
	public function get_timer_cleaner(): CaptchaTimerCleaner
	{
		$this->get_logger()->info('Retrieving the captcha timer cleaner instance.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Return the cleaner instance already created in the constructor.
		if (!($this->Captcha_Timer_Cleaner instanceof CaptchaTimerCleaner)) {
			$this->get_logger()->error('CaptchaTimerCleaner instance is not available or has the wrong type.');
			// Optional: A new instance could be created here or an exception could be thrown.
			// Since the instance is created in the constructor, this is an unexpected state.
		} else {
			$this->get_logger()->debug('CaptchaTimerCleaner instance successfully returned.');
		}

		return $this->Captcha_Timer_Cleaner;
	}


	/**
	 * Create and get a CaptchaTimer object.
	 *
	 * @return CaptchaTimer The newly created CaptchaTimer object.
	 * @throws \Exception
	 */
	public function factory(): CaptchaTimer
	{
		$this->get_logger()->info('Creating a new CaptchaTimer instance via the factory method.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instantiate a new CaptchaTimer object and pass the logger.
		$timer = new CaptchaTimer($this->get_logger());

		$this->get_logger()->info('CaptchaTimer object successfully created.');

		return $timer;
	}


	/**
	 * Checks if the protection time feature is enabled.
	 *
	 * This method retrieves the value of the 'protection_time_enable' setting from the global settings
	 * using the Controller object. The method compares the retrieved value with 1 and returns true if they are equal,
	 * indicating that the protection time feature is enabled. Otherwise, it returns false.
	 *
	 * @return bool True if the protection time feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = $this->Controller->get_settings('protection_time_enable', 'global') === 1;

		if ($is_enabled) {
			$this->get_logger()->info('Timer protection is enabled.');
		} else {
			$this->get_logger()->info('Timer protection is disabled.');
		}

		return $is_enabled;
	}

	/**
	 * Retrieves the latest timer.
	 *
	 * This method returns the latest instance of CaptchaTimer class that was set using the set_latest_timer() method.
	 * If no timer is set, it returns null.
	 *
	 * @return CaptchaTimer|null The latest timer object if it is set, or null if no timer is set.
	 */
	public function get_latest_timer(): ?CaptchaTimer
	{
		$this->get_logger()->info('Retrieving the most recently created timer.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Check if the Latest_Timer property is an instance of CaptchaTimer.
		if ($this->Latest_Timer instanceof CaptchaTimer) {
			$this->get_logger()->debug('The most recently created timer was successfully retrieved.', [
				'timer_hash' => $this->Latest_Timer->get_hash(),
			]);
			return $this->Latest_Timer;
		}

		$this->get_logger()->info('No recently created timer was found. Returning null.');

		// If the property is null, return null.
		return null;
	}

	/**
	 * @private WordPress Hook
	 */
	public function _init()
	{
		$this->get_logger()->info('Executing the "_init" initialization method.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Trigger an action to allow other parts of the code to initialize.
		do_action('f12_cf7_captcha_timer_validator_init');
		$this->get_logger()->debug('The action "f12_cf7_captcha_timer_validator_init" was triggered.');

		$this->get_logger()->info('The initialization method is complete.');
	}

	/**
	 * Get the create time of the object
	 *
	 * @return string The create time in the format 'Y-m-d H:i:s'
	 */
	private function get_create_time(): string
	{
		$this->get_logger()->info('Retrieving the creation time. Checking if it is already set.');

		// Check if the `createtime` property is empty.
		if (empty($this->createtime)) {
			$this->get_logger()->debug('The creation time is empty. Creating a new date object and setting the time.');

			try {
				// Instantiate a new DateTime object to capture the current time.
				$dt = new \DateTime();
				// Format the date into the SQL-compatible format 'YYYY-MM-DD HH:MM:SS'.
				$this->createtime = $dt->format('Y-m-d H:i:s');
				$this->get_logger()->info('Creation time successfully set to the current time.', ['createtime' => $this->createtime]);
			} catch (\Exception $e) {
				$this->get_logger()->error('Error creating the DateTime object.', ['error' => esc_html($e->getMessage())]);
				// In case of error, an empty string or a default value can be returned
				// to avoid further errors.
				return '';
			}
		} else {
			$this->get_logger()->debug('The creation time already exists. Returning the existing value.');
		}

		return $this->createtime;
	}

	/**
	 * Generate a hash for the given user's IP address
	 *
	 * @param string $user_ip_address The user's IP address
	 *
	 * @return string The generated hash
	 */
	private function generate_hash(string $user_ip_address): string
	{
		$this->get_logger()->info('Generating a new unique hash value for the timer.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Combine the current timestamp (seconds precision) with the user's IP address
		// to create a unique, unpredictable string.
		// The IP address ensures that hashes are different for different users.
		$data_to_hash = time() . $user_ip_address;

		// Use password_hash() with the default algorithm (PASSWORD_DEFAULT).
		// This provides a strong, salted hashing method that ensures
		// the hash cannot be easily guessed or looked up in a rainbow table.
		$hash = password_hash($data_to_hash, PASSWORD_DEFAULT);

		$this->get_logger()->debug('Hash generation completed. The resulting hash is ' . strlen($hash) . ' characters long.');

		return $hash;
	}

	/**
	 * Get the current time in milliseconds.
	 *
	 * @return float The current time in milliseconds.
	 */
	private function get_time_in_ms(): float
	{
		$time_in_seconds = microtime(true);

		$this->get_logger()->debug('Retrieving the current UNIX time in milliseconds.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'raw_time' => $time_in_seconds,
		]);

		// Convert the time from seconds to milliseconds and round the result.
		// microtime(true) returns the time as a float with high precision.
		$time_in_ms = round($time_in_seconds * 1000);

		$this->get_logger()->debug('Converted time in milliseconds: ' . $time_in_ms);

		return $time_in_ms;
	}

	/**
	 * Adds a timer to the system.
	 *
	 * This method creates a new instance of CaptchaTimer class and saves it in the system.
	 * The timer is associated with the user's IP address, and it includes a unique hash,
	 * value (time in milliseconds), and creation time.
	 *
	 * @return string|null The hash of the timer if it is successfully saved, or null if the saving fails.
	 * @throws \Exception
	 */
	public function add_timer(): ?string
	{
		$this->get_logger()->info('Starting the process to add a new timer entry to the database.');

		try {
			// Retrieve the user data instance to get the user's IP address.
			$User_Data = $this->Controller->get_module('user-data');
			$user_ip_address = $User_Data->get_ip_address();
			$this->get_logger()->debug('User IP address retrieved.', ['ip' => $user_ip_address]);
		} catch (\Exception $e) {
			$this->get_logger()->error('Error retrieving user data. Timer cannot be created.', ['error' => esc_html($e->getMessage())]);
			return null;
		}

		// Generate a unique hash for the timer.
		$hash = $this->generate_hash($user_ip_address);

		// Get the current time in milliseconds and the formatted creation time.
		$time_in_ms = $this->get_time_in_ms();
		$create_time = $this->get_create_time();

		// Create a new CaptchaTimer instance with the prepared data.
		$CaptchaTimer = new CaptchaTimer(
			$this->get_logger(),
			[
				'hash'       => $hash,
				'value'      => $time_in_ms,
				'createtime' => $create_time
			]
		);

		$this->get_logger()->debug('New CaptchaTimer object created.', [
			'hash' => $hash,
			'value' => $time_in_ms,
			'createtime' => $create_time,
		]);

		// Try to save the timer entry to the database.
		if ($CaptchaTimer->save()) {
			$this->get_logger()->info('Timer entry successfully saved to the database.');

			// Store the instance as the latest timer for later access.
			$this->Latest_Timer = $CaptchaTimer;

			// Return the generated hash that is used in the form HTML.
			return $hash;
		}

		$this->get_logger()->error('Error saving the timer entry to the database. Returning null.');

		// Return null if saving fails.
		return null;
	}

	/**
	 * Retrieves a timer by its hash.
	 *
	 * @param string $hash The hash of the timer to retrieve.
	 *
	 * @return CaptchaTimer|null The CaptchaTimer object if found, or null if not found.
	 *
	 * @throws RuntimeException When WPDB is not defined.
	 */
	public function get_timer(string $hash): ?CaptchaTimer
	{
		$this->get_logger()->info('Retrieving a captcha timer by hash value.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'hash' => $hash,
		]);

		global $wpdb;

		if (!$wpdb) {
			$error_message = 'The global variable $wpdb is not defined.';
			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new RuntimeException( $error_message );
		}

		try {
			$timer_handler = new CaptchaTimer($this->get_logger());
			$timer = $timer_handler->get_by_hash($hash);

			if ($timer) {
				$this->get_logger()->info('Timer entry successfully retrieved.');
			} else {
				$this->get_logger()->notice('No timer entry found for the given hash.');
			}

			return $timer;

		} catch (RuntimeException $e) {
			$this->get_logger()->error('Error retrieving the timer.', [
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	/**
	 * Removes a timer with the given hash.
	 *
	 * @param string $hash The hash of the timer to be removed.
	 *
	 * @return void
	 * @throws RuntimeException if the global $wpdb variable is not defined.
	 *
	 */
	public function remove_timer(string $hash): void
	{
		$this->get_logger()->info('Starting the process to remove a timer by hash value.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'hash' => $hash,
		]);

		global $wpdb;

		if (!$wpdb) {
			$error_message = 'The global variable $wpdb is not defined.';
			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new RuntimeException( $error_message );
		}

		try {
			$timer_handler = new CaptchaTimer($this->get_logger());
			$is_deleted = $timer_handler->delete_by_hash($hash);

			if ($is_deleted) {
				$this->get_logger()->info('Timer entry successfully removed.', ['hash' => $hash]);
			} else {
				$this->get_logger()->warning('No timer entry found for deletion or deletion failed.', ['hash' => $hash]);
			}
		} catch (\Exception $e) {
			$this->get_logger()->error('Error deleting the timer entry.', [
				'error' => $e->getMessage(),
			]);
		}
	}
}