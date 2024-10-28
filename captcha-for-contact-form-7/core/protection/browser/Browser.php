<?php

namespace f12_cf7_captcha\core\protection\browser;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

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

		require( 'Browser_User_Agent.php' );

		$this->browser_names       = $browser_names;
		$this->browser_regexes     = $browser_regexes;
		$this->platform_names      = $platform_names;
		$this->platform_regexes    = $platform_regexes;
		$this->device_type_names   = $device_type_names;
		$this->device_type_regexes = $device_type_regexes;

		add_filter( 'f12-cf7-captcha-log-data', [ $this, 'get_log_data' ] );
	}

	/**
	 * Checks if browser protection is enabled.
	 *
	 *
	 * @return bool Returns true if browser protection is enabled; otherwise, false.
	 */
	protected function is_enabled(): bool {
		$is_enabled = (int) $this->Controller->get_settings( 'protection_browser_enable', 'global' ) === 1;

		return apply_filters( 'f12-cf7-captcha-skip-validation-browser', $is_enabled );
	}

	/**
	 * Retrieves the spam protection code.
	 *
	 * @return string Returns the spam protection code.
	 */
	public function get_captcha( ...$args ): string {
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
		 * Get the Header DAta
		 */
		$data['Header Data'] = $this->get_headers_as_string();

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
			return '';
		}

		return $_SERVER['HTTP_USER_AGENT'];
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

		return empty( array_diff_assoc( $browser_data, $default_browser_data ) );
	}

	/**
	 * Retrieves the headers as a formatted string.
	 *
	 * @return string The headers as a formatted string.
	 */
	private function get_headers_as_string(): string {
		$header_data = $this->get_headers();

		$response = '';
		foreach ( $header_data as $key => $value ) {
			$response .= $key . ':' . $value . ',';
		}

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
		foreach ( $browser_data as $key => $value ) {
			$response .= $key . ':' . $value . ',';
		}

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
				$browser_data['browser_version'] = ( isset( $matches[3] ) ? $matches[3] : isset( $matches[2] ) ) ? $matches[2] : '';
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

		if ( preg_match( '/bot|crawl|slurp|spider/i', $user_agent ) ) {
			return true;
		}

		return false;
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

			return true;
		}

		if ( $this->is_default( $browser_data ) ) {
			$this->set_message( __( 'crawler-detected', 'captcha-for-contact-form-7' ) );

			return true;
		}

		return false;
	}

	/**
	 * Checks if the request is considered as spam.
	 *
	 * @return bool Returns true if the request is considered as spam; otherwise, false.
	 */
	public function is_spam(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return $this->is_crawler();
	}

	public function success(): void {
		// TODO: Implement success() method.
	}
}