<?php

namespace f12_cf7_captcha\core\settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves protection settings hierarchically: Global -> Integration -> Form.
 *
 * Each level can override specific settings. The `_enabled` flag controls
 * whether overrides at that level are active.
 */
class Settings_Resolver {

	private const OPTION_KEY = 'f12-cf7-captcha-form-overrides';

	/**
	 * Settings keys that can be overridden per integration/form.
	 */
	private const OVERRIDABLE_KEYS = [
		// Captcha
		'protection_captcha_enable',
		'protection_captcha_method',
		'protection_captcha_template',
		'protection_captcha_label',
		'protection_captcha_placeholder',
		'protection_captcha_reload_icon',
		'protection_captcha_reload_bg_color',
		'protection_captcha_reload_padding',
		'protection_captcha_reload_border_radius',
		'protection_captcha_reload_border_color',
		'protection_captcha_reload_icon_size',
		// Timer
		'protection_time_enable',
		'protection_time_ms',
		// Validators
		'protection_javascript_enable',
		'protection_browser_enable',
		'protection_multiple_submission_enable',
		// IP
		'protection_ip_enable',
		'protection_ip_max_retries',
		'protection_ip_max_retries_period',
		'protection_ip_period_between_submits',
		'protection_ip_block_time',
		// Content Rules
		'protection_rules_url_enable',
		'protection_rules_url_limit',
		'protection_rules_bbcode_enable',
		'protection_rules_blacklist_enable',
		'protection_rules_blacklist_greedy',
		// Whitelist
		'protection_whitelist_role_admin',
		'protection_whitelist_role_logged_in',
		// Logging
		'protection_log_enable',
	];

	/**
	 * Cached overrides loaded from the database (per-request).
	 *
	 * @var array|null
	 */
	private ?array $overrides_cache = null;

	/**
	 * Resolve settings for a given integration and optional form.
	 *
	 * @param array       $global_settings The global settings array (flat key => value).
	 * @param string      $integration_id  The integration identifier (e.g. 'cf7', 'wpforms').
	 * @param string|null $form_id         The form identifier (e.g. '42'), or null.
	 *
	 * @return array Merged settings array (flat key => value).
	 */
	public function resolve( array $global_settings, string $integration_id, ?string $form_id = null ): array {
		$overrides = $this->get_overrides();
		$resolved  = $global_settings;

		// Layer 1: Integration-level overrides
		if ( isset( $overrides['integration'][ $integration_id ] ) ) {
			$integration_overrides = $overrides['integration'][ $integration_id ];

			if ( ! empty( $integration_overrides['_enabled'] ) ) {
				$resolved = $this->merge_overrides( $resolved, $integration_overrides );
			}
		}

		// Layer 2: Form-level overrides
		if ( $form_id !== null ) {
			$form_key = $integration_id . ':' . $form_id;

			if ( isset( $overrides['form'][ $form_key ] ) ) {
				$form_overrides = $overrides['form'][ $form_key ];

				if ( ! empty( $form_overrides['_enabled'] ) ) {
					$resolved = $this->merge_overrides( $resolved, $form_overrides );
				}
			}
		}

		return $resolved;
	}

	/**
	 * Get the raw overrides array for a specific integration.
	 *
	 * @param string $integration_id
	 *
	 * @return array The integration overrides (may be empty).
	 */
	public function get_integration_overrides( string $integration_id ): array {
		$overrides = $this->get_overrides();

		return $overrides['integration'][ $integration_id ] ?? [];
	}

	/**
	 * Get the raw overrides array for a specific form.
	 *
	 * @param string $integration_id
	 * @param string $form_id
	 *
	 * @return array The form overrides (may be empty).
	 */
	public function get_form_overrides( string $integration_id, string $form_id ): array {
		$overrides = $this->get_overrides();
		$form_key  = $integration_id . ':' . $form_id;

		return $overrides['form'][ $form_key ] ?? [];
	}

	/**
	 * Save integration-level overrides.
	 *
	 * @param string $integration_id
	 * @param array  $settings Overrides to save (only overridable keys + _enabled).
	 */
	public function save_integration_overrides( string $integration_id, array $settings ): void {
		$overrides = $this->get_overrides();

		$filtered = $this->filter_overridable( $settings );

		if ( empty( $filtered ) && empty( $settings['_enabled'] ) ) {
			unset( $overrides['integration'][ $integration_id ] );
		} else {
			$filtered['_enabled']                            = ! empty( $settings['_enabled'] );
			$overrides['integration'][ $integration_id ] = $filtered;
		}

		$this->save_overrides( $overrides );
	}

	/**
	 * Save form-level overrides.
	 *
	 * @param string $integration_id
	 * @param string $form_id
	 * @param array  $settings Overrides to save (only overridable keys + _enabled).
	 */
	public function save_form_overrides( string $integration_id, string $form_id, array $settings ): void {
		$overrides = $this->get_overrides();
		$form_key  = $integration_id . ':' . $form_id;

		$filtered = $this->filter_overridable( $settings );

		if ( empty( $filtered ) && empty( $settings['_enabled'] ) ) {
			unset( $overrides['form'][ $form_key ] );
		} else {
			$filtered['_enabled']             = ! empty( $settings['_enabled'] );
			$overrides['form'][ $form_key ] = $filtered;
		}

		$this->save_overrides( $overrides );
	}

	/**
	 * Get the list of overridable setting keys.
	 *
	 * @return array
	 */
	public static function get_overridable_keys(): array {
		return self::OVERRIDABLE_KEYS;
	}

	/**
	 * Determine the source of each setting value for display in the UI.
	 *
	 * @param array       $global_settings
	 * @param string      $integration_id
	 * @param string|null $form_id
	 *
	 * @return array Associative array: key => 'global' | 'integration' | 'form'
	 */
	public function get_sources( array $global_settings, string $integration_id, ?string $form_id = null ): array {
		$overrides = $this->get_overrides();
		$sources   = [];

		foreach ( self::OVERRIDABLE_KEYS as $key ) {
			$sources[ $key ] = 'global';
		}

		// Check integration overrides
		if ( isset( $overrides['integration'][ $integration_id ] ) ) {
			$integration_overrides = $overrides['integration'][ $integration_id ];

			if ( ! empty( $integration_overrides['_enabled'] ) ) {
				foreach ( self::OVERRIDABLE_KEYS as $key ) {
					if ( array_key_exists( $key, $integration_overrides ) ) {
						$sources[ $key ] = 'integration';
					}
				}
			}
		}

		// Check form overrides
		if ( $form_id !== null ) {
			$form_key = $integration_id . ':' . $form_id;

			if ( isset( $overrides['form'][ $form_key ] ) ) {
				$form_overrides = $overrides['form'][ $form_key ];

				if ( ! empty( $form_overrides['_enabled'] ) ) {
					foreach ( self::OVERRIDABLE_KEYS as $key ) {
						if ( array_key_exists( $key, $form_overrides ) ) {
							$sources[ $key ] = 'form';
						}
					}
				}
			}
		}

		return $sources;
	}

	/**
	 * Get all overrides from the database (cached per request).
	 *
	 * @return array
	 */
	private function get_overrides(): array {
		if ( $this->overrides_cache === null ) {
			$raw = get_option( self::OPTION_KEY, [] );

			$this->overrides_cache = wp_parse_args( $raw, [
				'integration' => [],
				'form'        => [],
			] );
		}

		return $this->overrides_cache;
	}

	/**
	 * Persist overrides to the database and refresh cache.
	 *
	 * @param array $overrides
	 */
	private function save_overrides( array $overrides ): void {
		update_option( self::OPTION_KEY, $overrides, false );
		$this->overrides_cache = $overrides;
	}

	/**
	 * Merge override values into the base settings.
	 * Only overridable keys are merged; internal keys like `_enabled` are skipped.
	 *
	 * @param array $base
	 * @param array $overrides
	 *
	 * @return array
	 */
	private function merge_overrides( array $base, array $overrides ): array {
		foreach ( self::OVERRIDABLE_KEYS as $key ) {
			if ( array_key_exists( $key, $overrides ) ) {
				$base[ $key ] = $overrides[ $key ];
			}
		}

		return $base;
	}

	/**
	 * Filter an array to only contain overridable keys.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	private function filter_overridable( array $settings ): array {
		return array_intersect_key( $settings, array_flip( self::OVERRIDABLE_KEYS ) );
	}

	/**
	 * Invalidate the per-request cache (useful after saves within the same request).
	 */
	public function invalidate_cache(): void {
		$this->overrides_cache = null;
	}
}
