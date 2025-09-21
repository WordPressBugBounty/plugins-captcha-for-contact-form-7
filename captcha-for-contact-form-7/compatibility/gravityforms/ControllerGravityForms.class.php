<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerGravityForms
 */
class ControllerGravityForms extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'GravityForms';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'gravityforms';

	/**
	 * Check if the captcha is enabled for Gravity Forms
	 *
	 * @return bool True if the captcha is enabled, false otherwise
	 */
	public function is_enabled(): bool {
		// Log the start of the check.
		$this->get_logger()->info( 'Starte Überprüfung, ob das Gravity Forms-Modul aktiviert ist.' );

		// Check if the Gravity Forms plugin is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug( 'Installationsstatus des Moduls: ' . ( $is_installed ? 'Installiert' : 'Nicht installiert' ) );

		// Get the global setting for Gravity Forms protection.
		$setting_value = $this->Controller->get_settings( 'protection_gravityforms_enable', 'global' );
		$this->get_logger()->debug( 'Wert der Einstellung "protection_gravityforms_enable": ' . $setting_value );

		// Determine if the module should be active.
		if ( $setting_value === '' || $setting_value === null ) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_gravityforms_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		$is_active = $is_installed && ( (int) $setting_value === 1 );


		// Log the status before applying any filters.
		$this->get_logger()->debug( 'Modulstatus vor dem Filter: ' . ( $is_active ? 'Aktiv' : 'Inaktiv' ) );

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters( 'f12_cf7_captcha_is_installed_gravityforms', $is_active );

		// Log the final result after the filter.
		$this->get_logger()->info( 'Endgültiger Status nach dem Filter: ' . ( $result ? 'Aktiv' : 'Inaktiv' ) );

		return $result;
	}

	/**
	 * Check if the Gravity Forms plugin is installed
	 *
	 * @return bool Returns true if the Gravity Forms plugin is installed, false otherwise
	 */
	public function is_installed(): bool {
		// Log the start of the check.
		$this->get_logger()->info( 'Starte Überprüfung, ob Gravity Forms installiert ist.' );

		// Check if the 'GFCommon' class exists, which is a reliable indicator of Gravity Forms.
		$is_installed = class_exists( 'GFCommon' );

		// Log the result of the check.
		if ( $is_installed ) {
			$this->get_logger()->info( 'Gravity Forms wurde gefunden.' );
		} else {
			$this->get_logger()->critical( 'Gravity Forms wurde nicht gefunden. Das Modul kann nicht korrekt funktionieren.' );
		}

		// Return the result.
		return $is_installed;
	}

	/**
	 * @private WordPress Hook
	 */
	public function on_init(): void {
		// Log the start of the initialization process for the Gravity Forms module.
		$this->get_logger()->info( 'Starte die Initialisierung des Gravity Forms-Moduls.' );

		// Set the module name.
		$this->name = __( 'GravityForms', 'captcha-for-contact-form-7' );
		$this->get_logger()->debug( 'Modulname wurde gesetzt.', [ 'name' => $this->name ] );

		// Add a filter to modify the form HTML and insert the captcha.
		$this->get_logger()->debug( 'Füge den Filter "gform_get_form_filter" hinzu, um den Spamschutz in Formulare einzufügen.' );
		add_filter( 'gform_get_form_filter', array( $this, 'wp_add_spam_protection' ), 10, 2 );

		// Add a filter for spam validation.
		$this->get_logger()->debug( 'Füge den Filter "gform_entry_is_spam" hinzu, um Formulareinträge auf Spam zu prüfen.' );
		// Mark entry as spam
		add_filter( 'gform_entry_is_spam', array( $this, 'wp_is_spam' ), 10, 3 );
		// Show error in form without sending the form
		add_filter( 'gform_validation', array( $this, 'wp_validation' ), 10, 3 );


		// Adds an action to enqueue the necessary scripts and styles for the spam protection.
		// This hook is used to add assets to the front-end of the website.
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_add_assets' ) );
		$this->get_logger()->debug( 'Action "wp_enqueue_scripts" zum Laden der Assets registriert.' );

		// Log the successful completion of the initialization.
		$this->get_logger()->info( 'Initialisierung abgeschlossen.' );
	}

	/**
	 * Add assets for GravityForm form
	 */
	public function wp_add_assets() {
		// Logge den Beginn des Prozesses
		$this->get_logger()->info( 'Starte das Einreihen von Skripten.' );

		// Pfad zum Skript
		$script_url = plugin_dir_url( __FILE__ ) . 'assets/f12-cf7-captcha-gravityforms.js';

		// Logge die Details zum Skript
		$this->get_logger()->debug( 'Skript wird geladen.', [
			'handle'       => 'f12-cf7-captcha-gravityforms',
			'url'          => $script_url,
			'dependencies' => [ 'jquery' ],
		] );

		// Lade das Skript
		wp_enqueue_script( 'f12-cf7-captcha-gravityforms', $script_url, array( 'jquery' ) );

		// Die Daten für die Lokalisierung
		$localization_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		];

		// Logge die Lokalisierungsdaten
		$this->get_logger()->debug( 'Skript wird lokalisiert.', [
			'handle' => 'f12-cf7-captcha-gravityforms',
			'data'   => $localization_data,
		] );

		// Lokalisiere das Skript
		wp_localize_script( 'f12-cf7-captcha-gravityforms', 'f12_cf7_captcha_gravityforms', $localization_data );

		// Logge den erfolgreichen Abschluss
		$this->get_logger()->info( 'Skripte erfolgreich eingereiht und lokalisiert.' );
	}

	public function wp_validation( $validation_result ) {
		$form       = $validation_result['form'];
		$Protection = $this->Controller->get_modul( 'protection' );

		if ( $Protection->is_spam( $_POST ) ) {
			$this->get_logger()->warning( 'Spam erkannt in wp_validation – Formular wird blockiert.' );

			$validation_result['is_valid'] = false;

			// Hole das letzte Feld im Formular
			if ( ! empty( $form['fields'] ) ) {
				$last_index = array_key_last( $form['fields'] );
				if ( $last_index !== null ) {
					$form['fields'][ $last_index ]->failed_validation  = true;
					$form['fields'][ $last_index ]->validation_message = $Protection->get_message()
						?: __( 'Invalid input detected.', 'captcha-for-contact-form-7' );
				}
			}

			$validation_result['form'] = $form;
		}

		return $validation_result;
	}

	/**
	 * Add spam protection to the given content.
	 *
	 * This method adds spam protection to the given content by injecting a captcha field based on the specified
	 * validation method.
	 *
	 * @param mixed ...$args Any number of arguments.
	 *
	 * @return mixed The content with spam protection added.
	 *
	 * @throws \Exception
	 * @since 1.12.2
	 *
	 */
	public function wp_add_spam_protection( ...$args ) {
		// Log the beginning of the process.
		$this->get_logger()->info( 'Starte die Einfügung des Captcha-Codes in ein Gravity Forms-Formular.' );

		$form_string = $args[0];

		// Get the captcha HTML from the protection module.
		$captcha = $this->Controller->get_modul( 'protection' )->get_captcha();
		$this->get_logger()->debug( 'Captcha-Code wurde abgerufen. Größe: ' . strlen( $captcha ) . ' Zeichen.' );

		// Check if the captcha code is empty.
		if ( empty( $captcha ) ) {
			$this->get_logger()->warning( 'Der Captcha-Code ist leer. Es wird nichts zum Formular hinzugefügt.' );

			return $form_string;
		}

		// Check for a specific marker in the form string to place the captcha.
		if ( str_contains( $form_string, "<div class='gform_footer" ) ) {
			// Place the captcha before the form's footer.
			$form_string = str_replace( "<div class='gform_footer", $captcha . "<div class='gform_footer", $form_string );
			$this->get_logger()->info( 'Captcha wurde erfolgreich vor dem Footer eingefügt.' );
		} else {
			// If the marker is not found, append the captcha to the end of the form.
			$form_string .= $captcha;
			$this->get_logger()->warning( 'Kein gform_footer-Marker gefunden. Captcha wurde am Ende des Formulars angehängt.' );
		}

		// Return the modified form string.
		return $form_string;
	}

	/**
	 * Check if a post is considered as spam
	 *
	 * @param bool  $is_spam         Whether the post is considered as spam initially.
	 * @param array $array_post_data The array containing the POST data.
	 *
	 * @return bool Whether the post is considered as spam.
	 */
	public function wp_is_spam( ...$args ) {
		// Log the start of the spam check for Gravity Forms entries.
		$this->get_logger()->info( 'Starte Spam-Überprüfung für Gravity Forms Eintrag.' );

		$is_spam = $args[0];

		// Check if the entry is already marked as spam by another plugin or process.
		if ( $is_spam === true ) {
			$this->get_logger()->notice( 'Eintrag wurde bereits als Spam markiert. Überspringe weitere Überprüfung.' );

			return true;
		}

		// Get the POST data to check for spam.
		$array_post_data = $_POST;
		$this->get_logger()->debug( 'Überprüfe die folgenden POST-Daten auf Spam.', [
			'post_data_keys' => array_keys( $array_post_data ),
		] );

		// Get the protection module.
		$Protection = $this->Controller->get_modul( 'protection' );

		// Perform the spam check using the module.
		if ( $Protection->is_spam( $array_post_data ) ) {
			$message = $Protection->get_message();
			$this->get_logger()->warning( 'Spam erkannt! Markiere den Eintrag als Spam.' );

			// It is recommended to add a custom message or a hook here
			// to provide feedback to the user or admin, as Gravity Forms
			// might not show a specific message by default.
			// For example:
			// do_action('gform_after_spam', $Protection->get_message());

			// Log the final decision to return true.
			$this->get_logger()->critical( 'Spam erkannt. Gebe "true" zurück, um den Eintrag zu blockieren.' );

			return true;
		}

		// If no spam is detected, log the outcome and return the original value.
		$this->get_logger()->info( 'Kein Spam erkannt. Der ursprüngliche Spam-Status bleibt unverändert.' );

		return $is_spam;
	}
}