<?php

namespace f12_cf7_captcha\core\timer;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'CaptchaTimer.class.php' );
require_once( 'CaptchaTimerCleaner.class.php' );

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
		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instanziiere den CaptchaTimerCleaner
		$this->Captcha_Timer_Cleaner = new CaptchaTimerCleaner($Controller);
		$this->get_logger()->debug('CaptchaTimerCleaner-Instanz erstellt.');

		// Füge den '_init'-Hook hinzu
		add_action('init', array($this, '_init'));
		$this->get_logger()->debug('Hook "init" für die Methode "_init" hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
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
		$this->get_logger()->info('Rufe die Instanz des Captcha-Timer-Cleaners ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Gibt die bereits im Konstruktor erstellte Instanz des Cleaners zurück.
		if (!($this->Captcha_Timer_Cleaner instanceof CaptchaTimerCleaner)) {
			$this->get_logger()->error('CaptchaTimerCleaner-Instanz ist nicht verfügbar oder vom falschen Typ.');
			// Optional: Hier könnte man eine neue Instanz erstellen oder eine Exception werfen.
			// Da die Instanz im Konstruktor erstellt wird, ist dies ein unerwarteter Zustand.
		} else {
			$this->get_logger()->debug('CaptchaTimerCleaner-Instanz erfolgreich zurückgegeben.');
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
		$this->get_logger()->info('Erstelle eine neue Instanz von CaptchaTimer über die Factory-Methode.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instanziiere ein neues CaptchaTimer-Objekt und übergebe den Logger.
		$timer = new CaptchaTimer($this->get_logger());

		$this->get_logger()->info('CaptchaTimer-Objekt erfolgreich erstellt.');

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
			$this->get_logger()->info('Timer-Schutz ist aktiviert.');
		} else {
			$this->get_logger()->info('Timer-Schutz ist deaktiviert.');
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
		$this->get_logger()->info('Rufe den zuletzt erstellten Timer ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Überprüfe, ob die Eigenschaft Latest_Timer eine Instanz von CaptchaTimer ist.
		if ($this->Latest_Timer instanceof CaptchaTimer) {
			$this->get_logger()->debug('Der zuletzt erstellte Timer wurde erfolgreich abgerufen.', [
				'timer_hash' => $this->Latest_Timer->get_hash(),
			]);
			return $this->Latest_Timer;
		}

		$this->get_logger()->info('Es wurde kein zuletzt erstellter Timer gefunden. Rückgabe von null.');

		// Wenn die Eigenschaft null ist, gib null zurück.
		return null;
	}

	/**
	 * @private WordPress Hook
	 */
	public function _init()
	{
		$this->get_logger()->info('Führe die Initialisierungsmethode "_init" aus.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Löst eine Aktion aus, um anderen Teilen des Codes die Initialisierung zu ermöglichen.
		do_action('f12_cf7_captcha_timer_validator_init');
		$this->get_logger()->debug('Die Aktion "f12_cf7_captcha_timer_validator_init" wurde ausgelöst.');

		$this->get_logger()->info('Die Initialisierungsmethode ist abgeschlossen.');
	}

	/**
	 * Get the create time of the object
	 *
	 * @return string The create time in the format 'Y-m-d H:i:s'
	 */
	private function get_create_time(): string
	{
		$this->get_logger()->info('Rufe die Erstellungszeit ab. Überprüfe, ob sie bereits gesetzt ist.');

		// Prüfe, ob die Eigenschaft `createtime` leer ist.
		if (empty($this->createtime)) {
			$this->get_logger()->debug('Die Erstellungszeit ist leer. Erstelle ein neues Datum-Objekt und setze die Zeit.');

			try {
				// Instanziiere ein neues DateTime-Objekt, um die aktuelle Zeit zu erfassen.
				$dt = new \DateTime();
				// Formatiere das Datum in das SQL-kompatible Format 'YYYY-MM-DD HH:MM:SS'.
				$this->createtime = $dt->format('Y-m-d H:i:s');
				$this->get_logger()->info('Erstellungszeit erfolgreich auf die aktuelle Zeit gesetzt.', ['createtime' => $this->createtime]);
			} catch (\Exception $e) {
				$this->get_logger()->error('Fehler beim Erstellen des DateTime-Objekts.', ['error' => $e->getMessage()]);
				// Im Fehlerfall kann ein leerer String oder ein Standardwert zurückgegeben werden,
				// um einen weiteren Fehler zu vermeiden.
				return '';
			}
		} else {
			$this->get_logger()->debug('Die Erstellungszeit ist bereits vorhanden. Gebe den bestehenden Wert zurück.');
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
		$this->get_logger()->info('Generiere einen neuen eindeutigen Hash-Wert für den Timer.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Kombiniere den aktuellen Zeitstempel (sekundengenau) mit der IP-Adresse des Benutzers,
		// um eine einzigartige, nicht-vorhersehbare Zeichenkette zu erstellen.
		// Die IP-Adresse sorgt dafür, dass Hashes für verschiedene Benutzer unterschiedlich sind.
		$data_to_hash = time() . $user_ip_address;

		// Verwende password_hash() mit dem Standard-Algorithmus (PASSWORD_DEFAULT).
		// Dies bietet eine starke, salzige Hashing-Methode, die sicherstellt,
		// dass der Hash nicht leicht erraten oder in einer Rainbow-Tabelle nachgeschlagen werden kann.
		$hash = password_hash($data_to_hash, PASSWORD_DEFAULT);

		$this->get_logger()->debug('Hash-Generierung abgeschlossen. Der resultierende Hash ist ' . strlen($hash) . ' Zeichen lang.');

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

		$this->get_logger()->debug('Rufe die aktuelle UNIX-Zeit in Millisekunden ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'raw_time' => $time_in_seconds,
		]);

		// Konvertiere die Zeit von Sekunden in Millisekunden und runde das Ergebnis.
		// microtime(true) gibt die Zeit als float mit hoher Präzision zurück.
		$time_in_ms = round($time_in_seconds * 1000);

		$this->get_logger()->debug('Konvertierte Zeit in Millisekunden: ' . $time_in_ms);

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
		$this->get_logger()->info('Starte den Prozess zum Hinzufügen eines neuen Timer-Eintrags zur Datenbank.');

		try {
			// Rufe die User-Data-Instanz ab, um die IP-Adresse des Benutzers zu erhalten.
			$User_Data = $this->Controller->get_modul('user-data');
			$user_ip_address = $User_Data->get_ip_address();
			$this->get_logger()->debug('Benutzer-IP-Adresse abgerufen.', ['ip' => $user_ip_address]);
		} catch (\Exception $e) {
			$this->get_logger()->error('Fehler beim Abrufen der Benutzerdaten. Timer kann nicht erstellt werden.', ['error' => $e->getMessage()]);
			return null;
		}

		// Generiere einen eindeutigen Hash für den Timer.
		$hash = $this->generate_hash($user_ip_address);

		// Hole die aktuelle Zeit in Millisekunden und die formatierte Erstellungszeit.
		$time_in_ms = $this->get_time_in_ms();
		$create_time = $this->get_create_time();

		// Erstelle eine neue CaptchaTimer-Instanz mit den vorbereiteten Daten.
		$CaptchaTimer = new CaptchaTimer(
			$this->get_logger(),
			[
				'hash'       => $hash,
				'value'      => $time_in_ms,
				'createtime' => $create_time
			]
		);

		$this->get_logger()->debug('Neues CaptchaTimer-Objekt erstellt.', [
			'hash' => $hash,
			'value' => $time_in_ms,
			'createtime' => $create_time,
		]);

		// Versuche, den Timer-Eintrag in der Datenbank zu speichern.
		if ($CaptchaTimer->save()) {
			$this->get_logger()->info('Timer-Eintrag erfolgreich in der Datenbank gespeichert.');

			// Speichere die Instanz als den neuesten Timer für den späteren Zugriff.
			$this->Latest_Timer = $CaptchaTimer;

			// Gib den generierten Hash zurück, der im Formular-HTML verwendet wird.
			return $hash;
		}

		$this->get_logger()->error('Fehler beim Speichern des Timer-Eintrags in der Datenbank. Rückgabe von null.');

		// Gib null zurück, wenn das Speichern fehlschlägt.
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
		$this->get_logger()->info('Rufe einen Captcha-Timer anhand des Hash-Werts ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'hash' => $hash,
		]);

		global $wpdb;

		if (!$wpdb) {
			$error_message = 'Die globale Variable $wpdb ist nicht definiert.';
			$this->get_logger()->error($error_message);
			throw new RuntimeException($error_message);
		}

		try {
			$timer_handler = new CaptchaTimer($this->get_logger());
			$timer = $timer_handler->get_by_hash($hash);

			if ($timer) {
				$this->get_logger()->info('Timer-Eintrag erfolgreich abgerufen.');
			} else {
				$this->get_logger()->notice('Kein Timer-Eintrag für den gegebenen Hash gefunden.');
			}

			return $timer;

		} catch (RuntimeException $e) {
			$this->get_logger()->error('Fehler beim Abrufen des Timers.', [
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
		$this->get_logger()->info('Starte den Prozess zum Entfernen eines Timers anhand des Hash-Wertes.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'hash' => $hash,
		]);

		global $wpdb;

		if (!$wpdb) {
			$error_message = 'Die globale Variable $wpdb ist nicht definiert.';
			$this->get_logger()->error($error_message);
			throw new RuntimeException($error_message);
		}

		try {
			$timer_handler = new CaptchaTimer($this->get_logger());
			$is_deleted = $timer_handler->delete_by_hash($hash);

			if ($is_deleted) {
				$this->get_logger()->info('Timer-Eintrag erfolgreich entfernt.', ['hash' => $hash]);
			} else {
				$this->get_logger()->warning('Kein Timer-Eintrag zum Löschen gefunden oder Löschung fehlgeschlagen.', ['hash' => $hash]);
			}
		} catch (\Exception $e) {
			$this->get_logger()->error('Fehler beim Löschen des Timer-Eintrags.', [
				'error' => $e->getMessage(),
			]);
		}
	}
}