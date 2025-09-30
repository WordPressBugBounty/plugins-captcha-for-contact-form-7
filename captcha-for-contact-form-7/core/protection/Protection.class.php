<?php

namespace f12_cf7_captcha\core\protection;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\api\Api;
use f12_cf7_captcha\core\protection\browser\Browser;
use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
use f12_cf7_captcha\core\protection\ip\IPValidator;
use f12_cf7_captcha\core\protection\ip_blacklist\IP_Blacklist_Validator;
use f12_cf7_captcha\core\protection\javascript\Javascript_Validator;
use f12_cf7_captcha\core\protection\multiple_submission\Multiple_Submission_Validator;
use f12_cf7_captcha\core\protection\rules\RulesHandler;
use f12_cf7_captcha\core\protection\time\Timer_Validator;
use f12_cf7_captcha\core\protection\whitelist\Whitelist_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'api/Api.class.php' );
require_once( 'browser/Browser.php' );
require_once( 'multiple_submission/Multiple_Submission_Validator.class.php' );
require_once( 'time/Timer_Validator.class.php' );
require_once( 'captcha/Captcha_Validator.class.php' );
require_once( 'rules/RulesHandler.class.php' );
require_once( 'ip/IPValidator.class.php' );
require_once( 'ip_blacklist/IP_Blacklist_Validator.php' );
require_once( 'javascript/Javascript_Validator.php' );
require_once( 'whitelist/Whitelist_Validator.php' );

class Protection extends BaseModul {
	protected $_moduls = [];
	private Log_WordPress $Logger;

	public function __construct( CF7Captcha $Controller, Log_WordPress $Logger ) {
		parent::__construct( $Controller );

		$this->get_logger()->info( 'Konstruktor gestartet.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$this->Logger = $Logger;

		add_action( 'f12_cf7_captcha_compatibilities_loaded', array( $this, 'on_init' ) );
		$this->get_logger()->debug( 'Hook "f12_cf7_captcha_compatibilities_loaded" für die Methode "on_init" hinzugefügt.' );

		$this->get_logger()->info( 'Konstruktor abgeschlossen.' );
	}

	/**
	 * Initializes the modules for the software.
	 *
	 * This method initializes the modules required for the software to function properly.
	 *
	 * @return void
	 */
	private function init_moduls(): void {
		$this->get_logger()->info( 'Initialisiere die Schutzmodule für das Kontaktformular.', [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Definiere die Module, die initialisiert werden sollen.
		$moduls = [
			'api-validator'                 => new Api( $this->Controller ),
			'whitelist-validator'           => new Whitelist_Validator( $this->Controller ),
			'ip-blacklist-validator'        => new IP_Blacklist_Validator( $this->Controller ),
			'browser-validator'             => new Browser( $this->Controller ),
			'ip-validator'                  => new IPValidator( $this->Controller ),
			'javascript-validator'          => new Javascript_Validator( $this->Controller ),
			'rule-validator'                => new RulesHandler( $this->Controller ),
			'multiple-submission-validator' => new Multiple_Submission_Validator( $this->Controller ),
			'timer-validator'               => new Timer_Validator( $this->Controller ),
			'captcha-validator'             => new Captcha_Validator( $this->Controller ),
		];

		$this->get_logger()->debug( 'Module wurden definiert.', [ 'module_count' => count( $moduls ) ] );

		// Füge jedes Modul dem internen Modul-Array hinzu.
		foreach ( $moduls as $name => $BaseModul ) {
			$this->_moduls[ $name ] = $BaseModul;
			$this->get_logger()->debug( "Modul '{$name}' wurde erfolgreich geladen.", [
				'plugin'       => 'f12-cf7-captcha',
				'module_class' => get_class( $BaseModul ),
			] );
		}

		$this->get_logger()->info( 'Alle Schutzmodule erfolgreich initialisiert.', [ 'plugin' => 'f12-cf7-captcha' ] );
	}

	/**
	 * Retrieves the specified module based on its name.
	 *
	 * @param string $name The name of the module to retrieve.
	 *
	 * @return BaseProtection The specified module.
	 * @throws \Exception If the specified module does not exist.
	 */
	public function get_modul( string $name ): BaseProtection {
		$this->get_logger()->info( "Versuche, Modul '{$name}' abzurufen.", [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		if ( ! isset( $this->_moduls[ $name ] ) ) {
			$error_message = sprintf( 'Modul %s existiert nicht.', $name );
			$this->get_logger()->error( $error_message, [ 'plugin' => 'f12-cf7-captcha' ] );
			throw new \Exception( $error_message );
		}

		$modul = $this->_moduls[ $name ];
		$this->get_logger()->debug( "Modul '{$name}' erfolgreich abgerufen.", [
			'plugin'      => 'f12-cf7-captcha',
			'modul_class' => get_class( $modul ),
		] );

		return $modul;
	}


	/**
	 * Retrieves the name of the field.
	 *
	 * @return string The name of the field.
	 */
	protected function get_field_name(): string {
		$field_name = 'f12_captcha';

		$this->get_logger()->debug( 'Rufe den Standard-Feldnamen für das Captcha ab.', [
			'plugin'     => 'f12-cf7-captcha',
			'class'      => __CLASS__,
			'method'     => __METHOD__,
			'field_name' => $field_name,
		] );

		return $field_name;
	}

	/**
	 * Retrieves the captcha for spam protection.
	 *
	 * This method retrieves the captcha for spam protection by calling the `get_spam_protection()`
	 * method on each module and concatenating the results into a single string.
	 *
	 * @return string The captcha for spam protection.
	 */
	public function get_captcha(): string {
		$this->get_logger()->info( 'Starte die Generierung des kombinierten Captcha-HTML-Codes.', [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$captcha_parts = [];

		// Iteriere über jedes geladene Modul und rufe dessen Captcha-Code ab
		foreach ( $this->_moduls as $key => $modul ) {
			// Stelle sicher, dass die Methode 'get_captcha' existiert, bevor sie aufgerufen wird
			if ( method_exists( $modul, 'get_captcha' ) ) {
				$part                  = $modul->get_captcha();
				$captcha_parts[ $key ] = $part;

				if ( ! empty( $part ) ) {
					$this->get_logger()->debug( "Captcha-Teil von Modul '{$key}' erfolgreich generiert.", [
						'html_length' => strlen( $part ),
					] );
				}
			} else {
				$this->get_logger()->warning( "Modul '{$key}' hat keine 'get_captcha'-Methode. Überspringe." );
			}
		}

		// Kombiniere die einzelnen Captcha-Teile zu einem einzigen String
		$final_captcha_html = implode( "", $captcha_parts );

		$this->get_logger()->info( 'Kombinierter Captcha-HTML-Code erfolgreich generiert.', [
			'total_length' => strlen( $final_captcha_html ),
		] );

		return $final_captcha_html;
	}

	/**
	 * Determines if the submitted data is considered spam.
	 *
	 * This method checks if the submitted data is considered spam by iterating through the loaded modules
	 * and calling their respective "is_spam" method.
	 *
	 * @param mixed ...$args The arguments passed to the method. In this case, it is the data submitted.
	 *
	 * @param bool  $skip    Skip validation, default: false
	 *
	 * @return bool Returns true if the submitted data is spam, otherwise false.
	 *
	 * @since  1.12.2
	 *
	 * @filter f12-cf7-captcha-skip-validation
	 */
	public function is_spam( ...$args ): bool {
		$this->get_logger()->info( 'Starte die Haupt-Spam-Überprüfung für die übermittelten Formulardaten.', [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Keine Daten übermittelt
		if ( ! isset( $args[0] ) ) {
			$this->get_logger()->info( 'Keine Formulardaten übermittelt. Überspringe Validierung.', [ 'plugin' => 'f12-cf7-captcha' ] );

			return false;
		}

		$array_post_data = $args[0];

		// Prüfen ob Validation per Filter übersprungen werden soll
		if ( apply_filters( 'f12-cf7-captcha-skip-validation', false, $array_post_data ) ) {
			$this->get_logger()->notice( 'Validierung wurde durch den Filter "f12-cf7-captcha-skip-validation" übersprungen.', [ 'plugin' => 'f12-cf7-captcha' ] );

			return false;
		}

		// Counter laden und Gesamtprüfungen hochzählen
		$counters                 = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
		$counters['checks_total'] = ( $counters['checks_total'] ?? 0 ) + 1;

		// Whitelist prüfen
		$whitelist = $this->get_modul( 'whitelist-validator' ); // dein neues Modul
		if ( $whitelist && $whitelist->is_whitelisted( $array_post_data ) ) {
			$this->get_logger()->info( "User / IP / E-Mail ist auf der Whitelist. Alle Schutzmaßnahmen übersprungen.", [ 'plugin' => 'f12-cf7-captcha' ] );

			$counters['checks_clean'] = ( $counters['checks_clean'] ?? 0 ) + 1;
			update_option( 'f12_cf7_captcha_telemetry_counters', $counters, false );

			return false; // Sofort abbrechen
		}

		$is_spam         = false;
		$spam_modul_name = '';

		// Alle Module durchlaufen
		foreach ( $this->_moduls as $name => $modul ) {
			$this->get_logger()->info( "Überprüfe Daten mit Modul '{$name}'.", [
				'plugin'      => 'f12-cf7-captcha',
				'modul_class' => get_class( $modul ),
			] );

			if ( $name == "whitelist-validator" ) {
				$this->get_logger()->info( "Überspringe die Whitelist für is_spam()", [ 'plugin' => 'f12-cf7-captcha' ] );
				continue;
			}

			if ( $modul->is_spam( $array_post_data ) ) {
				$is_spam = true;

				// Modul-Counter hochzählen
				$counters[ $name ] = ( $counters[ $name ] ?? 0 ) + 1;

				// Nur erstes Modul setzt Fehlermeldung + Logging
				if ( $spam_modul_name === '' ) {
					$spam_modul_name = $name;

					$this->get_logger()->warning( "Modul '{$name}' hat Spam gefunden. Die Verarbeitung wird gestoppt.", [ 'plugin' => 'f12-cf7-captcha' ] );
					$this->set_message( $modul->get_message() );
					$this->Logger->maybe_log( 'protection', $array_post_data, true, $this->get_message() );
				}
			}
		}

		if ( $is_spam ) {
			// Spam-Counter hochzählen
			$counters['checks_spam'] = ( $counters['checks_spam'] ?? 0 ) + 1;
			update_option( 'f12_cf7_captcha_telemetry_counters', $counters, false );
		} else {
			$this->get_logger()->info( 'Alle Module erfolgreich durchlaufen. Kein Spam gefunden.' );

			// Erfolgs-Callbacks ausführen
			foreach ( $this->_moduls as $modul ) {
				$modul->success();
			}

			// Erfolg protokollieren
			$this->Logger->maybe_log( 'protection', $array_post_data, false );

			// Clean-Counter hochzählen
			$counters['checks_clean'] = ( $counters['checks_clean'] ?? 0 ) + 1;
			update_option( 'f12_cf7_captcha_telemetry_counters', $counters, false );
		}

		$this->get_logger()->info( 'Spam-Überprüfung abgeschlossen.', [
			'plugin'       => 'f12-cf7-captcha',
			'result'       => $is_spam ? 'Spam' : 'Kein Spam',
			'triggered_by' => $is_spam ? $spam_modul_name : 'N/A',
		] );

		return $is_spam;
	}


	public function on_init(): void {
		$this->get_logger()->info( 'Starte Initialisierung der Module in der on_init-Methode.', [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$this->init_moduls();

		$this->get_logger()->info( 'Module erfolgreich initialisiert in on_init.', [ 'plugin' => 'f12-cf7-captcha' ] );
	}

	protected function is_enabled(): bool {
		$is_enabled = true;

		$this->get_logger()->info( 'Überprüfe, ob die Methode aktiviert ist. Sie ist standardmäßig aktiviert.', [
			'plugin'     => 'f12-cf7-captcha',
			'class'      => __CLASS__,
			'method'     => __METHOD__,
			'is_enabled' => $is_enabled,
		] );

		return $is_enabled;
	}
}