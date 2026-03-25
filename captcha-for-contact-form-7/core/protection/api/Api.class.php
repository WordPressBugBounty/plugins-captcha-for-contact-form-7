<?php

namespace f12_cf7_captcha\core\protection\api;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\log\AuditLog;
use f12_cf7_captcha\core\log\BlockLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends BaseProtection {
	private string $api_endpoint;

	/**
	 * Last API response data (available after is_spam() call).
	 * Used by the block log to capture reason codes and score breakdown.
	 */
	private ?array $last_api_response = null;

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );
		$this->set_message( __( 'behavior-protection', 'captcha-for-contact-form-7' ) );

		$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		$this->api_endpoint = rtrim( $base_url, '/' ) . '/captcha/verify-nonce';
	}

	/**
	 * Get the last API response data (reason_codes, score, score_breakdown).
	 */
	public function get_last_api_response(): ?array {
		return $this->last_api_response;
	}

	public function is_enabled(): bool {
		$raw_setting = $this->Controller->get_settings( 'beta_captcha_enable', 'beta' );

		if ( $raw_setting === '' || $raw_setting === null ) {
			$raw_setting = 0;
		}

		$is_enabled = (int) $raw_setting === 1;

		return (bool) apply_filters( 'f12-cf7-captcha-skip-validation-api', $is_enabled );
	}

	public function success(): void {
	}

    public function is_spam(): bool {

        if ( ! $this->is_enabled() ) {
            return false;
        }

        $api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

        if ( $api_key === '' || $api_key === null ) {
            return false;
        }

        // DEBUG: Log all POST keys to identify nonce delivery issues
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug logging only
        $post_keys = array_keys( $_POST );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug logging only
        $has_behavior_nonce = isset( $_POST['behavior_nonce'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug logging only
        $has_formData = isset( $_POST['formData'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug logging only
        $has_data = isset( $_POST['data'] );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Temporary debug logging
        error_log( sprintf(
            '[SilentShield DEBUG] is_spam() called. POST keys: [%s] | behavior_nonce present: %s | formData present: %s | data present: %s',
            implode( ', ', $post_keys ),
            $has_behavior_nonce ? 'YES' : 'NO',
            $has_formData ? 'YES' : 'NO',
            $has_data ? 'YES' : 'NO'
        ) );

        // Determine behavior_nonce (directly or from formData)
        $nonce = null;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        if ( ! empty( $_POST['behavior_nonce'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
            $nonce = sanitize_text_field( wp_unslash( $_POST['behavior_nonce'] ) );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
            error_log( sprintf( '[SilentShield DEBUG] behavior_nonce found in $_POST: %s', substr( $nonce, 0, 16 ) . '...' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        } elseif ( ! empty( $_POST['formData'] ) || ! empty( $_POST['data'] ) ) {
			// Avada & Fluent Forms special cases. Avada uses "formData". FluentForms uses "data"
	        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by the calling compatibility controller; sanitized below
	        $raw = isset( $_POST['formData'] ) ? wp_unslash( $_POST['formData'] ) : wp_unslash( $_POST['data'] );

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
            error_log( sprintf( '[SilentShield DEBUG] Trying formData/data fallback. Raw type: %s, length: %d', gettype( $raw ), is_string( $raw ) ? strlen( $raw ) : 0 ) );

	        // Extract only behavior_nonce instead of parsing the entire string (prevents DoS via deeply nested keys)
	        if ( is_string( $raw ) && preg_match( '/(?:^|&)behavior_nonce=([^&]*)/', $raw, $matches ) ) {
		        $nonce = sanitize_text_field( urldecode( $matches[1] ) );
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                error_log( sprintf( '[SilentShield DEBUG] behavior_nonce extracted from formData: %s', substr( $nonce, 0, 16 ) . '...' ) );
	        } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                error_log( '[SilentShield DEBUG] behavior_nonce NOT found in formData/data string' );
            }
        }

        if ( empty( $nonce ) ) {
            $is_spam = true; // no nonce -> suspicious / block

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
            error_log( '[SilentShield DEBUG] RESULT: No nonce found -> marking as spam' );

            // Log missing nonce to block log
            $this->maybe_log_api_block( 'API_NO_NONCE', 'No behavior nonce was submitted with the form' );
        } else {
            // Use verbose=1 when detailed tracking is enabled to get score breakdown
            $endpoint = $this->api_endpoint;
            if ( BlockLog::is_enabled() ) {
                $endpoint .= ( strpos( $endpoint, '?' ) === false ? '?' : '&' ) . 'verbose=1';
            }

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
            error_log( sprintf( '[SilentShield DEBUG] Calling verify-nonce API: %s | nonce: %s | api-key: %s***', $endpoint, substr( $nonce, 0, 16 ) . '...', substr( $api_key, 0, 8 ) ) );

            $response = wp_remote_post( $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key'      => $api_key,
                ],
                'body'    => wp_json_encode( [ 'nonce' => $nonce ] ),
                'timeout' => 5,
            ] );

            if ( is_wp_error( $response ) ) {
                $fail_closed = apply_filters( 'f12-cf7-captcha-api-fail-closed', false );

                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                error_log( sprintf( '[SilentShield DEBUG] API request FAILED (WP_Error): %s | fail_mode: %s', $response->get_error_message(), $fail_closed ? 'closed' : 'open' ) );

                $this->get_logger()->error( 'API request failed', [
                    'plugin'    => 'f12-cf7-captcha',
                    'error'     => $response->get_error_message(),
                    'fail_mode' => $fail_closed ? 'closed' : 'open',
                ] );
                $is_spam = (bool) $fail_closed;

                AuditLog::log(
                    AuditLog::TYPE_API,
                    'API_VERIFY_UNREACHABLE',
                    AuditLog::SEVERITY_ERROR,
                    sprintf( 'SilentShield verify API unreachable: %s (fail-%s)', $response->get_error_message(), $fail_closed ? 'closed' : 'open' ),
                    [ 'endpoint' => $this->api_endpoint, 'error' => $response->get_error_message(), 'fail_closed' => $fail_closed ]
                );

                if ( $is_spam ) {
                    $this->maybe_log_api_block( 'API_UNREACHABLE', 'SilentShield API unreachable (fail-closed mode)' );
                }
            } else {
                $raw_body  = wp_remote_retrieve_body( $response );
                $data      = json_decode( $raw_body, true );
                $http_code = wp_remote_retrieve_response_code( $response );
                $this->last_api_response = $data;

                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                error_log( sprintf(
                    '[SilentShield DEBUG] API response: HTTP %d | ok=%s | verdict=%s | confidence=%s | body=%s',
                    $http_code,
                    isset( $data['ok'] ) ? ( $data['ok'] ? 'true' : 'false' ) : 'MISSING',
                    $data['verdict'] ?? 'MISSING',
                    $data['confidence'] ?? 'MISSING',
                    substr( $raw_body, 0, 500 )
                ) );

                // Audit HTTP error responses or unparseable JSON
                if ( $http_code >= 400 || $data === null ) {
                    AuditLog::log(
                        AuditLog::TYPE_API,
                        'API_VERIFY_ERROR_RESPONSE',
                        $http_code >= 500 ? AuditLog::SEVERITY_ERROR : AuditLog::SEVERITY_WARNING,
                        sprintf( 'SilentShield verify API returned HTTP %d', $http_code ),
                        [
                            'endpoint'  => $this->api_endpoint,
                            'http_code' => $http_code,
                            'body_null' => $data === null,
                        ]
                    );
                }

                if ( empty( $data['ok'] ) || $data['verdict'] !== 'human' ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                    error_log( sprintf(
                        '[SilentShield DEBUG] RESULT: SPAM | empty(ok)=%s | verdict!==human: %s | reason_codes: %s',
                        empty( $data['ok'] ) ? 'true' : 'false',
                        ( $data['verdict'] ?? 'null' ) !== 'human' ? 'true' : 'false',
                        implode( ', ', $data['reason_codes'] ?? [] )
                    ) );

                    $this->get_logger()->info( 'Spam-Check completed. Found spam.', [
                        'plugin'       => 'f12-cf7-captcha',
                        'protection'   => 'API',
                        'data'         => $data,
                        'api-endpoint' => $this->api_endpoint,
                        'api-key'      => substr( $api_key, 0, 4 ) . '***',
                    ] );
                    $is_spam = true;

                    // Log with API details
                    $verdict     = $data['verdict'] ?? 'unknown';
                    $score       = $data['confidence'] ?? 0.0;
                    $reason_code = $verdict === 'bot' ? 'API_VERDICT_BOT' : 'API_VERDICT_SUSPICIOUS';
                    $detail      = sprintf( 'SilentShield API: verdict=%s, score=%.3f', $verdict, $score );

                    $extra = [
                        'score'        => $score,
                        'reason_codes' => $data['reason_codes'] ?? [],
                        'verdict'      => $verdict,
                    ];
                    if ( isset( $data['score_breakdown'] ) ) {
                        $extra['meta'] = [ 'score_breakdown' => $data['score_breakdown'] ];
                    }

                    $this->maybe_log_api_block( $reason_code, $detail, $extra );
                } else {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging
                    error_log( sprintf( '[SilentShield DEBUG] RESULT: CLEAN | verdict=human | confidence=%s', $data['confidence'] ?? '?' ) );
                    $is_spam = false;
                }
            }
        }

        return $is_spam;
    }

    /**
     * Log an API block to the detailed block log (if enabled).
     *
     * @param string $reason_code Machine-readable reason code.
     * @param string $detail      Human-readable explanation.
     * @param array  $extra       Optional extra data (score, reason_codes, meta).
     */
    private function maybe_log_api_block( string $reason_code, string $detail, array $extra = [] ): void {
        if ( ! BlockLog::is_enabled() ) {
            return;
        }

        $block_log = new BlockLog( $this->get_logger() );
        $block_log->log( 'api', $reason_code, $detail, $extra );
    }

}