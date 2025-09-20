<?php

namespace f12_cf7_captcha\core\protection\whitelist;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

class Whitelist_Validator extends BaseProtection {
	/**
	 * Private constructor for the class.
	 *
	 * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
	 * This hook is used to retrieve log data.
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->get_logger()->info( 'Konstruktor gestartet.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$this->get_logger()->info( 'Konstruktor abgeschlossen.', [
			'class' => __CLASS__,
		] );
	}

	protected function is_enabled(): bool {
		$is_enabled = true;

		if ( $is_enabled ) {
			$this->get_logger()->info( 'Whitelist ist aktiviert.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
		} else {
			$this->get_logger()->warning( 'Whitelist ist deaktiviert.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
		}

		$result = apply_filters( 'f12-cf7-captcha-skip-validation-whitelist', $is_enabled );

		if ( $is_enabled && ! $result ) {
			$this->get_logger()->debug( 'Whitelist wird durch Filter übersprungen.', [
				'filter'         => 'f12-cf7-captcha-skip-validation-whitelist',
				'original_state' => $is_enabled,
			] );
		}

		return $result;
	}

	/**
	 * Immer false zurückgeben da es hier um whitelist geht.
	 * @return bool
	 */
	public function is_spam():bool{
		return false;
	}

	/**
	 * Checks if the given email(s) are in the whitelist.
	 *
	 * This method verifies whether the provided argument, which can be either a single email
	 * address or an array of email addresses, exists in the specified whitelist of emails.
	 *
	 * @param mixed $arg                A single email address as a string or an array of email addresses.
	 * @param array $whitelisted_emails An optional array of whitelisted email addresses.
	 *
	 * @return bool Returns true if the provided email(s) are found in the whitelist, otherwise false.
	 */
	private function is_whitelisted_email( $arg, $whitelisted_emails = [] ): bool {
		if ( empty( $whitelisted_emails ) ) {
			$this->get_logger()->debug( "Whitelist-Check: keine Whitelist-E-Mails konfiguriert", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false;
		}

		if ( is_array( $arg ) ) {
			foreach ( $arg as $value ) {
				if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
					return true;
				}
			}

			$this->get_logger()->debug( "Whitelist-Check: Array geprüft, keine Übereinstimmung gefunden", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false; // Wenn keine der E-Mail-Adressen in der Whitelist ist
		}

		// Sanitize and trim the current POST value
		$value = sanitize_text_field( trim( $arg ) );

		if ( empty( $value ) ) {
			$this->get_logger()->debug( "Whitelist-Check: Wert leer oder ungültig", [
				'plugin' => 'f12-cf7-captcha'
			] );

			return false;
		}

		// If any $_POST value matches a whitelisted email, skip protection
		if ( in_array( $value, $whitelisted_emails ) ) {
			$this->get_logger()->info( "Validation übersprungen: E-Mail ist auf Whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'email'  => $value
			] );
		}


		$this->get_logger()->debug( "Whitelist-Check: E-Mail nicht in Whitelist", [
			'plugin' => 'f12-cf7-captcha',
			'email'  => $value
		] );

		return false;
	}

	/**
	 * Determines if the submitted form is considered spam.
	 *
	 * This method checks if the submitted form is spam based on certain criteria.
	 *
	 * @return bool Returns true if the form is considered spam, false otherwise.
	 */
	public function is_whitelisted($args): bool {
		$this->get_logger()->info( 'Führe Whitelist-Überprüfung durch.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Wenn Modul deaktiviert ist → kein Spam
		if ( ! $this->is_enabled() ) {
			$this->get_logger()->debug( 'Whitelist-Check übersprungen: Whitelist-Schutz ist deaktiviert.', [
				'class' => __CLASS__,
			] );

			return false;
		}

		// Get the whitelist settings from the plugin options
		$settings               = get_option( 'f12-cf7-captcha-settings', [] );
		$whitelisted_emails     = isset( $settings['global']['protection_whitelist_emails'] ) ? explode( "\n", trim( $settings['global']['protection_whitelist_emails'] ) ) : [];
		$whitelisted_ips        = isset( $settings['global']['protection_whitelist_ips'] ) ? explode( "\n", $settings['global']['protection_whitelist_ips'] ) : [];
		$whitelisted_admin_role = isset( $settings['global']['protection_whitelist_role_admin'] ) ? (int) $settings['global']['protection_whitelist_role_admin'] : 0;
		$whitelisted_logged_in  = isset( $settings['global']['protection_whitelist_role_logged_in'] ) ? (int) $settings['global']['protection_whitelist_role_logged_in'] : 0;

		$user_id = wp_validate_auth_cookie( $_COOKIE[ LOGGED_IN_COOKIE ] ?? '', 'logged_in' );
		if ( $user_id ) {
			wp_set_current_user( $user_id ); // User-Kontext herstellen
		}

		$current_user = wp_get_current_user();


		$this->get_logger()->debug( "REST-Request erkannt", [
			'is_logged_in' => is_user_logged_in() ? 'yes' : 'no',
			'user'         => wp_get_current_user()->user_login ?: 'guest',
			'has_nonce'    => wp_verify_nonce( $_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest' ) ? 'valid' : 'missing/invalid',
		] );

		if ( $current_user->exists() && $whitelisted_logged_in ) {
			$this->get_logger()->info( "Validation übersprungen: Benutzer ist eingeloggt", [
				'plugin' => 'f12-cf7-captcha',
				'user'   => wp_get_current_user()->user_login ?? 'unknown'
			] );

			return true;
		}else {
			$this->get_logger()->info( "Validation nicht übersprungen: Benutzer nicht eingeloggt oder nicht auf Whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'user'   => is_user_logged_in() ? ( wp_get_current_user()->user_login ?? 'unknown' ) : 'guest',
				'whitelisted_logged_in' => $whitelisted_logged_in ? 'yes' : 'no',
			] );
		}

		if ( $current_user->exists() && $whitelisted_admin_role ) {

			// Check if the user has the 'administrator' role
			if ( in_array( 'administrator', (array)$current_user->roles ) ) {
				$this->get_logger()->info( "Validation übersprungen: Benutzer ist Administrator", [
					'plugin' => 'f12-cf7-captcha',
					'user'   => $current_user->user_login
				] );

				return true;
			} else {
				$this->get_logger()->debug( "Benutzer eingeloggt, aber kein Admin → keine Ausnahme", [
					'plugin' => 'f12-cf7-captcha',
					'user'   => $current_user->user_login
				] );

				return false;
			}
		} else {
			$this->get_logger()->info( "Validation nicht übersprungen: Kein Benutzer eingeloggt oder Admin-Whitelist deaktiviert", [
				'plugin'                => 'f12-cf7-captcha',
				'user'                  => is_user_logged_in() ? ( wp_get_current_user()->user_login ?? 'unknown' ) : 'guest',
				'whitelisted_admin_role' => $whitelisted_admin_role ? 'yes' : 'no',
			] );
		}


		// Get the current user's IP address
		$user_ip = $_SERVER['REMOTE_ADDR'];

		// Trim and clean whitelist values for comparison
		$whitelisted_emails = array_map( 'trim', $whitelisted_emails );
		$whitelisted_ips    = array_map( 'trim', $whitelisted_ips );

		$whitelisted_emails = array_filter( $whitelisted_emails );

		// Check if the user's IP is in the whitelist
		if ( in_array( $user_ip, $whitelisted_ips ) ) {
			$this->get_logger()->info( "Validation übersprungen: IP ist auf Whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'ip'     => $user_ip
			] );

			return true;
		} else {
			$this->get_logger()->info( "Validation nicht übersprungen: IP nicht auf Whitelist", [
				'plugin' => 'f12-cf7-captcha',
				'ip'     => $user_ip,
			] );
		}


		// Iterate through each $_POST variable to check if any match a whitelisted email
		foreach ( $args as $value ) {
			if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
				$this->get_logger()->info( "Validation übersprungen: Email ist auf Whitelist", [
					'plugin' => 'f12-cf7-captcha',
					'email'  => $value
				] );

				return true;
			}
		}

		$this->get_logger()->debug( "Validation nicht übersprungen", [
			'plugin' => 'f12-cf7-captcha',
			'ip'     => $user_ip,
			'args'   => $args
		] );

		return false;
	}


	public function success(): void {
		$this->get_logger()->info( 'Erfolgreiche Formularübermittlung erkannt.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Hier kann zusätzliche Logik implementiert werden,
		// die bei einer erfolgreichen Validierung ausgeführt werden soll.
		// Zum Beispiel:
		// - Löschen temporärer Daten
		// - Senden einer Benachrichtigung
		// - Aktualisieren von Zählern

		// TODO: Implementieren Sie die Erfolg-Logik hier.
	}
}