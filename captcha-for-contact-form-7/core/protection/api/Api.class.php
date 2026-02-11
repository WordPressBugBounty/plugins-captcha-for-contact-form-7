<?php

namespace f12_cf7_captcha\core\protection\api;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends BaseProtection {
	private string $api_endpoint;

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );
		$this->set_message( __( 'behavior-protection', 'captcha-for-contact-form-7' ) );

		$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io';
		$this->api_endpoint = rtrim( $base_url, '/' ) . '/v1/verify';
	}

	public function is_enabled(): bool {
		$raw_setting = $this->Controller->get_settings( 'beta_captcha_enable', 'beta' );

		if ( $raw_setting === '' || $raw_setting === null ) {
			$raw_setting = 1;
		}

		return (bool) apply_filters( 'f12-cf7-captcha-skip-validation-api', $raw_setting );
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

        // Determine behavior_nonce (directly or from formData)
        $nonce = null;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        if ( ! empty( $_POST['behavior_nonce'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
            $nonce = sanitize_text_field( wp_unslash( $_POST['behavior_nonce'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        } elseif ( ! empty( $_POST['formData'] ) || ! empty( $_POST['data'] ) ) {
			// Avada & Fluent Forms special cases. Avada uses "formData". FluentForms uses "data"
	        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by the calling compatibility controller; sanitized below
	        $raw = isset( $_POST['formData'] ) ? wp_unslash( $_POST['formData'] ) : wp_unslash( $_POST['data'] );

	        // Extract only behavior_nonce instead of parsing the entire string (prevents DoS via deeply nested keys)
	        if ( is_string( $raw ) && preg_match( '/(?:^|&)behavior_nonce=([^&]*)/', $raw, $matches ) ) {
		        $nonce = sanitize_text_field( urldecode( $matches[1] ) );
	        }
        }

        if ( empty( $nonce ) ) {
            $is_spam = true; // no nonce -> suspicious / block
        } else {
            $response = wp_remote_post( $this->api_endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key'      => $api_key,
                ],
                'body'    => wp_json_encode( [ 'nonce' => $nonce ] ),
                'timeout' => 5,
            ] );

            if ( is_wp_error( $response ) ) {
                $fail_closed = apply_filters( 'f12-cf7-captcha-api-fail-closed', false );
                $this->get_logger()->error( 'API request failed', [
                    'plugin'    => 'f12-cf7-captcha',
                    'error'     => $response->get_error_message(),
                    'fail_mode' => $fail_closed ? 'closed' : 'open',
                ] );
                $is_spam = (bool) $fail_closed;
            } else {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( empty( $data['ok'] ) || $data['verdict'] !== 'human' ) {
                    $this->get_logger()->info( 'Spam-Check completed. Found spam.', [
                        'plugin'       => 'f12-cf7-captcha',
                        'protection'   => 'API',
                        'data'         => $data,
                        'api-endpoint' => $this->api_endpoint,
                        'api-key'      => substr( $api_key, 0, 4 ) . '***',
                    ] );
                    $is_spam = true;
                } else {
                    $is_spam = false;
                }
            }
        }

        return $is_spam;
    }

}