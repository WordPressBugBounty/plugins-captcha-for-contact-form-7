<?php

namespace f12_cf7_captcha\core\protection;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\log\AuditLog;
use f12_cf7_captcha\core\log\BlockLog;
use f12_cf7_captcha\core\log\MailLog;
use f12_cf7_captcha\core\Log_WordPress_Interface;
use f12_cf7_captcha\core\protection\api\Api;
use f12_cf7_captcha\core\protection\Shadow_Mode;
use f12_cf7_captcha\core\protection\browser\Browser;
use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
use f12_cf7_captcha\core\protection\ip\IPValidator;
use f12_cf7_captcha\core\protection\ip_blacklist\IP_Blacklist_Validator;
use f12_cf7_captcha\core\protection\javascript\Javascript_Validator;
use f12_cf7_captcha\core\protection\multiple_submission\Multiple_Submission_Validator;
use f12_cf7_captcha\core\protection\rules\RulesHandler;
use f12_cf7_captcha\core\protection\time\Timer_Validator;
use f12_cf7_captcha\core\protection\whitelist\Whitelist_Validator;
use f12_cf7_captcha\core\settings\Settings_Resolver;

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

	/**
	 * Current context for hierarchical settings resolution.
	 */
	private ?string $context_integration_id = null;
	private ?string $context_form_id = null;

	/**
	 * Cached resolved settings for the current context.
	 *
	 * @var array|null
	 */
	private ?array $resolved_settings = null;

	/**
	 * @var Settings_Resolver|null
	 */
	private ?Settings_Resolver $settings_resolver = null;

	/**
	 * Stores the form context after a successful (non-spam) is_spam() check.
	 * Used by the wp_mail filter to log sent mails for ALL form plugins.
	 *
	 * @var array|null {form_plugin: string, form_id: string|null, form_data: array}
	 */
	private static ?array $last_passed_context = null;

	public function __construct( CF7Captcha $Controller, Log_WordPress_Interface $Logger ) {
		parent::__construct( $Controller );
		$this->Logger = $Logger;
		add_action( 'f12_cf7_captcha_compatibilities_loaded', array( $this, 'on_init' ) );

		// Universal wp_mail hook for logging sent mails across all form plugins
		add_filter( 'wp_mail', [ $this, 'capture_sent_mail' ], 999999 );
	}

	/**
	 * Transient key for caching the API health status.
	 */
	private const API_HEALTH_TRANSIENT = 'f12_captcha_api_health';

	/**
	 * How long to cache a successful API health check (5 minutes).
	 */
	private const API_HEALTH_TTL_OK = 5 * MINUTE_IN_SECONDS;

	/**
	 * How long to cache a failed API health check (15 minutes).
	 */
	private const API_HEALTH_TTL_FAIL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Initializes the modules for the software.
	 *
	 * All modules are loaded, but each module has its own is_enabled() method
	 * to check if it should be active. When API mode is enabled with a valid key
	 * AND the API is reachable, only API and whitelist modules are loaded.
	 * If the API is unreachable, all local modules are loaded as fallback.
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
		$api     = $moduls['api-validator'];
		$api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

		if ( $api->is_enabled() && ! empty( $api_key ) ) {
			if ( $this->is_api_reachable( $api_key ) ) {
				// API is reachable — keep api-validator alongside locally enabled modules
				// so that API and local protections (JS, timer, captcha, etc.) can coexist.
			} else {
				// API is unreachable — fallback to all local modules (without api-validator)
				unset( $moduls['api-validator'] );

				// Show admin warning about the fallback
				add_action( 'admin_notices', [ $this, 'render_api_fallback_notice' ] );
			}
		}

		$this->_modules = $moduls;
	}

	/**
	 * Check whether the SilentShield API is reachable.
	 *
	 * Uses a lightweight key validation request with transient caching
	 * to avoid hitting the API on every page load.
	 *
	 * @param string $api_key The API key to validate against.
	 *
	 * @return bool True if the API responded successfully.
	 */
	private function is_api_reachable( string $api_key ): bool {
		$cached = get_transient( self::API_HEALTH_TRANSIENT );

		if ( $cached !== false ) {
			return $cached === 'ok';
		}

		// Check if the previous state was also a failure (to avoid repeat audit logging)
		$was_failing = get_option( 'f12_captcha_api_health_failing', false );

		$base_url     = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		$api_endpoint = rtrim( $base_url, '/' ) . '/keys/validate';

		$response = wp_remote_post( $api_endpoint, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'key' => $api_key ] ),
			'timeout' => 3,
		] );

		if ( is_wp_error( $response ) ) {
			set_transient( self::API_HEALTH_TRANSIENT, 'fail', self::API_HEALTH_TTL_FAIL );

			// Only audit-log on the FIRST failure, not on every cache expiry
			if ( ! $was_failing ) {
				update_option( 'f12_captcha_api_health_failing', true, false );
				AuditLog::log(
					AuditLog::TYPE_API,
					'API_HEALTH_UNREACHABLE',
					AuditLog::SEVERITY_WARNING,
					sprintf(
						'SilentShield API unreachable — falling back to local protection modules (%s)',
						$response->get_error_message()
					),
					[ 'endpoint' => $api_endpoint, 'error' => $response->get_error_message() ]
				);
			}

			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 500 ) {
			set_transient( self::API_HEALTH_TRANSIENT, 'ok', self::API_HEALTH_TTL_OK );

			// API recovered — log recovery if it was failing before
			if ( $was_failing ) {
				delete_option( 'f12_captcha_api_health_failing' );
				AuditLog::log(
					AuditLog::TYPE_API,
					'API_HEALTH_RECOVERED',
					AuditLog::SEVERITY_INFO,
					'SilentShield API is reachable again — switching back to API mode',
					[ 'endpoint' => $api_endpoint, 'http_code' => $code ]
				);
			}

			return true;
		}

		// 5xx server error
		set_transient( self::API_HEALTH_TRANSIENT, 'fail', self::API_HEALTH_TTL_FAIL );

		if ( ! $was_failing ) {
			update_option( 'f12_captcha_api_health_failing', true, false );
			AuditLog::log(
				AuditLog::TYPE_API,
				'API_HEALTH_SERVER_ERROR',
				AuditLog::SEVERITY_WARNING,
				sprintf( 'SilentShield API returned HTTP %d — falling back to local protection modules', $code ),
				[ 'endpoint' => $api_endpoint, 'http_code' => $code ]
			);
		}

		return false;
	}

	/**
	 * Render an admin notice when the API is unreachable and local fallback is active.
	 */
	public function render_api_fallback_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=silentshield-admin' );

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>SilentShield:</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__(
				'Die SilentShield API ist nicht erreichbar. Die lokalen Schutzmodule (Captcha, Timer, JS-Erkennung etc.) wurden automatisch reaktiviert. Deine Formulare sind weiterhin geschützt.',
				'captcha-for-contact-form-7'
			),
			esc_url( $settings_url ),
			esc_html__( 'API-Einstellungen prüfen', 'captcha-for-contact-form-7' )
		);
	}

	/**
	 * Set the current context for hierarchical settings resolution.
	 *
	 * Must be called before get_captcha() or is_spam() to enable per-form settings.
	 *
	 * @param string      $integration_id The integration identifier (e.g. 'cf7').
	 * @param string|null $form_id        The form ID, or null for integration-level only.
	 */
	public function set_context( string $integration_id, ?string $form_id = null ): void {
		$this->context_integration_id = $integration_id;
		$this->context_form_id        = $form_id;
		$this->resolved_settings      = null; // Invalidate cache
	}

	/**
	 * Clear the current context (revert to global settings).
	 */
	public function clear_context(): void {
		$this->context_integration_id = null;
		$this->context_form_id        = null;
		$this->resolved_settings      = null;
	}

	/**
	 * Get a resolved setting value for the current context.
	 *
	 * Falls back to global settings if no context is set.
	 *
	 * @param string $key The setting key.
	 *
	 * @return mixed The setting value.
	 */
	public function get_setting( string $key ) {
		$resolved = $this->get_resolved_settings();

		return $resolved[ $key ] ?? $this->Controller->get_settings( $key, 'global' );
	}

	/**
	 * Get the Settings_Resolver instance (lazy-initialized).
	 *
	 * @return Settings_Resolver
	 */
	public function get_settings_resolver(): Settings_Resolver {
		if ( $this->settings_resolver === null ) {
			$this->settings_resolver = new Settings_Resolver();
		}

		return $this->settings_resolver;
	}

	/**
	 * Get the full resolved settings array for the current context.
	 *
	 * @return array
	 */
	private function get_resolved_settings(): array {
		if ( $this->resolved_settings !== null ) {
			return $this->resolved_settings;
		}

		// Get global settings as flat array
		$all_settings    = $this->Controller->get_settings( '', 'global' );
		$global_settings = is_array( $all_settings ) ? $all_settings : [];

		if ( $this->context_integration_id !== null ) {
			$this->resolved_settings = $this->get_settings_resolver()->resolve(
				$global_settings,
				$this->context_integration_id,
				$this->context_form_id
			);
		} else {
			$this->resolved_settings = $global_settings;
		}

		return $this->resolved_settings;
	}

	/**
	 * Checks whether a module with the given name is registered.
	 *
	 * @param string $name The name of the module to check.
	 *
	 * @return bool True if the module exists, false otherwise.
	 */
	public function has_module( string $name ): bool {
		return isset( $this->_modules[ $name ] );
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

					// Detailed block log
					$this->maybe_log_block( $name, $modul );
				}
			}
		}

		// Collect API response data for logging (available for both spam and clean)
		$api_response = null;
		if ( $this->has_module( 'api-validator' ) ) {
			/** @var Api $api_module */
			$api_module   = $this->get_module( 'api-validator' );
			$api_response = $api_module->get_last_api_response();
		}

		if ( $is_spam ) {
			self::$pending_deltas['checks_spam'] = ( self::$pending_deltas['checks_spam'] ?? 0 ) + 1;

			// Mail log: record blocked submission
			$this->maybe_log_mail_blocked( $spam_modul_name, $array_post_data, $api_response );
		} else {
			foreach ( $this->_modules as $modul ) {
				$modul->success();
			}

			$this->Logger->maybe_log( 'protection', $array_post_data, false );

			self::$pending_deltas['checks_clean'] = ( self::$pending_deltas['checks_clean'] ?? 0 ) + 1;

			// Store context for the universal wp_mail hook to log sent mails
			if ( MailLog::is_enabled() ) {
				// Clean form data: remove internal/captcha fields
				$clean = $array_post_data;
				$strip = [
					'_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag',
					'_wpcf7_container_post', '_wpcf7_posted_data_hash', '_wpcf7_nonce',
					'php_start_time', 'js_start_time', 'js_end_time',
					'f12_captcha', 'f12_captcha_hash', '_wpnonce', 'behavior_nonce',
				];
				foreach ( $strip as $k ) {
					unset( $clean[ $k ] );
				}

				self::$last_passed_context = [
					'form_plugin'  => $this->context_integration_id ?? '',
					'form_id'      => $this->context_form_id,
					'form_data'    => $clean,
					'api_response' => $api_response,
				];
			}
		}

		// Shadow Mode: record the local verdict for API comparison analytics.
		Shadow_Mode::record( $is_spam, $spam_modul_name, $array_post_data );

		return $is_spam;
	}


	/**
	 * Map module names to machine-readable reason codes and human-readable details.
	 */
	private static array $block_reason_map = [
		'timer-validator'               => [ 'SUBMIT_TOO_FAST',   'Form submitted too quickly (minimum time not reached)' ],
		'captcha-validator'             => [ 'CAPTCHA_FAILED',    'CAPTCHA verification failed' ],
		'ip-validator'                  => [ 'IP_RATE_LIMIT',     'IP rate limit exceeded or IP banned' ],
		'ip-blacklist-validator'        => [ 'IP_BLACKLISTED',    'IP address is on the blacklist' ],
		'browser-validator'             => [ 'NO_BROWSER',        'No valid browser detected (missing User-Agent)' ],
		'javascript-validator'          => [ 'NO_JAVASCRIPT',     'JavaScript validation failed' ],
		'rule-validator'                => [ 'BLACKLIST_MATCH',   'Content matched a blacklist rule' ],
		'multiple-submission-validator' => [ 'DUPLICATE_SUBMIT',  'Duplicate submission detected' ],
		'api-validator'                 => [ 'API_VERDICT_BOT',   'SilentShield API classified as bot/suspicious' ],
	];

	/**
	 * Log a block event to the detailed block log (if enabled).
	 *
	 * @param string         $module_name The protection module name.
	 * @param BaseProtection $modul       The protection module instance.
	 */
	private function maybe_log_block( string $module_name, BaseProtection $modul ): void {
		if ( ! BlockLog::is_enabled() ) {
			return;
		}

		$reason      = self::$block_reason_map[ $module_name ] ?? [ strtoupper( str_replace( '-', '_', $module_name ) ), '' ];
		$reason_code = $reason[0];
		// Use the module's specific message as detail (more precise than the generic map description)
		$reason_detail = $modul->get_message();
		if ( empty( $reason_detail ) ) {
			$reason_detail = $reason[1];
		}

		$block_log  = new BlockLog( $this->get_logger() );

		$extra = [
			'form_plugin' => $this->context_integration_id ?? '',
			'form_id'     => $this->context_form_id ?? '',
		];

		$block_log->log( $module_name, $reason_code, $reason_detail, $extra );
	}

	/**
	 * Log a blocked submission to the mail log (if enabled).
	 *
	 * @param string     $module_name  The protection module that triggered the block.
	 * @param array      $post_data    The submitted form data.
	 * @param array|null $api_response Optional API response data for meta.
	 */
	private function maybe_log_mail_blocked( string $module_name, array $post_data, ?array $api_response = null ): void {
		if ( ! MailLog::is_enabled() ) {
			return;
		}

		$reason = self::$block_reason_map[ $module_name ] ?? [ strtoupper( str_replace( '-', '_', $module_name ) ), '' ];

		$mail_log = new MailLog( $this->get_logger() );
		$mail_log->log_blocked(
			$this->context_integration_id ?? '',
			$this->context_form_id,
			$reason[0],
			$post_data,
			$api_response
		);
	}

	/**
	 * Universal wp_mail filter that logs sent mails for ALL form plugins.
	 *
	 * Only logs when a form just passed spam validation (context is stored).
	 * This ensures only form-related emails are logged, not password resets etc.
	 *
	 * @param array $args wp_mail arguments: to, subject, message, headers, attachments.
	 *
	 * @return array Unmodified $args (pass-through filter).
	 */
	public function capture_sent_mail( $args ) {
		if ( self::$last_passed_context === null || ! is_array( $args ) ) {
			return $args;
		}

		$ctx = self::$last_passed_context;
		// Clear immediately to prevent double-logging (e.g. CF7 sends mail + mail_2)
		self::$last_passed_context = null;

		if ( ! MailLog::is_enabled() ) {
			return $args;
		}

		$log_sent = (int) $this->Controller->get_settings( 'protection_mail_log_sent', 'global' );
		if ( $log_sent !== 1 ) {
			return $args;
		}

		$recipient = $args['to'] ?? '';
		$subject   = $args['subject'] ?? '';
		$body      = $args['message'] ?? '';

		// Extract sender from headers
		$sender  = '';
		$headers = $args['headers'] ?? [];
		if ( is_string( $headers ) ) {
			$headers = array_filter( array_map( 'trim', explode( "\n", $headers ) ) );
		}
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'From:' ) === 0 ) {
				$sender = trim( substr( $header, 5 ) );
				break;
			}
		}

		$attachments = $args['attachments'] ?? [];
		if ( ! is_array( $attachments ) ) {
			$attachments = [];
		}

		$mail_log = new MailLog( $this->get_logger() );
		$mail_log->log_sent(
			$ctx['form_plugin'],
			$ctx['form_id'] ?? '',
			is_array( $recipient ) ? implode( ', ', $recipient ) : $recipient,
			$sender,
			$subject,
			$body,
			$headers,
			$attachments,
			$ctx['form_data'] ?? [],
			$ctx['api_response'] ?? null
		);

		return $args;
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