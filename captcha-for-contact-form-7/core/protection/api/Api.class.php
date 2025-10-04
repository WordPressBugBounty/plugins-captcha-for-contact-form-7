<?php

namespace f12_cf7_captcha\core\protection\api;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

class Api extends BaseProtection {
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );
		$this->get_logger()->info(
			"__construct(): Api Controller initialisiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$this->set_message( __( 'behavior-protection', 'captcha-for-contact-form-7' ) );

		$this->get_logger()->info(
			"__construct(): Initialisierung abgeschlossen",
			[ 'plugin' => 'f12-cf7-captcha' ]
		);
	}

	protected function is_enabled(): bool {
		$raw_setting = $this->Controller->get_settings( 'beta_captcha_enable', 'beta' );

		if ( $raw_setting === '' || $raw_setting === null ) {
			// Default: aktiv, wenn nicht explizit gesetzt
			$raw_setting = 1;
		}

		$is_enabled = apply_filters( 'f12-cf7-captcha-skip-validation-api', $raw_setting );

		$this->get_logger()->debug( "API Protection Status geprüft", [
			'plugin'      => 'f12-cf7-captcha',
			'raw_setting' => $raw_setting,
			'final_value' => $is_enabled
		] );

		return (bool) $is_enabled;
	}

	public function success(): void {
		$this->get_logger()->info( "Validierung erfolgreich", [
			'plugin' => 'f12-cf7-captcha',
			'status' => 'success'
		] );
	}

    public function is_spam(): bool {
        if ( ! $this->is_enabled() ) {
            $this->get_logger()->debug( "Spam-Check übersprungen (Feature [API] deaktiviert)", [
                'plugin' => 'f12-cf7-captcha'
            ] );
            return false;
        }

        $api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

        if ( $api_key === '' || $api_key === null ) {
            $this->get_logger()->debug( "API Key not defined within the settings of the plugin. Skipping spam-check.", [ 'plugin' => 'f12-cf7-captcha' ] );
            return false;
        }

        // 🔎 behavior_nonce ermitteln (direkt oder aus formData)
        $nonce = null;

        if ( ! empty( $_POST['behavior_nonce'] ) ) {
            $nonce = sanitize_text_field( $_POST['behavior_nonce'] );
        } elseif ( ! empty( $_POST['formData'] ) || ! empty( $_POST['data'] ) ) {
			// Avada & Fluent Forms special cases. Avada uses "formData". FluentForms uses "data"
	        $parsed = [];
	        $raw = $_POST['formData'] ?? $_POST['data']; // beide Varianten abfangen
	        parse_str( $raw, $parsed );
	        if ( ! empty( $parsed['behavior_nonce'] ) ) {
		        $nonce = sanitize_text_field( $parsed['behavior_nonce'] );
	        }
        }

        if ( empty( $nonce ) ) {
            $this->get_logger()->debug( "Spam-Check - behavior_nonce missing. Mark as spam", [ 'plugin' => 'f12-cf7-captcha' ] );
            $is_spam = true; // kein Nonce → verdächtig / blocken
        } else {
            $response = wp_remote_post( 'https://api.silentshield.io/api/captcha/verify-nonce', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key'      => $api_key,
                ],
                'body'    => wp_json_encode( [ 'nonce' => $nonce ] ),
                'timeout' => 5,
            ] );

            if ( is_wp_error( $response ) ) {
                $this->get_logger()->error( "API request failed, skip spam-block (fail-open)", [
                    'plugin' => 'f12-cf7-captcha',
                    'error'  => $response->get_error_message()
                ] );
                $is_spam = false;
            } else {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( empty( $data['ok'] ) || $data['verdict'] !== 'human' ) {
                    $this->get_logger()->info( 'Spam-Check completed. Found spam.', [ 'plugin' => 'f12-cf7-captcha' ] );
                    $is_spam = true; // blockieren
                } else {
                    $is_spam = false;
                }
				$this->get_logger()->debug( "Spam-Check completed. Found no spam.", [ 'plugin' => 'f12-cf7-captcha', 'protection' => 'API' ] );
            }
        }

        $this->get_logger()->info( "Spam-Check durchgeführt", [
            'plugin' => 'f12-cf7-captcha',
            'result' => $is_spam ? 'SPAM erkannt' : 'kein Spam'
        ] );

        return $is_spam;
    }

}