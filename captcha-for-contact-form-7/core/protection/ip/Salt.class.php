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

		$this->logger->info('Die Klasse wurde initialisiert.', [
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
		$this->logger->info('Setze Parameter...', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		foreach ($params as $key => $value) {
			$this->logger->debug("Verarbeite Parameter: '{$key}'", [
				'class' => __CLASS__,
			]);

			if (isset($this->{$key})) {
				if ($key === 'salt') {
					$this->logger->debug('Dekodiere "salt" von Base64.', [
						'class' => __CLASS__,
					]);
					$value = base64_decode($value);
				}
				$this->{$key} = $value;
			} else {
				$this->logger->warning("Parameter '{$key}' konnte nicht gesetzt werden, da er nicht existiert.", [
					'class' => __CLASS__,
				]);
			}
		}

		$this->logger->info('Parameter-Setzung abgeschlossen.', [
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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			return 0;
		}

		$this->logger->info("Lösche Einträge, die älter als '{$period}' sind.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$timestamp = strtotime($period);
		if ($timestamp === false) {
			$this->logger->error('Ungültiges Periodenformat.', ['period' => $period]);
			return 0;
		}

		$wp_table_name = $this->get_table_name();

		$dt = new DateTime();
		$dt->setTimestamp($timestamp);
		$dt_formatted = $dt->format('Y-m-d H:i:s');

		$this->logger->debug("Generiere SQL-Abfrage zum Löschen.", [
			'table' => $wp_table_name,
			'cutoff_date' => $dt_formatted,
		]);

		$query = $wpdb->prepare(
			"DELETE FROM {$wp_table_name} WHERE createtime < %s",
			$dt_formatted
		);

		$rows_deleted = $wpdb->query($query);

		if ($rows_deleted === false) {
			$this->logger->error('Fehler bei der Datenbankabfrage.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Erfolgreich {$rows_deleted} Einträge gelöscht.", [
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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			return false;
		}

		$this->logger->warning('Tabelle wird zurückgesetzt! Alle Daten werden gelöscht.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$wp_table_name = $this->get_table_name();

		$query = $wpdb->prepare("DELETE FROM {$wp_table_name}");

		$result = $wpdb->query($query);

		if ($result === false) {
			$this->logger->error('Fehler beim Zurücksetzen der Tabelle.', ['db_error' => $wpdb->last_error]);
			return false;
		}

		$this->logger->info('Tabelle erfolgreich zurückgesetzt.', ['rows_deleted' => $result]);

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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			return 0;
		}

		$wp_table_name = $this->get_table_name();
		$prepare_stmt = 'SELECT count(*) AS entries FROM ' . $wp_table_name;

		if ($validated !== -1) {
			$this->logger->info("Zähle Einträge mit dem Validierungsstatus '{$validated}'.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
			$prepare_stmt .= ' WHERE validated = %d';
			$results = $wpdb->get_results($wpdb->prepare($prepare_stmt, $validated));
		} else {
			$this->logger->info("Zähle alle Einträge in der Tabelle.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
			$results = $wpdb->get_results($prepare_stmt);
		}

		if (is_array($results) && isset($results[0])) {
			$count = (int)$results[0]->entries;
			$this->logger->debug("Anzahl der Einträge: {$count}", [
				'count' => $count,
			]);
			return $count;
		}

		$this->logger->error('Fehler beim Abrufen der Zählergebnisse aus der Datenbank.', ['db_error' => $wpdb->last_error]);
		return 0;
	}

	/**
	 * Create a new table in the WordPress database for storing salts.
	 *
	 * @return void
	 */
	public function create_table(): void
	{
		$this->logger->info('Versuche, die Datenbanktabelle zu erstellen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$wp_table_name = $this->get_table_name();

		if (!function_exists('dbDelta')) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$this->logger->debug('dbDelta-Funktion geladen.');
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

		$this->logger->info('dbDelta-Abfrage ausgeführt. Überprüfe, ob die Tabelle erstellt wurde.', [
			'table' => $wp_table_name,
		]);

		// Optional: Überprüfe den Status der Tabelle nach dbDelta, um sicherzugehen.
		global $wpdb;
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wp_table_name}'") === $wp_table_name;
		if ($table_exists) {
			$this->logger->info('Tabelle erfolgreich erstellt oder aktualisiert.', [
				'table' => $wp_table_name,
			]);
		} else {
			$this->logger->error('Fehler beim Erstellen der Tabelle. Überprüfen Sie die SQL-Syntax oder Datenbankberechtigungen.', [
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

		$this->logger->warning('Versuche, die Datenbanktabelle und den zugehörigen Cron-Job zu löschen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$wpdb) {
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar. Löschvorgang abgebrochen.');
			return;
		}

		$wp_table_name = $this->get_table_name();

		// SQL-Abfrage zum Löschen der Tabelle
		$sql = "DROP TABLE IF EXISTS " . $wp_table_name;
		$result = $wpdb->query($sql);

		if ($result === false) {
			$this->logger->error('Fehler beim Löschen der Tabelle.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Tabelle '{$wp_table_name}' erfolgreich gelöscht.");
		}

		// Löschen des Cron-Jobs
		$hook = 'weeklyIPClear';
		$scheduled = wp_next_scheduled($hook);

		if ($scheduled) {
			wp_clear_scheduled_hook($hook);
			$this->logger->info("Der geplante Cron-Job '{$hook}' wurde erfolgreich gelöscht.");
		} else {
			$this->logger->info("Der Cron-Job '{$hook}' war nicht geplant und musste nicht gelöscht werden.");
		}

		$this->logger->info('Löschvorgang der Tabelle und des Cron-Jobs abgeschlossen.');
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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar. Kann Tabellennamen nicht ermitteln.');
			return '';
		}

		$table_name = $wpdb->prefix . 'f12_cf7_salt';

		$this->logger->debug('Tabellenname ermittelt.', [
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
		$this->logger->debug('Rufe die ID ab.', [
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
		$this->logger->debug('Setze die ID.', [
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
		$this->logger->debug('Rufe den Salt-Wert ab.', [
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
		$this->logger->debug('Rufe die Erstellungszeit ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->createtime)) {
			$this->logger->info('Erstellungszeit ist leer, generiere neuen Zeitstempel.', [
				'class' => __CLASS__,
			]);

			$dt = new DateTime();
			$this->createtime = $dt->format('Y-m-d H:i:s');

			$this->logger->debug('Neuer Zeitstempel generiert.', [
				'createtime' => $this->createtime,
			]);
		}

		$this->logger->debug('Erstellungszeit zurückgegeben.', [
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
		$this->logger->info('Setze die Erstellungszeit.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$dt = new DateTime();
		$this->createtime = $dt->format('Y-m-d H:i:s');

		$this->logger->debug('Erstellungszeit auf ' . $this->createtime . ' gesetzt.', [
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
		$this->logger->info('Erstelle einen neuen Salt-Datensatz.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Erstelle ein neues Salt-Objekt, wenn noch keines existiert
		$generated_salt = $this->generate_salt();
		$this->logger->debug('Neuer Salt-Wert generiert.', [
			'generated_salt_length' => strlen($generated_salt),
		]);

		$Salt = new Salt($this->get_logger(), [
			'salt' => $generated_salt
		]);
		$Salt->save();

		if ($Salt->get_id() === 0) {
			$this->logger->error('Fehler: Salt-Datensatz konnte nicht erstellt werden.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'error_message' => 'Die Datenbankabfrage zum Speichern des Salts ist fehlgeschlagen.',
			]);
			throw new RuntimeException("Salt could not be created. Please check the Database");
		}

		$this->logger->info('Neuer Salt-Datensatz erfolgreich erstellt.', [
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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			throw new \RuntimeException('WPDB not found');
		}

		$table = $this->get_table_name();
		$this->logger->info('Suche nach dem letzten Salt-Datensatz in der Datenbank.');

		$prepare_stmt = sprintf("SELECT * FROM %s ORDER BY createtime DESC LIMIT 1", $table);
		$results = $wpdb->get_results($prepare_stmt, ARRAY_A);

		$Salt = null;

		if (is_array($results) && isset($results[0])) {
			$this->logger->debug('Letzter Salt-Datensatz gefunden.', ['salt_id' => $results[0]['id']]);
			$Salt = new Salt($this->get_logger(), $results[0]);
		} else {
			$this->logger->info('Kein Salt-Datensatz gefunden. Erstelle einen neuen.');
		}

		/*
		 * Erstelle einen Salt, falls keiner existiert
		 */
		if (null === $Salt) {
			try {
				$Salt = $this->create_salt();
			} catch (\RuntimeException $e) {
				$this->logger->error('Fehler beim Erstellen eines neuen Salt-Datensatzes.', ['error' => $e->getMessage()]);
				return null;
			}
		}

		/*
		 * Erstelle einen neuen Salt, falls der existierende älter als 30 Tage ist
		 */
		if ($this->is_older_than($Salt->get_create_time())) {
			$this->logger->info('Der bestehende Salt ist älter als 30 Tage. Erstelle einen neuen.');
			try {
				$Salt = $this->create_salt();
			} catch (\RuntimeException $e) {
				$this->logger->error('Fehler beim Erstellen eines neuen, zeitbasierten Salt-Datensatzes.', ['error' => $e->getMessage()]);
				return null;
			}
		}

		$this->logger->debug('Gibt den aktuellen Salt-Datensatz zurück.', ['salt_id' => $Salt->get_id()]);
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
		$this->logger->debug("Überprüfe, ob das Datum '{$date}' älter ist als '{$days}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			$d1 = new DateTime($date);
			$d1->modify($days);
			$d2 = new DateTime();
		} catch (\Exception $e) {
			$this->logger->error('Fehler bei der Datumsverarbeitung.', [
				'error' => $e->getMessage(),
				'input_date' => $date,
				'days_to_add' => $days,
			]);
			return false;
		}

		$is_older = $d2 > $d1;

		$this->logger->debug('Vergleichsergebnis.', [
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
		$this->logger->info('Generiere einen neuen Salt-Wert.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			$salt = random_bytes(512);
			$this->logger->debug('Neuer Salt erfolgreich generiert.', [
				'length' => strlen($salt),
			]);
			return $salt;
		} catch (\Exception $e) {
			$this->logger->error('Fehler beim Generieren des Salt-Wertes.', [
				'error_message' => $e->getMessage(),
			]);
			throw $e; // Oder ein anderer geeigneter Fehler-Handler
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
		$this->logger->info('Erzeuge einen gesalzenen Hash-Wert.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->salt)) {
			$this->logger->error('Fehler: Der Salt-Wert fehlt.', [
				'class' => __CLASS__,
			]);
			throw new \RuntimeException('Salt-Wert ist nicht gesetzt.');
		}

		$hash = hash_pbkdf2('sha512', $value, $this->salt, 10);

		$this->logger->debug('Hash erfolgreich generiert.', [
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
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			return null;
		}

		$this->logger->info("Versuche, einen Salt-Datensatz mit dem Offset '{$offset}' abzurufen.");

		$table = $this->get_table_name();

		if (!is_numeric($offset) || $offset < 0) {
			$this->logger->error('Ungültiger Offset-Wert. Erwartet wurde eine nicht-negative Ganzzahl.', [
				'offset' => $offset,
			]);
			return null;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY createtime DESC LIMIT 1 OFFSET %d",
			$offset
		);

		$this->logger->debug('Führe Datenbankabfrage aus.', [
			'query' => $query,
		]);

		$results = $wpdb->get_results($query, ARRAY_A);

		$Salt = null;

		if (is_array($results) && isset($results[0])) {
			$this->logger->info('Salt-Datensatz gefunden.', ['id' => $results[0]['id']]);
			$Salt = new Salt($this->get_logger(), $results[0]);
		} else {
			$this->logger->warning('Kein Salt-Datensatz für den gegebenen Offset gefunden.', [
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

		$this->logger->info('Versuche, alte Datenbankeinträge zu bereinigen.');

		if (!$wpdb) {
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			throw new \RuntimeException('WPDB not found');
		}

		$table = $this->get_table_name();

		// Datumsintervall: 3 Wochen
		try {
			$date_time = new DateTime('-3 Weeks');
			$date_time_formatted = $date_time->format('Y-m-d H:i:s');
			$this->logger->debug("Datums-Grenze für die Löschung berechnet.", [
				'cutoff_date' => $date_time_formatted,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Fehler bei der Datumsberechnung.', ['error' => $e->getMessage()]);
			return;
		}

		// Führe die Abfrage aus, um alle Einträge zu löschen, die älter als 3 Wochen sind
		$query = $wpdb->prepare(
			"DELETE FROM {$table} WHERE createtime < %s",
			$date_time_formatted
		);

		$this->logger->info('Führe Bereinigungsabfrage aus.', [
			'query' => $query,
		]);

		$result = $wpdb->query($query);

		if ($result === false) {
			$this->logger->error('Fehler bei der Datenbankbereinigung.', ['db_error' => $wpdb->last_error]);
		} else {
			$this->logger->info("Erfolgreich {$result} alte Einträge gelöscht.", [
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

		$this->logger->info('Versuche, den Salt-Datensatz in der Datenbank zu speichern.');

		if (null === $wpdb) {
			$this->logger->error('Globales $wpdb-Objekt nicht verfügbar.');
			throw new RuntimeException('WPDB not found');
		}

		if ($this->id !== 0) {
			$this->logger->warning('Datensatz existiert bereits und kann nicht erneut gespeichert werden.', [
				'id' => $this->id,
			]);
			return 0;
		}

		$table = $this->get_table_name();

		$this->logger->debug('Führe wpdb->insert() aus.', [
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
			$this->logger->error('Fehler beim Einfügen in die Datenbank.', [
				'db_error' => $wpdb->last_error,
			]);
			throw new RuntimeException('Database error occurred. Reactivate the plugin to create missing tables.');
		}

		$this->set_id($wpdb->insert_id);
		$this->logger->info('Datensatz erfolgreich gespeichert. Neue ID: ' . $this->id, [
			'id' => $this->id,
		]);

		// Bereinige ältere Einträge nach dem Speichern
		try {
			$this->maybe_clean();
		} catch (\RuntimeException $e) {
			$this->logger->error('Fehler bei der Bereinigung der Datenbank.', ['error' => $e->getMessage()]);
		}

		return $result;
	}
}