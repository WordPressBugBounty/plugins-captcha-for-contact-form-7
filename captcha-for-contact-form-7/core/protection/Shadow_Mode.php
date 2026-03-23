<?php

namespace f12_cf7_captcha\core\protection;

use f12_cf7_captcha\CF7Captcha;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shadow Mode — API Ghost/Comparison Mode (Phase 2.3)
 *
 * When enabled (and the API is NOT already active), this class tracks all local
 * protection verdicts and maintains counters that power the "API Comparison"
 * section on the Analytics page.
 *
 * The actual SilentShield shadow endpoint does not exist yet, so no real API
 * calls are made. Instead, shadow counters are maintained locally and a
 * statistical estimate is shown to the user:
 *
 *   "Based on industry data, SilentShield API catches ~30% more sophisticated
 *    bots that bypass rule-based detection."
 *
 * A prepared (but dormant) fire-and-forget call to the future shadow endpoint
 * is included behind the `F12_CAPTCHA_SHADOW_API_LIVE` constant flag.
 */
class Shadow_Mode {

	/**
	 * Option key used to persist weekly shadow counters.
	 */
	const OPTION_KEY = 'f12_cf7_captcha_shadow_counters';

	/**
	 * The estimated percentage of additional bots the API would catch
	 * beyond what rule-based (standalone) detection blocks.
	 */
	const ESTIMATED_ADDITIONAL_CATCH_PCT = 30;

	/**
	 * Check whether shadow mode is enabled AND should run.
	 *
	 * Shadow mode only runs when:
	 *  1. The setting `protection_api_shadow_mode` is turned on.
	 *  2. The API is NOT already actively protecting (no key or not enabled).
	 *
	 * @return bool
	 */
	public static function should_run(): bool {
		$instance = CF7Captcha::get_instance();

		$shadow_enabled = (int) $instance->get_settings( 'protection_api_shadow_mode', 'global' );
		if ( ! $shadow_enabled ) {
			return false;
		}

		// If the API is already active with a valid key, shadow mode is unnecessary.
		$api_key    = $instance->get_settings( 'beta_captcha_api_key', 'beta' );
		$api_enable = (int) $instance->get_settings( 'beta_captcha_enable', 'beta' );

		if ( $api_enable && ! empty( $api_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Record a shadow-mode observation after the local protection verdict.
	 *
	 * Called from Protection::is_spam() after all local modules have run.
	 *
	 * @param bool   $is_spam        The local verdict (true = blocked).
	 * @param string $block_module   The module name that triggered the block (empty if ham).
	 * @param array  $post_data      The (raw) form submission data — NOT sent anywhere.
	 */
	public static function record( bool $is_spam, string $block_module = '', array $post_data = [] ): void {
		if ( ! self::should_run() ) {
			return;
		}

		// --- Local counter tracking ------------------------------------------

		$counters = get_option( self::OPTION_KEY, self::default_counters() );

		// Ensure all keys exist (handles upgrades from older stored data).
		$counters = wp_parse_args( $counters, self::default_counters() );

		$counters['total']        = (int) $counters['total'] + 1;
		$counters['last_updated'] = time();

		$week_key = 'week_' . gmdate( 'o_W' ); // e.g. week_2026_11

		if ( ! isset( $counters['weekly'][ $week_key ] ) ) {
			$counters['weekly'][ $week_key ] = [ 'total' => 0, 'blocked' => 0, 'passed' => 0 ];
		}

		$counters['weekly'][ $week_key ]['total'] = (int) $counters['weekly'][ $week_key ]['total'] + 1;

		if ( $is_spam ) {
			$counters['blocked'] = (int) $counters['blocked'] + 1;
			$counters['weekly'][ $week_key ]['blocked'] = (int) $counters['weekly'][ $week_key ]['blocked'] + 1;
		} else {
			$counters['passed'] = (int) $counters['passed'] + 1;
			$counters['weekly'][ $week_key ]['passed'] = (int) $counters['weekly'][ $week_key ]['passed'] + 1;
		}

		// Keep only the last 12 weeks of data to avoid unbounded growth.
		$counters['weekly'] = self::prune_weekly( $counters['weekly'], 12 );

		update_option( self::OPTION_KEY, $counters, false );

		// --- Prepared (dormant) API shadow call ------------------------------
		// Uncomment or set F12_CAPTCHA_SHADOW_API_LIVE = true when the
		// SilentShield shadow endpoint is deployed on the API side.

		if ( defined( 'F12_CAPTCHA_SHADOW_API_LIVE' ) && F12_CAPTCHA_SHADOW_API_LIVE ) {
			$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io';

			wp_remote_post( rtrim( $base_url, '/' ) . '/api/v1/shadow/check', [
				'timeout'  => 2,
				'blocking' => false, // Fire-and-forget
				'body'     => wp_json_encode( [
					'domain'        => site_url(),
					'local_verdict' => $is_spam ? 'spam' : 'ham',
					'local_module'  => $block_module,
					'timestamp'     => time(),
					// NO personal data — no IP, no email, no form content.
				] ),
				'headers'  => [ 'Content-Type' => 'application/json' ],
			] );
		}
	}

	/**
	 * Return the current shadow counters (with computed estimates).
	 *
	 * @return array{
	 *     total: int,
	 *     blocked: int,
	 *     passed: int,
	 *     estimated_additional: int,
	 *     estimated_additional_pct: int,
	 *     current_week: array{total: int, blocked: int, passed: int},
	 *     last_updated: int
	 * }
	 */
	public static function get_stats(): array {
		$counters = get_option( self::OPTION_KEY, self::default_counters() );
		$counters = wp_parse_args( $counters, self::default_counters() );

		$week_key    = 'week_' . gmdate( 'o_W' );
		$current_week = $counters['weekly'][ $week_key ] ?? [ 'total' => 0, 'blocked' => 0, 'passed' => 0 ];

		// Statistical estimate: X% of submissions that passed local checks
		// would have been caught by the API.
		$passed              = (int) $counters['passed'];
		$estimated_additional = (int) round( $passed * self::ESTIMATED_ADDITIONAL_CATCH_PCT / 100 );

		$week_passed              = (int) $current_week['passed'];
		$week_estimated_additional = (int) round( $week_passed * self::ESTIMATED_ADDITIONAL_CATCH_PCT / 100 );

		return [
			'total'                      => (int) $counters['total'],
			'blocked'                    => (int) $counters['blocked'],
			'passed'                     => $passed,
			'estimated_additional'       => $estimated_additional,
			'estimated_additional_pct'   => self::ESTIMATED_ADDITIONAL_CATCH_PCT,
			'current_week'               => [
				'total'                => (int) $current_week['total'],
				'blocked'              => (int) $current_week['blocked'],
				'passed'               => (int) $current_week['passed'],
				'estimated_additional' => $week_estimated_additional,
			],
			'last_updated'               => (int) $counters['last_updated'],
		];
	}

	/**
	 * Reset all shadow counters.
	 */
	public static function reset(): void {
		update_option( self::OPTION_KEY, self::default_counters(), false );
	}

	/**
	 * Default counter structure.
	 *
	 * @return array
	 */
	private static function default_counters(): array {
		return [
			'total'        => 0,
			'blocked'      => 0,
			'passed'       => 0,
			'weekly'       => [],
			'last_updated' => 0,
		];
	}

	/**
	 * Keep only the most recent $keep weeks of weekly data.
	 *
	 * @param array $weekly
	 * @param int   $keep
	 *
	 * @return array
	 */
	private static function prune_weekly( array $weekly, int $keep ): array {
		if ( count( $weekly ) <= $keep ) {
			return $weekly;
		}

		// Keys are like "week_2026_11"; sorting alphabetically gives chronological order.
		ksort( $weekly );

		return array_slice( $weekly, -$keep, $keep, true );
	}
}
