<?php
namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

/**
 * Führt Updates / Migrationen durch, wenn Plugin-Version sich ändert.
 */
function on_update() {
	$logger = Logger::getInstance();

	$currentVersion = get_option( 'f12-cf7-captcha_version' );

	if ( ! get_option( 'f12_cf7_captcha_installed_at' ) ) {
		update_option( 'f12_cf7_captcha_installed_at', time() );
	}

	// 🔹 Installation-UUID nachziehen (falls noch nicht gesetzt)
	if ( ! get_option('f12_cf7_captcha_installation_uuid') ) {
		update_option('f12_cf7_captcha_installation_uuid', wp_generate_uuid4(), true);
		$logger->info("Neue Installation UUID gesetzt", ['plugin' => 'f12-cf7-captcha']);
	}

	// 🔹 Upgrade auf 1.7 (alte Settings migrieren)
	if ( version_compare( $currentVersion, '1.7', '<' ) ) {
		$settings_old = get_option( 'f12_captcha_settings' );
		update_option( 'f12-cf7-captcha-settings', $settings_old );
		update_option( 'f12-cf7-captcha_version', '1.7' );

		$logger->info( "Upgrade durchgeführt", [
			'plugin' => 'f12-cf7-captcha',
			'from'   => $currentVersion ?: 'none',
			'to'     => '1.7'
		] );
	}

	// 🔹 Upgrade auf 2.0.0 (neues Settings-Mapping)
	if ( version_compare( $currentVersion, '2.0.0', '<' ) ) {
		$settings_old = get_option( 'f12-cf7-captcha-settings' );
		$settings_new = [];

		// Mapping definieren
		$mappings = [
			'global' => [
				'protection_method' => 'protection_captcha_method',
			],
			'javascript' => [
				'protect' => 'protection_javascript_enable',
			],
			'browser' => [
				'protect' => 'protection_browser_enable',
			],
			'gravity_forms' => [
				'protect_enable' => 'protection_gravityforms_enable',
			],
			'wpforms' => [
				'protect_enable' => 'protection_wpforms_enable',
			],
			'avada' => [
				'protect_avada' => 'protection_avada_enable',
			],
			'cf7' => [
				'protect_cf7_time_enable' => 'protection_cf7_enable',
			],
			'comments' => [
				'protect_comments' => 'protection_wordpress_comments_enable',
			],
			'elementor' => [
				'protect_elementor' => 'protection_elementor_enable',
			],
			'rules' => [
				'rule_url'                     => 'protection_rules_url_enable',
				'rule_url_limit'               => 'protection_rules_url_limit',
				'rule_blacklist'               => 'protection_rules_blacklist_enable',
				'rule_blacklist_greedy'        => 'protection_rules_blacklist_greedy',
				'rule_blacklist_value'         => 'protection_rules_blacklist_value',
				'rule_bbcode_url'              => 'protection_rules_bbcode_enable',
				'rule_error_message_url'       => 'protection_rules_error_message_url',
				'rule_error_message_bbcode'    => 'protection_rules_error_message_bbcode',
				'rule_error_message_blacklist' => 'protection_rules_error_message_blacklist',
			],
			'ip' => [
				'protect_ip'             => 'protection_ip_enable',
				'max_retry'              => 'protection_ip_max_retries',
				'max_retry_period'       => 'protection_ip_max_retries_period',
				'blockedtime'            => 'protection_ip_block_time',
				'period_between_submits' => 'protection_ip_period_between_submits',
			],
			'ultimatemember' => [
				'protect_enable' => 'protection_ultimatemember_enable',
			],
			'woocommerce' => [
				'protect_login' => 'protection_woocommerce_enable',
			],
			'wp_login_page' => [
				'protect_login' => 'protection_wordpress_enable',
			],
			'logs' => [
				'enable' => 'protection_log_enable',
			],
		];

		// Standardwerte für neue Struktur
		$settings_defaults = [
			'protection_time_enable'                   => 0,
			'protection_time_field_name'               => 'f12_timer',
			'protection_time_ms'                       => 500,
			'protection_captcha_enable'                => 1,
			'protection_captcha_method'                => 'honey',
			'protection_captcha_field_name'            => 'f12_captcha',
			'protection_multiple_submission_enable'    => 0,
			'protection_ip_enable'                     => 0,
			'protection_ip_max_retries'                => 3,
			'protection_ip_max_retries_period'         => 300,
			'protection_ip_period_between_submits'     => 60,
			'protection_ip_block_time'                 => 3600,
			'protection_log_enable'                    => 0,
			'protection_rules_url_enable'              => 0,
			'protection_rules_url_limit'               => 0,
			'protection_rules_blacklist_enable'        => 0,
			'protection_rules_blacklist_value'         => '',
			'protection_rules_blacklist_greedy'        => 0,
			'protection_rules_bbcode_enable'           => 0,
			'protection_rules_error_message_url'       => __( 'The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' ),
			'protection_rules_error_message_bbcode'    => __( 'BBCode is not allowed.', 'captcha-for-contact-form-7' ),
			'protection_rules_error_message_blacklist' => __( 'The word %s is blacklisted.', 'captcha-for-contact-form-7' ),
			'protection_browser_enable'                => 1,
			'protection_javascript_enable'             => 1,
			'protection_support_enable'                => 1,
		];

		// Mapping anwenden
		foreach ( $mappings as $container => $map ) {
			foreach ( $map as $old_key => $new_key ) {
				if ( isset( $settings_old[ $container ][ $old_key ] ) ) {
					$settings_defaults[ $new_key ] = $settings_old[ $container ][ $old_key ];
				}
			}
		}

		$settings = [ 'global' => $settings_defaults ];

		update_option( 'f12-cf7-captcha-settings', $settings );
		update_option( 'f12-cf7-captcha-settings-backup', $settings_old );
		update_option( 'f12-cf7-captcha_version', '2.0.0' );

		$logger->info( "Upgrade durchgeführt", [
			'plugin'   => 'f12-cf7-captcha',
			'from'     => $currentVersion,
			'to'       => '2.0.0',
			'mappings' => array_keys( $settings_defaults )
		] );
	} else {
		$logger->debug( "Kein Upgrade erforderlich", [
			'plugin'  => 'f12-cf7-captcha',
			'version' => $currentVersion
		] );
	}
}
