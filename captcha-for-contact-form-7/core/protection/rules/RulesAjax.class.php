<?php

namespace f12_cf7_captcha\core\protection\rules;
use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Async Task for Rules
 */
class RulesAjax extends BaseModul
{
    /**
     * Constructs an instance of the class.
     *
     * This method registers an action hook that loads the required assets for the admin section.
     *
     * @return void
     */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		add_action('admin_enqueue_scripts', array($this, 'load_assets'));
		$this->get_logger()->debug('Hook "admin_enqueue_scripts" für die Methode "load_assets" hinzugefügt.');

		add_action('wp_ajax_f12_cf7_blacklist_sync', [$this, 'wp_handle_blacklist_sync']);
		$this->get_logger()->debug('Hook "wp_ajax_f12_cf7_blacklist_sync" für AJAX-Anfragen von angemeldeten Benutzern hinzugefügt.');

		add_action('wp_ajax_nopriv_f12_cf7_blacklist_sync', [$this, 'wp_handle_blacklist_sync']);
		$this->get_logger()->debug('Hook "wp_ajax_nopriv_f12_cf7_blacklist_sync" für AJAX-Anfragen von nicht angemeldeten Benutzern hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

    /**
     * Loads the required assets for the plugin.
     *
     * This method enqueues the script 'f12-cf7-rules-ajax' with the URL to the 'f12-cf7-rules-ajax.js' file
     * located in the 'assets' directory of the plugin. It specifies that the script depends on jQuery,
     * does not specify a version, and should be loaded in the footer of the page.
     *
     * It also localizes the script 'f12-cf7-rules-ajax' by creating the JavaScript object 'f12_cf7_captcha_rules'
     * and setting its 'ajaxurl' property to the admin-ajax.php URL.
     *
     * @return void
     */
	public function load_assets()
	{
		$this->get_logger()->info('Lade Assets für die Blacklist-Synchronisierung im Admin-Bereich.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// JavaScript-Datei registrieren und in die Warteschlange stellen
		$script_handle = 'f12-cf7-rules-ajax';
		$script_url = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/f12-cf7-rules-ajax.js';
		$dependencies = ['jquery'];
		$version = null; // Oder eine spezifische Versionsnummer
		$in_footer = true;

		wp_enqueue_script($script_handle, $script_url, $dependencies, $version, $in_footer);

		$this->get_logger()->debug('JavaScript-Datei in die Warteschlange gestellt.', [
			'handle' => $script_handle,
			'url' => $script_url,
		]);

		// Daten für das JavaScript lokalisieren
		$object_name = 'f12_cf7_captcha_rules';
		$localized_data = ['ajaxurl' => admin_url('admin-ajax.php')];

		wp_localize_script($script_handle, $object_name, $localized_data);

		$this->get_logger()->debug('Ajax-URL für JavaScript lokalisiert.', [
			'object_name' => $object_name,
			'ajaxurl' => $localized_data['ajaxurl'],
		]);

		$this->get_logger()->info('Asset-Ladevorgang abgeschlossen.');
	}

    /**
     * Retrieves the content of the blacklist from an API.
     *
     * This method fetches the content of the blacklist from the specified API endpoint and returns it as a string.
     *
     * @return string The content of the blacklist as a string.
     */
	public function get_blacklist_content(): string
	{
		$this->get_logger()->info('Versuche, den Blacklist-Inhalt von der externen API abzurufen.');

		$url = 'https://api.forge12.com/v1/tools/blacklist.txt';

		// Führe die API-Anfrage sicher über die WordPress-HTTP-API aus.
		$response = wp_remote_get($url, [
			'timeout' => 15, // Setze ein großzügiges Timeout
			'headers' => [
				'Accept' => 'text/plain',
				'User-Agent' => 'CF7-Captcha-Plugin/' . F12_CF7_CAPTCHA_VERSION,
			],
		]);

		// Prüfe auf WordPress-HTTP-API-Fehler.
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$this->get_logger()->error('Fehler beim Abruf der Blacklist.', ['error' => $error_message]);
			return '';
		}

		$body = wp_remote_retrieve_body($response);
		$http_code = wp_remote_retrieve_response_code($response);

		// Prüfe den HTTP-Statuscode.
		if ($http_code !== 200) {
			$this->get_logger()->error('API-Anfrage fehlgeschlagen. Ungültiger HTTP-Statuscode.', [
				'http_code' => $http_code,
			]);
			return '';
		}

		// Prüfe, ob der Body leer ist.
		if (empty($body)) {
			$this->get_logger()->warning('Der Body der API-Antwort ist leer.');
			return '';
		}

		$this->get_logger()->info('Blacklist-Inhalt erfolgreich abgerufen.', ['content_length' => strlen($body)]);

		return $body;
	}

    /**
     * Handles the synchronization of the blacklist.
     *
     * This method retrieves the blacklist content using the method get_blacklist_content(),
     * encodes it as JSON using wp_json_encode(), and then echoes the JSON encoded content
     * with the 'value' key. Finally, it terminates the script execution using wp_die().
     *
     * @return void
     */
	public function wp_handle_blacklist_sync(): void
	{
		$this->get_logger()->info('Starte die Handhabung der Blacklist-Synchronisierungsanfrage über AJAX.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			$content = $this->get_blacklist_content();

			if (empty($content)) {
				$this->get_logger()->warning('Kein Blacklist-Inhalt zum Synchronisieren vorhanden.');
				echo wp_json_encode(['value' => '', 'status' => 'error', 'message' => 'Kein Inhalt verfügbar.']);
			} else {
				$this->get_logger()->info('Blacklist-Inhalt erfolgreich abgerufen. Sende ihn als JSON-Antwort.');
				echo wp_json_encode(['value' => $content, 'status' => 'success']);
			}
		} catch (\Exception $e) {
			$this->get_logger()->error('Fehler während der Blacklist-Synchronisierung.', [
				'error_message' => $e->getMessage(),
			]);
			echo wp_json_encode(['value' => '', 'status' => 'error', 'message' => 'Ein interner Fehler ist aufgetreten.']);
		}

		wp_die();
	}
}