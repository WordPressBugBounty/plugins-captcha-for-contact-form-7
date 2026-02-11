<?php

namespace f12_cf7_captcha\core\protection\ip;

use DateTime;
use Exception;
use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\LoggerInterface;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Salt
 *
 * @package forge12\contactform7
 */
class Salt {
	private LoggerInterface $logger;
	/**
	 * The unique ID
	 *
	 * @var int
	 */
	private $id = 0;
	/**
	 * The Salt
	 *
	 * @var string
	 */
	private $salt = '';

	/**
	 * The datetime whenever the captcha code has been created
	 *
	 * @var string
	 */
	private $createtime = '';

	/**
	 * Create a new Captcha Object
	 *
	 * @param $object
	 */
	public function __construct( LoggerInterface $logger, $params = array() ) {
		$this->logger = $logger;
		$this->set_params( $params );

		$this->logger->info('The class has been initialized.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
			'params' => $params,
		]);
	}

	private function get_logger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * Sets the parameters of the object.
	 *
	 * @param array $params An associative array containing the parameters
	 *                      to be set. The keys correspond to the names of
	 *                      the properties of the object.
	 *                      The values are the new values to be assigned to
	 *                      the corresponding properties.
	 *
	 * @return void
	 */
	public function set_params(array $params): void
	{
		$this->logger->info('Setting parameters...', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		foreach ($params as $key => $value) {
			$this->logger->debug("Processing parameter: '{$key}'", [
				'class' => __CLASS__,
			]);

			if (isset($this->{$key})) {
				if ($key === 'salt') {
					$this->logger->debug('Decoding "salt" from Base64.', [
						'class' => __CLASS__,
					]);
					$value = base64_decode($value);
				}
				$this->{$key} = $value;
			} else {
				$this->logger->warning("Parameter '{$key}' could not be set because it does not exist.", [
					'class' => __CLASS__,
				]);
			}
		}

		$this->logger->info('Parameter setting completed.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}

	/**
	 * Remove records older than the specified period from the database table.
	 *
	 * @param string $period The period indicating the age of the records to be removed.
	 *
	 * @return int The number of records deleted.
	 */
	public function remove_older_than(string $period): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.');
			return 0;
		}

		$this->logger->info("Deleting entries older than '{$period}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$timestamp = strtotime($period);
		if ($timestamp === false) {
			$this->logger->error('Invalid period format.', ['period' => $period]);
			return 0;
		}

		$wp_table_name = $this->get_table_name();

		$dt = new DateTime();
		$dt->setTimestamp($timestamp);
		$dt_formatted = $dt->format('Y-m-d H:i:s');

		$this->logger->debug("Generating SQL query for deletion.", [
			'table' => $wp_table_name,
			'cutoff_date' => $dt_formatted,
		]);

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$wp_table_name} WHERE createtime < %s",
			$dt_formatted
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows_deleted = $wpdb->query($query);

		if ($rows_deleted === false) {
			$this->logger->error('Error during database query.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Successfully deleted {$rows_deleted} entries.", [
				'rows_deleted' => $rows_deleted,
			]);
		}

		return (int)$rows_deleted;
	}

	/**
	 * Reset the table by deleting all records.
	 *
	 * @return bool True if the table was reset successfully; otherwise, false.
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 */
	public function reset_table(): bool
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.');
			return false;
		}

		$this->logger->warning('Table is being reset! All data will be deleted.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$wp_table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query("DELETE FROM `{$wp_table_name}`");

		if ($result === false) {
			$this->logger->error('Error resetting the table.', ['db_error' => $wpdb->last_error]);
			return false;
		}

		$this->logger->info('Table successfully reset.', ['rows_deleted' => $result]);

		return true;
	}

	/**
	 * Retrieves the count of entries from the specified table.
	 *
	 * @param int $validated The validation parameter.
	 *
	 * @return int The count of entries.
	 */
	public function get_count(int $validated = -1): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.');
			return 0;
		}

		$wp_table_name = $this->get_table_name();
		$prepare_stmt = 'SELECT count(*) AS entries FROM ' . $wp_table_name;

		if ($validated !== -1) {
			$this->logger->info("Counting entries with validation status '{$validated}'.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
			$prepare_stmt .= ' WHERE validated = %d';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results($wpdb->prepare($prepare_stmt, $validated));
		} else {
			$this->logger->info("Counting all entries in the table.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results($prepare_stmt);
		}

		if (is_array($results) && isset($results[0])) {
			$count = (int)$results[0]->entries;
			$this->logger->debug("Number of entries: {$count}", [
				'count' => $count,
			]);
			return $count;
		}

		$this->logger->error('Error retrieving count results from the database.', ['db_error' => $wpdb->last_error]);
		return 0;
	}

	/**
	 * Create a new table in the WordPress database for storing salts.
	 *
	 * @return void
	 */
	public function create_table(): void
	{
		$this->logger->info('Attempting to create the database table.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$wp_table_name = $this->get_table_name();

		if (!function_exists('dbDelta')) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$this->logger->debug('dbDelta function loaded.');
		}

		$sql = sprintf(
			"CREATE TABLE %s (
            id int(11) NOT NULL auto_increment, 
            salt varchar(255) NOT NULL,
            createtime datetime DEFAULT '0000-00-00 00:00:00', 
            PRIMARY KEY  (id)
        )",
			$wp_table_name
		);

		dbDelta($sql);

		$this->logger->info('dbDelta query executed. Checking if the table was created.', [
			'table' => $wp_table_name,
		]);

		// Optional: Check the table status after dbDelta to make sure.
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wp_table_name}'") === $wp_table_name;
		if ($table_exists) {
			$this->logger->info('Table successfully created or updated.', [
				'table' => $wp_table_name,
			]);
		} else {
			$this->logger->error('Error creating the table. Check the SQL syntax or database permissions.', [
				'table' => $wp_table_name,
				'sql' => $sql,
				'last_error' => $wpdb->last_error,
			]);
		}
	}

	/**
	 * Deletes the table associated with the current object.
	 *
	 * @return void
	 */
	public function delete_table(): void
	{
		global $wpdb;

		$this->logger->warning('Attempting to delete the database table and associated cron job.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available. Deletion process aborted.');
			return;
		}

		$wp_table_name = $this->get_table_name();

		// SQL query to delete the table
		$sql = "DROP TABLE IF EXISTS " . $wp_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query($sql);

		if ($result === false) {
			$this->logger->error('Error deleting the table.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Table '{$wp_table_name}' successfully deleted.");
		}

		// Delete the cron job
		$hook = 'weeklyIPClear';
		$scheduled = wp_next_scheduled($hook);

		if ($scheduled) {
			wp_clear_scheduled_hook($hook);
			$this->logger->info("The scheduled cron job '{$hook}' was successfully deleted.");
		} else {
			$this->logger->info("The cron job '{$hook}' was not scheduled and did not need to be deleted.");
		}

		$this->logger->info('Deletion of table and cron job completed.');
	}

	/**
	 * Retrieves the name of the table prefixed with the WordPress database table prefix.
	 *
	 * @return string The name of the table prefixed with the WordPress database table prefix.
	 * @global wpdb $wpdb WordPress database access abstraction class instance.
	 */
	public function get_table_name(): string
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available. Cannot determine table name.');
			return '';
		}

		$table_name = $wpdb->prefix . 'f12_cf7_salt';

		$this->logger->debug('Table name determined.', [
			'table_name' => $table_name,
		]);

		return $table_name;
	}

	/**
	 * Get the ID of the current object.
	 *
	 * @return int The ID of the current object.
	 */
	public function get_id(): int
	{
		$this->logger->debug('Retrieving the ID.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'id' => $this->id,
		]);

		return $this->id;
	}

	/**
	 * Set the ID of the object.
	 *
	 * @param int $id The ID to set. Must be an integer.
	 *
	 * @return void
	 */
	private function set_id(int $id)
	{
		$this->logger->debug('Setting the ID.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
			'old_id' => $this->id,
			'new_id' => $id,
		]);

		$this->id = $id;
	}

	/**
	 * Get the salt value for the object
	 *
	 * @return string The salt value
	 */
	private function get_salt(): string
	{
		$this->logger->debug('Retrieving the salt value.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		return $this->salt;
	}

	/**
	 * Get the creation time of the object.
	 * If the creation time is empty, it will generate a new DateTime object and set the creation time to the current
	 * date and time.
	 *
	 * @return string The creation time formatted as 'Y-m-d H:i:s'
	 */
	public function get_create_time(): string
	{
		$this->logger->debug('Retrieving the creation time.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->createtime)) {
			$this->logger->info('Creation time is empty, generating new timestamp.', [
				'class' => __CLASS__,
			]);

			$dt = new DateTime();
			$this->createtime = $dt->format('Y-m-d H:i:s');

			$this->logger->debug('New timestamp generated.', [
				'createtime' => $this->createtime,
			]);
		}

		$this->logger->debug('Creation time returned.', [
			'createtime' => $this->createtime,
		]);

		return $this->createtime;
	}

	/**
	 * Set the creation time for the object to current date and time
	 *
	 * @return void
	 */
	public function set_create_time(): void
	{
		$this->logger->info('Setting the creation time.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$dt = new DateTime();
		$this->createtime = $dt->format('Y-m-d H:i:s');

		$this->logger->debug('Creation time set to ' . $this->createtime . '.', [
			'createtime' => $this->createtime,
		]);
	}

	/**
	 * Create a new Salt object.
	 *
	 * @return Salt The newly created Salt object.
	 * @throws RuntimeException If the Salt could not be created.
	 * @throws Exception
	 */
	private function create_salt(): Salt
	{
		$this->logger->info('Creating a new salt record.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Create a new salt object if one does not exist yet
		$generated_salt = $this->generate_salt();
		$this->logger->debug('New salt value generated.', [
			'generated_salt_length' => strlen($generated_salt),
		]);

		$Salt = new Salt($this->get_logger(), [
			'salt' => $generated_salt
		]);
		$Salt->save();

		if ($Salt->get_id() === 0) {
			$this->logger->error('Error: Salt record could not be created.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'error_message' => 'The database query to save the salt failed.',
			]);
			throw new RuntimeException("Salt could not be created. Please check the Database");
		}

		$this->logger->info('New salt record successfully created.', [
			'salt_id' => $Salt->get_id(),
		]);

		return $Salt;
	}

	/**
	 * Retrieves the last record from the database table.
	 *
	 * @return Salt|null The last record retrieved from the database table, or null if the global $wpdb object is not
	 *                   available.
	 * @throws Exception
	 */
	public function get_last(): ?Salt
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.');
			throw new \RuntimeException('WPDB not found');
		}

		$table = $this->get_table_name();
		$this->logger->info('Searching for the last salt record in the database.');

		$prepare_stmt = sprintf("SELECT * FROM %s ORDER BY createtime DESC LIMIT 1", $table);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results($prepare_stmt, ARRAY_A);

		$Salt = null;

		if (is_array($results) && isset($results[0])) {
			$this->logger->debug('Last salt record found.', ['salt_id' => $results[0]['id']]);
			$Salt = new Salt($this->get_logger(), $results[0]);
		} else {
			$this->logger->info('No salt record found. Creating a new one.');
		}

		/*
		 * Create a salt if none exists
		 */
		if (null === $Salt) {
			try {
				$Salt = $this->create_salt();
			} catch (\RuntimeException $e) {
				$this->logger->error('Error creating a new salt record.', ['error' => $e->getMessage()]);
				return null;
			}
		}

		/*
		 * Create a new salt if the existing one is older than 30 days
		 */
		if ($this->is_older_than($Salt->get_create_time())) {
			$this->logger->info('The existing salt is older than 30 days. Creating a new one.');
			try {
				$Salt = $this->create_salt();
			} catch (\RuntimeException $e) {
				$this->logger->error('Error creating a new time-based salt record.', ['error' => $e->getMessage()]);
				return null;
			}
		}

		$this->logger->debug('Returning the current salt record.', ['salt_id' => $Salt->get_id()]);
		return $Salt;
	}

	/**
	 * Determines if a given date is older than a specified number of days.
	 *
	 * @param string $date The date to check. Format: "Y-m-d" or "Y-m-d H:i:s".
	 * @param string $days The number of days to compare against. Format: "+/-n day(s)", where n is a positive or
	 *                     negative integer.
	 *
	 * @return bool Returns true if the date is older than the specified number of days, otherwise returns false.
	 */
	public function is_older_than(string $date, string $days = '+30 Days'): bool
	{
		$this->logger->debug("Checking if the date '{$date}' is older than '{$days}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			$d1 = new DateTime($date);
			$d1->modify($days);
			$d2 = new DateTime();
		} catch (\Exception $e) {
			$this->logger->error('Error during date processing.', [
				'error' => $e->getMessage(),
				'input_date' => $date,
				'days_to_add' => $days,
			]);
			return false;
		}

		$is_older = $d2 > $d1;

		$this->logger->debug('Comparison result.', [
			'is_older' => $is_older,
			'given_date' => $d1->format('Y-m-d H:i:s'),
			'current_date' => $d2->format('Y-m-d H:i:s'),
		]);

		return $is_older;
	}

	/**
	 * Generates a random salt.
	 *
	 * @return string The randomly generated salt as a string.
	 * @throws Exception
	 */
	public function generate_salt(): string
	{
		$this->logger->info('Generating a new salt value.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			$salt = random_bytes(512);
			$this->logger->debug('New salt successfully generated.', [
				'length' => strlen($salt),
			]);
			return $salt;
		} catch (\Exception $e) {
			$this->logger->error('Error generating the salt value.', [
				'error_message' => $e->getMessage(),
			]);
			throw $e; // Or another suitable error handler
		}
	}

	/**
	 * Returns a salted hash of the given value using PBKDF2 algorithm with SHA512 hashing.
	 *
	 * @param string $value The value to be hashed and salted.
	 *
	 * @return string The salted hash of the given value.
	 */
	public function get_salted(string $value): string
	{
		$this->logger->info('Creating a salted hash value.', [
			"plugin" => "f12-cf7-captcha",
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->salt)) {
			$this->logger->error('Error: The salt value is missing.', [
				"plugin" => "f12-cf7-captcha",
				'class' => __CLASS__,
			]);

			$salt = $this->create_salt();
			if(!empty($salt)) {
				$this->logger->debug( 'Salt value successfully generated.', ["plugin" => "f12-cf7-captcha","salt" => $salt ] );
			}else{
				$this->logger->critical( 'Salt value could not be generated.', ["plugin" => "f12-cf7-captcha"] );
			}
		}

		$hash = hash_hmac('sha512', $value, $this->salt);

		$this->logger->debug('Hash successfully generated.', [
			"plugin" => "f12-cf7-captcha",
			'hash_length' => strlen($hash),
		]);

		return $hash;
	}

	/**
	 * Retrieves a single salt object by its offset.
	 * This will return the previous salt. We only store 2 salts - the new one and the previous one. So therefor
	 * it is a little bit special
	 *
	 * @param int $offset The offset of the salt object to retrieve.
	 *
	 * @return Salt|null The salt object found at the specified offset, or null if not found.
	 *
	 */
	public function get_one_salt_by_offset(int $offset): ?Salt
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.', ["plugin" => "f12-cf7-captcha"]);
			return null;
		}

		$this->logger->info("Attempting to retrieve a salt record with offset '{$offset}'.", ["plugin" => "f12-cf7-captcha"]);

		$table = $this->get_table_name();

		if (!is_numeric($offset) || $offset < 0) {
			$this->logger->error('Invalid offset value. Expected a non-negative integer.', [
				"plugin" => "f12-cf7-captcha",
				'offset' => $offset,
			]);
			return null;
		}

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table} ORDER BY createtime DESC LIMIT 1 OFFSET %d",
			$offset
		);

		$this->logger->debug('Executing database query.', [
			"plugin" => "f12-cf7-captcha",
			'query' => $query,
		]);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results($query, ARRAY_A);

		$Salt = null;

		if (is_array($results) && isset($results[0])) {
			$this->logger->info('Salt record found.', ['id' => $results[0]['id']]);
			$Salt = new Salt($this->get_logger(), $results[0]);
		} else {
			$this->logger->warning('No salt record found for the given offset.', [
				"plugin" => "f12-cf7-captcha",
				'offset' => $offset,
			]);
		}

		return $Salt;
	}

	/**
	 * Clean up the database by deleting old records.
	 *
	 * This method deletes records from the specified table that have a creation
	 * time older than three weeks ago. It uses the global $wpdb object to execute
	 * the delete query.
	 *
	 * @return void
	 */
	private function maybe_clean(): void
	{
		global $wpdb;

		$this->logger->info('Attempting to clean up old database entries.', ["plugin" => "f12-cf7-captcha"]);

		if (!$wpdb) {
			$this->logger->error('Global $wpdb object not available.', ["plugin" => "f12-cf7-captcha"]);
			throw new \RuntimeException('WPDB not found');
		}

		$table = $this->get_table_name();

		// Date interval: 3 weeks
		try {
			$date_time = new DateTime('-3 Weeks');
			$date_time_formatted = $date_time->format('Y-m-d H:i:s');
			$this->logger->debug("Date boundary for deletion calculated.", [
				"plugin" => "f12-cf7-captcha",
				'cutoff_date' => $date_time_formatted,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error during date calculation.', ['error' => $e->getMessage()]);
			return;
		}

		// Execute the query to delete all entries older than 3 weeks
		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$table} WHERE createtime < %s",
			$date_time_formatted
		);

		$this->logger->info('Executing cleanup query.', [
			'query' => $query,
		]);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query($query);

		if ($result === false) {
			$this->logger->error('Error during database cleanup.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Successfully deleted {$result} old entries.", [
				'rows_deleted' => $result,
			]);
		}
	}

	/**
	 * Saves the object to the database.
	 *
	 * @return int|null The result of the save operation, or null if $wpdb is not available.
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 */
	public function save(): int
	{
		global $wpdb;

		$this->logger->info('Attempting to save the salt record to the database.');

		if (null === $wpdb) {
			$this->logger->error('Global $wpdb object not available.');
			throw new RuntimeException('WPDB not found');
		}

		if ($this->id !== 0) {
			$this->logger->warning('Record already exists and cannot be saved again.', [
				'id' => $this->id,
			]);
			return 0;
		}

		$table = $this->get_table_name();

		$this->logger->debug('Executing wpdb->insert().', [
			'table' => $table,
			'salt_length' => strlen($this->salt),
			'createtime' => $this->get_create_time(),
		]);

		$result = $wpdb->insert(
			$table,
			[
				'salt' => base64_encode($this->salt),
				'createtime' => $this->get_create_time()
			]
		);

		if ($result === false) {
			$this->logger->warning('Insert failed, attempting to recreate table', [
				'db_error' => $wpdb->last_error,
				'class'    => __CLASS__,
				'method'   => __METHOD__,
			]);

			$this->create_table();
			$result = $wpdb->insert(
				$table,
				[
					'salt'       => base64_encode($this->salt),
					'createtime' => $this->get_create_time()
				]
			);

			if ($result === false) {
				$this->logger->error('Insert failed again after table creation', [
					'db_error' => $wpdb->last_error,
					'class'    => __CLASS__,
					'method'   => __METHOD__,
				]);
				throw new RuntimeException('Database error occurred.');
			}
		}

		$this->set_id($wpdb->insert_id);
		$this->logger->info('Record successfully saved. New ID: ' . $this->id, [
			'id' => $this->id,
		]);

		// Clean up older entries after saving
		try {
			$this->maybe_clean();
		} catch (\RuntimeException $e) {
			$this->logger->error('Error during database cleanup.', ['error' => $e->getMessage()]);
		}

		return $result;
	}
}