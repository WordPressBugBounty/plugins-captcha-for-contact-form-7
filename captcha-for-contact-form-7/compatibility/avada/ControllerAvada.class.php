<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerAvada
 */
class ControllerAvada extends BaseController {
	/**
	 * @var string
	 */
	protected string $name = 'Avada';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = 'avada';

	/**
	 * Check if Avada captcha integration is enabled
	 *
	 * @return bool Returns true if Avada captcha integration is enabled, false otherwise
	 * @throws \Exception
	 */
	public function is_enabled(): bool {
		$this->get_logger()->info( 'Überprüfe, ob die Avada-Kompatibilität aktiviert ist.', [

			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Überprüfe, ob das Avada-Plugin installiert ist.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug( 'Installationsstatus des Moduls: ' . ( $is_installed ? 'Installiert' : 'Nicht installiert' ) );

		// Rufe den Aktivierungsstatus aus den globalen Einstellungen ab.
		$setting_value = $this->Controller->get_settings( 'protection_avada_enable', 'global' );
		$this->get_logger()->debug( 'Wert der Einstellung "protection_avada_enable": ' . $setting_value );

		if ($setting_value === '' || $setting_value === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_avada_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		// Die Kompatibilität ist nur aktiviert, wenn beide Bedingungen erfüllt sind.
		$is_active = $is_installed && ( (int) $setting_value === 1 );

		$this->get_logger()->debug( 'Modulstatus vor dem Filter: ' . ( $is_active ? 'Aktiv' : 'Inaktiv' ) );

		// Wende einen Filter an, um anderen Entwicklern die Möglichkeit zu geben, das Verhalten zu überschreiben.
		// Der Filter 'f12_cf7_captcha_is_installed_avada' erlaubt es, den Aktivierungsstatus
		// von außen zu modifizieren, bevor er zurückgegeben wird.
		$result = apply_filters( 'f12_cf7_captcha_is_installed_avada', $is_active );

		$this->get_logger()->info( 'Endgültiger Status nach dem Filter: ' . ( $result ? 'Aktiv' : 'Inaktiv' ) );

		return $result;
	}

	/**
	 * Checks if Avada theme is installed.
	 *
	 * @return bool Returns true if Avada theme is installed, false otherwise.
	 */
	public function is_installed(): bool {
		$this->get_logger()->info( 'Überprüfe, ob das Avada-Theme installiert ist.', [
			'plugin' => 'f12-cf7-captcha',
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Die Methode `function_exists()` ist die Standard-WordPress-Funktion,
		// um zu überprüfen, ob eine Funktion (und somit ein Theme oder Plugin) geladen wurde.
		// 'Avada' ist hier der spezifische Name einer Funktion, die vom Avada-Theme bereitgestellt wird.
		$is_installed = function_exists( 'Avada' );

		if ( $is_installed ) {
			$this->get_logger()->info( 'Das Avada-Theme wurde erfolgreich erkannt.' );
		} else {
			$this->get_logger()->info( 'Das Avada-Theme ist nicht installiert oder nicht aktiv.' );
		}

		// Gib den booleschen Wert zurück, der angibt, ob die Funktion existiert.
		return $is_installed;
	}

	/**
	 * Initialize Avada settings and validators
	 */
	protected function on_init(): void {
		// Sets the name of the component for the UI. The name is translatable.
		$this->name = __( 'Avada', 'captcha-for-contact-form-7' );
		$this->get_logger()->info( 'Komponentenname auf "Avada" gesetzt.' );

		// Adds a filter to the Avada form content to insert spam protection fields.
		// The filter `fusion_element_form_content` is a specific hook provided by the Avada theme.
		// The `wp_add_spam_protection` method of this class will be called with a priority of 10.
		add_filter( 'fusion_element_form_content', [ $this, 'wp_add_spam_protection' ], 10, 2 );
		$this->get_logger()->debug( 'Filter "fusion_element_form_content" für die Spam-Schutz-Felder registriert.' );

		// Adds a filter to check if a form submission is spam.
		// The filter `fusion_form_demo_mode` is used by Avada to check form validity.
		// The `wp_is_spam` method of this class will be called to perform the spam check.
		add_filter( 'fusion_form_demo_mode', [ $this, 'wp_is_spam' ], 10, 1 );
		$this->get_logger()->debug( 'Filter "fusion_form_demo_mode" für die Spam-Prüfung registriert.' );

		// Adds an action to enqueue the necessary scripts and styles for the spam protection.
		// This hook is used to add assets to the front-end of the website.
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_add_assets' ) );
		$this->get_logger()->debug( 'Action "wp_enqueue_scripts" zum Laden der Assets registriert.' );

		$this->get_logger()->info( 'Initialisierung der Avada-Komponente abgeschlossen. Hooks wurden registriert.' );
	}

	/**
	 * Adds spam protection to the given HTML form
	 *
	 * @param string $html    The HTML form content
	 * @param mixed  ...$args Additional arguments (not used in this method)
	 *
	 * @return string The modified HTML form content with spam protection added
	 */
	public function wp_add_spam_protection( string $html ): string {
		$this->get_logger()->info( 'Füge Spam-Schutz-Elemente zum Avada-Formular-HTML hinzu.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		// Rufe die HTML-Struktur des Captchas ab.
		$captcha_html = $this->Controller->get_modul( 'protection' )->get_captcha();
		$this->get_logger()->debug( 'Captcha-HTML abgerufen.', [ 'html_length' => strlen( $captcha_html ) ] );

		// Standardposition für das Captcha. Dies kann in zukünftigen Versionen konfigurierbar gemacht werden.
		$position = 'before_submit';

		$is_captcha_added = false;

		// Füge das Captcha vor dem Absende-Button hinzu.
		if ( $position === 'before_submit' && str_contains( $html, '<div class="fusion-form-field fusion-form-submit-field' ) ) {
			$html             = str_replace(
				'<div class="fusion-form-field fusion-form-submit-field',
				$captcha_html . '<div class="fusion-form-field fusion-form-submit-field',
				$html
			);
			$is_captcha_added = true;
			$this->get_logger()->info( 'Captcha vor dem Absende-Button eingefügt.' );
		}

		// Füge das Captcha vor dem schließenden </form>-Tag hinzu.
		if ( ! $is_captcha_added && str_contains( $html, '</form>' ) ) {
			$html             = str_replace(
				'</form>',
				$captcha_html . '</form>',
				$html
			);
			$is_captcha_added = true;
			$this->get_logger()->info( 'Captcha vor dem schließenden Formular-Tag eingefügt.' );
		}

		// Fallback: Füge das Captcha am Ende des HTML-Strings hinzu, wenn keine spezifische Position gefunden wurde.
		if ( ! $is_captcha_added ) {
			$html .= $captcha_html;
			$this->get_logger()->info( 'Fallback: Captcha am Ende des Formulars eingefügt.' );
		}

		$this->get_logger()->info( 'Spam-Schutz-Elemente erfolgreich hinzugefügt. Gebe das modifizierte HTML zurück.' );

		return $html;
	}

	/**
	 * Converts form data to an associative array
	 *
	 * @param string $data The form data to be converted
	 *
	 * @return array The associative array representation of the form data
	 */
	protected function form_data_to_arary( string $data ): array {
		// Logge den Beginn des Prozesses
		$this->get_logger()->info( 'Konvertiere Formulardaten-String in ein Array.', [
			'class'        => __CLASS__,
			'method'       => __METHOD__,
			'input_length' => strlen( $data ),
		] );

		// Entfernt Slashes (\\) aus dem String, die von der WordPress-Funktion `addslashes`
		// oder ähnlichen Mechanismen zur Sicherheits- oder Escaping-Zwecken hinzugefügt wurden.
		// Der Code ignoriert PHPCS-Warnungen für fehlende Nonce-Überprüfung und Sanitisierung,
		// was in einer realen Anwendung ein potenzielles Sicherheitsrisiko darstellen könnte.
		$unslashed_data = wp_unslash( $data );
		$this->get_logger()->debug( 'Input-String mit wp_unslash() bereinigt.' );

		$value = [];
		// Die PHP-Funktion `parse_str()` zerlegt den URL-kodierten Abfragestring und
		// befüllt das `value`-Array mit den Schlüssel-Wert-Paaren.
		// Beispiel: "firstName=John&lastName=Doe" wird zu `['firstName' => 'John', 'lastName' => 'Doe']`.
		parse_str( $unslashed_data, $value );

		// Logge den Abschluss des Prozesses und das Ergebnis
		$this->get_logger()->info( 'Konvertierung erfolgreich. Gebe Array zurück.', [
			'array_keys' => array_keys( $value ),
			'array_size' => count( $value ),
		] );

		// Gib das resultierende Array zurück.
		return $value;
	}

	/**
	 * Checks if the submitted form data is considered as spam
	 *
	 * @param mixed ...$args The arguments passed to the function (variadic)
	 *
	 * @return mixed The original value if the form data is not spam, otherwise does not return anything
	 */
	public function wp_is_spam( ...$args ) {
		$this->get_logger()->info( 'Starte die Spam-Überprüfung.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		] );

		$value = $args[0];

		if ( ! isset( $_POST['formData'] ) ) {
			$this->get_logger()->notice( 'Keine Formulardaten gefunden. Breche Spam-Überprüfung ab.' );

			return false;
		}

		// Formulardaten als Array
		$array_post_data = $this->form_data_to_arary( $_POST['formData'] );

		/** @var Protection $Protection */
		$Protection = $this->Controller->get_modul( 'protection' );

		if ( ! $Protection->is_spam( $array_post_data ) ) {
			$this->get_logger()->info( 'Kein Spam erkannt. Gebe ursprünglichen Wert zurück.', [
				'return_value' => $value,
			] );

			return $value;
		}

		// Spam erkannt
		$message = $Protection->get_message() ?: __( 'Invalid input detected.', 'captcha-for-contact-form-7' );
		$this->get_logger()->warning( 'Spam erkannt! Fehler wird ans letzte sichtbare Feld angehängt.', [
			'spam_message' => $message,
		] );

		// Finde das letzte sichtbare Feld
		parse_str( $_POST['formData'], $fields_array );

		$last_visible_key = null;
		$skip_patterns    = [
			'hidden',
			'nonce',
			'submit',
			'fusion-fields-hold-private-data',
			'form-id',
			'f12_multiple_submission_protection',
			'js_start_time',
			'js_end_time',
			'php_start_time',
			'php_end_time'
		];

		foreach ( array_reverse( $fields_array, true ) as $key => $val ) {
			$skip = false;
			foreach ( $skip_patterns as $pattern ) {
				if ( stripos( $key, $pattern ) !== false ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$last_visible_key = $key;
			break;
		}

		if ( ! $last_visible_key ) {
			$last_visible_key = 'general'; // Fallback
		}

		wp_send_json( [
			'status' => 'error',
			'errors' => [
				$last_visible_key => $message,
			],
		] );
	}

	/**
	 * Add assets for Avada form
	 */
	public function wp_add_assets() {
		// Logge den Beginn des Prozesses
		$this->get_logger()->info( 'Starte das Einreihen von Skripten.' );

		// Pfad zum Skript
		$script_url = plugin_dir_url( __FILE__ ) . 'assets/f12-cf7-captcha-avada.js';

		// Logge die Details zum Skript
		$this->get_logger()->debug( 'Skript wird geladen.', [
			'handle'       => 'f12-cf7-captcha-avada',
			'url'          => $script_url,
			'dependencies' => [ 'jquery' ],
		] );

		// Lade das Skript
		wp_enqueue_script( 'f12-cf7-captcha-avada', $script_url, array( 'jquery' ) );

		// Die Daten für die Lokalisierung
		$localization_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		];

		// Logge die Lokalisierungsdaten
		$this->get_logger()->debug( 'Skript wird lokalisiert.', [
			'handle' => 'f12-cf7-captcha-avada',
			'data'   => $localization_data,
		] );

		// Lokalisiere das Skript
		wp_localize_script( 'f12-cf7-captcha-avada', 'f12_cf7_captcha_avada', $localization_data );

		// Logge den erfolgreichen Abschluss
		$this->get_logger()->info( 'Skripte erfolgreich eingereiht und lokalisiert.' );
	}
}
