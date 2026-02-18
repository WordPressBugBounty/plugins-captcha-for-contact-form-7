<?php

namespace f12_cf7_captcha\core\rest;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\protection\captcha\CaptchaAjax;
use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
use f12_cf7_captcha\core\protection\rules\RulesAjax;
use f12_cf7_captcha\core\protection\rules\RulesHandler;
use f12_cf7_captcha\core\settings\Settings_Resolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestController extends BaseModul {

	private const NAMESPACE = 'f12-cf7-captcha/v1';

	/**
	 * Maximum allowed requests per IP per endpoint within the rate limit window.
	 */
	private const RATE_LIMIT_MAX = 30;

	/**
	 * Maximum allowed requests for admin endpoints (more restrictive).
	 */
	private const RATE_LIMIT_ADMIN_MAX = 10;

	/**
	 * Rate limit window in seconds.
	 */
	private const RATE_LIMIT_WINDOW = 60;

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'add_security_headers' ], 10, 3 );

		$this->get_logger()->info(
			'__construct(): REST API controller registered',
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
			]
		);
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/captcha/reload', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_captcha_reload' ],
			'permission_callback' => [ $this, 'validate_public_request' ],
			'args'                => [
				'captchamethod' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return in_array( $value, [ 'math', 'image', 'honey' ], true );
					},
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/timer/reload', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_timer_reload' ],
			'permission_callback' => [ $this, 'validate_public_request' ],
			'args'                => [],
		] );

		register_rest_route( self::NAMESPACE, '/blacklist/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_blacklist_sync' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		register_rest_route( self::NAMESPACE, '/overrides/save', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_overrides_save' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [
				'type'           => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return in_array( $value, [ 'integration', 'form' ], true );
					},
				],
				'integration_id' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'form_id'        => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'enabled'        => [
					'required' => true,
					'type'     => 'boolean',
				],
				'overrides'      => [
					'required' => true,
					'type'     => 'object',
				],
			],
		] );

		$this->get_logger()->info(
			'register_routes(): REST routes registered',
			[
				'plugin' => 'f12-cf7-captcha',
				'routes' => [
					'captcha/reload',
					'timer/reload',
					'blacklist/sync',
					'overrides/save',
				],
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_captcha_reload( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'captcha_reload' );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$method = $request->get_param( 'captchamethod' );

			/** @var Captcha_Validator $captcha_validator */
			$captcha_validator = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' );
			$captcha_ajax      = $captcha_validator->get_captcha_ajax();

			$data = $captcha_ajax->handle_reload_captcha( $method );

			if ( ! isset( $data['Captcha'] ) ) {
				return new WP_Error(
					'captcha_not_initialized',
					'Captcha not initialized',
					[ 'status' => 500 ]
				);
			}

			if ( ! isset( $data['Generator'] ) ) {
				return new WP_Error(
					'generator_not_initialized',
					'Captcha Generator not initialized',
					[ 'status' => 500 ]
				);
			}

			$Captcha   = $data['Captcha'];
			$Generator = $data['Generator'];

			return new WP_REST_Response( [
				'hash'  => $Captcha->get_hash(),
				'label' => $Generator->get_ajax_response(),
			], 200 );

		} catch ( \Throwable $e ) {
			$this->get_logger()->error(
				'handle_captcha_reload(): Error',
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				]
			);

			return new WP_Error(
				'captcha_reload_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_timer_reload( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'timer_reload' );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			/** @var Captcha_Validator $captcha_validator */
			$captcha_validator = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' );
			$captcha_ajax      = $captcha_validator->get_captcha_ajax();

			$hash = $captcha_ajax->handle_reload_timer();

			return new WP_REST_Response( [
				'hash' => $hash,
			], 200 );

		} catch ( \Throwable $e ) {
			$this->get_logger()->error(
				'handle_timer_reload(): Error',
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				]
			);

			return new WP_Error(
				'timer_reload_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_blacklist_sync( WP_REST_Request $request ) {
		// Apply stricter rate limiting for admin endpoints
		$rate_check = $this->check_rate_limit( 'blacklist_sync', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			/** @var RulesHandler $rules_handler */
			$rules_handler = $this->Controller->get_module( 'protection' )->get_module( 'rule-validator' );
			$rules_ajax    = $rules_handler->get_rules_ajax();

			$content = $rules_ajax->get_blacklist_content();

			if ( empty( $content ) ) {
				return new WP_REST_Response( [
					'value'   => '',
					'status'  => 'error',
					'message' => 'No content available.',
				], 200 );
			}

			return new WP_REST_Response( [
				'value'  => $content,
				'status' => 'success',
			], 200 );

		} catch ( \Throwable $e ) {
			$this->get_logger()->error(
				'handle_blacklist_sync(): Error',
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				]
			);

			return new WP_Error(
				'blacklist_sync_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Handle saving override settings via REST API.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_overrides_save( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'overrides_save', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$type           = $request->get_param( 'type' );
			$integration_id = $request->get_param( 'integration_id' );
			$form_id        = $request->get_param( 'form_id' );
			$enabled        = (bool) $request->get_param( 'enabled' );
			$raw_overrides  = $request->get_param( 'overrides' );

			if ( ! is_array( $raw_overrides ) ) {
				$raw_overrides = [];
			}

			// Sanitize override values
			$overridable_keys = Settings_Resolver::get_overridable_keys();
			$overrides        = [ '_enabled' => $enabled ];

			foreach ( $raw_overrides as $key => $value ) {
				if ( ! in_array( $key, $overridable_keys, true ) ) {
					continue;
				}
				$sanitized = sanitize_text_field( (string) $value );
				if ( $sanitized !== '__inherit__' && $sanitized !== '' ) {
					$overrides[ $key ] = $sanitized;
				}
			}

			$resolver = new Settings_Resolver();

			if ( $type === 'form' && ! empty( $form_id ) ) {
				$resolver->save_form_overrides( $integration_id, $form_id, $overrides );
			} else {
				$resolver->save_integration_overrides( $integration_id, $overrides );
			}

			return new WP_REST_Response( [
				'status' => 'success',
			], 200 );
		} catch ( \Throwable $e ) {
			$this->get_logger()->error(
				'handle_overrides_save(): Error',
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				]
			);

			return new WP_Error(
				'overrides_save_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Validates admin REST requests.
	 *
	 * Requires both manage_options capability and valid nonce.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_admin_request() {
		// First check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'captcha-for-contact-form-7' ),
				[ 'status' => 403 ]
			);
		}

		// Then validate nonce
		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			$this->get_logger()->warning(
				'Admin request rejected due to invalid nonce.',
				[
					'plugin'   => 'f12-cf7-captcha',
					'endpoint' => 'blacklist/sync',
				]
			);
			return new WP_Error(
				'rest_nonce_invalid',
				__( 'Invalid or missing security token.', 'captcha-for-contact-form-7' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Validates the request origin for public REST endpoints.
	 *
	 * For authenticated users: validates the X-WP-Nonce header.
	 * For unauthenticated users: validates the referer header.
	 *
	 * @return bool True if request is valid, false otherwise.
	 */
	public function validate_public_request(): bool {
		// Check for WordPress REST API nonce (authenticated users)
		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		// If nonce is present and valid, allow the request
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		// For unauthenticated users, validate referer
		$referer = wp_get_referer();
		if ( empty( $referer ) ) {
			// Also check HTTP_REFERER directly as fallback
			$referer = isset( $_SERVER['HTTP_REFERER'] )
				? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
				: '';
		}

		if ( empty( $referer ) ) {
			$this->get_logger()->warning(
				'Request rejected due to missing referer.',
				[
					'plugin'   => 'f12-cf7-captcha',
					'endpoint' => 'public',
				]
			);
			return false;
		}

		// Verify referer is from the same site
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );

		if ( $site_host !== $referer_host ) {
			$this->get_logger()->warning(
				'Request rejected due to foreign host.',
				[
					'plugin'       => 'f12-cf7-captcha',
					'site_host'    => $site_host,
					'referer_host' => $referer_host,
				]
			);
			return false;
		}

		return true;
	}

	/**
	 * IP-based rate limiter using transients.
	 *
	 * @param string   $endpoint  Identifier for the endpoint being rate-limited.
	 * @param int|null $max_limit Optional custom limit. Defaults to RATE_LIMIT_MAX.
	 *
	 * @return WP_Error|null WP_Error if rate limit exceeded, null otherwise.
	 */
	private function check_rate_limit( string $endpoint, ?int $max_limit = null ): ?WP_Error {
		$limit = $max_limit ?? self::RATE_LIMIT_MAX;
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		$key   = 'f12_rl_' . md5( $endpoint . '|' . $ip );

		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			$this->get_logger()->warning(
				'Rate limit reached.',
				[
					'plugin'   => 'f12-cf7-captcha',
					'endpoint' => $endpoint,
					'ip'       => $ip,
					'limit'    => $limit,
				]
			);

			return new WP_Error(
				'rate_limit_exceeded',
				'Too many requests. Please try again later.',
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

		return null;
	}

	/**
	 * Adds security headers to REST API responses for this plugin's namespace.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_REST_Server   $server   The REST server instance.
	 * @param WP_REST_Request  $request  The request object.
	 *
	 * @return WP_REST_Response Modified response with security headers.
	 */
	public function add_security_headers( WP_REST_Response $response, $server, WP_REST_Request $request ): WP_REST_Response {
		// Only add headers for our plugin's REST endpoints
		$route = $request->get_route();
		if ( strpos( $route, '/f12-cf7-captcha/' ) === false ) {
			return $response;
		}

		// Prevent MIME type sniffing
		$response->header( 'X-Content-Type-Options', 'nosniff' );

		// Prevent clickjacking
		$response->header( 'X-Frame-Options', 'DENY' );

		// Enable XSS filter in browsers
		$response->header( 'X-XSS-Protection', '1; mode=block' );

		// Strict referrer policy
		$response->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );

		// Content Security Policy for JSON responses
		$response->header( 'Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'" );

		// Permissions Policy (formerly Feature Policy)
		$response->header( 'Permissions-Policy', 'geolocation=(), microphone=(), camera=()' );

		// Cache control - prevent caching of dynamic captcha data
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );

		$this->get_logger()->debug(
			'Security headers added.',
			[
				'plugin' => 'f12-cf7-captcha',
				'route'  => $route,
			]
		);

		return $response;
	}
}
