<?php

namespace f12_cf7_captcha\core\protection;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\Log_WordPress_Interface;
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


class Protection extends BaseModul {
	protected $_modules = [];
	private Log_WordPress_Interface $Logger;

	/**
	 * In-memory telemetry counter deltas for the current request, flushed once via shutdown hook.
	 */
	private static array $pending_deltas = [];
	private static bool $shutdown_registered = false;

	public function __construct( CF7Captcha $Controller, Log_WordPress_Interface $Logger ) {
		parent::__construct( $Controller );
		$this->Logger = $Logger;
		add_action( 'f12_cf7_captcha_compatibilities_loaded', array( $this, 'on_init' ) );
	}

	/**
	 * Initializes the modules for the software.
	 *
	 * All modules are loaded, but each module has its own is_enabled() method
	 * to check if it should be active. The only optimization is for API mode:
	 * when API is enabled with a key, only API and whitelist modules are loaded.
	 *
	 * @return void
	 */
	private function init_modules(): void {
		// Define the modules to be initialized.
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

		// Check if API is enabled and API key is present
		/** @var Api $api */
		$api = $moduls['api-validator'];
		$api_key = $this->Controller->get_settings('beta_captcha_api_key', 'beta');

		if ($api->is_enabled() && !empty($api_key)) {
			// Only keep whitelist & API active
			$moduls = [
				'api-validator'       => $api,
				'whitelist-validator' => $moduls['whitelist-validator'],
			];
		}

		$this->_modules = $moduls;
	}

	/**
	 * Retrieves the specified module based on its name.
	 *
	 * @param string $name The name of the module to retrieve.
	 *
	 * @return BaseProtection The specified module.
	 * @throws \Exception If the specified module does not exist.
	 */
	public function get_module( string $name ): BaseProtection {
		if ( ! isset( $this->_modules[ $name ] ) ) {
			$error_message = sprintf( 'Module %s does not exist.', $name );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new \Exception( $error_message );
		}

		return $this->_modules[ $name ];
	}

	/**
	 * @deprecated Use get_module() instead.
	 */
	public function get_modul( string $name ): BaseProtection {
		_deprecated_function( __METHOD__, '2.3.0', 'Protection::get_module()' );
		return $this->get_module( $name );
	}


	/**
	 * Retrieves the name of the field.
	 *
	 * @return string The name of the field.
	 */
	protected function get_field_name(): string {
		return 'f12_captcha';
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
		$captcha_parts = [];

		foreach ( $this->_modules as $key => $modul ) {
			if ( method_exists( $modul, 'get_captcha' ) ) {
				$captcha_parts[ $key ] = $modul->get_captcha();
			}
		}

		return implode( "", $captcha_parts );
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
		// No data submitted
		if ( ! isset( $args[0] ) ) {
			return false;
		}

		$array_post_data = $args[0];

		// Check if validation should be skipped via filter
		if ( apply_filters( 'f12-cf7-captcha-skip-validation', false, $array_post_data ) ) {
			return false;
		}

		// Track delta for this request (merged with DB at shutdown)
		self::$pending_deltas['checks_total'] = ( self::$pending_deltas['checks_total'] ?? 0 ) + 1;
		$this->schedule_counter_flush();

		// Check whitelist
		$whitelist = $this->get_module( 'whitelist-validator' );
		if ( $whitelist && $whitelist->is_whitelisted( $array_post_data ) ) {
			self::$pending_deltas['checks_clean'] = ( self::$pending_deltas['checks_clean'] ?? 0 ) + 1;

			return false;
		}

		$is_spam         = false;
		$spam_modul_name = '';

		// Iterate through all modules
		foreach ( $this->_modules as $name => $modul ) {
			if ( $name === "whitelist-validator" ) {
				continue;
			}

			if ( $modul->is_spam( $array_post_data ) ) {
				$is_spam = true;

				// Increment module counter
				self::$pending_deltas[ $name ] = ( self::$pending_deltas[ $name ] ?? 0 ) + 1;

				// Only first module sets error message + logging
				if ( $spam_modul_name === '' ) {
					$spam_modul_name = $name;

					$this->get_logger()->warning( "Module '{$name}' found spam.", [ 'plugin' => 'f12-cf7-captcha' ] );
					$this->set_message( $modul->get_message() );
					$this->Logger->maybe_log( 'protection', $array_post_data, true, $this->get_message() );
				}
			}
		}

		if ( $is_spam ) {
			self::$pending_deltas['checks_spam'] = ( self::$pending_deltas['checks_spam'] ?? 0 ) + 1;
		} else {
			foreach ( $this->_modules as $modul ) {
				$modul->success();
			}

			$this->Logger->maybe_log( 'protection', $array_post_data, false );

			self::$pending_deltas['checks_clean'] = ( self::$pending_deltas['checks_clean'] ?? 0 ) + 1;
		}

		return $is_spam;
	}


	/**
	 * Override modules (for testing).
	 *
	 * @param array<string, BaseProtection> $modules
	 */
	public function set_modules(array $modules): void {
		$this->_modules = $modules;
	}

	/**
	 * Reset static telemetry state (for testing).
	 *
	 * @internal
	 */
	public static function reset_static_state(): void {
		self::$pending_deltas = [];
		self::$shutdown_registered = false;
	}

	public function on_init(): void {
		$this->init_modules();
	}

	protected function is_enabled(): bool {
		return true;
	}

	/**
	 * Register the shutdown hook (once) to flush counters at end of request.
	 */
	private function schedule_counter_flush(): void {
		if ( self::$shutdown_registered ) {
			return;
		}
		self::$shutdown_registered = true;

		register_shutdown_function( [ __CLASS__, 'flush_counters' ] );
	}

	/**
	 * Merge accumulated deltas into the current DB counters (called once at shutdown).
	 *
	 * Re-reads from DB at flush time to minimize lost updates under concurrent requests.
	 */
	public static function flush_counters(): void {
		if ( empty( self::$pending_deltas ) ) {
			return;
		}

		$current = get_option( 'f12_cf7_captcha_telemetry_counters', [] );

		foreach ( self::$pending_deltas as $key => $delta ) {
			$current[ $key ] = ( $current[ $key ] ?? 0 ) + $delta;
		}

		update_option( 'f12_cf7_captcha_telemetry_counters', $current, false );
		self::$pending_deltas = [];
	}
}