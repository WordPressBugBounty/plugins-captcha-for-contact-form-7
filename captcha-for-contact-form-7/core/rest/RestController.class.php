<?php

namespace f12_cf7_captcha\core\rest;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\log\AuditLog;
use f12_cf7_captcha\core\log\BlockLog;
use f12_cf7_captcha\core\log\MailLog;
use f12_cf7_captcha\core\Log_WordPress;
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
	private const RATE_LIMIT_ADMIN_MAX = 60;

	/**
	 * Maximum allowed requests for audio endpoint per IP per minute.
	 */
	private const RATE_LIMIT_AUDIO_MAX = 5;

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

		register_rest_route( self::NAMESPACE, '/captcha/audio', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_captcha_audio' ],
			'permission_callback' => [ $this, 'validate_public_request' ],
			'args'                => [
				'hash' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'captchamethod' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return in_array( $value, [ 'math', 'image' ], true );
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

		// Analytics endpoints (admin-only)
		$analytics_args = [
			'days' => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return in_array( (int) $value, [ 7, 30, 90 ], true );
				},
			],
		];

		register_rest_route( self::NAMESPACE, '/analytics/summary', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_analytics_summary' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => $analytics_args,
		] );

		register_rest_route( self::NAMESPACE, '/analytics/timeline', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_analytics_timeline' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => $analytics_args,
		] );

		register_rest_route( self::NAMESPACE, '/analytics/reasons', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_analytics_reasons' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => $analytics_args,
		] );

		register_rest_route( self::NAMESPACE, '/analytics/log', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_analytics_log' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => array_merge( $analytics_args, [
				'limit'  => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 50,
					'sanitize_callback' => 'absint',
				],
				'offset' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
			] ),
		] );

		// Trial activation endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/trial/activate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_trial_activate' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		// Audit log endpoints (admin-only)
		$audit_args = [
			'days' => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 90,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return (int) $value >= 1 && (int) $value <= 365;
				},
			],
			'type' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'severity' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];

		register_rest_route( self::NAMESPACE, '/audit/entries', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_audit_entries' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => array_merge( $audit_args, [
				'limit'  => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 50,
					'sanitize_callback' => 'absint',
				],
				'offset' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
			] ),
		] );

		register_rest_route( self::NAMESPACE, '/audit/summary', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_audit_summary' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => $audit_args,
		] );

		// Settings endpoints (admin-only)
		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_settings_get' ],
				'permission_callback' => [ $this, 'validate_admin_request' ],
				'args'                => [],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_settings_save' ],
				'permission_callback' => [ $this, 'validate_admin_request' ],
				'args'                => [
					'global' => [
						'required' => false,
						'type'     => 'object',
					],
					'beta' => [
						'required' => false,
						'type'     => 'object',
					],
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/settings/overrides', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_overrides_get' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		// Form discovery endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/forms/discover', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_forms_discover' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		// Dashboard stats endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/dashboard/stats', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_dashboard_stats' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		// Module status endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/status/modules', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_status_modules' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		// Cleanup endpoints (admin-only)
		register_rest_route( self::NAMESPACE, '/cleanup/counts', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_cleanup_counts' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		register_rest_route( self::NAMESPACE, '/cleanup/run', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_cleanup_run' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [
				'type' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return in_array( $value, [
							'captchas_all',
							'captchas_validated',
							'captchas_unvalidated',
							'ip_logs',
							'ip_bans',
							'logs_all',
							'logs_old',
							'timers',
							'mail_log_all',
							'mail_log_blocked',
						], true );
					},
				],
			],
		] );

		// Mail log endpoints (admin-only)
		register_rest_route( self::NAMESPACE, '/mail-log/entries', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_mail_log_entries' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [
				'days'        => [
					'required' => false, 'type' => 'integer', 'default' => 30,
					'sanitize_callback' => 'absint',
					'validate_callback' => function ( $v ) { return in_array( (int) $v, [ 7, 30, 90 ], true ); },
				],
				'limit'       => [ 'required' => false, 'type' => 'integer', 'default' => 50, 'sanitize_callback' => 'absint' ],
				'offset'      => [ 'required' => false, 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ],
				'status'      => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'form_plugin' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'search'      => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/mail-log/summary', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_mail_log_summary' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [
				'days' => [
					'required' => false, 'type' => 'integer', 'default' => 30,
					'sanitize_callback' => 'absint',
					'validate_callback' => function ( $v ) { return in_array( (int) $v, [ 7, 30, 90 ], true ); },
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/mail-log/entry/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_mail_log_entry' ],
				'permission_callback' => [ $this, 'validate_admin_request' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_mail_log_delete' ],
				'permission_callback' => [ $this, 'validate_admin_request' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/mail-log/resend/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_mail_log_resend' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
		] );

		// API key validation endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/api/validate-key', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_api_validate_key' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [
				'api_key' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		// Trial status endpoint (admin-only)
		register_rest_route( self::NAMESPACE, '/trial/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_trial_status' ],
			'permission_callback' => [ $this, 'validate_admin_request' ],
			'args'                => [],
		] );

		$this->get_logger()->info(
			'register_routes(): REST routes registered',
			[
				'plugin' => 'f12-cf7-captcha',
				'routes' => [
					'captcha/reload',
					'captcha/audio',
					'timer/reload',
					'blacklist/sync',
					'overrides/save',
					'analytics/summary',
					'analytics/timeline',
					'analytics/reasons',
					'analytics/log',
					'trial/activate',
					'audit/entries',
					'audit/summary',
					'settings',
					'settings/overrides',
					'forms/discover',
					'dashboard/stats',
					'status/modules',
					'cleanup/counts',
					'cleanup/run',
					'api/validate-key',
					'trial/status',
					'mail-log/entries',
					'mail-log/summary',
					'mail-log/entry',
					'mail-log/resend',
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
	 * Handle audio captcha request — returns the captcha text for TTS.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_captcha_audio( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'captcha_audio', self::RATE_LIMIT_AUDIO_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$hash   = $request->get_param( 'hash' );
			$method = $request->get_param( 'captchamethod' );

			$Captcha_Model = new \f12_cf7_captcha\core\protection\captcha\Captcha(
				$this->Controller->get_logger(),
				''
			);
			$Captcha = $Captcha_Model->get_by_hash( $hash );

			if ( ! $Captcha ) {
				return new WP_Error(
					'captcha_not_found',
					'Captcha not found.',
					[ 'status' => 404 ]
				);
			}

			if ( $Captcha->get_validated() == 1 ) {
				return new WP_Error(
					'captcha_already_validated',
					'Captcha already validated.',
					[ 'status' => 410 ]
				);
			}

			$code = $Captcha->get_code();

			if ( $method === 'math' ) {
				// Convert math formula to speech-friendly text
				$text = str_replace(
					[ '+', '-', '*', '=' ],
					[
						' ' . __( 'plus', 'captcha-for-contact-form-7' ) . ' ',
						' ' . __( 'minus', 'captcha-for-contact-form-7' ) . ' ',
						' ' . __( 'times', 'captcha-for-contact-form-7' ) . ' ',
						' ' . __( 'equals', 'captcha-for-contact-form-7' ) . ' ',
					],
					$code
				);

				// Math code is the result (a number), not the formula.
				// We need to reconstruct something speakable from the DOM, but the code
				// is just the answer. The frontend will read the formula from the DOM for math.
				// This endpoint is primarily for image captchas.
				return new WP_REST_Response( [
					'type' => 'math',
					'text' => $code,
				], 200 );
			}

			if ( $method === 'image' ) {
				// Spell out characters individually with pauses (dots)
				$chars = str_split( $code );
				$spelled = implode( '. ', $chars ) . '.';

				return new WP_REST_Response( [
					'type' => 'image',
					'text' => $spelled,
				], 200 );
			}

			return new WP_REST_Response( [
				'type' => 'honeypot',
			], 200 );

		} catch ( \Throwable $e ) {
			$this->get_logger()->error(
				'handle_captcha_audio(): Error',
				[
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				]
			);

			return new WP_Error(
				'captcha_audio_failed',
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

			// Audit: log override change
			$target = $type === 'form' ? $integration_id . ':' . $form_id : $integration_id;
			AuditLog::log(
				AuditLog::TYPE_SETTINGS,
				'OVERRIDE_SAVED',
				AuditLog::SEVERITY_INFO,
				sprintf( 'Override saved for %s (%s) by user #%d', $target, $type, get_current_user_id() ),
				[ 'type' => $type, 'target' => $target, 'enabled' => $enabled, 'overrides' => $overrides ]
			);

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
	 * Get the BlockLog instance.
	 */
	private function get_block_log(): BlockLog {
		return new BlockLog( $this->get_logger() );
	}

	/**
	 * Analytics: Overview summary (today, week, month, rate).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_analytics_summary( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'analytics_summary', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$log      = $this->get_block_log();
			$overview = $log->get_overview();

			return new WP_REST_Response( $overview, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'analytics_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Analytics: Daily block counts timeline.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_analytics_timeline( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'analytics_timeline', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days     = (int) $request->get_param( 'days' );
			$log      = $this->get_block_log();
			$timeline = $log->get_daily_counts( $days );

			return new WP_REST_Response( [ 'timeline' => $timeline ], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'analytics_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Analytics: Block reasons breakdown (by protection + by reason_code).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_analytics_reasons( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'analytics_reasons', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days          = (int) $request->get_param( 'days' );
			$log           = $this->get_block_log();
			$by_protection = $log->get_summary_by_protection( $days );
			$by_reason     = $log->get_summary_by_reason( $days );

			return new WP_REST_Response( [
				'by_protection' => $by_protection,
				'by_reason'     => $by_reason,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'analytics_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Analytics: Paginated block log entries.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_analytics_log( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'analytics_log', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days   = (int) $request->get_param( 'days' );
			$limit  = min( 100, max( 1, (int) $request->get_param( 'limit' ) ) );
			$offset = max( 0, (int) $request->get_param( 'offset' ) );

			$log     = $this->get_block_log();
			$entries = $log->get_entries( $limit, $offset, $days );
			$total   = $log->get_total_count( $days );

			return new WP_REST_Response( [
				'data'   => $entries,
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'analytics_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Audit Log: Paginated audit entries with optional filters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_audit_entries( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'audit_entries', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days     = (int) $request->get_param( 'days' );
			$limit    = min( 100, max( 1, (int) $request->get_param( 'limit' ) ) );
			$offset   = max( 0, (int) $request->get_param( 'offset' ) );
			$type     = $request->get_param( 'type' ) ?: null;
			$severity = $request->get_param( 'severity' ) ?: null;

			$audit   = new AuditLog( $this->get_logger() );
			$entries = $audit->get_entries( $limit, $offset, $type, $severity, $days );
			$total   = $audit->get_count( $type, $severity, $days );

			return new WP_REST_Response( [
				'data'   => $entries,
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'audit_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Audit Log: Summary counts by event type.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_audit_summary( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'audit_summary', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days  = (int) $request->get_param( 'days' );
			$audit = new AuditLog( $this->get_logger() );

			$by_type     = $audit->get_summary_by_type( $days );
			$total       = $audit->get_count( null, null, $days );
			$warnings    = $audit->get_count( null, AuditLog::SEVERITY_WARNING, $days );
			$errors      = $audit->get_count( null, AuditLog::SEVERITY_ERROR, $days );
			$critical    = $audit->get_count( null, AuditLog::SEVERITY_CRITICAL, $days );

			return new WP_REST_Response( [
				'total'    => $total,
				'warnings' => $warnings,
				'errors'   => $errors,
				'critical' => $critical,
				'by_type'  => $by_type,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'audit_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Handle trial activation — calls SilentShield API to create a trial account
	 * and stores the API key in plugin settings.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_trial_activate( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'trial_activate', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			// Check if already has an active API key
			$existing_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );
			if ( ! empty( $existing_key ) ) {
				return new WP_Error(
					'trial_already_active',
					__( 'An API key is already configured. Remove it first to start a new trial.', 'captcha-for-contact-form-7' ),
					[ 'status' => 409 ]
				);
			}

			// Gather site info for trial creation
			$domain      = wp_parse_url( home_url(), PHP_URL_HOST );
			$admin_email = get_option( 'admin_email' );

			$base_url     = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
			$api_endpoint = rtrim( $base_url, '/' ) . '/trial/create';

			$response = wp_remote_post( $api_endpoint, [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( [
					'domain'      => $domain,
					'admin_email' => $admin_email,
					'source'      => 'wp-plugin',
					'version'     => defined( 'FORGE12_CAPTCHA_VERSION' ) ? FORGE12_CAPTCHA_VERSION : 'unknown',
				] ),
				'timeout' => 15,
			] );

			if ( is_wp_error( $response ) ) {
				$this->get_logger()->error( 'Trial activation failed: API unreachable', [
					'error' => $response->get_error_message(),
				] );

				AuditLog::log(
					AuditLog::TYPE_API,
					'TRIAL_API_UNREACHABLE',
					AuditLog::SEVERITY_ERROR,
					sprintf( 'Trial activation failed: API unreachable (%s)', $response->get_error_message() ),
					[ 'domain' => $domain, 'error' => $response->get_error_message() ]
				);

				return new WP_Error(
					'trial_api_error',
					__( 'Could not reach the SilentShield API. Please try again later.', 'captcha-for-contact-form-7' ),
					[ 'status' => 502 ]
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code !== 200 && $code !== 201 ) {
				$message = $body['message'] ?? __( 'Trial activation failed. Please try again.', 'captcha-for-contact-form-7' );

				AuditLog::log(
					AuditLog::TYPE_API,
					'TRIAL_API_ERROR',
					AuditLog::SEVERITY_ERROR,
					sprintf( 'Trial activation failed: HTTP %d — %s', $code, $message ),
					[ 'domain' => $domain, 'http_code' => $code, 'message' => $message ]
				);

				return new WP_Error(
					'trial_creation_failed',
					$message,
					[ 'status' => $code ]
				);
			}

			if ( empty( $body['api_key'] ) ) {
				AuditLog::log(
					AuditLog::TYPE_API,
					'TRIAL_INVALID_RESPONSE',
					AuditLog::SEVERITY_ERROR,
					'Trial activation failed: API returned no api_key in response body',
					[ 'domain' => $domain, 'response_keys' => is_array( $body ) ? array_keys( $body ) : 'not_array' ]
				);

				return new WP_Error(
					'trial_invalid_response',
					__( 'Invalid response from API. No API key received.', 'captcha-for-contact-form-7' ),
					[ 'status' => 502 ]
				);
			}

			// Store the trial API key and enable beta mode
			$settings = get_option( 'f12-cf7-captcha-settings', [] );
			if ( ! isset( $settings['beta'] ) || ! is_array( $settings['beta'] ) ) {
				$settings['beta'] = [];
			}

			$settings['beta']['beta_captcha_api_key'] = sanitize_text_field( $body['api_key'] );
			$settings['beta']['beta_captcha_enable']   = 1;

			update_option( 'f12-cf7-captcha-settings', $settings );

			// Invalidate settings cache
			$this->Controller->invalidate_settings_cache();

			// Store trial metadata
			$trial_meta = [
				'activated_at' => current_time( 'mysql' ),
				'expires_at'   => $body['expires_at'] ?? gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) ),
				'domain'       => $domain,
				'plan'         => $body['plan'] ?? 'trial',
			];
			update_option( 'f12_cf7_captcha_trial_meta', $trial_meta );

			// Reset trial-expired audit flag so a future expiration is logged again
			delete_option( 'f12_cf7_captcha_trial_expired_logged' );

			// Clear any cached API key validation status
			delete_transient( 'f12_beta_api_key_status_' . md5( $body['api_key'] ) );

			$this->get_logger()->info( 'Trial activated successfully', [
				'domain'     => $domain,
				'expires_at' => $trial_meta['expires_at'],
			] );

			// Audit: log trial activation
			AuditLog::log(
				AuditLog::TYPE_TRIAL,
				'TRIAL_ACTIVATED',
				AuditLog::SEVERITY_INFO,
				'SilentShield API trial activated successfully',
				[ 'domain' => $domain, 'expires_at' => $trial_meta['expires_at'] ]
			);

			return new WP_REST_Response( [
				'status'     => 'success',
				'message'    => __( 'Trial activated! SilentShield API protection is now active.', 'captcha-for-contact-form-7' ),
				'expires_at' => $trial_meta['expires_at'],
			], 200 );

		} catch ( \Throwable $e ) {
			$this->get_logger()->error( 'handle_trial_activate(): Error', [
				'error' => $e->getMessage(),
			] );

			return new WP_Error(
				'trial_activate_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Settings: Get all plugin settings.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_settings_get( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'settings_get', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$settings = $this->Controller->get_settings();

			return new WP_REST_Response( $settings, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'settings_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Settings: Save plugin settings.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_settings_save( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'settings_save', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$body = $request->get_json_params();

			if ( empty( $body ) || ! is_array( $body ) ) {
				return new WP_Error( 'invalid_body', 'Request body must be a JSON object.', [ 'status' => 400 ] );
			}

			$current  = get_option( 'f12-cf7-captcha-settings', [] );
			$changed  = [];

			// Process each container (global, beta)
			foreach ( [ 'global', 'beta' ] as $container ) {
				if ( ! isset( $body[ $container ] ) || ! is_array( $body[ $container ] ) ) {
					continue;
				}

				if ( ! isset( $current[ $container ] ) || ! is_array( $current[ $container ] ) ) {
					$current[ $container ] = [];
				}

				foreach ( $body[ $container ] as $key => $value ) {
					$sanitized_key   = sanitize_text_field( $key );
					if ( is_array( $value ) ) {
						$sanitized_value = $value;
					} elseif ( is_int( $value ) || is_float( $value ) ) {
						$sanitized_value = $value;
					} elseif ( is_bool( $value ) ) {
						$sanitized_value = $value ? 1 : 0;
					} else {
						$sanitized_value = sanitize_text_field( (string) $value );
					}

					// Track changes for audit
					if ( ! isset( $current[ $container ][ $sanitized_key ] ) || $current[ $container ][ $sanitized_key ] !== $sanitized_value ) {
						$changed[] = $container . '.' . $sanitized_key;
					}

					$current[ $container ][ $sanitized_key ] = $sanitized_value;
				}
			}

			update_option( 'f12-cf7-captcha-settings', $current );
			$this->Controller->invalidate_settings_cache();

			// Audit: log settings change
			if ( ! empty( $changed ) ) {
				AuditLog::log(
					AuditLog::TYPE_SETTINGS,
					'SETTINGS_UPDATED',
					AuditLog::SEVERITY_INFO,
					sprintf( 'Settings updated by user #%d: %s', get_current_user_id(), implode( ', ', array_slice( $changed, 0, 10 ) ) ),
					[ 'changed_keys' => $changed, 'user_id' => get_current_user_id() ]
				);
			}

			return new WP_REST_Response( [ 'status' => 'success' ], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'settings_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Settings: Get all overrides (integration + form).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_overrides_get( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'overrides_get', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$overrides = get_option( 'f12-cf7-captcha-form-overrides', [
				'integration' => [],
				'form'        => [],
			] );

			return new WP_REST_Response( $overrides, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'overrides_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Forms: Discover available integrations and their forms.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_forms_discover( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'forms_discover', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			/** @var \f12_cf7_captcha\core\Compatibility $compatibility */
			$compatibility = $this->Controller->get_module( 'compatibility' );
			$components    = $compatibility->get_components();

			$integrations = [];

			foreach ( $components as $class_name => $component ) {
				if ( ! isset( $component['object'] ) ) {
					continue;
				}

				$object = $component['object'];
				$id     = $object->get_id();
				$name   = $object->get_name();

				$detected = false;
				try {
					$detected = method_exists( $object, 'is_installed' ) && $object->is_installed();
				} catch ( \Throwable $e ) {
					// skip detection errors
				}

				$forms = [];

				// Discover forms for integrations that have them
				if ( $detected ) {
					$forms = $this->discover_forms_for_integration( $id );
				}

				$integrations[] = [
					'id'       => $id,
					'name'     => $name,
					'detected' => $detected,
					'forms'    => $forms,
				];
			}

			return new WP_REST_Response( [ 'integrations' => $integrations ], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'forms_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Discover forms for a specific integration by querying its post type or API.
	 *
	 * @param string $integration_id
	 *
	 * @return array Array of ['id' => string, 'title' => string]
	 */
	private function discover_forms_for_integration( string $integration_id ): array {
		$forms     = [];
		$post_type = null;

		// Map integration IDs to their form post types
		$post_type_map = [
			'cf7'         => 'wpcf7_contact_form',
			'wpforms'     => 'wpforms',
			'avada'       => 'fusion_form',
			'jetform'     => 'jet-form-builder',
		];

		if ( isset( $post_type_map[ $integration_id ] ) ) {
			$post_type = $post_type_map[ $integration_id ];

			$posts = get_posts( [
				'post_type'      => $post_type,
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );

			foreach ( $posts as $post ) {
				$forms[] = [
					'id'    => (string) $post->ID,
					'title' => $post->post_title ?: sprintf( '#%d', $post->ID ),
				];
			}
		} elseif ( $integration_id === 'gravityforms' && class_exists( 'GFAPI' ) ) {
			$gf_forms = \GFAPI::get_forms();
			foreach ( $gf_forms as $gf_form ) {
				$forms[] = [
					'id'    => (string) $gf_form['id'],
					'title' => $gf_form['title'] ?? sprintf( '#%s', $gf_form['id'] ),
				];
			}
		} elseif ( $integration_id === 'fluentform' && defined( 'FLUENTFORM' ) ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'fluentform_forms';
			$results = $wpdb->get_results( "SELECT id, title FROM {$table} WHERE status = 'published' ORDER BY title ASC LIMIT 100" );

			if ( $results ) {
				foreach ( $results as $row ) {
					$forms[] = [
						'id'    => (string) $row->id,
						'title' => $row->title ?: sprintf( '#%s', $row->id ),
					];
				}
			}
		}

		return $forms;
	}

	/**
	 * Dashboard: Get summary statistics.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_dashboard_stats( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'dashboard_stats', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );

			if ( ! is_array( $counters ) ) {
				$counters = maybe_unserialize( $counters );
			}
			if ( ! is_array( $counters ) ) {
				$counters = [];
			}

			$checks_total = isset( $counters['checks_total'] ) ? (int) $counters['checks_total'] : 0;
			$checks_spam  = isset( $counters['checks_spam'] ) ? (int) $counters['checks_spam'] : 0;
			$checks_clean = $checks_total - $checks_spam;

			if ( $checks_clean < 0 ) {
				$checks_clean = 0;
			}

			return new WP_REST_Response( [
				'checks_total' => $checks_total,
				'checks_spam'  => $checks_spam,
				'checks_clean' => $checks_clean,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'dashboard_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Status: Get active modules and integrations.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_status_modules( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'status_modules', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			// Protection modules — check settings directly since is_enabled() is protected
			$global = $this->Controller->get_settings( '', 'global' );
			if ( ! is_array( $global ) ) {
				$global = [];
			}
			$beta = $this->Controller->get_settings( '', 'beta' );
			if ( ! is_array( $beta ) ) {
				$beta = [];
			}

			$module_defs = [
				'captcha-validator'             => [ 'name' => 'Captcha', 'key' => 'protection_captcha_enable' ],
				'timer-validator'               => [ 'name' => 'Timer', 'key' => 'protection_time_enable' ],
				'javascript-validator'          => [ 'name' => 'JavaScript Detection', 'key' => 'protection_javascript_enable' ],
				'browser-validator'             => [ 'name' => 'Browser Detection', 'key' => 'protection_browser_enable' ],
				'ip-validator'                  => [ 'name' => 'IP Rate Limiting', 'key' => 'protection_ip_enable' ],
				'ip-blacklist-validator'        => [ 'name' => 'IP Blacklist', 'key' => 'protection_rules_blacklist_enable' ],
				'rule-validator'                => [ 'name' => 'Content Rules', 'key' => 'protection_rules_url_enable' ],
				'multiple-submission-validator' => [ 'name' => 'Multiple Submission', 'key' => 'protection_multiple_submission_enable' ],
				'whitelist-validator'           => [ 'name' => 'Whitelist', 'key' => 'protection_whitelist_role_admin' ],
				'api-validator'                 => [ 'name' => 'SilentShield API', 'key' => 'beta_captcha_enable', 'container' => 'beta' ],
			];

			$modules       = [];
			$active_count  = 0;

			foreach ( $module_defs as $module_id => $def ) {
				$setting_key = $def['key'];
				$container   = $def['container'] ?? null;

				if ( $container === 'beta' ) {
					$enabled = ! empty( $beta[ $setting_key ] ) && (int) $beta[ $setting_key ] === 1;
				} else {
					$enabled = isset( $global[ $setting_key ] ) && (int) $global[ $setting_key ] === 1;
				}

				$modules[] = [
					'id'      => $module_id,
					'name'    => $def['name'],
					'enabled' => $enabled,
				];

				if ( $enabled ) {
					$active_count ++;
				}
			}

			// Integrations
			/** @var \f12_cf7_captcha\core\Compatibility $compatibility */
			$compatibility     = $this->Controller->get_module( 'compatibility' );
			$components        = $compatibility->get_components();
			$integrations      = [];
			$active_integrations = 0;

			foreach ( $components as $class_name => $component ) {
				if ( ! isset( $component['object'] ) ) {
					continue;
				}

				$object   = $component['object'];
				$detected = false;

				try {
					$detected = method_exists( $object, 'is_installed' ) && $object->is_installed();
				} catch ( \Throwable $e ) {
					// skip
				}

				$integrations[] = [
					'id'       => $object->get_id(),
					'name'     => $object->get_name(),
					'detected' => $detected,
				];

				if ( $detected ) {
					$active_integrations ++;
				}
			}

			return new WP_REST_Response( [
				'active_modules'      => $active_count,
				'total_modules'       => count( $modules ),
				'modules'             => $modules,
				'active_integrations' => $active_integrations,
				'total_integrations'  => count( $integrations ),
				'integrations'        => $integrations,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'status_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Cleanup: Get counts for each cleanup category.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_cleanup_counts( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'cleanup_counts', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$counts = [
				'captchas_all'         => 0,
				'captchas_validated'   => 0,
				'captchas_unvalidated' => 0,
				'ip_logs'              => 0,
				'ip_bans'              => 0,
				'logs_all'             => 0,
				'logs_old'             => 0,
				'timers'               => 0,
			];

			// Captcha counts
			try {
				/** @var Captcha_Validator $captcha_validator */
				$captcha_validator = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' );
				$captcha_cleaner   = $captcha_validator->get_captcha_cleaner();
				$counts['captchas_all']         = (int) $captcha_cleaner->get_count();
				$counts['captchas_validated']   = (int) $captcha_cleaner->get_count( 1 );
				$counts['captchas_unvalidated'] = (int) $captcha_cleaner->get_count( 0 );
			} catch ( \Throwable $e ) {
				// Module not available
			}

			// IP counts
			try {
				$ip_validator = $this->Controller->get_module( 'protection' )->get_module( 'ip-validator' );

				$ip_log = $ip_validator->get_log_cleaner();
				$counts['ip_logs'] = (int) $ip_log->get_count();

				$ip_ban = $ip_validator->get_ban_cleaner();
				$counts['ip_bans'] = (int) $ip_ban->get_count();
			} catch ( \Throwable $e ) {
				// Module not available
			}

			// Log counts
			try {
				$log_count = (int) Log_WordPress::get_instance()->get_count();
				$counts['logs_all'] = $log_count;
				$counts['logs_old'] = $log_count; // Approximation; cron cleans entries > 3 weeks
			} catch ( \Throwable $e ) {
				// Logger not available
			}

			// Timer counts
			try {
				$timer_controller = $this->Controller->get_module( 'timer' );
				$timer_cleaner    = $timer_controller->get_timer_cleaner();
				$counts['timers'] = (int) $timer_cleaner->get_count();
			} catch ( \Throwable $e ) {
				// Module not available
			}

			// Mail log counts
			$mail_log = new MailLog( $this->get_logger() );
			$counts['mail_log_all']     = $mail_log->get_total_row_count();
			$counts['mail_log_blocked'] = $mail_log->get_blocked_count();

			return new WP_REST_Response( $counts, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'cleanup_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Cleanup: Run a cleanup action by type.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_cleanup_run( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'cleanup_run', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$type    = $request->get_param( 'type' );
			$deleted = 0;

			switch ( $type ) {
				case 'captchas_all':
					$cleaner = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' )->get_captcha_cleaner();
					$deleted = (int) $cleaner->reset_table();
					break;

				case 'captchas_validated':
					$cleaner = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' )->get_captcha_cleaner();
					$deleted = (int) $cleaner->clean_validated();
					break;

				case 'captchas_unvalidated':
					$cleaner = $this->Controller->get_module( 'protection' )->get_module( 'captcha-validator' )->get_captcha_cleaner();
					$deleted = (int) $cleaner->clean_non_validated();
					break;

				case 'ip_logs':
					$ip_log  = $this->Controller->get_module( 'protection' )->get_module( 'ip-validator' )->get_log_cleaner();
					$deleted = (int) $ip_log->reset_table();
					break;

				case 'ip_bans':
					$ip_ban  = $this->Controller->get_module( 'protection' )->get_module( 'ip-validator' )->get_ban_cleaner();
					$deleted = (int) $ip_ban->reset_table();
					break;

				case 'logs_all':
					$log_cleaner = $this->Controller->get_module( 'log-cleaner' );
					$deleted     = (int) $log_cleaner->reset_table();
					break;

				case 'logs_old':
					$log_cleaner = $this->Controller->get_module( 'log-cleaner' );
					$deleted     = (int) $log_cleaner->clean();
					break;

				case 'timers':
					$timer_cleaner = $this->Controller->get_module( 'timer' )->get_timer_cleaner();
					$deleted       = (int) $timer_cleaner->reset_table();
					break;

				case 'mail_log_all':
					$mail_log = new MailLog( $this->get_logger() );
					$deleted  = $mail_log->reset_table();
					break;

				case 'mail_log_blocked':
					$mail_log = new MailLog( $this->get_logger() );
					$deleted  = $mail_log->delete_blocked();
					break;
			}

			AuditLog::log(
				AuditLog::TYPE_SETTINGS,
				'CLEANUP_RUN',
				AuditLog::SEVERITY_INFO,
				sprintf( 'Cleanup "%s" executed by user #%d, %d entries deleted', $type, get_current_user_id(), $deleted ),
				[ 'type' => $type, 'deleted' => $deleted, 'user_id' => get_current_user_id() ]
			);

			return new WP_REST_Response( [
				'deleted' => $deleted,
				'type'    => $type,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'cleanup_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	// ─── Mail Log Handlers ───────────────────────────────────────────────

	/**
	 * Mail Log: Get paginated entries.
	 */
	public function handle_mail_log_entries( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'mail_log_entries', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days        = (int) $request->get_param( 'days' );
			$limit       = (int) $request->get_param( 'limit' );
			$offset      = (int) $request->get_param( 'offset' );
			$status      = $request->get_param( 'status' );
			$form_plugin = $request->get_param( 'form_plugin' );
			$search      = $request->get_param( 'search' );

			if ( $status === 'all' || $status === '' ) {
				$status = null;
			}
			if ( $form_plugin === 'all' || $form_plugin === '' ) {
				$form_plugin = null;
			}

			$mail_log = new MailLog( $this->get_logger() );
			$entries  = $mail_log->get_entries( $limit, $offset, $days, $status, $form_plugin, $search );
			$total    = $mail_log->get_count( $days, $status, $form_plugin, $search );

			return new WP_REST_Response( [
				'data'  => $entries,
				'total' => $total,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'mail_log_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Mail Log: Get summary counts by status.
	 */
	public function handle_mail_log_summary( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'mail_log_summary', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$days     = (int) $request->get_param( 'days' );
			$mail_log = new MailLog( $this->get_logger() );

			return new WP_REST_Response( $mail_log->get_summary( $days ), 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'mail_log_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Mail Log: Get single entry with full body.
	 */
	public function handle_mail_log_entry( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'mail_log_entry', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$id       = (int) $request->get_param( 'id' );
			$mail_log = new MailLog( $this->get_logger() );
			$entry    = $mail_log->get_entry( $id );

			if ( ! $entry ) {
				return new WP_Error( 'not_found', 'Mail log entry not found.', [ 'status' => 404 ] );
			}

			return new WP_REST_Response( $entry, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'mail_log_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Mail Log: Delete single entry.
	 */
	public function handle_mail_log_delete( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'mail_log_delete', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$id       = (int) $request->get_param( 'id' );
			$mail_log = new MailLog( $this->get_logger() );
			$deleted  = $mail_log->delete_entry( $id );

			if ( ! $deleted ) {
				return new WP_Error( 'not_found', 'Mail log entry not found.', [ 'status' => 404 ] );
			}

			AuditLog::log(
				AuditLog::TYPE_SETTINGS,
				'MAIL_LOG_ENTRY_DELETED',
				AuditLog::SEVERITY_INFO,
				sprintf( 'Mail log entry #%d deleted by user #%d', $id, get_current_user_id() ),
				[ 'entry_id' => $id, 'user_id' => get_current_user_id() ]
			);

			return new WP_REST_Response( [ 'status' => 'deleted' ], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'mail_log_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Mail Log: Resend a previously blocked or sent mail.
	 */
	public function handle_mail_log_resend( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'mail_log_resend', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$id       = (int) $request->get_param( 'id' );
			$mail_log = new MailLog( $this->get_logger() );
			$entry    = $mail_log->get_entry( $id );

			if ( ! $entry ) {
				return new WP_Error( 'not_found', 'Mail log entry not found.', [ 'status' => 404 ] );
			}

			$recipient = $entry['recipient'] ?? '';
			$subject   = $entry['subject'] ?? '';
			$body      = $entry['body'] ?? '';

			if ( empty( $recipient ) ) {
				return new WP_Error( 'missing_recipient', 'No recipient address found in this entry.', [ 'status' => 400 ] );
			}

			// Reconstruct headers
			$headers = [];
			if ( ! empty( $entry['headers'] ) ) {
				$decoded = json_decode( $entry['headers'], true );
				if ( is_array( $decoded ) ) {
					$headers = $decoded;
				}
			}

			// Add From header if sender is available
			if ( ! empty( $entry['sender'] ) ) {
				$has_from = false;
				foreach ( $headers as $h ) {
					if ( stripos( $h, 'From:' ) === 0 ) {
						$has_from = true;
						break;
					}
				}
				if ( ! $has_from ) {
					$headers[] = 'From: ' . $entry['sender'];
				}
			}

			// Reconstruct attachments (only if files still exist)
			$attachments = [];
			if ( ! empty( $entry['attachments'] ) ) {
				$decoded = json_decode( $entry['attachments'], true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $path ) {
						if ( file_exists( $path ) ) {
							$attachments[] = $path;
						}
					}
				}
			}

			// Send the mail
			$sent = wp_mail( $recipient, $subject, $body, $headers, $attachments );

			if ( $sent ) {
				// Update status to 'resent'
				$mail_log->update_status( $id, 'resent' );

				AuditLog::log(
					AuditLog::TYPE_SETTINGS,
					'MAIL_RESENT',
					AuditLog::SEVERITY_INFO,
					sprintf( 'Mail log entry #%d resent to %s by user #%d', $id, $recipient, get_current_user_id() ),
					[ 'entry_id' => $id, 'recipient' => $recipient, 'user_id' => get_current_user_id() ]
				);

				return new WP_REST_Response( [ 'status' => 'sent', 'message' => 'Mail successfully resent.' ], 200 );
			}

			return new WP_Error( 'send_failed', 'wp_mail() returned false. Check your mail configuration.', [ 'status' => 500 ] );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'mail_log_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * API: Validate an API key against the SilentShield API.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_api_validate_key( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'api_validate_key', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$api_key = $request->get_param( 'api_key' );

			if ( empty( $api_key ) ) {
				return new WP_REST_Response( [
					'valid'   => false,
					'message' => __( 'API key is empty.', 'captcha-for-contact-form-7' ),
				], 200 );
			}

			// Check transient cache first
			$cache_key = 'f12_beta_api_key_status_' . md5( $api_key );
			$cached    = get_transient( $cache_key );

			if ( $cached !== false ) {
				return new WP_REST_Response( $cached, 200 );
			}

			$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
			$response = wp_remote_post( rtrim( $base_url, '/' ) . '/keys/validate', [
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => wp_json_encode( [
					'domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
				] ),
				'timeout' => 10,
			] );

			if ( is_wp_error( $response ) ) {
				return new WP_REST_Response( [
					'valid'   => false,
					'message' => __( 'Could not reach the API server.', 'captcha-for-contact-form-7' ),
				], 200 );
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			$result = [
				'valid'   => $code === 200 && ! empty( $body['valid'] ),
				'message' => $body['message'] ?? ( $code === 200 ? __( 'API key is valid.', 'captcha-for-contact-form-7' ) : __( 'API key is invalid.', 'captcha-for-contact-form-7' ) ),
			];

			// Cache for 5 minutes
			set_transient( $cache_key, $result, 300 );

			return new WP_REST_Response( $result, 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'api_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Trial: Get current trial status.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_trial_status( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'trial_status', self::RATE_LIMIT_ADMIN_MAX );
		if ( $rate_check !== null ) {
			return $rate_check;
		}

		try {
			$trial_meta = get_option( 'f12_cf7_captcha_trial_meta', [] );
			$api_key    = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

			if ( empty( $trial_meta ) || empty( $api_key ) ) {
				return new WP_REST_Response( [
					'active'         => false,
					'expires_at'     => null,
					'activated_at'   => null,
					'days_remaining' => 0,
				], 200 );
			}

			$expires_at     = $trial_meta['expires_at'] ?? null;
			$days_remaining = 0;

			if ( $expires_at ) {
				$now            = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
				$expiry         = new \DateTime( $expires_at, new \DateTimeZone( 'UTC' ) );
				$diff           = $now->diff( $expiry );
				$days_remaining = $diff->invert ? 0 : (int) $diff->days;
			}

			return new WP_REST_Response( [
				'active'         => $days_remaining > 0,
				'expires_at'     => $expires_at,
				'activated_at'   => $trial_meta['activated_at'] ?? null,
				'days_remaining' => $days_remaining,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'trial_error', $e->getMessage(), [ 'status' => 500 ] );
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

			// Audit: log rate limit violation (throttled by AuditLog)
			AuditLog::log(
				AuditLog::TYPE_RATE_LIMIT,
				'RATE_LIMIT_EXCEEDED',
				AuditLog::SEVERITY_WARNING,
				sprintf( 'Rate limit exceeded for endpoint "%s" (%d/%d)', $endpoint, $count, $limit ),
				[ 'endpoint' => $endpoint, 'limit' => $limit, 'window_seconds' => self::RATE_LIMIT_WINDOW ]
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
