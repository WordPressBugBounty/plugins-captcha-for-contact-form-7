<?php

namespace f12_cf7_captcha\core\protection\browser;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Browser extends BaseProtection {
	/**
	 * Array variable to store browser names.
	 *
	 * @var array $browser_names
	 */
	private $browser_names = [];
	/**
	 * Array variable to store browser regular expressions.
	 *
	 * @var array $browser_regexes
	 */
	private $browser_regexes = [];
	/**
	 * Array variable to store platform names.
	 *
	 * @var array $platform_names
	 */
	private $platform_names = [];
	/**
	 * Array variable to store platform regular expressions.
	 *
	 * @var array $platform_regexes
	 */
	private $platform_regexes = [];
	/**
	 * Array variable to store device type names.
	 *
	 * @var array $device_type_names
	 */
	private $device_type_names = [];
	/**
	 * Array variable to store device type regular expressions.
	 *
	 * @var array $device_type_regexes
	 */
	private $device_type_regexes = [];

	/**
	 * Constructor method for the class.
	 *
	 * This method loads a file called "Browser_User_Agent.php" and sets the class variables:
	 *
	 * - `$browser_names`
	 * - `$browser_regexes`
	 * - `$platform_names`
	 * - `$platform_regexes`
	 * - `$device_type_names`
	 * - `$device_type_regexes`
	 *
	 * It also adds a filter to the "f12-cf7-captcha-log-data" hook, using the class method "get_log_data".
	 *
	 * @return void
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		try {
			require( 'Browser_User_Agent.php' );

			$this->browser_names       = $browser_names ?? [];
			$this->browser_regexes     = $browser_regexes ?? [];
			$this->platform_names      = $platform_names ?? [];
			$this->platform_regexes    = $platform_regexes ?? [];
			$this->device_type_names   = $device_type_names ?? [];
			$this->device_type_regexes = $device_type_regexes ?? [];

			$this->get_logger()->info("Browser user agent data loaded", [
				'plugin'    => 'f12-cf7-captcha',
				'browsers'  => count($this->browser_names),
				'platforms' => count($this->platform_names),
				'devices'   => count($this->device_type_names)
			]);
		} catch (\Throwable $e) {
			$this->get_logger()->error("Error loading Browser_User_Agent.php", [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $e->getMessage()
			]);
			throw $e;
		}

		add_filter( 'f12-cf7-captcha-log-data', [ $this, 'get_log_data' ] );
	}


	/**
	 * Checks if browser protection is enabled.
	 *
	 *
	 * @return bool Returns true if browser protection is enabled; otherwise, false.
	 */
	protected function is_enabled(): bool {
		$raw_setting = $this->Controller->get_settings( 'protection_browser_enable', 'global' );

		if ($raw_setting === '' || $raw_setting === null) {
			// Default: active if not explicitly set
			$raw_setting = 1;
		}

		$is_enabled = apply_filters( 'f12-cf7-captcha-skip-validation-browser', $raw_setting );

		$this->get_logger()->debug("Browser protection status checked", [
			'plugin'      => 'f12-cf7-captcha',
			'raw_setting' => $raw_setting,
			'final_value' => $is_enabled
		]);

		return $is_enabled;
	}


	/**
	 * Retrieves the spam protection code.
	 *
	 * @return string Returns the spam protection code.
	 */
	public function get_captcha( ...$args ): string {
		$this->get_logger()->debug("get_captcha() called", [
			'plugin' => 'f12-cf7-captcha',
			'args'   => $args,
			'protection' => 'Browser'
		]);

		// Currently no captcha implemented yet
		return '';
	}


	/**
	 * Get the log data.
	 *
	 * This method takes in an array of data and adds the browser data and header data to it.
	 *
	 * The browser data is obtained by using the "get_browser_as_string" method of the class and is added to the
	 * array under the key "Browser Data".
	 *
	 * The header data is obtained by using the "get_headers_as_string" method of the class and is added to the
	 * array under the key "Header Data".
	 *
	 * @param array $data The input data array.
	 *
	 * @return array The modified data array with added browser data and header data.
	 */
	public function get_log_data( $data ): array {
		/*
		 * Get the Browser Data
		 */
		$data['Browser Data'] = $this->get_browser_as_string();

		/*
		 * Get the Header Data
		 */
		$data['Header Data'] = $this->get_headers_as_string();

		$this->get_logger()->debug("Additional log data collected", [
			'plugin'       => 'f12-cf7-captcha',
			'browser_data' => mb_substr($data['Browser Data'] ?? '', 0, 80) . (strlen($data['Browser Data'] ?? '') > 80 ? '...' : ''),
			'header_data'  => mb_substr($data['Header Data'] ?? '', 0, 80) . (strlen($data['Header Data'] ?? '') > 80 ? '...' : '')
		]);

		return $data;
	}


	/**
	 * Retrieves the user agent string from the $_SERVER superglobal.
	 *
	 * If the user agent string is not set in $_SERVER['HTTP_USER_AGENT'], an empty string is returned.
	 *
	 * @return string The user agent string.
	 */
	private function get_user_agent(): string {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$this->get_logger()->debug("No user agent found", [
				'plugin' => 'f12-cf7-captcha'
			]);
			return '';
		}

		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		$this->get_logger()->debug("User agent read", [
			'plugin'  => 'f12-cf7-captcha',
			'preview' => mb_substr($ua, 0, 120) . (strlen($ua) > 120 ? '...' : '')
		]);

		return $ua;
	}


	/**
	 * Checks if the given browser data matches the default settings.
	 *
	 * @formatter:off
		 *
		 * @param array $browser_data {
		 *      The browser data to compare against the default settings.
		 *
		 *      @type string    $browser_name       The name of the Browser
		 *      @type string    $browser_version    The version of the Browser
		 *      @type string    $platform_name      The name of the platform
		 *      @type string    $device_type_name   The name of the device
		 *      @type bool      $is_mobile          Indicates whether the device is mobile. Default: false
		 * }
		 *
		 * @formatter:on
	 *
	 * @return bool Returns true if the given browser data matches the default settings; otherwise, false.
	 */
	private function is_default( $browser_data ): bool {
		$default_browser_data = [
			'browser_name'     => '',
			'browser_version'  => '',
			'platform_name'    => '',
			'device_type_name' => '',
			'is_mobile'        => false,
		];

		$is_default = empty( array_diff_assoc( $browser_data, $default_browser_data ) );

		$this->get_logger()->debug("Browser data checked", [
			'plugin'      => 'f12-cf7-captcha',
			'is_default'  => $is_default,
			'browser'     => $browser_data['browser_name'] ?? '',
			'platform'    => $browser_data['platform_name'] ?? '',
			'device_type' => $browser_data['device_type_name'] ?? '',
			'is_mobile'   => $browser_data['is_mobile'] ?? false,
		]);

		return $is_default;
	}


	/**
	 * Retrieves the headers as a formatted string.
	 *
	 * @return string The headers as a formatted string.
	 */
	private function get_headers_as_string(): string {
		$header_data = $this->get_headers();

		$sanitized = [];
		foreach ( $header_data as $key => $value ) {
			$lower = strtolower($key);

			// Mask sensitive headers
			if (in_array($lower, ['cookie', 'authorization', 'php_auth_pw'])) {
				$value = '[masked]';
			}

			$sanitized[$key] = $value;
		}

		$response = '';
		foreach ( $sanitized as $key => $value ) {
			$response .= $key . ':' . $value . ',';
		}

		// Logging with truncated preview
		$this->get_logger()->debug("Headers collected", [
			'plugin'  => 'f12-cf7-captcha',
			'preview' => mb_substr($response, 0, 150) . (strlen($response) > 150 ? '...' : '')
		]);

		return $response;
	}

	/**
	 * Retrieves the headers from the request.
	 *
	 * @return array Returns an array containing the required headers from the request.
	 */
	private function get_headers(): array {
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
		} else {
			$headers = [];
		}

		$required_headers = array(
			'Accept',
			'Accept-Charset',
			'Accept-Encoding',
			'Accept-Language',
			'Connection',
			//'Host',
			//'Referer',
			'User-Agent',
		);

		$header_data = [];

		foreach ( $required_headers as $header ) {
			if ( isset( $headers[ $header ] ) ) {
				$header_data[ $header ] = $headers[ $header ];
			}
		}

		// Add logging
		$this->get_logger()->debug("Headers collected", [
			'plugin'       => 'f12-cf7-captcha',
			'header_count' => count($header_data),
			'headers'      => array_map(function($v) {
				return mb_substr($v, 0, 60) . (strlen($v) > 60 ? '...' : '');
			}, $header_data)
		]);

		return $header_data;
	}



	/**
	 * Returns the browser data as a string.
	 *
	 * @return string Returns a string representation of the browser data in the following format:
	 *                'key1:value1,key2:value2,key3:value3,...'
	 */
	private function get_browser_as_string(): string {
		$browser_data = $this->get_browser();

		$response = '';
		foreach ($browser_data as $key => $value) {
			$response .= $key . ':' . $value . ',';
		}

		// Check if only default data is present
		$is_default = $this->is_default($browser_data);

		$this->get_logger()->debug("Browser data collected", [
			'plugin'      => 'f12-cf7-captcha',
			'is_default'  => $is_default,
			'preview'     => mb_substr($response, 0, 120) . (strlen($response) > 120 ? '...' : '')
		]);

		return $response;
	}


	/**
	 * Retrieves browser data based on the given user agent.
	 *
	 * If no user agent is provided, the method will attempt to retrieve it using the get_user_agent() method.
	 *
	 * @param string            $user_agent         The user agent to retrieve browser data from. Default: ''
	 *
	 * @formatter:off
         *
		 * @return array {
		 *      The browser data array containing the following keys
         *
		 *      @type string    $browser_name       The name of the Browser
		 *      @type string    $browser_version    The version of the Browser
		 *      @type string    $platform_name      The name of the platform
		 *      @type string    $device_type_name   The name of the device
		 *      @type bool      $is_mobile          Indicates whether the device is mobile. Default: false
		 * }
         *
		 * @formatter:on
	 */
	private function get_browser( $user_agent = '' ): array {
		if ( empty( $user_agent ) ) {
			$user_agent = $this->get_user_agent();
		}

		$browser_data = [
			'browser_name'     => '',
			'browser_version'  => '',
			'platform_name'    => '',
			'device_type_name' => '',
			'is_mobile'        => false,
		];

		/*
		 * Check browser data
		 */
		foreach ( $this->browser_regexes as $index => $regex ) {
			if ( preg_match( $regex, $user_agent, $matches ) ) {
				$browser_data['browser_name']    = $this->browser_names[ $index ];
				$browser_data['browser_version'] = isset($matches[2]) ? $matches[2] : ( $matches[3] ?? '' );
				break;
			}
		}

		/*
		 * Check platform data
		 */
		foreach ( $this->platform_regexes as $index => $regex ) {
			if ( preg_match( $regex, $user_agent, $matches ) ) {
				$browser_data['platform_name'] = $this->platform_names[ $index ];
				break;
			}
		}

		/*
		 * Check device data
		 */
		foreach ( $this->device_type_regexes as $index => $regex ) {
			if ( preg_match( $regex, $user_agent, $matches ) ) {
				$browser_data['device_type_name'] = $this->device_type_names[ $index ];
				break;
			}
		}

		/*
		 * Check Mobile data
		 */
		if ( preg_match( '/Mobile/i', $user_agent ) ) {
			$browser_data['is_mobile'] = true;
		}

		// Logging
		$this->get_logger()->debug("Browser detected", [
			'plugin'    => 'f12-cf7-captcha',
			'userAgent' => mb_substr($user_agent, 0, 120) . (strlen($user_agent) > 120 ? '...' : ''),
			'detected'  => $browser_data
		]);

		return $browser_data;
	}


	/**
	 * Checks if the given user agent string indicates a bot.
	 *
	 * @param string $user_agent (Optional) The user agent string to check. Default: ''
	 *
	 * @return bool Returns true if the user agent string indicates a bot; otherwise, false.
	 */
	private function is_bot( $user_agent = '' ): bool {
		if ( empty( $user_agent ) ) {
			$user_agent = $this->get_user_agent();
		}

		$is_bot = (bool) preg_match( '/bot|crawl|slurp|spider/i', $user_agent );

		$this->get_logger()->debug("Bot detection performed", [
			'plugin'    => 'f12-cf7-captcha',
			'userAgent' => mb_substr($user_agent, 0, 120) . (strlen($user_agent) > 120 ? '...' : ''),
			'is_bot'    => $is_bot
		]);

		return $is_bot;
	}


	/**
	 * Determine if the current call is done by a crawler
	 *
	 * @return bool|void
	 */
	public function is_crawler(): bool {
		$browser_data = $this->get_browser();

		if ( $this->is_bot() ) {
			$this->set_message( __( 'bot-detected', 'captcha-for-contact-form-7' ) );

			$this->get_logger()->info("Bot detected", [
				'plugin'       => 'f12-cf7-captcha',
				'userAgent'    => mb_substr($this->get_user_agent(), 0, 120) . (strlen($this->get_user_agent()) > 120 ? '...' : ''),
				'browser_data' => $browser_data
			]);

			return true;
		}

		if ( $this->is_default( $browser_data ) ) {
			$this->set_message( __( 'crawler-detected', 'captcha-for-contact-form-7' ) );

			$this->get_logger()->info("Crawler detected (default data only)", [
				'plugin'       => 'f12-cf7-captcha',
				'userAgent'    => mb_substr($this->get_user_agent(), 0, 120) . (strlen($this->get_user_agent()) > 120 ? '...' : ''),
				'browser_data' => $browser_data
			]);

			return true;
		}

		$this->get_logger()->debug("No bot/crawler detected", [
			'plugin'       => 'f12-cf7-captcha',
			'userAgent'    => mb_substr($this->get_user_agent(), 0, 120) . (strlen($this->get_user_agent()) > 120 ? '...' : ''),
			'browser_data' => $browser_data
		]);

		return false;
	}

	/**
	 * Checks if the request is considered as spam.
	 *
	 * @return bool Returns true if the request is considered as spam; otherwise, false.
	 */
	public function is_spam(): bool {
		if ( ! $this->is_enabled() ) {
			$this->get_logger()->debug("Spam check skipped (feature disabled)", [
				'plugin' => 'f12-cf7-captcha'
			]);
			return false;
		}

		$is_spam = $this->is_crawler();

		$this->get_logger()->info("Spam check performed", [
			'plugin' => 'f12-cf7-captcha',
			'result' => $is_spam ? 'SPAM detected' : 'no spam'
		]);

		return $is_spam;
	}


	public function success(): void {
		$this->get_logger()->info("Validation successful", [
			'plugin' => 'f12-cf7-captcha',
			'status' => 'success'
		]);
	}

}