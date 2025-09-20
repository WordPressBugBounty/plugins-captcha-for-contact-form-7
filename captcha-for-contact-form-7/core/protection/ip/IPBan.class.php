<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\LoggerInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

require_once('IPBanCleaner.class.php');

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
     * @var string, e.g.: 2024-05-27 22:13:00
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

		$this->logger->info(
			"__construct(): Neue Instanz erstellt",
			[
				'plugin'  => 'f12-cf7-captcha',
				'class'   => __CLASS__,
				'params'  => !empty($params) ? array_keys($params) : []
			]
		);
	}

	private function get_logger(): LoggerInterface{
		return $this->logger;
	}

    /**
     * Sets the params of the object.
     *
     * @param array $params The params to set.
     *
     * @return void
     */
	public function set_params(array $params): void
	{
		$applied = [];
		$ignored = [];

		foreach ($params as $key => $value) {
			if (property_exists($this, $key)) {
				$this->{$key} = $value;
				$applied[$key] = is_scalar($value) ? $value : gettype($value);
			} else {
				$ignored[] = $key;
			}
		}

		$this->get_logger()->debug(
			"set_params(): Parameter verarbeitet",
			[
				'plugin'  => 'f12-cf7-captcha',
				'class'   => __CLASS__,
				'applied' => $applied,
				'ignored' => $ignored
			]
		);
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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined in get_count.');
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();
		$this->get_logger()->debug('Using table name in get_count.', ['table' => $table_name]);

		if (!empty($hash) && !empty($previous_hash)) {
			$dt = new \DateTime();
			$block_time = $dt->format('Y-m-d H:i:s');

			$prepare_stmt = sprintf(
				'SELECT count(*) AS entries FROM %s WHERE (hash="%s" OR hash="%s") AND blockedtime > "%s"',
				$table_name,
				esc_sql($hash),
				esc_sql($previous_hash),
				esc_sql($block_time)
			);

			$this->get_logger()->debug('Executing get_count query with hash and previous_hash.', [
				'hash'          => $hash,
				'previous_hash' => $previous_hash,
				'block_time'    => $block_time,
				'query'         => $prepare_stmt,
			]);

			$results = $wpdb->get_results($prepare_stmt);
		} else {
			$prepare_stmt = sprintf('SELECT count(*) AS entries FROM %s', $table_name);

			$this->get_logger()->debug('Executing get_count query without hashes.', [
				'query' => $prepare_stmt,
			]);

			$results = $wpdb->get_results($prepare_stmt);
		}

		if (is_array($results) && isset($results[0])) {
			$count = (int) $results[0]->entries;
			$this->get_logger()->info('get_count query returned results.', ['count' => $count]);
			return $count;
		}

		$this->get_logger()->warning('get_count query returned no results.', ['query' => $prepare_stmt]);
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
		$this->get_logger()->debug('Preparing to create table.', ['table' => $table_name]);

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$sql = sprintf(
			"CREATE TABLE %s (
            id int(11) NOT NULL auto_increment, 
            hash varchar(255) NOT NULL,
            createtime varchar(255) DEFAULT '',
            blockedtime varchar(255) DEFAULT '',
            PRIMARY KEY  (id)
        )",
			$table_name
		);

		$this->get_logger()->debug('Executing CREATE TABLE statement.', ['sql' => $sql]);

		try {
			dbDelta($sql);
			$this->get_logger()->info('Table created or updated successfully.', ['table' => $table_name]);
		} catch (\Throwable $e) {
			$this->get_logger()->error('Error creating table.', [
				'table'   => $table_name,
				'message' => $e->getMessage(),
			]);
			throw $e;
		}
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
			$this->get_logger()->error('reset_table failed: $wpdb is not defined.');
			return false;
		}

		$wp_table_name = $this->get_table_name();
		$this->get_logger()->debug('Resetting table.', ['table' => $wp_table_name]);

		try {
			$rows = $wpdb->query(sprintf('DELETE FROM %s', $wp_table_name));

			if ($rows === false) {
				$this->get_logger()->error('reset_table query failed.', ['table' => $wp_table_name]);
				return false;
			}

			$this->get_logger()->info('reset_table executed successfully.', [
				'table' => $wp_table_name,
				'rows'  => $rows,
			]);

			return true;
		} catch (\Throwable $e) {
			$this->get_logger()->error('Exception in reset_table.', [
				'table'   => $wp_table_name,
				'message' => $e->getMessage(),
			]);
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

		if (!$wpdb) {
			$this->get_logger()->error('delete_table failed: $wpdb is not defined.');
			return;
		}

		$table_name = $this->get_table_name();
		$prepare_stmt = sprintf("DROP TABLE IF EXISTS %s", $table_name);

		$this->get_logger()->debug('Executing DROP TABLE statement.', [
			'table' => $table_name,
			'sql'   => $prepare_stmt,
		]);

		try {
			$wpdb->query($prepare_stmt);
			$this->get_logger()->info('Table deleted successfully (or did not exist).', ['table' => $table_name]);
		} catch (\Throwable $e) {
			$this->get_logger()->error('Error deleting table.', [
				'table'   => $table_name,
				'message' => $e->getMessage(),
			]);
			throw $e;
		}

		// Cron-Hook löschen
		wp_clear_scheduled_hook('weeklyIPClear');
		$this->get_logger()->info('Cleared scheduled hook.', ['hook' => 'weeklyIPClear']);
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
			$this->get_logger()->error('delete_older_than failed: $wpdb is not defined.');
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();
		$sql   = sprintf(
			'DELETE FROM %s WHERE blockedtime < "%s"',
			$table,
			esc_sql($create_time)
		);

		$this->get_logger()->debug('Executing delete_older_than query.', [
			'table'       => $table,
			'create_time' => $create_time,
			'sql'         => $sql,
		]);

		try {
			$rows = $wpdb->query($sql);

			if ($rows === false) {
				$this->get_logger()->error('delete_older_than query failed.', [
					'table'       => $table,
					'create_time' => $create_time,
				]);
				return 0;
			}

			$this->get_logger()->info('delete_older_than executed successfully.', [
				'table'       => $table,
				'create_time' => $create_time,
				'rows'        => $rows,
			]);

			return (int) $rows;
		} catch (\Throwable $e) {
			$this->get_logger()->error('Exception in delete_older_than.', [
				'table'       => $table,
				'create_time' => $create_time,
				'message'     => $e->getMessage(),
			]);
			throw $e;
		}
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
			$this->get_logger()->error('get_table_name failed: $wpdb is not defined.');
			throw new \RuntimeException('WPDB not found');
		}

		$table = $wpdb->prefix . 'f12_cf7_ip_ban';
		$this->get_logger()->debug('Resolved table name.', ['table' => $table]);

		return $table;
	}

    /**
     * Retrieves the id of the current object.
     *
     * @return int The id of the object.
     */
	public function get_id(): int
	{
		$this->get_logger()->debug('get_id called.', ['id' => $this->id]);
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
		$oldId = $this->id ?? null;
		$this->id = $id;

		$this->get_logger()->info('ID wurde gesetzt', [
			'old_id' => $oldId,
			'new_id' => $id,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}

    /**
     * Retrieves the hash value.
     *
     * @return string The hash value.
     */
    public function get_hash(): string
    {
		$hash = $this->hash;
	    $this->get_logger()->debug('Hash wurde abgefragt', [
		    'hash'   => $hash,
		    'class'  => __CLASS__,
		    'method' => __METHOD__,
	    ]);
		return $hash;
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
			$this->get_logger()->warning('Blocked-Time war leer, Standardwert wird gesetzt', [
				'default' => 3600,
				'class'   => __CLASS__,
				'method'  => __METHOD__,
			]);

			$this->set_blocked_time(3600);
		} else {
			$this->get_logger()->debug('Blocked-Time wurde abgefragt', [
				'blocked_time' => $this->blockedtime,
				'class'        => __CLASS__,
				'method'       => __METHOD__,
			]);
		}

		return $this->blockedtime;
	}

    /**
     * Sets the blocked time.
     *
     * @param string $seconds The number of seconds to block.
     *
     * @return void
     * @throws \Exception
     */
	public function set_blocked_time(string $seconds): void
	{
		$dt = new \DateTime('+' . $seconds . ' seconds');
		$oldValue = $this->blockedtime ?? null;
		$this->blockedtime = $dt->format('Y-m-d H:i:s');

		$this->get_logger()->info('Blocked-Time wurde gesetzt', [
			'old_value' => $oldValue,
			'new_value' => $this->blockedtime,
			'seconds'   => $seconds,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);
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

			$this->get_logger()->warning('Create-Time war leer, neuer Wert wurde gesetzt', [
				'new_value' => $this->createtime,
				'class'     => __CLASS__,
				'method'    => __METHOD__,
			]);
		} else {
			$this->get_logger()->debug('Create-Time wurde abgefragt', [
				'value'  => $this->createtime,
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
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
		$oldValue = $this->createtime ?? null;
		$dt = new \DateTime();
		$this->createtime = $dt->format('Y-m-d H:i:s');

		$this->get_logger()->info('Create-Time wurde gesetzt', [
			'old_value' => $oldValue,
			'new_value' => $this->createtime,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);
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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		if ($this->id !== 0) {
			$this->get_logger()->warning('Speichern übersprungen: ID bereits gesetzt', [
				'id'     => $this->id,
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			return 0;
		}

		$table = $this->get_table_name();
		$data = [
			'hash'        => $this->get_hash(),
			'createtime'  => $this->get_create_time(),
			'blockedtime' => $this->get_blocked_time(),
		];

		$this->get_logger()->debug('Versuche Insert in Tabelle', [
			'table'  => $table,
			'data'   => $data,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$result = $wpdb->insert($table, $data);

		if ($result === false) {
			$this->get_logger()->error('Insert fehlgeschlagen', [
				'table'           => $table,
				'data'            => $data,
				'wpdb_last_error' => $wpdb->last_error ?? null,
				'class'           => __CLASS__,
				'method'          => __METHOD__,
			]);

			throw new \RuntimeException('Database error occurred. Reactivate the plugin to create missing tables.');
		}

		$this->id = (int) $wpdb->insert_id;

		$this->get_logger()->info('Insert erfolgreich', [
			'table'         => $table,
			'insert_id'     => $this->id,
			'affected_rows' => (int) $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return (int) $result;
	}
}