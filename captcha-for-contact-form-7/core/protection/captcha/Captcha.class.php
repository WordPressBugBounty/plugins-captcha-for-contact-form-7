<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\core\UserData;
use f12_cf7_captcha\core\wpdb;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;
use IPAddress;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Captcha
 * Model
 *
 * @package forge12\contactform7
 */
class Captcha
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
     * The code validated against
     *
     * @var string
     */
    private $code = '';
    /**
     * Flag if the code has been validated already
     *
     * @var int
     */
    private $validated = 0;
    /**
     * The datetime whenever the captcha code has been created
     *
     * @var string
     */
    private $createtime = '';
    /**
     * The datetime whenever the captcha code has been updated
     *
     * @var string
     */
    private $updatetime = '';

    private $ip_address = '';

	/**
	 * Create a new Captcha Object
	 *
	 * @param LoggerInterface $logger
	 * @param string          $ip_address
	 * @param array           $params
	 */
	public function __construct(LoggerInterface $logger, string $ip_address, $params = array())
	{
		$this->logger     = $logger;
		$this->ip_address = $ip_address;

		$this->set_params($params);

		$this->logger->debug("Neue Instanz erstellt", [
			'plugin'    => 'f12-cf7-captcha',
			'ip'        => $ip_address,
			'paramKeys' => is_array($params) ? implode(',', array_keys($params)) : 'none'
		]);
	}

	private function get_logger(): LoggerInterface{
		return $this->logger;
	}

    /**
     * Sets the parameters of the object.
     *
     * @param array $params An associative array where the keys represent the parameter names and the values
     *                      represent the new values for the corresponding parameters.
     *
     * @return void
     */
	private function set_params(array $params): void
	{
		foreach ($params as $key => $value) {
			if (isset($this->{$key})) {
				$this->{$key} = $value;
				$this->logger->debug("Parameter gesetzt", [
					'plugin' => 'f12-cf7-captcha',
					'key'    => $key,
					'value'  => is_scalar($value) ? (string)$value : gettype($value)
				]);
			} else {
				$this->logger->debug("Unbekannter Parameter ignoriert", [
					'plugin' => 'f12-cf7-captcha',
					'key'    => $key
				]);
			}
		}
	}


    /**
     * Retrieves the count of entries from the specified table.
     *
     * @param int $validated (optional) Optional argument to filter the count based on validation status.
     *                       If -1 (default), it returns the count of all entries.
     *                       If 0, it returns the count of entries with validation status as 0.
     *                       If 1, it returns the count of entries with validation status as 1.
     *
     * @return int The count of entries from the specified table. Returns 0 if the count cannot be retrieved or if
     *             the result is empty.
     */
	public function get_count(int $validated = -1): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->logger->error("WPDB nicht verfügbar", [
				'plugin' => 'f12-cf7-captcha'
			]);
			return 0;
		}

		$wp_table_name = $this->get_table_name();

		if ($validated == -1) {
			$sql = 'SELECT count(*) AS entries FROM ' . $wp_table_name;
		} else if ($validated == 0) {
			$sql = 'SELECT count(*) AS entries FROM ' . $wp_table_name . ' WHERE validated=0';
		} else {
			$sql = 'SELECT count(*) AS entries FROM ' . $wp_table_name . ' WHERE validated=1';
		}

		$results = $wpdb->get_results($sql);

		$count = 0;
		if (is_array($results) && isset($results[0])) {
			$count = (int) $results[0]->entries;
		}

		// Logging
		$this->logger->debug("Logeinträge gezählt", [
			'plugin'   => 'f12-cf7-captcha',
			'validated'=> $validated,
			'sql'      => $sql,
			'count'    => $count
		]);

		return $count;
	}


    /**
     * Create the database which saves the captcha codes
     * for the validation to be wordpress conform
     *
     * @return void
     * @deprecated
     */
	public static function createTable()
	{
		$logger = \Forge12\Shared\Logger::getInstance();

		$logger->info("Starte Tabellenerstellung", [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__
		]);

		try {
			$Captcha = new Captcha($logger, '');
			$Captcha->create_table();

			$logger->info("Tabellenerstellung erfolgreich", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]);
		} catch (\Throwable $e) {
			$logger->error("Tabellenerstellung fehlgeschlagen", [
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'errorMsg' => $e->getMessage()
			]);
		}
	}


    /**
     * Creates a new table in the WordPress database using the specified table name and schema.
     *
     * @return void
     */
	public function create_table(): void
	{
		$wp_table_name = $this->get_table_name();

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$sql = "CREATE TABLE " . $wp_table_name . " (
                id int(11) NOT NULL auto_increment, 
                hash varchar(255) NOT NULL, 
                code varchar(255) NOT NULL, 
                validated int(1) DEFAULT 0,
                createtime varchar(255) DEFAULT '', 
                updatetime varchar(255) DEFAULT '',
                PRIMARY KEY  (id)
            )";

		$this->get_logger()->info("Tabellenerstellung gestartet", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $wp_table_name,
			'sql'    => $sql
		]);

		try {
			dbDelta($sql);
			$this->get_logger()->info("Tabellenerstellung abgeschlossen", [
				'plugin' => 'f12-cf7-captcha',
				'table'  => $wp_table_name
			]);
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler bei Tabellenerstellung", [
				'plugin'   => 'f12-cf7-captcha',
				'table'    => $wp_table_name,
				'errorMsg' => $e->getMessage()
			]);
		}
	}


    /**
     * @return void
     * @deprecated
     */
	public static function deleteTable()
	{
		$logger = \Forge12\Shared\Logger::getInstance();

		$logger->warning("Starte Tabellenlöschung", [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__
		]);

		try {
			$Captcha = new Captcha($logger, '');
			$Captcha->delete_table();

			$logger->info("Tabellenlöschung erfolgreich", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]);
		} catch (\Throwable $e) {
			$logger->error("Tabellenlöschung fehlgeschlagen", [
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'errorMsg' => $e->getMessage()
			]);
		}
	}


    /**
     * Deletes the table associated with the current object from the WordPress database.
     *
     * @return void
     * @global wpdb $wpdb The WordPress database object.
     *
     */
	public function delete_table(): void
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error("Tabellenlöschung fehlgeschlagen: WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table_name = $this->get_table_name();

		$this->get_logger()->warning("Starte Tabellenlöschung", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name
		]);

		try {
			$wpdb->query(sprintf("DROP TABLE IF EXISTS %s", $table_name));

			$this->get_logger()->info("Tabelle gelöscht (falls vorhanden)", [
				'plugin' => 'f12-cf7-captcha',
				'table'  => $table_name
			]);

			// Clear Cronjobs
			wp_clear_scheduled_hook('dailyCaptchaClear');
			$this->get_logger()->debug("Cronjob 'dailyCaptchaClear' entfernt", [
				'plugin' => 'f12-cf7-captcha'
			]);

		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler bei Tabellenlöschung", [
				'plugin'   => 'f12-cf7-captcha',
				'table'    => $table_name,
				'errorMsg' => $e->getMessage()
			]);
			throw $e; // Fehler weiterreichen, damit er nicht verschluckt wird
		}
	}


    /**
     * Resets the table by deleting all records.
     *
     * @return int The number of rows deleted from the table.
     *
     * @throws RuntimeException If $wpdb is not defined.
     */
	public function reset_table(): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error("Tabellen-Reset fehlgeschlagen: WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table_name = $this->get_table_name();

		$this->get_logger()->warning("Starte Tabellen-Reset", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name
		]);

		try {
			$deleted = (int) $wpdb->query(sprintf("DELETE FROM %s", $table_name));

			$this->get_logger()->info("Tabellen-Reset abgeschlossen", [
				'plugin'  => 'f12-cf7-captcha',
				'table'   => $table_name,
				'deleted' => $deleted
			]);

			return $deleted;
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler beim Tabellen-Reset", [
				'plugin'   => 'f12-cf7-captcha',
				'table'    => $table_name,
				'errorMsg' => $e->getMessage()
			]);
			throw $e;
		}
	}


    /**
     * Deletes rows from the database table where the 'validated' column matches the given value.
     *
     * @param int $validated (Optional) The value to match against the 'validated' column. Defaults to 1.
     *
     * @return int The number of rows affected by the deletion operation.
     *
     * @throws RuntimeException When global $wpdb is not defined.
     */
	public function delete_by_validate_status(int $validated = 1): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error("Löschung nach Validierungsstatus fehlgeschlagen: WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha',
				'status' => $validated
			]);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table_name = $this->get_table_name();

		$this->get_logger()->warning("Starte Löschung nach Validierungsstatus", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name,
			'status' => $validated
		]);

		try {
			$deleted = (int) $wpdb->query(
				sprintf('DELETE FROM %s WHERE validated="%d"', $table_name, $validated)
			);

			$this->get_logger()->info("Löschung nach Validierungsstatus abgeschlossen", [
				'plugin'  => 'f12-cf7-captcha',
				'table'   => $table_name,
				'status'  => $validated,
				'deleted' => $deleted
			]);

			return $deleted;
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler bei Löschung nach Validierungsstatus", [
				'plugin'   => 'f12-cf7-captcha',
				'table'    => $table_name,
				'status'   => $validated,
				'errorMsg' => $e->getMessage()
			]);
			throw $e;
		}
	}


    /**
     * Deletes records from the database that are older than the specified creation time.
     *
     * @param string $create_time The creation time to compare against.
     *
     * @return int The number of records deleted.
     *
     * @throws RuntimeException If the WPDB global variable is not defined.
     */
	public function delete_older_than(string $create_time): int
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error("Löschung älterer Einträge fehlgeschlagen: WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha',
				'before' => $create_time
			]);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table_name = $this->get_table_name();

		$this->get_logger()->warning("Starte Löschung älterer Einträge", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name,
			'before' => $create_time
		]);

		try {
			$deleted = (int) $wpdb->query(
				sprintf('DELETE FROM %s WHERE createtime < "%s"', $table_name, esc_sql($create_time))
			);

			$this->get_logger()->info("Löschung älterer Einträge abgeschlossen", [
				'plugin'  => 'f12-cf7-captcha',
				'table'   => $table_name,
				'before'  => $create_time,
				'deleted' => $deleted
			]);

			return $deleted;
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler bei Löschung älterer Einträge", [
				'plugin'   => 'f12-cf7-captcha',
				'table'    => $table_name,
				'before'   => $create_time,
				'errorMsg' => $e->getMessage()
			]);
			throw $e;
		}
	}


    /**
     * Retrieves the table name for storing contact form 7 captcha data.
     *
     * @return string The full table name including the WordPress database prefix.
     */
	public function get_table_name(): string
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error("Tabellenname konnte nicht ermittelt werden: WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table_name = $wpdb->prefix . 'f12_cf7_captcha';

		$this->get_logger()->debug("Tabellenname ermittelt", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name
		]);

		return $table_name;
	}

    /**
     * Retrieves the ID of the object.
     *
     * @return int The ID of the object.
     */
	public function get_id(): int
	{
		$id = $this->id;

		if ($id === 0) {
			$this->get_logger()->warning(
				"get_id(): ID ist 0 oder nicht gesetzt",
				['plugin' => 'f12-cf7-captcha']
			);
		} else {
			$this->get_logger()->debug(
				"get_id(): ID erfolgreich ermittelt",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $id
				]
			);
		}

		return $id;
	}

    /**
     * Set the ID for the object.
     *
     * @param int $id The ID to set.
     *
     * @return void
     */
	private function set_id(int $id): void
	{
		$this->id = $id;

		if ($id === 0) {
			$this->get_logger()->warning(
				"set_id(): ID wurde auf 0 gesetzt",
				['plugin' => 'f12-cf7-captcha']
			);
		} else {
			$this->get_logger()->info(
				"set_id(): ID erfolgreich gesetzt",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $id
				]
			);
		}
	}


    /**
     * Returns the hash value of the current object.
     *
     * If the hash value is not already set, it will be generated using the `generate_hash()` method.
     *
     * @return string The hash value.
     */
	public function get_hash(): string
	{
		if (empty($this->hash)) {
			$this->get_logger()->info(
				"get_hash(): Neuer Hash wird generiert",
				[
					'plugin'     => 'f12-cf7-captcha',
					'ip_address' => $this->ip_address
				]
			);

			$this->hash = $this->generate_hash($this->ip_address);

			$this->get_logger()->debug(
				"get_hash(): Hash erfolgreich generiert",
				[
					'plugin' => 'f12-cf7-captcha',
					'hash'   => $this->hash
				]
			);
		} else {
			$this->get_logger()->debug(
				"get_hash(): Vorhandener Hash zurückgegeben",
				[
					'plugin' => 'f12-cf7-captcha',
					'hash'   => $this->hash
				]
			);
		}

		return $this->hash;
	}


    /**
     * Generates a hash using the current timestamp and the user's IP address.
     *
     * @return string The generated hash string.
     */
	private function generate_hash(string $ip_address): string
	{
		if (empty($ip_address)) {
			$this->get_logger()->warning(
				"generate_hash(): IP-Adresse ist leer – kein Hash generiert",
				['plugin' => 'f12-cf7-captcha']
			);
			return '';
		}

		$hash = \password_hash(time() . $ip_address, PASSWORD_DEFAULT);

		$this->get_logger()->info(
			"generate_hash(): Neuer Hash generiert",
			[
				'plugin'     => 'f12-cf7-captcha',
				'ip_address' => $ip_address,
				// ⚠️ besser NICHT den Hash loggen → Sicherheitsrisiko / DSGVO
			]
		);

		return $hash;
	}


    /**
     * Checks if the hash value is valid.
     *
     * @return bool Returns true if the hash value is not empty, otherwise returns false.
     */
	private function is_valid_hash(): bool
	{
		$valid = !empty($this->hash);

		if ($valid) {
			$this->get_logger()->debug(
				"is_valid_hash(): Hash ist gültig",
				['plugin' => 'f12-cf7-captcha']
			);
		} else {
			$this->get_logger()->warning(
				"is_valid_hash(): Kein gültiger Hash vorhanden",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $valid;
	}

    /**
     * Retrieves the value of the code property.
     *
     * @return string The value of the code property.
     */
    public function get_code(): string
    {
        $code =  $this->code;

	    if (empty($code)) {
		    $this->get_logger()->warning(
			    "getCode(): Code ist leer oder nicht gesetzt",
			    ['plugin' => 'f12-cf7-captcha']
		    );
	    } else {
		    $this->get_logger()->debug(
			    "getCode(): Code erfolgreich ermittelt",
			    [
				    'plugin' => 'f12-cf7-captcha',
				    'code'   => $code
			    ]
		    );
	    }

		return $code;
    }

    /**
     * Set the code for the object.
     *
     * @param string $code The code to be set.
     *
     * @return void
     */
    public function set_code(string $code): void
    {
        $this->code = $code;

	    if (empty($code)) {
		    $this->get_logger()->warning(
			    "setCode(): Code ist leer gesetzt worden",
			    ['plugin' => 'f12-cf7-captcha']
		    );
	    } else {
		    $this->get_logger()->info(
			    "setCode(): Neuer Code gesetzt",
			    [
				    'plugin' => 'f12-cf7-captcha',
				    'code'   => $code
			    ]
		    );
	    }
    }

    /**
     * Returns the validated value.
     *
     * @return int The validated value.
     */
	public function get_validated(): int
	{
		$validated = $this->validated;

		if ($validated === 1) {
			$this->get_logger()->debug(
				"get_validated(): Eintrag ist validiert",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		} elseif ($validated === 0) {
			$this->get_logger()->info(
				"get_validated(): Eintrag ist nicht validiert",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		} else {
			$this->get_logger()->warning(
				"get_validated(): Unerwarteter Wert für 'validated'",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		}

		return $validated;
	}


    /**
     * Sets the validated property of the object.
     *
     * @param int $validated The new value for the validated property.
     *
     * @return void
     */
	public function set_validated(int $validated): void
	{
		$this->validated = $validated;

		if ($validated === 1) {
			$this->get_logger()->info(
				"set_validated(): Eintrag wurde auf 'validiert' gesetzt",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		} elseif ($validated === 0) {
			$this->get_logger()->info(
				"set_validated(): Eintrag wurde auf 'nicht validiert' gesetzt",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		} else {
			$this->get_logger()->warning(
				"set_validated(): Unerwarteter Wert für 'validated' gesetzt",
				[
					'plugin'    => 'f12-cf7-captcha',
					'validated' => $validated
				]
			);
		}
	}

    /**
     * Returns the creation time of the object in string format.
     *
     * If the `createtime` property is not set or is empty, it will be initialized with the current date and time.
     *
     * @return string The creation time in the format 'Y-m-d H:i:s'.
     */
	public function get_create_time(): string
	{
		if (empty($this->createtime)) {
			$dt = new \DateTime();
			$this->createtime = $dt->format('Y-m-d H:i:s');

			$this->get_logger()->info(
				"get_create_time(): Neuer Zeitstempel generiert",
				[
					'plugin'     => 'f12-cf7-captcha',
					'createtime' => $this->createtime
				]
			);
		} else {
			$this->get_logger()->debug(
				"get_create_time(): Vorhandener Zeitstempel zurückgegeben",
				[
					'plugin'     => 'f12-cf7-captcha',
					'createtime' => $this->createtime
				]
			);
		}

		return $this->createtime;
	}

    /**
     * Sets the createtime value of the object.
     *
     * This method sets the value of the createtime property to the current date and time in the format 'Y-m-d
     * H:i:s'.
     *
     * @return void
     */
	public function set_create_time(): void
	{
		$dt = new \DateTime();
		$this->createtime = $dt->format('Y-m-d H:i:s');

		$this->get_logger()->info(
			"set_create_time(): Neuer Zeitstempel gesetzt",
			[
				'plugin'     => 'f12-cf7-captcha',
				'createtime' => $this->createtime
			]
		);
	}

    /**
     * Retrieves the update time of the object.
     *
     * If the update time is not set, it will be initialized with the current date and time.
     *
     * @return string The update time of the object in the format 'Y-m-d H:i:s'.
     */
	public function get_update_time(): string
	{
		if (empty($this->updatetime)) {
			$dt = new \DateTime();
			$this->updatetime = $dt->format('Y-m-d H:i:s');

			$this->get_logger()->info(
				"get_update_time(): Neuer Update-Zeitstempel generiert",
				[
					'plugin'     => 'f12-cf7-captcha',
					'updatetime' => $this->updatetime
				]
			);
		} else {
			$this->get_logger()->debug(
				"get_update_time(): Vorhandener Update-Zeitstempel zurückgegeben",
				[
					'plugin'     => 'f12-cf7-captcha',
					'updatetime' => $this->updatetime
				]
			);
		}

		return $this->updatetime;
	}


    /**
     * Sets the update time of the object to the current date and time.
     *
     * @return void
     */
	public function set_update_time(): void
	{
		$dt = new \DateTime();
		$this->updatetime = $dt->format('Y-m-d H:i:s');

		$this->get_logger()->info(
			"set_update_time(): Neuer Update-Zeitstempel gesetzt",
			[
				'plugin'     => 'f12-cf7-captcha',
				'updatetime' => $this->updatetime
			]
		);
	}

    /**
     * Checks if the object represents an update.
     *
     * It checks if the object has a valid hash and the ID is not equal to 0.
     *
     * @return bool Returns true if the object represents an update, otherwise returns false.
     */
	private function is_update(): bool
	{
		$result = $this->is_valid_hash() && $this->id != 0;

		if ($result) {
			$this->get_logger()->debug(
				"is_update(): Datensatz wird als Update behandelt",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->id
				]
			);
		} else {
			$this->get_logger()->debug(
				"is_update(): Datensatz wird als Neuerstellung (Insert) behandelt",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->id,
					'hash'   => empty($this->hash) ? 'leer' : 'gesetzt'
				]
			);
		}

		return $result;
	}


    /**
     * Retrieves a Captcha object by its ID from the database.
     *
     * @param int $id The ID of the Captcha to retrieve.
     *
     * @return Captcha|null The Captcha object corresponding to the provided ID, or null if the global $wpdb object
     *                      is not available or no record is found.
     */
	public function get_by_id(int $id): ?Captcha
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error(
				"get_by_id(): WPDB nicht definiert",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $id
				]
			);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table = $this->get_table_name();

		$this->get_logger()->debug(
			"get_by_id(): Starte Datenbankabfrage",
			[
				'plugin' => 'f12-cf7-captcha',
				'table'  => $table,
				'id'     => $id
			]
		);

		$results = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id),
			ARRAY_A
		);

		if (!empty($results)) {
			$this->get_logger()->info(
				"get_by_id(): Datensatz gefunden",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $id
				]
			);

			return new Captcha($this->logger, $this->ip_address, $results[0]);
		}

		$this->get_logger()->warning(
			"get_by_id(): Kein Datensatz gefunden",
			[
				'plugin' => 'f12-cf7-captcha',
				'id'     => $id
			]
		);

		return null;
	}

    /**
     * Retrieves a Captcha object by its hash.
     *
     * @param string $hash The hash value of the Captcha.
     *
     * @return Captcha|null The Captcha object matching the provided hash, or null if not found.
     */
	public function get_by_hash(string $hash): ?Captcha
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error(
				"get_by_hash(): WPDB nicht definiert",
				[
					'plugin' => 'f12-cf7-captcha',
					'hash'   => '***'
				]
			);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table = $this->get_table_name();

		// Hash im Log maskieren (z. B. erste und letzte 4 Zeichen anzeigen)
		$masked = substr($hash, 0, 4) . '...' . substr($hash, -4);

		$this->get_logger()->debug(
			"get_by_hash(): Starte Datenbankabfrage",
			[
				'plugin' => 'f12-cf7-captcha',
				'table'  => $table,
				'hash'   => $masked
			]
		);

		$results = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE hash=%s", $hash),
			ARRAY_A
		);

		if (isset($results[0])) {
			$this->get_logger()->info(
				"get_by_hash(): Datensatz gefunden",
				[
					'plugin' => 'f12-cf7-captcha',
					'hash'   => $masked
				]
			);

			return new Captcha($this->logger, $this->ip_address, $results[0]);
		}

		$this->get_logger()->warning(
			"get_by_hash(): Kein Datensatz gefunden",
			[
				'plugin' => 'f12-cf7-captcha',
				'hash'   => $masked
			]
		);

		return null;
	}

    /**
     * Save the object to the database
     */
	public function save()
	{
		global $wpdb;

		if (!$wpdb) {
			$this->get_logger()->error(
				"save(): WPDB nicht definiert",
				['plugin' => 'f12-cf7-captcha']
			);
			throw new \RuntimeException('WPDB not defined.');
		}

		$table = $this->get_table_name();

		if ($this->is_update()) {
			$this->get_logger()->debug(
				"save(): Starte Update",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->get_id()
				]
			);

			$result = $wpdb->update(
				$table,
				[
					'hash'      => $this->get_hash(),
					'createtime'=> $this->get_create_time(),
					'updatetime'=> $this->get_update_time(),
					'code'      => $this->get_code(),
					'validated' => $this->get_validated(),
				],
				['id' => $this->get_id()]
			);

			if ($result !== false) {
				$this->get_logger()->info(
					"save(): Datensatz erfolgreich aktualisiert",
					[
						'plugin' => 'f12-cf7-captcha',
						'id'     => $this->get_id(),
						'result' => $result
					]
				);
			} else {
				$this->get_logger()->error(
					"save(): Fehler beim Aktualisieren des Datensatzes",
					[
						'plugin' => 'f12-cf7-captcha',
						'id'     => $this->get_id()
					]
				);
			}

			return $result;
		}

		// --- INSERT ---
		$this->get_logger()->debug(
			"save(): Starte Insert",
			['plugin' => 'f12-cf7-captcha']
		);

		$result = $wpdb->insert(
			$table,
			[
				'hash'      => $this->get_hash(),
				'code'      => $this->get_code(),
				'updatetime'=> $this->get_update_time(),
				'createtime'=> $this->get_create_time(),
				'validated' => $this->get_validated()
			]
		);

		if ($result) {
			$this->set_id($wpdb->insert_id);

			$this->get_logger()->info(
				"save(): Neuer Datensatz erfolgreich eingefügt",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->get_id(),
					'result' => $result
				]
			);
		} else {
			$this->get_logger()->error(
				"save(): Fehler beim Einfügen des Datensatzes",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $result;
	}
}