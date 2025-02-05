<?php

namespace f12_cf7_captcha;

use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Compatibility;
use f12_cf7_captcha\core\log\Log_Cleaner;
use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\captcha\Captcha;
use f12_cf7_captcha\core\protection\ip\IPBan;
use f12_cf7_captcha\core\protection\ip\IPLog;
use f12_cf7_captcha\core\protection\ip\Salt;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\Support;
use f12_cf7_captcha\core\TemplateController;
use f12_cf7_captcha\core\timer\CaptchaTimer;
use f12_cf7_captcha\core\timer\Timer_Controller;
use f12_cf7_captcha\core\UserData;
use f12_cf7_captcha\ui\UI_Manager;

/**
 * Dependencies
 */
require_once( 'core/BaseController.class.php' );
require_once( 'core/BaseModul.class.php' );
require_once( 'core/BaseProtection.class.php' );
require_once( 'core/Validator.class.php' );

require_once( 'core/TemplateController.class.php' );

require_once( 'core/UserData.class.php' );

# Logs
require_once( 'core/log/Log_Cleaner.class.php' );
require_once( 'core/log/Log_WordPress.class.php' );

# Timer
require_once( 'core/timer/Timer_Controller.class.php' );

# Protections
require_once( 'core/protection/Protection.class.php' );

require_once( 'core/Compatibility.class.php' );
require_once( 'core/Messages.class.php' );
require_once( 'ui/UI_Manager.php' );
require_once( 'core/Support.class.php' );

/**
 * Plugin Name: Captcha for WordPress
 * Plugin URI: https://www.forge12.com/product/wordpress-captcha/
 * Description: This plugin allows you to add captcha protection to forms, wordpress and woocommerce.
 * Version: 2.1.2
 * Author: Forge12 Interactive GmbH
 * Author URI: https://www.forge12.com
 * Text Domain: captcha-for-contact-form-7
 * Domain Path: /languages
 */
define( 'FORGE12_CAPTCHA_VERSION', '2.1.2' );
define( 'FORGE12_CAPTCHA_SLUG', 'f12-cf7-captcha' );
define( 'FORGE12_CAPTCHA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Class CF7Captcha
 * Controller for the Custom Links.
 *
 * @package forge12\contactform7
 */
class CF7Captcha {
	/**
	 * @var CF7Captcha|Null
	 */
	private static $_instance = null;

	/**
	 * @var BaseModul[]
	 */
	private array $_moduls = [];

	/**
	 * Get the instance of the class
	 *
	 * @return CF7Captcha
	 * @deprecated
	 */
	public static function getInstance() {
		return self::get_instance();
	}

	/**
	 * Get the singleton instance of CF7Captcha.
	 *
	 * @return CF7Captcha The singleton instance of CF7Captcha.
	 */
	public static function get_instance(): CF7Captcha {
		if ( self::$_instance == null ) {
			self::$_instance = new CF7Captcha();
		}

		return self::$_instance;
	}

	/**
	 * Retrieves the settings for a specific feature or the entire plugin.
	 *
	 * @param string $single    The name of the specific setting to retrieve. Optional.
	 * @param string $container The name of the container for the specific setting. Optional.
	 *
	 * @return mixed The retrieved settings as an array or a specific setting if $single and $container are
	 *               provided. If the settings or the specific setting is not found, an empty array is returned.
	 */
	public function get_settings( $single = '', $container = null ) {
		$default = array();

		$default = apply_filters( 'f12-cf7-captcha_settings', $default );

		$settings = get_option( 'f12-cf7-captcha-settings' );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		/*
		 * Load Settings for Blacklist
		 */
		$settings['global']['protection_rules_blacklist_value'] = get_option( 'disallowed_keys', '' );

		foreach ( $default as $key => $data ) {
			if ( isset( $settings[ $key ] ) ) {
				$default[ $key ] = array_merge( $data, $settings[ $key ] );
			}
		}

		$settings = $default;

		if ( empty( $single ) && $container == null ) {
			return $settings;
		}

		if ( empty( $single ) && $container != null ) {
			if ( isset( $settings[ $container ] ) ) {
				return $settings[ $container ];
			}
		}

		if ( ! empty( $single ) && $container != null ) {
			if ( isset( $settings[ $container ][ $single ] ) ) {
				return $settings[ $container ][ $single ];
			}
		}

		return null;
	}

	/**
	 * Sets the value of a single setting or a nested setting within the WordPress options table.
	 *
	 * @param string      $single    The name of the setting to set.
	 * @param string      $value     The value to set for the specified setting.
	 * @param string|null $container Optional. The name of the container in which the setting resides.
	 *                               If not provided, the setting will be added at the root level.
	 *
	 * @return void
	 */
	public function set_settings( string $single, string $value, ?string $container = null ): void {
		$settings = $this->get_settings();

		if ( null == $container ) {
			$settings[ $single ] = $value;
		} else {
			$settings[ $container ][ $single ] = $value;
		}

		update_option( 'f12-cf7-captcha-settings', $settings );
	}

	/**
	 * Sets the settings for a specific container.
	 *
	 * The method sets the settings for a specific container by updating the global settings array
	 * with the provided values. The updated settings array is then stored in the WordPress options database.
	 *
	 * @param string $container The name of the container for which the settings are being set.
	 * @param array  $values    The new values to be set for the container.
	 *
	 * @return void
	 * @see get_settings()
	 *
	 */
	public function set_settings_by_container( string $container, array $values ): void {
		$settings = $this->get_settings();

		$settings[ $container ] = $values;

		update_option( 'f12-cf7-captcha-settings', $settings );
	}

	/**
	 * Initializes the modules for the software.
	 *
	 * This method initializes the modules required for the software to function properly.
	 *
	 * @return void
	 */
	private function init_moduls(): void {
		$this->_moduls = [
			'template'      => new TemplateController( $this ),
			'log-cleaner'   => new Log_Cleaner( $this, Log_WordPress::get_instance() ),
			'compatibility' => new Compatibility( $this, Log_WordPress::get_instance() ),
			'support'       => new Support( $this ),
			'user-data'     => new UserData( $this ),
			'timer'         => new Timer_Controller( $this ),
			'protection'    => new Protection( $this, Log_WordPress::get_instance() )
		];
	}

	/**
	 * Retrieves the specified module based on its name.
	 *
	 * @param string $name The name of the module to retrieve.
	 *
	 * @return BaseModul The specified module.
	 * @throws \Exception If the specified module does not exist.
	 */
	public function get_modul( string $name ): BaseModul {
		if ( ! isset( $this->_moduls[ $name ] ) ) {
			throw new \Exception( sprintf( 'Modul %s does not exist.', $name ) );
		}

		return $this->_moduls[ $name ];
	}

	/**
	 * Private constructor for initializing the class.
	 *
	 * This constructor performs several initialization tasks, including:
	 * - Removing a filter that will not work with the filter list.
	 * - Registering an instance of the UI_Manager class.
	 * - Creating an instance of the Compatibility class.
	 * - Loading the text domain for translations.
	 * - Loading the admin and frontend assets.
	 * - Setting up support.
	 * - Adding cronjobs.
	 * - Loading the plugin text domain.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->init_moduls();

		// Remove Filter which will not work with our filter list
		add_action( 'init', function () {
			remove_filter( 'wpcf7_spam', 'wpcf7_disallowed_list', 10 );
		} );
		load_plugin_textdomain( 'captcha-for-contact-form-7', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Filter for Blacklist
		add_filter( 'f12-cf7-captcha_settings_loaded', [ $this, 'wp_load_blacklist' ] );

		$UI_Manager = UI_Manager::register_instance( 'f12-cf7-captcha',
			plugin_dir_url( __FILE__ ),
			plugin_dir_path( __FILE__ ),
			__NAMESPACE__,
			'Captcha',
			'manage_options',
			plugins_url( 'ui/assets/icon-captcha-20x20.png', __FILE__ )
		);

		// Load assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_frontend_assets' ) );


		// Add Cronjobs
		$this->add_cron_jobs();

		// Check Upgrade Notice
		add_action( 'in_plugin_update_message-f12-cf7-captcha/f12-cf7-captcha.php', [
			$this,
			'wp_show_update_message'
		], 10, 2 );

		// Hook to the Settings page in the Plugin view
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'wp_plugin_action_links' ] );

		// Skip all protection if whitelisted
		add_filter( 'f12-cf7-captcha-skip-validation', [ $this, 'skip_whitelisted_emails_and_ips' ], 10, 2 );
	}

	/**
	 * Determines whether to skip certain actions for whitelisted emails and IPs.
	 *
	 * This method checks if the current request should be skipped based on whitelisted emails
	 * and IP addresses defined in the plugin settings. If the current user's IP address or any
	 * provided argument matches an entry in the whitelist, the action will be skipped.
	 *
	 * @param bool  $skip Initial skip flag that indicates whether to skip protection.
	 * @param array $args An array of arguments to be checked against the whitelisted emails.
	 *
	 * @return bool Returns true if the action should be skipped, otherwise false.
	 */
	function skip_whitelisted_emails_and_ips( $skip, $args = [] ): bool {
		if ( $skip ) {
			return $skip;
		}

		// Get the whitelist settings from the plugin options
		$settings               = $this->get_settings();
		$whitelisted_emails     = isset( $settings['global']['protection_whitelist_emails'] ) ? explode( "\n", trim( $settings['global']['protection_whitelist_emails'] ) ) : [];
		$whitelisted_ips        = isset( $settings['global']['protection_whitelist_ips'] ) ? explode( "\n", $settings['global']['protection_whitelist_ips'] ) : [];
		$whitelisted_admin_role = isset( $settings['global']['protection_whitelist_role_admin'] ) ? (int)$settings['global']['protection_whitelist_role_admin'] : 0;
		$whitelisted_logged_in = isset( $settings['global']['protection_whitelist_role_logged_in'] ) ? (int)$settings['global']['protection_whitelist_role_logged_in'] : 0;

		if(is_user_logged_in() && $whitelisted_logged_in){
			return true;
		}

		if(is_user_logged_in() && $whitelisted_admin_role){

			// Get the current user
			$current_user = wp_get_current_user();

			// Check if the user has the 'administrator' role
			if (in_array('administrator', $current_user->roles)) {
				return true;
			} else {
				return false;
			}
		}

		// Get the current user's IP address
		$user_ip = $_SERVER['REMOTE_ADDR'];

		// Trim and clean whitelist values for comparison
		$whitelisted_emails = array_map( 'trim', $whitelisted_emails );
		$whitelisted_ips    = array_map( 'trim', $whitelisted_ips );

		$whitelisted_emails = array_filter( $whitelisted_emails );

		// Check if the user's IP is in the whitelist
		if ( in_array( $user_ip, $whitelisted_ips ) ) {
			return true;
		}

		// Iterate through each $_POST variable to check if any match a whitelisted email
		foreach ( $args as $value ) {
			if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
				return true;
			}
		}

		return $skip;
	}

	/**
	 * Checks if the given email(s) are in the whitelist.
	 *
	 * This method verifies whether the provided argument, which can be either a single email
	 * address or an array of email addresses, exists in the specified whitelist of emails.
	 *
	 * @param mixed $arg                A single email address as a string or an array of email addresses.
	 * @param array $whitelisted_emails An optional array of whitelisted email addresses.
	 *
	 * @return bool Returns true if the provided email(s) are found in the whitelist, otherwise false.
	 */
	private function is_whitelisted_email( $arg, $whitelisted_emails = [] ): bool {
		if ( empty( $whitelisted_emails ) ) {
			return false;
		}

		if ( is_array( $arg ) ) {
			foreach ( $arg as $value ) {
				if ( $this->is_whitelisted_email( $value, $whitelisted_emails ) ) {
					return true;
				}
			}

			return false; // Wenn keine der E-Mail-Adressen in der Whitelist ist
		}

		// Sanitize and trim the current POST value
		$value = sanitize_text_field( trim( $arg ) );

		if ( empty( $value ) ) {
			return false;
		}

		// If any $_POST value matches a whitelisted email, skip protection
		return in_array( $value, $whitelisted_emails );
	}

	/**
	 * Loads the blacklist settings into the provided settings array.
	 *
	 * This method loads the blacklist settings from the WordPress options table and
	 * assigns them to the corresponding key in the provided settings array. If the
	 * setting is not already set, it will be assigned an empty string value.
	 *
	 * @param array $settings The array of settings to load the blacklist into.
	 *
	 * @return array The updated settings array with the blacklist loaded.
	 */
	public function wp_load_blacklist( array $settings ): array {
		if ( isset( $settings['global']['protection_rules_blacklist_value'] ) ) {
			$settings['global']['protection_rules_blacklist_value'] = get_option( 'disallowed_keys', '' );
		}

		return $settings;
	}

	/**
	 * Modifies the action links displayed for the plugin on the WordPress plugins page.
	 *
	 * This function adds a "Settings" link to the action links array for the plugin.
	 * The "Settings" link points to the plugin settings page in the WordPress admin area.
	 *
	 * @param array $links An associative array of the existing action links for the plugin.
	 *
	 * @return array The modified action links array.
	 */
	function wp_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=f12-cf7-captcha' ) . '" aria-label="' . esc_attr__( 'View Settings', 'captcha-for-contact-form-7' ) . '">' . esc_html__( 'Settings', 'captcha-for-contact-form-7' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Displays an update message.
	 *
	 * This method checks if an upgrade notice is present in the given data. If an
	 * upgrade notice is present, it displays the message in a div element with the
	 * class "update-message".
	 *
	 * @param array  $data     The data containing the upgrade notice.
	 * @param object $response The response object.
	 *
	 * @return void
	 */
	public function wp_show_update_message( $data, $response ) {
		if ( isset( $data['upgrade_notice'] ) ) {
			printf(
				'<div class="update-message">%s</div>',
				wpautop( $data['upgrade_notice'] )
			);
		}
	}

	/**
	 * Adds cron jobs to the WordPress installation.
	 *
	 * This method adds several cron jobs to the WordPress installation. The cron jobs
	 * are scheduled to run at specific intervals. If the cron jobs are not already
	 * scheduled, they will be added.
	 *
	 * @return void
	 * @global object $wp The main WordPress object containing request information.
	 */
	private function add_cron_jobs() {
		// Add cron
		if ( ! wp_next_scheduled( 'weeklyIPClear' ) ) {
			wp_schedule_event( time(), 'weekly', 'weeklyIPClear' );
		}

		// Add cron
		if ( ! wp_next_scheduled( 'dailyCaptchaClear' ) ) {
			wp_schedule_event( time(), 'daily', 'dailyCaptchaClear' );
		}

		// Add cron
		if ( ! wp_next_scheduled( 'dailyCaptchaTimerClear' ) ) {
			wp_schedule_event( time(), 'daily', 'dailyCaptchaTimerClear' );
		}

		// Add cron
		if ( ! wp_next_scheduled( 'weeklyIPClear' ) ) {
			wp_schedule_event( time(), 'weekly', 'weeklyIPClear' );
		}

		// Add cron
		if ( ! wp_next_scheduled( 'weeklyIPClear' ) ) {
			wp_schedule_event( time(), 'weekly', 'weeklyIPClear' );
		}
	}

	public function load_frontend_assets() {
		$atts = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		wp_enqueue_script( 'f12-cf7-captcha-reload', plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha-cf7.js', array( 'jquery' ), null, true );
		wp_localize_script( 'f12-cf7-captcha-reload', 'f12_cf7_captcha', $atts );

		wp_enqueue_style( 'f12-cf7-captcha-style', plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha.css' );
	}

	public function load_admin_assets() {
		wp_enqueue_script( 'f12-cf7-captcha-toggle', plugins_url( 'core/assets/toggle.js', __FILE__ ), array( 'jquery' ), '1.0' );
	}

	/**
	 * Check if is a plugin activated.
	 *
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function is_plugin_activated( $plugin ) {
		if ( empty( $this->plugins ) ) {
			$this->plugins = (array) get_option( 'active_plugins', array() );
		}

		if ( strpos( $plugin, '.php' ) === false ) {
			$plugin = trailingslashit( $plugin ) . $plugin . '.php';
		}

		return in_array( $plugin, $this->plugins ) || array_key_exists( $plugin, $this->plugins );
	}

}

/**
 * Create all required tables to store the captcha codes within the database
 */
function on_activation() {
	/**
	 * User Data
	 */
	$Captcha = new Captcha( '' );
	$Captcha->create_table();

	$Salt = new Salt();
	$Salt->create_table();

	$Captcha_Timer = new CaptchaTimer();
	$Captcha_Timer->create_table();;

	$IP_Log = new IPLog();
	$IP_Log->create_table();

	$IP_Ban = new IPBan();
	$IP_Ban->create_table();
}

register_activation_hook( __FILE__, 'f12_cf7_captcha\on_activation' );

/**
 * On Update
 */
function on_update() {
	// Only run if the version installed not exist or the version is < 1.7
	// this will convert all ui Options to the new version.
	if ( version_compare( get_option( 'f12-cf7-captcha_version' ), '1.7' ) < 0 ) {
		$settings_old = get_option( 'f12_captcha_settings' );
		update_option( 'f12-cf7-captcha-settings', $settings_old );

		update_option( 'f12-cf7-captcha_version', '1.7' );
	}

	// this will convert all ui options to the version 2.0
	if ( version_compare( get_option( 'f12-cf7-captcha_version' ), '2.0.0' ) < 0 ) {
		$settings_old = get_option( 'f12-cf7-captcha-settings' );

		$settings = [];

		$settings['global'] = array(
			'protection_method' => 'protection_captcha_method'
		);

		$settings['javascript'] = array(
			'protect' => 'protection_javascript_enable'
		);

		$settings['browser'] = array(
			'protect' => 'protection_browser_enable'
		);

		$settings['gravity_forms'] = array(
			'protect_enable' => 'protection_gravityforms_enable',
		);

		$settings['wpforms'] = array(
			'protect_enable' => 'protection_wpforms_enable',
		);

		$settings['avada'] = array(
			'protect_avada' => 'protection_avada_enable',
		);

		$settings['cf7'] = array(
			'protect_cf7_time_enable' => 'protection_cf7_enable',
		);

		$settings['comments'] = array(
			'protect_comments' => 'protection_wordpress_comments_enable',
		);

		$settings['elementor'] = array(
			'protect_elementor' => 'protection_elementor_enable',
		);

		$settings['rules'] = array(
			'rule_url'                     => 'protection_rules_url_enable',
			'rule_url_limit'               => 'protection_rules_url_limit',
			'rule_blacklist'               => 'protection_rules_blacklist_enable',
			'rule_blacklist_greedy'        => 'protection_rules_blacklist_greedy',
			'rule_blacklist_value'         => 'protection_rules_blacklist_value',
			'rule_bbcode_url'              => 'protection_rules_bbcode_enable',
			'rule_error_message_url'       => 'protection_rules_error_message_url',
			'rule_error_message_bbcode'    => 'protection_rules_error_message_bbcode',
			'rule_error_message_blacklist' => 'protection_rules_error_message_blacklist',
		);

		$settings['ip'] = array(
			'protect_ip'             => 'protection_ip_enable',
			// enabled or not
			'max_retry'              => 'protection_ip_max_retries',
			// max retries
			'max_retry_period'       => 'protection_ip_max_retries_period',
			// time in seconds,
			'blockedtime'            => 'protection_ip_block_time',
			// time in seconds - how long will the user be blocked if he fails to often
			'period_between_submits' => 'protection_ip_period_between_submits',
			// time between forms submits
		);

		$settings['ultimatemember'] = array(
			'protect_enable' => 'protection_ultimatemember_enable',
		);

		$settings['woocommerce'] = array(
			'protect_login' => 'protection_woocommerce_enable',
		);

		$settings['wp_login_page'] = array(
			'protect_login' => 'protection_wordpress_enable',
		);

		$settings['logs'] = array(
			'enable' => 'protection_log_enable'
		);

		$settings_new = [
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
			'protection_browser_enable'                => 0,
			'protection_javascript_enable'             => 0,
			'protection_support_enable'                => 1,
		];

		foreach ( $settings as $container => $item ) {
			foreach ( $item as $old_mapping => $new_mapping ) {
				if ( isset( $settings_old[ $container ][ $old_mapping ] ) ) {
					$settings_new[ $new_mapping ] = $settings_old[ $container ][ $old_mapping ];
				}
			}
		}

		$settings = [ 'global' => $settings_new ];

		update_option( 'f12-cf7-captcha-settings', $settings );
		update_option( 'f12-cf7-captcha-settings-backup', $settings_old );

		update_option( 'f12-cf7-captcha_version', '2.0.0' );
	}
}

#var_dump(get_option('f12-cf7-captcha-settings'));exit;
on_update();

/**
 * Init the contact form 7 captcha
 */
CF7Captcha::get_instance();

do_action( 'f12_cf7_captcha_init' );