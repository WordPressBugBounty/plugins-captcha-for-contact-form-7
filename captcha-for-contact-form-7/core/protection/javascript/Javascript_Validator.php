<?php

namespace f12_cf7_captcha\core\protection\javascript;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

class Javascript_Validator extends BaseProtection
{
    /**
     * @var array<string => float>
     */
    private $start_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * @var array <string => float>
     */
    private $end_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * Private constructor for the class.
     *
     * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
     * This hook is used to retrieve log data.
     */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->init_php();
		$this->init_js();

		add_filter('f12-cf7-captcha-log-data', [$this, 'get_log_data']);

		$this->get_logger()->info('Konstruktor abgeschlossen.', [
			'class' => __CLASS__,
		]);
	}

	protected function is_enabled(): bool
	{
		$is_enabled = $this->Controller->get_settings('protection_javascript_enable', 'global');

		if ($is_enabled === '' || $is_enabled === null) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$is_enabled = 1;
		}

		if ($is_enabled) {
			$this->get_logger()->info('JavaScript-Schutz ist aktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		} else {
			$this->get_logger()->warning('JavaScript-Schutz ist deaktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-javascript', $is_enabled);

		if ($is_enabled && !$result) {
			$this->get_logger()->debug('JavaScript-Schutz wird durch Filter übersprungen.', [
				'filter' => 'f12-cf7-captcha-skip-validation-javascript',
				'original_state' => $is_enabled,
			]);
		}

		return $result;
	}

    /**
     * Add the Timer Data to the Data
     *
     * @param $data
     *
     * @return mixed
     */
	public function get_log_data($data)
	{
		$this->get_logger()->info('Füge Timer-Daten zu den Log-Daten hinzu.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Holen Sie sich die Standarddaten
		$data['Timer Data'] = $this->get_timer_as_string();
		$this->get_logger()->debug('Gesamte Timer-Daten hinzugefügt.', [
			'timer_data' => $data['Timer Data'],
		]);

		// Holen Sie sich die PHP-Daten
		$data['Timer Data PHP'] = $this->get_timer_as_string('php');
		$this->get_logger()->debug('PHP-Timer-Daten hinzugefügt.', [
			'timer_data_php' => $data['Timer Data PHP'],
		]);

		// Holen Sie sich die JS-Daten
		$data['Timer Data JS'] = $this->get_timer_as_string('js');
		$this->get_logger()->debug('JS-Timer-Daten hinzugefügt.', [
			'timer_data_js' => $data['Timer Data JS'],
		]);

		$this->get_logger()->info('Log-Daten-Array vervollständigt.', [
			'final_data_keys' => array_keys($data),
		]);

		return $data;
	}

    /**
     * Initializes JavaScript variables for tracking form submission times.
     *
     * This method initializes JavaScript variables by extracting start and end times from the $_POST array
     * and sets the start and end times for tracking form submission times.
     */
	public function init_js()
	{
		$this->get_logger()->info('Initialisiere JavaScript-Timer-Daten.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$start = 0.0;
		$end = 0.0;

		// Standard-Verarbeitung
		if (isset($_POST['js_start_time']) && isset($_POST['js_end_time'])) {
			$start = (float)$_POST['js_start_time'];
			$end = (float)$_POST['js_end_time'];
			$this->get_logger()->debug('Standard-JS-Timer-Daten aus $_POST gefunden.', [
				'start' => $start,
				'end' => $end,
			]);
		}

		// Spezifische Avada-Verarbeitung
		if (isset($_POST['formData']) && !is_array($_POST['formData'])) {
			$this->get_logger()->debug('Avada-spezifische FormData-Struktur erkannt.');
			parse_str(wp_unslash($_POST['formData']), $form_data);

			if (isset($form_data['js_start_time']) && isset($form_data['js_end_time'])) {
				$start = (float)$form_data['js_start_time'];
				$end = (float)$form_data['js_end_time'];
				$this->get_logger()->debug('JS-Timer-Daten aus Avada-FormData gefunden.', [
					'start' => $start,
					'end' => $end,
				]);
			}
		}

		// Spezifische Fluent Forms-Verarbeitung
		if (isset($_POST['data']) && defined('FLUENTFORM') && is_string($_POST['data'])) {
			$this->get_logger()->debug('Fluent Forms-spezifische Datenstruktur erkannt.');
			$decodedFormData = urldecode($_POST['data']);
			parse_str($decodedFormData, $form_data);

			if (isset($form_data['js_start_time'])) {
				$start = (float)$form_data['js_start_time'];
				$this->get_logger()->debug('JS-Start-Zeit aus Fluent Forms-Daten gefunden.', ['start' => $start]);
			}

			if (isset($form_data['js_end_time'])) {
				$end = (float)$form_data['js_end_time'];
				$this->get_logger()->debug('JS-End-Zeit aus Fluent Forms-Daten gefunden.', ['end' => $end]);
			}
		}

		$this->set_start_time('js', $start);
		$this->set_end_time('js', $end);

		$this->get_logger()->info('JavaScript-Timer-Daten erfolgreich gesetzt.', [
			'js_start' => $this->get_start_time('js'),
			'js_end' => $this->get_end_time('js'),
		]);
	}

    /**
     * @param string $type php or js
     * @param float  $microtime
     *
     * @return void
     */
	private function set_start_time(string $type, float $microtime)
	{
		$this->get_logger()->debug("Setze Startzeit für den Typ '{$type}'.", [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'type'      => $type,
			'microtime' => $microtime,
		]);

		$this->start_time[$type] = $microtime;
	}

    /**
     * @param string $type php or js
     * @param float  $microtime
     *
     * @return void
     */
	private function set_end_time(string $type, float $microtime)
	{
		$this->get_logger()->debug("Setze Endzeit für den Typ '{$type}'.", [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'type'      => $type,
			'microtime' => $microtime,
		]);

		$this->end_time[$type] = $microtime;
	}

    /**
     * Initializes the PHP start time for form processing.
     *
     * This method retrieves the PHP start time from the request data and sets it for form processing.
     * It first checks if the 'php_start_time' parameter is set in the $_POST superglobal array.
     * If found, it assigns the float value of the parameter to the local variable $start.
     *
     * If the 'php_start_time' parameter is not found in the $_POST array,
     * it checks if the 'formData' parameter is set in the $_POST superglobal array.
     * If found, it extracts and assigns the 'php_start_time' parameter value from the 'formData' using parse_str
     * and wp_unslash.
     *
     * Finally, it calls the 'set_start_time' method of the current object to set the PHP start time.
     * If the $start value is not equal to 0.0, it also calls the 'set_end_time' method to set the PHP end time.
     * If the $start value is equal to 0.0, it calls the 'set_start_time' method to set the PHP start time using
     * microtime.
     *
     * @return void
     */
	private function init_php()
	{
		$this->get_logger()->info('Initialisiere PHP-Timer-Daten.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$start = 0.0;

		// Standard-Verarbeitung (z.B. für Contact Form 7)
		if (isset($_POST['php_start_time'])) {
			$start = (float)$_POST['php_start_time'];
			$this->get_logger()->debug('Standard-PHP-Startzeit aus $_POST gefunden.', [
				'start' => $start,
			]);
		}

		// Spezifische Avada-Verarbeitung
		if (isset($_POST['formData'])) {
			$this->get_logger()->debug('Avada-spezifische FormData-Struktur erkannt.');
			parse_str(wp_unslash($_POST['formData']), $form_data);

			if (isset($form_data['php_start_time'])) {
				$start = (float)$form_data['php_start_time'];
				$this->get_logger()->debug('PHP-Startzeit aus Avada-FormData gefunden.', [
					'start' => $start,
				]);
			}
		}

		// Spezifische Fluent Forms-Verarbeitung
		if (isset($_POST['data']) && defined('FLUENTFORM') && is_string($_POST['data'])) {
			$this->get_logger()->debug('Fluent Forms-spezifische Datenstruktur erkannt.');
			$decodedFormData = urldecode($_POST['data']);
			parse_str($decodedFormData, $form_data);

			if (isset($form_data['php_start_time'])) {
				$start = (float)$form_data['php_start_time'];
				$this->get_logger()->debug('PHP-Startzeit aus Fluent Forms-Daten gefunden.', ['start' => $start]);
			}
		}

		$this->set_start_time('php', $start);

		if ($start != 0.0) {
			$this->get_logger()->debug('PHP-Startzeit existiert. Setze Endzeit.');
			$this->set_end_time('php', microtime(true));
		} else {
			$this->get_logger()->debug('PHP-Startzeit fehlt. Setze aktuelle Zeit als Startzeit.');
			$this->set_start_time('php', microtime(true));
		}

		$this->get_logger()->info('PHP-Timer-Daten erfolgreich gesetzt.', [
			'php_start' => $this->get_start_time('php'),
			'php_end' => $this->get_end_time('php'),
		]);
	}

    /**
     * Retrieves additional form fields for the current form.
     *
     * This method generates HTML code for additional form fields that should be included in the form.
     *
     * @return string The additional form fields HTML code.
     */
	public function get_form_field(): string
	{
		$this->get_logger()->info('Generiere verborgene Formularfelder für den Timer.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Timer-Felder werden nicht generiert, da der JavaScript-Schutz deaktiviert ist.');
			return '';
		}

		$time = $this->get_start_time('php');
		$this->get_logger()->debug('PHP-Startzeit-Wert: ' . $time);

		$additional_fields = [
			'<input type="hidden" name="php_start_time" value="' . esc_attr($time) . '" />',
			'<input type="hidden" name="js_end_time" class="js_end_time" value="" />',
			'<input type="hidden" name="js_start_time" class="js_start_time" value="" />'
		];

		$output = implode("", $additional_fields);

		$this->get_logger()->debug('Generierte Formularfelder zurückgegeben.', [
			'output_length' => strlen($output),
		]);

		return $output;
	}

    /**
     * Retrieves the CAPTCHA field for the current form.
     *
     * This method generates the CAPTCHA field HTML code that should be included in the form.
     *
     * @param mixed ...$args Optional arguments.
     *
     * @return string The CAPTCHA field HTML code.
     */
	public function get_captcha(...$args): string
	{
		$this->get_logger()->info('Versuche, das Captcha-Formularfeld zu generieren.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha-Ausgabe übersprungen, da der JavaScript-Schutz deaktiviert ist.');
			return '';
		}

		$form_field_html = $this->get_form_field();

		if (empty($form_field_html)) {
			$this->get_logger()->error('Die Generierung des Formularfeldes ist fehlgeschlagen.', [
				'class' => __CLASS__,
			]);
			// Optional: Ein Fallback-Wert oder eine Fehlermeldung
		} else {
			$this->get_logger()->debug('Captcha-Formularfeld erfolgreich generiert.', [
				'html_length' => strlen($form_field_html),
			]);
		}

		return $form_field_html;
	}

    /**
     * Retrieves the start time for a given type.
     *
     * This method returns the start time for a specified type. The default type is 'php'.
     *
     * @param string $type The type of start time to retrieve. Default is 'php'.
     *
     * @return float The start time for the specified type.
     */
	private function get_start_time(string $type = 'php'): float
	{
		if (!isset($this->start_time[$type])) {
			$this->get_logger()->error("Fehler: Startzeit-Typ '{$type}' nicht gefunden.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'available_types' => array_keys($this->start_time),
			]);
			return 0.0;
		}

		$this->get_logger()->debug("Startzeit für Typ '{$type}' abgerufen: " . $this->start_time[$type], [
			'type' => $type,
		]);

		return $this->start_time[$type];
	}

    /**
     * Retrieves the end time for a given type.
     *
     * This method returns the end time for the specified type. The default type is 'php'.
     *
     * @param string $type (optional) The type of end time to retrieve. Defaults to 'php'.
     *
     * @return float The end time for the specified type.
     */
	private function get_end_time(string $type = 'php'): float
	{
		if (!isset($this->end_time[$type])) {
			$this->get_logger()->error("Fehler: Endzeit-Typ '{$type}' nicht gefunden.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'available_types' => array_keys($this->end_time),
			]);
			return 0.0;
		}

		$this->get_logger()->debug("Endzeit für Typ '{$type}' abgerufen: " . $this->end_time[$type], [
			'type' => $type,
		]);

		return $this->end_time[$type];
	}

    /**
     * @param string $type   php or js
     * @param string $output ms for milliseconds, s for seconds
     *
     * @return string
     */
	private function get_difference(string $type = 'php', string $output = 'ms'): string
	{
		$this->get_logger()->info("Berechne die Zeitdifferenz für Typ '{$type}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'output_format' => $output,
		]);

		$start_time = $this->get_start_time($type);
		$end_time = $this->get_end_time($type);

		$difference = $end_time - $start_time;

		$this->get_logger()->debug("Roh-Zeitdifferenz: " . $difference . " Sekunden.", [
			'start_time' => $start_time,
			'end_time' => $end_time,
		]);

		if ($output === 'ms') {
			$result = round($difference * 1000);
			$this->get_logger()->debug('Zeit in Millisekunden umgerechnet.', [
				'result_ms' => $result,
			]);
			return (string)$result;
		}

		$result = round($difference);
		$this->get_logger()->debug('Zeit in Sekunden gerundet.', [
			'result_s' => $result,
		]);
		return (string)$result;
	}

    /**
     * Retrieves the timer information as a formatted string.
     *
     * This method retrieves the start time, end time, and time passed and formats them into a string.
     *
     * @param string $type (optional) The type of timer to retrieve. Default is 'php'.
     *
     * @return string The timer information as a formatted string.
     */
	private function get_timer_as_string(string $type = 'php'): string
	{
		$this->get_logger()->info("Erzeuge eine formatierte Zeitangabe als Zeichenkette für Typ '{$type}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$start_time = $this->get_start_time($type);
		$end_time = $this->get_end_time($type);

		if ($start_time === 0.0 || $end_time === 0.0) {
			$this->get_logger()->warning('Zeitdaten fehlen oder sind ungültig. Kann die Zeichenkette nicht formatieren.', [
				'start_time' => $start_time,
				'end_time' => $end_time,
			]);
			return 'Zeitdaten nicht verfügbar.';
		}

		$data = [
			'Formular geladen' => date('d.m.Y H:i:s', (int)$start_time) . ' [' . $start_time . ']',
			'Formular gesendet' => date('d.m.Y H:i:s', (int)$end_time) . ' [' . $end_time . ']',
			'Verstrichene Zeit' => $this->get_difference($type) . ' ms, ' . $this->get_difference($type, 's') . ' s',
		];

		$response = '';
		foreach ($data as $key => $value) {
			$response .= $key . ': ' . $value . ', ';
		}

		$response = rtrim($response, ', '); // Entfernt das letzte Komma und Leerzeichen

		$this->get_logger()->debug("Formatierte Zeitzeichenkette erstellt.", [
			'output' => $response,
		]);

		return $response;
	}

    /**
     * Check if the user is a human.
     *
     * @return bool
     */
	public function is_human(): bool
	{
		$this->get_logger()->info('Führe JavaScript-basierte Human-Überprüfung durch.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// Überprüfe die Differenz der JavaScript-Zeiten
		$js_difference = $this->get_difference('js');
		if ((string)$js_difference === '0' || (string)$js_difference === '0.0') {
			$this->get_logger()->warning('JS-Zeitdifferenz ist Null. Möglicherweise ein Bot oder technisches Problem.');
			return false;
		}

		// Überprüfe, ob die Startzeit erfasst wurde
		if ($this->get_start_time('js') == 0.0) {
			$this->get_logger()->warning('JS-Startzeit wurde nicht erfasst.');
			return false;
		}

		// Überprüfe, ob die Endzeit erfasst wurde
		if ($this->get_end_time('js') == 0.0) {
			$this->get_logger()->warning('JS-Endzeit wurde nicht erfasst.');
			return false;
		}

		$this->get_logger()->info('JavaScript-basierte Überprüfung erfolgreich. Als Mensch eingestuft.');

		return true;
	}

    /**
     * Determines if the submitted form is considered spam.
     *
     * This method checks if the submitted form is spam based on certain criteria.
     *
     * @return bool Returns true if the form is considered spam, false otherwise.
     */
	public function is_spam(): bool
	{
		$this->get_logger()->info('Führe Spam-Überprüfung durch.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam-Check übersprungen: JavaScript-Schutz ist deaktiviert.', [
				'class' => __CLASS__,
			]);
			return false;
		}

		if (!$this->is_human()) {
			$this->get_logger()->warning('Formular als Spam eingestuft: JavaScript-Validierung fehlgeschlagen.', [
				'class' => __CLASS__,
			]);
			$this->set_message(__('javascript-protection', 'captcha-for-contact-form-7'));
			return true;
		}

		$this->get_logger()->info('Formular als nicht-Spam eingestuft.');

		return false;
	}

	public function success(): void
	{
		$this->get_logger()->info('Erfolgreiche Formularübermittlung erkannt.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Hier kann zusätzliche Logik implementiert werden,
		// die bei einer erfolgreichen Validierung ausgeführt werden soll.
		// Zum Beispiel:
		// - Löschen temporärer Daten
		// - Senden einer Benachrichtigung
		// - Aktualisieren von Zählern

		// TODO: Implementieren Sie die Erfolg-Logik hier.
	}
}