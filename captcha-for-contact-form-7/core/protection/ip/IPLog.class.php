<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\LoggerInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

require_once('IPLogCleaner.class.php');

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

		foreach ($params as $key => $value) {
			if (isset($this->{$key})) {
				$oldValue = $this->{$key};
				$this->{$key} = $value;

				$this->get_logger()->debug('Parameter im Konstruktor gesetzt', [
					'property'  => $key,
					'old_value' => $oldValue,
					'new_value' => $value,
					'class'     => __CLASS__,
					'method'    => __METHOD__,
				]);
			}
		}

		$this->get_logger()->info('Instanz erstellt', [
			'params' => is_object($params) ? get_object_vars($params) : (array) $params,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
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
     * @return array An array of timestamps for matching records. If no records are found, an empty array is
     *               returned.
     * @throws RuntimeException|\Exception If global $wpdb is not defined.
     * @deprecated
     */
	public function get_timestamps(string $hash, string $previous_hash, int $seconds = 0): ?IPLog
	{
		$this->get_logger()->debug('Hole Timestamps anhand Hashes', [
			'hash'           => $hash,
			'previous_hash'  => $previous_hash,
			'seconds'        => $seconds,
			'class'          => __CLASS__,
			'method'         => __METHOD__,
		]);

		$entry = $this->get_last_entry_by_hash($hash, $previous_hash);

		if ($entry === null) {
			$this->get_logger()->info('Keine Einträge für Hashes gefunden', [
				'hash'          => $hash,
				'previous_hash' => $previous_hash,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);
		} else {
			$this->get_logger()->debug('Eintrag für Hashes gefunden', [
				'hash'          => $hash,
				'previous_hash' => $previous_hash,
				'entry_id'      => $entry->get_id() ?? null,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);
		}

		return $entry;
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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();
		$prepare_stmt = sprintf(
			'SELECT * FROM %s WHERE (hash="%s" OR hash="%s") ORDER BY createtime DESC LIMIT %d,1',
			$table,
			esc_sql($hash),
			esc_sql($previous_hash),
			$offset
		);

		$this->get_logger()->debug('Führe Query aus', [
			'query'          => $prepare_stmt,
			'hash'           => $hash,
			'previous_hash'  => $previous_hash,
			'offset'         => $offset,
			'class'          => __CLASS__,
			'method'         => __METHOD__,
		]);

		$results = $wpdb->get_results($prepare_stmt);

		if (!is_array($results) || !isset($results[0])) {
			$this->get_logger()->info('Kein Eintrag für Hashes gefunden', [
				'hash'          => $hash,
				'previous_hash' => $previous_hash,
				'offset'        => $offset,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);
			return null;
		}

		$this->get_logger()->debug('Eintrag für Hashes gefunden', [
			'hash'          => $hash,
			'previous_hash' => $previous_hash,
			'offset'        => $offset,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();
		$results = null;
		$prepare_stmt = '';

		if (!empty($hash) && !empty($previous_hash) && $submitted !== -1) {
			$dt = new \DateTime();
			$dt->sub(new \DateInterval('PT' . (int) $seconds . 'S'));
			$create_time = $dt->format('Y-m-d H:i:s');

			$prepare_stmt = sprintf(
				'SELECT count(*) AS entries FROM %s WHERE (hash="%s" OR hash="%s") AND submitted="%d" AND createtime > "%s"',
				$table,
				esc_sql($hash),
				esc_sql($previous_hash),
				(int) $submitted,
				esc_sql($create_time)
			);
		} elseif (!empty($hash) && !empty($previous_hash) && $submitted === -1) {
			$prepare_stmt = sprintf(
				'SELECT count(*) AS entries FROM %s WHERE hash="%s" OR hash="%s"',
				$table,
				esc_sql($hash),
				esc_sql($previous_hash)
			);
		} else {
			$prepare_stmt = sprintf('SELECT count(*) AS entries FROM %s', $table);
		}

		$this->get_logger()->debug('Führe Count-Query aus', [
			'query'          => $prepare_stmt,
			'hash'           => $hash,
			'previous_hash'  => $previous_hash,
			'submitted'      => $submitted,
			'seconds'        => $seconds,
			'class'          => __CLASS__,
			'method'         => __METHOD__,
		]);

		$results = $wpdb->get_results($prepare_stmt);

		if (is_array($results) && isset($results[0])) {
			$count = (int) ($results[0]->entries ?? 0);

			$this->get_logger()->info('Count-Query Ergebnis', [
				'count'         => $count,
				'hash'          => $hash,
				'previous_hash' => $previous_hash,
				'submitted'     => $submitted,
				'seconds'       => $seconds,
				'class'         => __CLASS__,
				'method'        => __METHOD__,
			]);

			return $count;
		}

		$this->get_logger()->warning('Count-Query lieferte kein Ergebnis', [
			'hash'          => $hash,
			'previous_hash' => $previous_hash,
			'submitted'     => $submitted,
			'seconds'       => $seconds,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
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
            PRIMARY KEY  (id)
        )",
			$table
		);

		$this->get_logger()->debug('Erstelle/aktualisiere Tabelle via dbDelta', [
			'table'  => $table,
			'sql'    => $sql,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$result = dbDelta($sql);

		$this->get_logger()->info('dbDelta ausgeführt', [
			'table'        => $table,
			'statements'   => is_array($result) ? array_values($result) : $result,
			'class'        => __CLASS__,
			'method'       => __METHOD__,
		]);
	}


	/**
     * Resets the table by deleting all rows.
     *
     * @return bool True if the table is successfully reset, false otherwise.
     * @throws RuntimeException If WPDB is not defined.
     * @global wpdb $wpdb The WordPress database object.
     */
	public function reset_table(): bool
	{
		global $wpdb;

		if (null === $wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		$this->get_logger()->warning('Starte Reset der Tabelle', [
			'table'  => $table,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$result = $wpdb->query(sprintf("DELETE FROM %s", $table));

		$this->get_logger()->info('Reset der Tabelle abgeschlossen', [
			'table'         => $table,
			'affected_rows' => $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return (bool) $result;
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
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		$this->get_logger()->warning('Lösche Tabelle, falls vorhanden', [
			'table'  => $table,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$wpdb->query(sprintf("DROP TABLE IF EXISTS %s", $table));

		$this->get_logger()->info('Tabelle gelöscht und Cron-Job entfernt', [
			'table' => $table,
			'hook'  => 'weeklyIPClear',
			'class' => __CLASS__,
			'method'=> __METHOD__,
		]);

		// clear cron
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
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $this->get_table_name();

		$this->get_logger()->warning('Lösche Einträge älter als angegebenes Datum', [
			'table'       => $table,
			'create_time' => $create_time,
			'class'       => __CLASS__,
			'method'      => __METHOD__,
		]);

		$result = $wpdb->query(
			sprintf('DELETE FROM %s WHERE createtime < "%s"', $table, esc_sql($create_time))
		);

		$this->get_logger()->info('Löschung älterer Einträge abgeschlossen', [
			'table'         => $table,
			'create_time'   => $create_time,
			'affected_rows' => $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

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
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table = $wpdb->prefix . 'f12_cf7_ip';

		$this->get_logger()->debug('Tabellenname ermittelt', [
			'table'  => $table,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return $table;
	}

    /**
     * Get the id of the object.
     *
     * @return int The id of the object as an integer.
     */
	public function get_id(): int
	{
		$this->get_logger()->debug('ID abgefragt', [
			'id'     => $this->id,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return $this->id;
	}


    /**
     * Sets the ID value.
     *
     * @param int $id The ID value to be set.
     *
     * @return void
     */
	private function set_id(int $id): void
	{
		$oldId = $this->id ?? null;
		$this->id = $id;

		$this->get_logger()->info('ID gesetzt', [
			'old_id' => $oldId,
			'new_id' => $id,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);
	}


    /**
     * Returns the hash value associated with this object.
     *
     * @return string The hash value.
     */
	public function get_hash(): string
	{
		$this->get_logger()->debug('Hash abgefragt', [
			'hash'   => $this->hash,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		return $this->hash;
	}


    /**
     * Returns the create time associated with this object.
     *
     * If the create time is empty, a new DateTime object is created and the current WordPress timezone is set.
     * The create time is then formatted as 'Y-m-d H:i:s' and stored in the internal variable $this->createtime.
     *
     * @return string The create time in the format 'Y-m-d H:i:s'.
     */
	public function get_create_time(): string
	{
		if (empty($this->createtime)) {
			$dt = new \DateTime();
			$dt->setTimezone(wp_timezone());
			$this->createtime = $dt->format('Y-m-d H:i:s');

			$this->get_logger()->warning('Create-Time war leer, neuer Wert gesetzt', [
				'new_value' => $this->createtime,
				'class'     => __CLASS__,
				'method'    => __METHOD__,
			]);
		} else {
			$this->get_logger()->debug('Create-Time abgefragt', [
				'value'  => $this->createtime,
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
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
		$this->get_logger()->debug('Submitted abgefragt', [
			'submitted' => $this->submitted,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);

		return (int) $this->submitted;
	}

    /**
     * Sets the create time for the object.
     *
     * @return void
     */
	public function set_create_time(): void
	{
		$oldValue = $this->createtime ?? null;

		$dt = new \DateTime();
		$dt->setTimezone(wp_timezone());
		$this->createtime = $dt->format('Y-m-d H:i:s');

		$this->get_logger()->info('Create-Time gesetzt', [
			'old_value' => $oldValue,
			'new_value' => $this->createtime,
			'class'     => __CLASS__,
			'method'    => __METHOD__,
		]);
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
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();

		$prepare_stmt = sprintf(
			'DELETE FROM %s WHERE (hash="%s" OR hash="%s") AND submitted="%d"',
			$table_name,
			esc_sql($hash),
			esc_sql($previous_hash),
			(int) $submitted
		);

		$this->get_logger()->warning('Lösche Einträge anhand Hashes', [
			'table'         => $table_name,
			'hash'          => $hash,
			'previous_hash' => $previous_hash,
			'submitted'     => $submitted,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		$result = $wpdb->query($prepare_stmt);

		$this->get_logger()->info('Löschung abgeschlossen', [
			'table'         => $table_name,
			'affected_rows' => $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

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

		if (!$wpdb) {
			$this->get_logger()->error('WPDB not defined', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			throw new \RuntimeException('WPDB not defined');
		}

		$table_name = $this->get_table_name();

		if ($this->id !== 0) {
			$this->get_logger()->warning('Speichern übersprungen: ID bereits gesetzt', [
				'id'     => $this->id,
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
			return 0;
		}

		$data = [
			'hash'       => $this->get_hash(),
			'createtime' => $this->get_create_time(),
			'submitted'  => $this->submitted,
		];

		$this->get_logger()->debug('Versuche Insert in Tabelle', [
			'table'  => $table_name,
			'data'   => $data,
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$result = $wpdb->insert($table_name, $data);

		if ($result === false) {
			$this->get_logger()->error('Insert fehlgeschlagen', [
				'table'           => $table_name,
				'data'            => $data,
				'wpdb_last_error' => $wpdb->last_error ?? null,
				'class'           => __CLASS__,
				'method'          => __METHOD__,
			]);
			throw new \RuntimeException('Database error occurred. Reactivate the plugin to create missing tables.');
		}

		$this->id = (int) $wpdb->insert_id;

		$this->get_logger()->info('Insert erfolgreich', [
			'table'         => $table_name,
			'insert_id'     => $this->id,
			'affected_rows' => (int) $result,
			'class'         => __CLASS__,
			'method'        => __METHOD__,
		]);

		return (int) $result;
	}


	/**
     * Returns the submission timestamp associated with this object.
     *
     * @return int The submission timestamp as a Unix timestamp.
     * @throws \Exception
     */
	public function get_submission_timestamp(): int
	{
		$dt = new \DateTime($this->get_create_time());
		$timestamp = $dt->getTimestamp();

		$this->get_logger()->debug('Submission-Timestamp abgefragt', [
			'create_time' => $this->get_create_time(),
			'timestamp'   => $timestamp,
			'class'       => __CLASS__,
			'method'      => __METHOD__,
		]);

		return $timestamp;
	}

}