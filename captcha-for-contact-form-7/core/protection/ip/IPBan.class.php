<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\LoggerInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IPBan
 *
 * @package forge12\contactform7
 */
class IPBan
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
     * The datetime until the user is blocked for submitting data
     *
     * @var string e.g.: 2024-05-27 22:13:00
     */
    private $blockedtime = '';

	/**
	 * Create a new Captcha Object
	 *
	 * @param LoggerInterface $logger
	 * @param array           $params
	 */
	public function __construct(LoggerInterface $logger, $params = array())
	{
		$this->logger = $logger;
		$this->set_params($params);
	}

	private function get_logger(): LoggerInterface{
		return $this->logger;
	}

    /**
     * Sets the params of the object.
     *
     * Only allows known database columns to be set.
     *
     * @param array $params The params to set.
     *
     * @return void
     */
	public function set_params(array $params): void
	{
		$allowed = ['id', 'hash', 'createtime', 'blockedtime'];

		foreach ($params as $key => $value) {
			if (in_array($key, $allowed, true)) {
				$this->{$key} = $value;
			}
		}
	}


    /**
     * Get the count of entries from the database table.
     *
     * @param string $hash          (optional) The hash value to filter the entries.
     * @param string $previous_hash (optional) The previous hash value to filter the entries.
     *
     * @return int The count of entries.
     * @throws \RuntimeException If WPDB is not defined.
     */
	public function get_count(string $hash = '', string $previous_hash = ''): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();

		if (!empty($hash) && !empty($previous_hash)) {
			$dt = new \DateTime();
			$block_time = $dt->format('Y-m-d H:i:s');

			$prepare_stmt = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT count(*) AS entries FROM {$table_name} WHERE (hash=%s OR hash=%s) AND blockedtime > %s",
				$hash,
				$previous_hash,
				$block_time
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results($prepare_stmt);
		} else {
			$prepare_stmt = "SELECT count(*) AS entries FROM {$table_name}";

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results($prepare_stmt);
		}

		if (is_array($results) && isset($results[0])) {
			return (int) $results[0]->entries;
		}

		return 0;
	}


    /**
     * Creates a table in the database with the provided table name.
     *
     * @return void
     */
	public function create_table(): void
	{
		$table_name = $this->get_table_name();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$sql = sprintf(
			"CREATE TABLE %s (
            id int(11) NOT NULL auto_increment,
            hash varchar(255) NOT NULL,
            createtime varchar(255) DEFAULT '',
            blockedtime varchar(255) DEFAULT '',
            PRIMARY KEY  (id),
            KEY hash (hash),
            KEY blockedtime (blockedtime),
            KEY hash_blockedtime (hash, blockedtime)
        )",
			$table_name
		);

		dbDelta($sql);
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

		if (null === $wpdb) {
			return false;
		}

		$wp_table_name = $this->get_table_name();

		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->query(sprintf('DELETE FROM %s', $wp_table_name));

			return $rows !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}



    /**
     * Deletes the table associated with the current object.
     *
     * Executes a SQL query to drop the table from the database if it exists.
     * Also, clears the scheduled cron job for 'weeklyIPClear'.
     *
     * @return void
     * @global wpdb $wpdb WordPress database object.
     */
	public function delete_table(): void
	{
		global $wpdb;

		if (null === $wpdb) {
			return;
		}

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(sprintf("DROP TABLE IF EXISTS %s", $table_name));

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
		$sql   = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$table} WHERE blockedtime < %s",
			$create_time
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->query($sql);

		if ($rows === false) {
			return 0;
		}

		return (int) $rows;
	}


    /**
     * Retrieves the table name for storing banned IP addresses.
     *
     * @return string The table name prefixed with the WordPress database prefix.
     * @throws \RuntimeException If WPDB is not found.
     */
	public function get_table_name(): string
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not found');
		}

		return $wpdb->prefix . 'f12_cf7_ip_ban';
	}

    /**
     * Retrieves the id of the current object.
     *
     * @return int The id of the object.
     */
	public function get_id(): int
	{
		return (int) $this->id;
	}

    /**
     * Set the ID of the object.
     *
     * @param int $id The ID to set.
     *
     * @return void
     */
	private function set_id(int $id): void
	{
		$this->id = $id;
	}

    /**
     * Retrieves the hash value.
     *
     * @return string The hash value.
     */
    public function get_hash(): string
    {
		return $this->hash;
    }

    /**
     * Retrieves the blocked time value.
     *
     * This method returns the blocked time value. If the blocked time value is empty, it sets the blocked time value
     * to the current datetime in 'Y-m-d H:i:s' format.
     *
     * @return string The blocked time value in 'Y-m-d H:i:s' format.
     * @throws \Exception
     */
	public function get_blocked_time(): string
	{
		if (empty($this->blockedtime)) {
			$this->set_blocked_time(3600);
		}

		return $this->blockedtime;
	}

    /**
     * Sets the blocked time.
     *
     * @param int $seconds The number of seconds to block.
     *
     * @return void
     * @throws \Exception
     */
	public function set_blocked_time(int $seconds): void
	{
		$dt = new \DateTime('+' . $seconds . ' seconds');
		$this->blockedtime = $dt->format('Y-m-d H:i:s');
	}

    /**
     * Retrieves the create time value.
     *
     * If the create time value is empty, a new DateTime object is created and the current date and time
     * is formatted according to the 'Y-m-d H:i:s' format and stored in the $createtime property.
     * The $createtime property is then returned.
     *
     * @return string The create time value in 'Y-m-d H:i:s' format.
     */
	public function get_create_time(): string
	{
		if (empty($this->createtime)) {
			$dt = new \DateTime();
			$this->createtime = $dt->format('Y-m-d H:i:s');
		}

		return $this->createtime;
	}

    /**
     * Sets the create time.
     *
     * @return void
     */
	public function set_create_time(): void
	{
		$dt = new \DateTime();
		$this->createtime = $dt->format('Y-m-d H:i:s');
	}


    /**
     * Saves the current instance to the database.
     *
     * @return int The result of the save operation. Returns 0 if the instance already has an identifier (id) set.
     *             Returns 1 if the save operation was successful.
     *
     * @throws \RuntimeException If WPDB is not defined.
     */
	public function save(): int
	{
		global $wpdb;

		if (null === $wpdb) {
			throw new \RuntimeException('WPDB not defined');
		}

		if ($this->id !== 0) {
			return 0;
		}

		$table = $this->get_table_name();
		$data = [
			'hash'        => $this->get_hash(),
			'createtime'  => $this->get_create_time(),
			'blockedtime' => $this->get_blocked_time(),
		];

		$result = $wpdb->insert($table, $data);

		if ($result === false) {
			$this->create_table();
			$result = $wpdb->insert($table, $data);

			if ($result === false) {
				$this->get_logger()->error('Insert failed after table recreation', [
					'table'           => $table,
					'wpdb_last_error' => $wpdb->last_error ?? null,
				]);
				throw new \RuntimeException('Database error occurred.');
			}
		}

		$this->id = (int) $wpdb->insert_id;

		return (int) $result;
	}
}