<?php

namespace f12_cf7_captcha;

use Exception;
use Forge12\Shared\Logger;

/**
 * Baut die Telemetry-Payload für das Plugin
 *
 * @return array
 */
function build_telemetry_payload(): array {
	// Logger initialisieren (stellen Sie sicher, dass $logger verfügbar ist)
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

			$counters = new \stdClass(); // statt leerem String
		}

		// Erfolgreiche Erstellung des Telemetrie-Payloads
		$payload = [
			'installation_uuid' => f12_cf7_captcha_get_installation_uuid(),
			'plugin_slug'       => FORGE12_CAPTCHA_SLUG,
			'plugin_version'    => FORGE12_CAPTCHA_VERSION,
			'snapshot_date'     => gmdate('Y-m-d'),
			'settings'          => get_option('f12-cf7-captcha-settings', []),
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
		// Fehlerhandling falls etwas schiefgeht
		$logger->error("Error creating telemetry payload", [
			'plugin'   => FORGE12_CAPTCHA_SLUG,
			'message'  => $e->getMessage(),
			'trace'    => $e->getTraceAsString(),
		]);

		return []; // Rückgabe eines leeren Arrays im Fehlerfall
	}
}

/**
 * Sendet die Telemetry-Daten an den Server
 *
 * @return void
 */
function send_telemetry_snapshot(): void {
	$logger = Logger::getInstance();
	$payload = build_telemetry_payload();

	$logger->debug("Telemetry Payload vorbereitet", [
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
		$logger->error("Telemetry fehlgeschlagen", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $response->get_error_message(),
		]);
		return;
	}

	$code = wp_remote_retrieve_response_code($response);

	if ($code === 201) {
		$logger->info("Telemetry erfolgreich gesendet", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'code'   => $code,
		]);
	} else {
		$logger->warning("Telemetry unerwartete Antwort", [
			'plugin'   => FORGE12_CAPTCHA_SLUG,
			'code'     => $code,
			'response' => wp_remote_retrieve_body($response),
		]);
	}
}

/**
 * Hook für den Daily-Cronjob
 */
add_action('f12_cf7_captcha_daily_telemetry', __NAMESPACE__ . '\\send_telemetry_snapshot');
