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

		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		add_action('admin_enqueue_scripts', array($this, 'load_assets'));
		$this->get_logger()->debug('Hook "admin_enqueue_scripts" added for method "load_assets".');

		$this->get_logger()->info('Constructor completed.');
	}

    /**
     * Loads the required assets for the plugin.
     *
     * This method enqueues the script 'f12-cf7-rules-ajax' with the URL to the 'f12-cf7-rules-ajax.js' file
     * located in the 'assets' directory of the plugin. It specifies that the script depends on jQuery,
     * does not specify a version, and should be loaded in the footer of the page.
     *
     * It also localizes the script 'f12-cf7-rules-ajax' by creating the JavaScript object 'f12_cf7_captcha_rules'
     * and setting its 'resturl' and 'restnonce' properties for the REST API.
     *
     * @return void
     */
	public function load_assets()
	{
		$this->get_logger()->info('Loading assets for blacklist synchronization in admin area.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Register and enqueue JavaScript file
		$script_handle = 'f12-cf7-rules-ajax';
		$script_url = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/f12-cf7-rules-ajax.js';
		$dependencies = ['jquery'];
		$version = null; // Or a specific version number
		$in_footer = true;

		wp_enqueue_script($script_handle, $script_url, $dependencies, $version, $in_footer);

		$this->get_logger()->debug('JavaScript file enqueued.', [
			'handle' => $script_handle,
			'url' => $script_url,
		]);

		// Localize data for JavaScript
		$object_name = 'f12_cf7_captcha_rules';
		$localized_data = [
			'resturl'   => rest_url('f12-cf7-captcha/v1/'),
			'restnonce' => wp_create_nonce('wp_rest'),
		];

		wp_localize_script($script_handle, $object_name, $localized_data);

		$this->get_logger()->debug('REST URL localized for JavaScript.', [
			'object_name' => $object_name,
			'resturl' => $localized_data['resturl'],
		]);

		$this->get_logger()->info('Asset loading completed.');
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
		$this->get_logger()->info('Attempting to retrieve blacklist content from external API.');

		//$url = 'https://api.forge12.com/v1/tools/blacklist.txt';
		$url = 'https://api.silentshield.io/api/captcha/blacklist';

		// Execute API request securely via WordPress HTTP API.
		$response = wp_remote_get($url, [
			'timeout' => 3, // Set generous timeout
			'headers' => [
				'Accept' => 'text/plain',
				'User-Agent' => 'CF7-Captcha-Plugin/' . FORGE12_CAPTCHA_VERSION,
			],
		]);

		// Check for WordPress HTTP API errors.
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$this->get_logger()->error('Error retrieving blacklist.', ['error' => $error_message]);
			return '';
		}

		$body = wp_remote_retrieve_body($response);
		$http_code = wp_remote_retrieve_response_code($response);

		// Check HTTP status code.
		if ($http_code !== 200) {
			$this->get_logger()->error('API request failed. Invalid HTTP status code.', [
				'http_code' => $http_code,
			]);
			return '';
		}

		// Check if body is empty.
		if (empty($body)) {
			$this->get_logger()->warning('API response body is empty.');
			return '';
		}

		$this->get_logger()->info('Blacklist content retrieved successfully.', ['content_length' => strlen($body)]);

		return $body;
	}
}
