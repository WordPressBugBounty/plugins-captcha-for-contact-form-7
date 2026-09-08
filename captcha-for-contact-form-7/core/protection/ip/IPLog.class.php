<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\LoggerInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IPLog
 *
 * @package forge12\contactform7
 */
class IPLog
{
	private LoggerInterface $logger;

    /**
     * The unique ID
     *
     * @var int
     */
    private $id = 0;
    /**
     * The identifier used in the contact form
     *
     * @var string
     */
    private $hash = '';
    /**
     * The datetime whenever the captcha code has been created
     *
     * @var string
     */
    private $createtime = '';

    /**
     * Upper bound for the retry window in get_count(), in seconds (30 days).
     *
     * The rows themselves are removed after three weeks by IPLogCleaner, so anything beyond this
     * cannot be a duration the caller meant — it is a unit mix-up.
     */
    private const MAX_WINDOW_SECONDS = 2592000;

    /**
     * The flag to determine if the data has been submitted or not
     *
     * @var int
     */
    private $submitted = 0;

    /**
     * Create a new Captcha Object
     *
     * @param $object
     */
	public function __construct(LoggerInterface $logger, $params = array())
	{
		$this->logger = $logger;

		$allowed = ['id', 'hash', 'createtime', 'submitted'];

		foreach ($params as $key => $value) {
			if (in_array($key, $allowed, true)) {
				$this->{$key} = $value;
			}
		}
	}


	public function get_logger():  LoggerInterface
	{
		return $this->logger;
	}

    /**
     * Retrieves an array of timestamps for records matching the given hash and previous_hash.
     * Only successfull submissions will be returned.
     *
     * @param string $hash          The hash to match against the "hash" column in the database.
     * @param string $previous_hash The previous hash to match against the "hash" column in the database.
     * @param int    $seconds       The number of seconds to subtract from the current time. Defaults to 0.
     *
     * @return IPLog|null The last matching entry, or null when there is none.
     * @throws RuntimeException|\Exception If global $wpdb is not defined.
     * @deprecated
     */
	public function get_timestamps(string $hash, string $previous_hash, int $seconds = 0): ?IPLog
	{
		return $this->get_last_entry_by_hash($hash, $previous_hash);
	}


    /**
     * Retrieves the last submitted entry by the given hash and previous hash.
     *
     * @param string $hash          The hash to search for.
     * @param string $previous_hash The previous hash to search for.
     * @param int    $offset        The offset of the elements
     *
     * @return IPLog|null The last submitted entry with the given hash and previous hash,
     *         or null if no entry is found.
     * @throws RuntimeException If WPDB is not defined.
     */
	public function get_last_entry_by_hash(string $hash, string $previous_hash, int $offset = 0): ?IPLog
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();
		$prepare_stmt = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table} WHERE (hash=%s OR hash=%s) ORDER BY createtime DESC LIMIT %d,1",
			$hash,
			$previous_hash,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results($prepare_stmt);

		if (!is_array($results) || !isset($results[0])) {
			return null;
		}

		return new IPLog($this->get_logger(), $results[0]);
	}


    /**
     * Retrieves the count of entries based on the provided parameters.
     *
     * @param string $hash          The hash value to search for (optional)
     * @param string $previous_hash The previous hash value to search for (optional)
     * @param int    $submitted     The submitted value to search for (optional)
     * @param int    $seconds       The number of seconds to subtract from the current time (optional)
     *
     * @return int The count of entries that match the given criteria
     * @throws RuntimeException|\Exception When WPDB is not defined
     */
	public function get_count(string $hash = '', string $previous_hash = '', int $submitted = -1, int $seconds = 0): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();
		$prepare_stmt = '';

		if (!empty($hash) && !empty($previous_hash) && $submitted !== -1) {
			// Guard against a caller handing over an absolute timestamp instead of a duration.
			// IPValidator did exactly that until 2.15.5, which pushed the window floor back to
			// 1970 and made the retry counter span every row the cleaner had not yet removed.
			$window = (int) $seconds;

			if ($window < 0 || $window > self::MAX_WINDOW_SECONDS) {
				$this->get_logger()->warning('Retry window out of range - clamped', [
					'given'  => $seconds,
					'used'   => $window < 0 ? 0 : self::MAX_WINDOW_SECONDS,
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				$window = $window < 0 ? 0 : self::MAX_WINDOW_SECONDS;
			}

			$dt = new \DateTime();
			$dt->sub(new \DateInterval('PT' . $window . 'S'));
			$create_time = $dt->format('Y-m-d H:i:s');

			$prepare_stmt = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT count(*) AS entries FROM {$table} WHERE (hash=%s OR hash=%s) AND submitted=%d AND createtime > %s",
				$hash,
				$previous_hash,
				(int) $submitted,
				$create_time
			);
		} elseif (!empty($hash) && !empty($previous_hash) && $submitted === -1) {
			$prepare_stmt = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT count(*) AS entries FROM {$table} WHERE hash=%s OR hash=%s",
				$hash,
				$previous_hash
			);
		} else {
			$prepare_stmt = "SELECT count(*) AS entries FROM {$table}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results($prepare_stmt);

		if (is_array($results) && isset($results[0])) {
			return (int) ($results[0]->entries ?? 0);
		}

		return 0;
	}


	/**
     * Creates a table in the database.
     *
     * This method creates a table with the specified structure in the database using the WordPress dbDelta()
     * function.
     *
     * @return void
     */
	public function create_table(): void
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = sprintf(
			"CREATE TABLE %s (
            id int(11) NOT NULL auto_increment,
            hash varchar(255) NOT NULL,
            createtime varchar(255) DEFAULT '',
            submitted int(1) NOT NULL,
            PRIMARY KEY  (id),
            KEY hash (hash),
            KEY createtime (createtime),
            KEY hash_createtime (hash, createtime)
        )",
			$table
		);

		dbDelta($sql);
	}


	/**
	 * Empty the IP log table.
	 *
	 * Returns the row count rather than a success flag, for the same reason as
	 * IPBan::reset_table(): the cleaner passes this straight on as the number of deleted
	 * entries, and a bool arrived there as 1.
	 *
	 * @return int Number of deleted rows; 0 when the query failed.
	 */
	public function reset_table(): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query(sprintf("DELETE FROM %s", $table));

		return false === $result ? 0 : (int) $result;
	}


    /**
     * Delete the table from the database.
     *
     * @return void
     * @throws RuntimeException if WPDB is not defined
     *
     * @global wpdb $wpdb The WordPress database object.
     *
     */
	public function delete_table(): void
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(sprintf("DROP TABLE IF EXISTS %s", $table));

		wp_clear_scheduled_hook('weeklyIPClear');
	}


    /**
     * Deletes records older than the specified creation time.
     *
     * @param string $create_time The creation time to compare against.
     *
     * @return int The number of deleted records.
     * @throws RuntimeException if WPDB is not defined.
     */
	public function delete_older_than(string $create_time): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		$result = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare("DELETE FROM {$table} WHERE createtime < %s", $create_time)
		);

		return (int) $result;
	}


    /**
     * Retrieves the table name for storing CF7 IP data.
     *
     * The table name is generated by concatenating the WordPress database prefix with "f12_cf7_ip".
     *
     * @return string The table name.
     *
     * @throws RuntimeException If WPDB global variable is null.
     */
	public function get_table_name(): string
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		return $wpdb->prefix . 'f12_cf7_ip';
	}

    /**
     * Get the id of the object.
     *
     * @return int The id of the object as an integer.
     */
	public function get_id(): int
	{
		return $this->id;
	}


    /**
     * Returns the hash value associated with this object.
     *
     * @return string The hash value.
     */
	public function get_hash(): string
	{
		return $this->hash;
	}


    /**
     * Returns the create time associated with this object.
     *
     * The value is UTC, because WordPress fixes PHP's default timezone to UTC in wp-settings.php
     * and every reader of this column parses the string without a timezone. Writing it in the site
     * timezone instead — which this method did until 2.15.5 — put the stored timestamp ahead of
     * time() by the GMT offset, so IPValidator's interval check could never pass and every site
     * east of Greenwich refused legitimate submissions for the length of its offset.
     *
     * Sibling tables (IPBan, Salt, Captcha, CaptchaTimer) have always written UTC here.
     *
     * @return string The create time in UTC, in the format 'Y-m-d H:i:s'.
     */
	public function get_create_time(): string
	{
		if (empty($this->createtime)) {
			$this->createtime = ( new \DateTime() )->format('Y-m-d H:i:s');
		}

		return $this->createtime;
	}


    /**
     * Returns the submitted value associated with this object.
     *
     * @return int 0 or 1
     */
	public function get_submitted(): int
	{
		return (int) $this->submitted;
	}

    /**
     * Sets the create time for the object, in UTC. See get_create_time() for why not the site timezone.
     *
     * @return void
     */
	public function set_create_time(): void
	{
		$this->createtime = ( new \DateTime() )->format('Y-m-d H:i:s');
	}

    /**
     * Deletes a single record by its identifier.
     *
     * Used to drop an entry whose createtime cannot be trusted — a row from before 2.15.5 written
     * in the site timezone, or one written while the clock was off. Such a row would otherwise stay
     * the newest entry for the length of the offset and keep the rate limit in its fail-open branch.
     *
     * @param int $id The identifier of the record to delete.
     *
     * @return int The number of rows affected by the delete operation.
     */
	public function delete_by_id(int $id): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		if ($id <= 0) {
			return 0;
		}

		$result = $wpdb->delete($this->get_table_name(), ['id' => $id], ['%d']);

		return false === $result ? 0 : (int) $result;
	}


    /**
     * Deletes records from the specified table based on provided hash values and submitted flag.
     *
     * @param string $hash      The current hash value.
     * @param        $previous_hash
     * @param int    $submitted (Optional) The submitted flag. Default is 0.
     *
     * @return int The number of rows affected by the delete operation.
     */
	public function delete($hash, $previous_hash, $submitted = 0): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();

		$prepare_stmt = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$table_name} WHERE (hash=%s OR hash=%s) AND submitted=%d",
			$hash,
			$previous_hash,
			(int) $submitted
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query($prepare_stmt);

		return (int) $result;
	}


    /**
     * Saves the object to the database.
     *
     * @return int Returns the number of rows affected in the database.
     * @throws RuntimeException if WPDB is not defined or if a database error occurs.
     *
     */
	public function save(): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();

		if ($this->id !== 0) {
			return 0;
		}

		$data = [
			'hash'       => $this->get_hash(),
			'createtime' => $this->get_create_time(),
			'submitted'  => $this->submitted,
		];

		$result = $wpdb->insert($table_name, $data);

		if ($result === false) {
			$this->create_table();
			$result = $wpdb->insert($table_name, $data);

			if ($result === false) {
				$this->get_logger()->error('Insert failed after table recreation', [
					'table'           => $table_name,
					'wpdb_last_error' => $wpdb->last_error ?? null,
				]);
				throw new \RuntimeException('Database error occurred.');
			}
		}

		$this->id = (int) $wpdb->insert_id;

		return (int) $result;
	}


	/**
     * Returns the submission timestamp associated with this object.
     *
     * Never throws: an unreadable value yields 0, which reads as "long ago" to the caller and so
     * lets the submission through rather than refusing someone over a row we cannot parse.
     *
     * @return int The submission timestamp as a Unix timestamp, or 0 if it cannot be read.
     */
	public function get_submission_timestamp(): int
	{
		$create_time = $this->get_create_time();

		try {
			return (new \DateTime($create_time))->getTimestamp();
		} catch (\Exception $e) {
			// A row we cannot read must not cost a real enquiry. Returning 0 makes the caller's
			// difference enormous, which reads as "long ago" and lets the submission through.
			$this->get_logger()->warning('Unreadable createtime - submission let through', [
				'createtime' => $create_time,
				'error'      => $e->getMessage(),
				'class'      => __CLASS__,
				'method'     => __METHOD__,
			]);

			return 0;
		}
	}

}