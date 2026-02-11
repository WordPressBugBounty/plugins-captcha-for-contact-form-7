<?php

namespace f12_cf7_captcha;

use Exception;
use Forge12\Shared\Logger;

/**
 * Strips sensitive data from the settings array for telemetry.
 *
 * Only keeps boolean and integer values (feature flags, toggle states).
 * Removes API keys, blacklist content, and any other free-text strings.
 *
 * @param array $settings The raw plugin settings.
 *
 * @return array Sanitized settings safe for telemetry transmission.
 */
function sanitize_telemetry_settings( array $settings ): array {
	$safe = [];

	foreach ( $settings as $key => $value ) {
		if ( is_array( $value ) ) {
			$filtered = [];
			foreach ( $value as $sub_key => $sub_value ) {
				if ( is_bool( $sub_value ) || is_int( $sub_value ) || $sub_value === '0' || $sub_value === '1' ) {
					$filtered[ $sub_key ] = (int) $sub_value;
				}
			}
			if ( ! empty( $filtered ) ) {
				$safe[ $key ] = $filtered;
			}
		} elseif ( is_bool( $value ) || is_int( $value ) || $value === '0' || $value === '1' ) {
			$safe[ $key ] = (int) $value;
		}
	}

	return $safe;
}

/**
 * Builds the telemetry payload for the plugin
 *
 * @return array
 */
function build_telemetry_payload(): array {
	// Initialize logger (make sure $logger is available)
	$logger = Logger::getInstance();

	// Logger: Start der Telemetrie-Erstellung
	$logger->info("Telemetry payload creation started", [
		'plugin' => FORGE12_CAPTCHA_SLUG,
	]);

	try {
		$counters = get_option('f12_cf7_captcha_telemetry_counters', []);

		if (!is_array($counters)) {
			$logger->warning("Telemetry: Counters are not an array, attempting unserialize.", [
				'plugin' => FORGE12_CAPTCHA_SLUG,
			]);

			$counters = maybe_unserialize($counters);
		}

		if (empty($counters)) {
			$logger->info("Telemetry: Counters are empty, initializing as stdClass.", [
				'plugin' => FORGE12_CAPTCHA_SLUG,
			]);

			$counters = new \stdClass(); // instead of empty string
		}

		// Only send non-sensitive settings (booleans/integers).
		// Strips API keys, blacklist content, and any other string values.
		$raw_settings = get_option( 'f12-cf7-captcha-settings', [] );
		$safe_settings = sanitize_telemetry_settings( is_array( $raw_settings ) ? $raw_settings : [] );

		// Successful creation of the telemetry payload
		$payload = [
			'installation_uuid' => f12_cf7_captcha_get_installation_uuid(),
			'plugin_slug'       => FORGE12_CAPTCHA_SLUG,
			'plugin_version'    => FORGE12_CAPTCHA_VERSION,
			'snapshot_date'     => gmdate('Y-m-d'),
			'settings'          => $safe_settings,
			'features'          => [
				'cf7_enabled' => 1,
				'ip_ban'      => get_option('f12_cf7_ip_ban_enabled', 0),
			],
			'counters'          => $counters,
			'wp_version'        => get_bloginfo('version'),
			'php_version'       => PHP_VERSION,
			'locale'            => get_locale(),
		];

		$logger->info("Telemetry payload created successfully", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'payload' => $payload,
		]);

		return $payload;

	} catch (Exception $e) {
		// Error handling if something goes wrong
		$logger->error("Error creating telemetry payload", [
			'plugin'   => FORGE12_CAPTCHA_SLUG,
			'message'  => $e->getMessage(),
			'trace'    => $e->getTraceAsString(),
		]);

		return []; // Return an empty array in case of error
	}
}

/**
 * Sends the telemetry data to the server
 *
 * @return void
 */
function send_telemetry_snapshot(): void {
	$logger = Logger::getInstance();
	$payload = build_telemetry_payload();

	$logger->debug("Telemetry payload prepared", [
		'plugin'  => FORGE12_CAPTCHA_SLUG,
		'payload' => $payload,
	]);

	$response = wp_remote_post('https://api.silentshield.io/api/telemetry/snapshot', [
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8',
		],
		'body'    => wp_json_encode($payload),
		'timeout' => 15,
	]);

	if (is_wp_error($response)) {
		$logger->error("Telemetry failed", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $response->get_error_message(),
		]);
		return;
	}

	$code = wp_remote_retrieve_response_code($response);

	if ($code === 201) {
		$logger->info("Telemetry successfully sent", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'code'   => $code,
		]);
	} else {
		$logger->warning("Telemetry unexpected response", [
			'plugin'   => FORGE12_CAPTCHA_SLUG,
			'code'     => $code,
			'response' => wp_remote_retrieve_body($response),
		]);
	}
}

/**
 * Hook for the daily cron job
 */
add_action('f12_cf7_captcha_daily_telemetry', __NAMESPACE__ . '\\send_telemetry_snapshot');
