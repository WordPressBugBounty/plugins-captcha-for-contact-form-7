<?php
/**
 * Plugin Name: SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce)
 * Plugin URI: https://www.forge12.com/product/wordpress-captcha/
 * Description: SilentShield is an all-in-one spam protection plugin. Protects WordPress login, registration, comments, and popular form plugins (CF7, WPForms, Elementor, WooCommerce) with captcha, honeypot, blacklist, IP blocking, and whitelisting for logged-in users.
 * Version: 2.3.3
 * Requires PHP: 7.4
 * Author: Forge12 Interactive GmbH
 * Author URI: https://www.forge12.com
 * Text Domain: captcha-for-contact-form-7
 * Domain Path: /languages
 */
namespace f12_cf7_captcha;


define( 'FORGE12_CAPTCHA_VERSION', '2.3.3' );
define( 'FORGE12_CAPTCHA_SLUG', 'f12-cf7-captcha' );
define( 'FORGE12_CAPTCHA_BASENAME', plugin_basename( __FILE__ ) );


use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Compatibility;
use f12_cf7_captcha\core\log\Log_Cleaner;
use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\rest\RestController;
use f12_cf7_captcha\core\TemplateController;
use f12_cf7_captcha\core\timer\Timer_Controller;
use f12_cf7_captcha\core\UserData;
use f12_cf7_captcha\ui\UI_Manager;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

/**
 * Dependencies
 */
require_once( 'autoload.php' );
require_once( 'logger/logger.php' );
require_once( 'core/helpers/uuid.php' );
require_once( 'core/bootstrap.php' );

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
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * @var BaseModul[]
	 */
	private array $_modules = [];

	/**
	 * @var array
	 */
	private array $plugins = [];

	/**
	 * Request-level cache for settings to avoid repeated get_option() calls.
	 */
	private ?array $_settings_cache = null;

	/**
	 * Get the instance of the class
	 *
	 * @return CF7Captcha
	 * @deprecated Use get_instance() instead.
	 */
	public static function getInstance() {
		_deprecated_function( __METHOD__, '2.3.0', 'CF7Captcha::get_instance()' );
		return self::get_instance();
	}

	/**
	 * Get the singleton instance of CF7Captcha.
	 *
	 * @return CF7Captcha The singleton instance of CF7Captcha.
	 */
	public static function get_instance(): CF7Captcha {
		if ( self::$_instance === null ) {
			self::$_instance = new CF7Captcha();
		}

		return self::$_instance;
	}

	/**
	 * Get the Logger for System Logs
	 *
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->logger;
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
		// Use request-level cache
		if ( $this->_settings_cache === null ) {
			$default = apply_filters( 'f12-cf7-captcha_settings', array() );

			$settings = get_option( 'f12-cf7-captcha-settings' );

			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			// Load Settings for Blacklist
			$settings['global']['protection_rules_blacklist_value'] = get_option( 'disallowed_keys', '' );

			foreach ( $default as $key => $data ) {
				if ( isset( $settings[ $key ] ) ) {
					$default[ $key ] = array_merge( $data, $settings[ $key ] );
				}
			}

			$this->_settings_cache = $default;
		}

		$settings = $this->_settings_cache;

		// Return complete setting
		if ( empty( $single ) && $container == null ) {
			return $settings;
		}

		// Return container
		if ( empty( $single ) && $container != null ) {
			return $settings[ $container ] ?? null;
		}

		// Return single setting
		if ( ! empty( $single ) && $container != null ) {
			return $settings[ $container ][ $single ] ?? null;
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

		if ( null === $container ) {
			$settings[ $single ] = $value;
		} else {
			$settings[ $container ][ $single ] = $value;
		}

		update_option( 'f12-cf7-captcha-settings', $settings );

		// Invalidate request-level cache
		$this->_settings_cache = null;
	}


	/**
	 * Initializes the modules for the software.
	 *
	 * This method initializes the modules required for the software to function properly.
	 *
	 * @return void
	 */
	private function init_modules(): void {
		$this->_modules = [];

		$modules = [
			'template'      => TemplateController::class,
			'log-cleaner'   => Log_Cleaner::class,
			'compatibility' => Compatibility::class,
			'user-data'     => UserData::class,
			'timer'         => Timer_Controller::class,
			'protection'    => Protection::class,
			'rest'          => RestController::class,
		];

		foreach ( $modules as $key => $class ) {
			try {
				// Some require Logger as dependency
				if ( in_array( $key, [ 'log-cleaner', 'compatibility', 'protection' ], true ) ) {
					$this->_modules[ $key ] = new $class( $this, Log_WordPress::get_instance() );
				} else {
					$this->_modules[ $key ] = new $class( $this );
				}

				$this->logger->info( "Module initialized", [
					'plugin' => 'f12-cf7-captcha',
					'module' => $key,
					'class'  => $class
				] );

			} catch ( \Throwable $e ) {
				$this->logger->error( "Failed to initialize module", [
					'plugin' => 'f12-cf7-captcha',
					'module' => $key,
					'class'  => $class,
					'error'  => $e->getMessage()
				] );
			}
		}
	}


	/**
	 * Retrieves the specified module based on its name.
	 *
	 * @param string $name The name of the module to retrieve.
	 *
	 * @return BaseModul The specified module.
	 * @throws \Exception If the specified module does not exist.
	 */
	public function get_module( string $name ): BaseModul {
		if ( ! isset( $this->_modules[ $name ] ) ) {
			$this->logger->error( "Requested module does not exist", [
				'plugin'         => 'f12-cf7-captcha',
				'module'         => $name,
				'loaded_modules' => array_keys( $this->_modules )
			] );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new \Exception( sprintf( 'Modul %s does not exist.', $name ) );
		}

		$this->logger->debug( "Module retrieved successfully", [
			'plugin' => 'f12-cf7-captcha',
			'module' => $name,
			'class'  => get_class( $this->_modules[ $name ] )
		] );

		return $this->_modules[ $name ];
	}

	/**
	 * @deprecated Use get_module() instead.
	 */
	public function get_modul( string $name ): BaseModul {
		_deprecated_function( __METHOD__, '2.3.0', 'CF7Captcha::get_module()' );
		return $this->get_module( $name );
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
		// Forge12 Logger initialisieren
		$this->logger = Logger::getInstance();

		$this->logger->info( "Plugin started", [
			'plugin'  => 'f12-cf7-captcha',
			'version' => FORGE12_CAPTCHA_VERSION
		] );

		$this->init_modules();
		$this->logger->debug( "Modules initialized", [ 'plugin' => 'f12-cf7-captcha' ] );

		// Remove Filter which will not work with our filter list
		add_action( 'init', function () {
			remove_filter( 'wpcf7_spam', 'wpcf7_disallowed_list', 10 );
			$this->logger->debug( "Spam filter removed", [ 'plugin' => 'f12-cf7-captcha', 'filter' => 'wpcf7_spam' ] );
		} );

		add_action( 'init', function () {
			load_plugin_textdomain( 'captcha-for-contact-form-7', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			$this->logger->debug( "Textdomain loaded", [ 'plugin' => 'f12-cf7-captcha' ] );
		} );

		// Filter for Blacklist
		add_filter( 'f12-cf7-captcha_settings_loaded', [ $this, 'wp_load_blacklist' ] );
		$this->logger->debug( "Blacklist filter added", [ 'plugin' => 'f12-cf7-captcha' ] );

		$UI_Manager = UI_Manager::register_instance( $this->logger, 'f12-cf7-captcha',
			plugin_dir_url( __FILE__ ),
			plugin_dir_path( __FILE__ ),
			__NAMESPACE__,
			'SilentShield',
			'manage_options',
			plugins_url( 'ui/assets/icon-captcha-20x20.png', __FILE__ )
		);
		$this->logger->debug( "UI Manager registered", [ 'plugin' => 'f12-cf7-captcha' ] );

		// Load assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_frontend_assets' ) );
		$this->logger->debug( "Asset loader hooks registered", [ 'plugin' => 'f12-cf7-captcha' ] );

		// Check Upgrade Notice
		add_action( 'in_plugin_update_message-f12-cf7-captcha/f12-cf7-captcha.php', [
			$this,
			'wp_show_update_message'
		], 10, 2 );

		// Hook to the Settings page in the Plugin view
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'wp_plugin_action_links' ] );
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
			$blacklist                                              = get_option( 'disallowed_keys', '' );
			$settings['global']['protection_rules_blacklist_value'] = $blacklist;

			if ( empty( trim( $blacklist ) ) ) {
				$this->logger->debug( "Blacklist loaded but empty", [
					'plugin' => 'f12-cf7-captcha'
				] );
			} else {
				$this->logger->info( "Blacklist loaded successfully", [
					'plugin'   => 'f12-cf7-captcha',
					'keywords' => substr( $blacklist, 0, 50 ) . ( strlen( $blacklist ) > 50 ? '...' : '' )
				] );
			}
		} else {
			$this->logger->debug( "Blacklist option not found in settings", [
				'plugin' => 'f12-cf7-captcha'
			] );
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
	public function wp_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=f12-cf7-captcha' ) . '" aria-label="' . esc_attr__( 'View Settings', 'captcha-for-contact-form-7' ) . '">' . esc_html__( 'Settings', 'captcha-for-contact-form-7' ) . '</a>',
		);

		$merged = array_merge( $action_links, $links );

		$this->logger->debug( "Plugin action links added", [
			'plugin' => 'f12-cf7-captcha',
			'links'  => array_keys( $action_links )
		] );

		return $merged;
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
			$notice = $data['upgrade_notice'];

			printf(
				'<div class="update-message">%s</div>',
				wp_kses_post( wpautop( $notice ) )
			);

			// Log upgrade notice (shortened to avoid log flooding)
			$this->logger->info( "Upgrade notice displayed", [
				'plugin' => 'f12-cf7-captcha',
				'notice' => substr( strip_tags( $notice ), 0, 100 ) . ( strlen( $notice ) > 100 ? '...' : '' )
			] );
		} else {
			$this->logger->debug( "No upgrade notice available", [
				'plugin' => 'f12-cf7-captcha'
			] );
		}
	}

	/**
	 * Determines whether frontend assets should be loaded on the current page.
	 *
	 * Assets are loaded when:
	 * 1. Force-load filter returns true
	 * 2. CF7 shortcode is present in post content
	 * 3. Other supported form plugin shortcodes are present
	 * 4. Current page is login or registration
	 * 5. WooCommerce checkout/account pages
	 * 6. Page Builders (Elementor, Avada/Fusion Builder) are active
	 *
	 * @return bool True if assets should be loaded, false otherwise.
	 */
	private function should_load_assets(): bool {
		// 1a. Filter for Force-Load (allows themes/plugins to force asset loading)
		if ( apply_filters( 'f12_captcha_force_load_assets', false ) ) {
			$this->logger->debug( "Assets force-loaded via filter", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}

		// 1b. Global asset loading setting (admin toggle)
		$global_loading = $this->get_settings( 'protection_global_asset_loading', 'global' );
		if ( (int) $global_loading === 1 ) {
			$this->logger->debug( "Assets force-loaded via global setting", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}

		// 1c. Custom URL path exceptions
		$custom_urls = $this->get_settings( 'protection_asset_loading_urls', 'global' );
		if ( ! empty( $custom_urls ) ) {
			$request_uri  = isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$request_path = strtok( $request_uri, '?' );
			$url_patterns = array_filter( array_map( 'trim', explode( "\n", $custom_urls ) ) );
			foreach ( $url_patterns as $pattern ) {
				if ( ! empty( $pattern ) && strpos( $request_path, $pattern ) !== false ) {
					$this->logger->debug( "Assets loaded - custom URL matched", [
						'plugin'  => 'f12-cf7-captcha',
						'pattern' => $pattern,
					] );
					return true;
				}
			}
		}

		// 2. Login/Register pages always need assets
		$pagenow = $GLOBALS['pagenow'] ?? '';
		if ( in_array( $pagenow, [ 'wp-login.php', 'wp-register.php' ], true ) ) {
			$this->logger->debug( "Assets loaded for login/register page", [ 'plugin' => 'f12-cf7-captcha', 'page' => $pagenow ] );
			return true;
		}

		// 3. Check for form shortcodes in post content
		global $post;
		if ( $post && ! empty( $post->post_content ) ) {
			// CF7 shortcode
			if ( has_shortcode( $post->post_content, 'contact-form-7' ) ) {
				$this->logger->debug( "Assets loaded - CF7 shortcode detected", [ 'plugin' => 'f12-cf7-captcha' ] );
				return true;
			}

			// Other supported form plugins
			$form_shortcodes = [
				'wpforms',
				'gravityform',
				'gravityforms',
				'formidable',
				'ninja_form',
				'ninja_forms',
				'fluentform',
				'fusion_form', // Avada Fusion Builder forms
			];

			foreach ( $form_shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					$this->logger->debug( "Assets loaded - form shortcode detected", [ 'plugin' => 'f12-cf7-captcha', 'shortcode' => $shortcode ] );
					return true;
				}
			}
		}

		// 4. WooCommerce pages (checkout, my-account)
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$this->logger->debug( "Assets loaded for WooCommerce checkout", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$this->logger->debug( "Assets loaded for WooCommerce account page", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}

		// 5. Check for Elementor (forms can be in Elementor widgets)
		if ( $post ) {
			// Check if Elementor is used on this page
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( ! empty( $elementor_data ) ) {
				// Check for form widget in Elementor data
				if ( is_string( $elementor_data ) && (
					strpos( $elementor_data, '"widgetType":"form"' ) !== false ||
					strpos( $elementor_data, '"widgetType":"login"' ) !== false
				) ) {
					$this->logger->debug( "Assets loaded - Elementor form widget detected", [ 'plugin' => 'f12-cf7-captcha' ] );
					return true;
				}
			}

			// Also check post content for Elementor markers
			if ( ! empty( $post->post_content ) && (
				strpos( $post->post_content, 'elementor-widget-form' ) !== false ||
				strpos( $post->post_content, 'elementor-form' ) !== false
			) ) {
				$this->logger->debug( "Assets loaded - Elementor form in content", [ 'plugin' => 'f12-cf7-captcha' ] );
				return true;
			}
		}

		// 6. Check for Avada/Fusion Builder forms
		if ( $post ) {
			// Fusion Builder stores data in post content with specific markers
			if ( ! empty( $post->post_content ) && (
				strpos( $post->post_content, '[fusion_form' ) !== false ||
				strpos( $post->post_content, 'fusion-form' ) !== false ||
				strpos( $post->post_content, 'class="fusion-form"' ) !== false
			) ) {
				$this->logger->debug( "Assets loaded - Avada Fusion form detected", [ 'plugin' => 'f12-cf7-captcha' ] );
				return true;
			}

			// Check Avada-specific meta
			$fusion_builder_status = get_post_meta( $post->ID, 'fusion_builder_status', true );
			if ( $fusion_builder_status === 'active' ) {
				// Avada page - load assets to be safe (Fusion forms might be included dynamically)
				$this->logger->debug( "Assets loaded - Avada Fusion Builder active", [ 'plugin' => 'f12-cf7-captcha' ] );
				return true;
			}
		}

		// 7. Check for comment forms on singular pages
		if ( is_singular() && comments_open() ) {
			$this->logger->debug( "Assets loaded for comment form", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}

		// 8. AJAX requests - always load (forms might be loaded dynamically)
		if ( wp_doing_ajax() ) {
			$this->logger->debug( "Assets loaded for AJAX request", [ 'plugin' => 'f12-cf7-captcha' ] );
			return true;
		}

		$this->logger->debug( "Assets not loaded - no form detected on page", [ 'plugin' => 'f12-cf7-captcha' ] );
		return false;
	}

	/**
	 * Loads the required frontend assets such as JavaScript and CSS files.
	 *
	 * Registers and enqueues the necessary scripts and styles for the plugin's
	 * frontend functionality. Additionally, localizes the script by passing
	 * dynamic data to the JavaScript file.
	 *
	 * @return void
	 */
	public function load_frontend_assets() {
		// Check if assets should be loaded on this page
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Settings
		$settings = $this->get_settings();

		if (isset($settings['beta'], $settings['beta']['beta_captcha_enable'], $settings['beta']['beta_captcha_api_key']) && (bool)$settings['beta']['beta_captcha_enable'] === true) {
			if(!empty( $settings['beta']['beta_captcha_api_key'])) {
				$this->get_logger()->info( "Behavior API enabled" );
				// Insert JavaScript
				wp_enqueue_script(
					'f12-cf7-captcha-client',
					plugin_dir_url( __FILE__ ) . 'core/assets/client.js',
					array(),
					FORGE12_CAPTCHA_VERSION,
					true
				);
				wp_script_add_data( 'f12-cf7-captcha-client', 'strategy', 'defer' );

				// Provide data for the script locally
				$api_url = defined( 'F12_CAPTCHA_API_URL' )
					? F12_CAPTCHA_API_URL
					: 'https://api.silentshield.io';

				wp_localize_script(
					'f12-cf7-captcha-client',
					'f12_client_data',
					[
						'key' => $settings['beta']['beta_captcha_api_key'],
						'url' => $api_url,
					]
				);

				$this->logger->debug( "API assets loaded", [
					'plugin'  => 'f12-cf7-captcha',
					'scripts' => [ 'f12-cf7-captcha-client' ], // Korrigierter Scriptname
					'styles'  => [],                        // Aktuell keine Styles vorhanden
					'context' => ( is_admin() ? 'admin' : ( is_user_logged_in() ? 'frontend-logged-in' : 'frontend-guest' ) )
				] );
			}
		}else{
			// Hole aktive Komponenten aus dem Compatibility-Modul
			$active_components = [];
			try {
				$compatibility = $this->get_module('compatibility');
				if ( method_exists( $compatibility, 'get_active_component_names' ) ) {
					$active_components = $compatibility->get_active_component_names();
				}
			} catch ( \Exception $e ) {
				$this->logger->error( 'Compatibility module not available', [
					'plugin' => 'f12-cf7-captcha',
					'error'  => $e->getMessage(),
				] );
			}

			$atts = array(
				'resturl'    => rest_url( 'f12-cf7-captcha/v1/' ),
				'restnonce'  => wp_create_nonce( 'wp_rest' ),
				'components' => $active_components,
			);

			wp_enqueue_script(
				'f12-cf7-captcha-reload',
				plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha-cf7.js',
				array( 'jquery' ),
				FORGE12_CAPTCHA_VERSION,
				true
			);
			wp_script_add_data( 'f12-cf7-captcha-reload', 'strategy', 'defer' );

			wp_localize_script(
				'f12-cf7-captcha-reload',
				'f12_cf7_captcha',
				$atts
			);
		}

		wp_enqueue_style(
			'f12-cf7-captcha-style',
			plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha.css',
			[],
			FORGE12_CAPTCHA_VERSION
		);

		// Reload button styling fallback (global defaults applied via CSS)
		$reload_css    = '';
		$reload_bg     = $this->get_settings( 'protection_captcha_reload_bg_color', 'global' );
		$reload_pad    = $this->get_settings( 'protection_captcha_reload_padding', 'global' );
		$reload_radius = $this->get_settings( 'protection_captcha_reload_border_radius', 'global' );
		$reload_border = $this->get_settings( 'protection_captcha_reload_border_color', 'global' );
		$reload_icon   = $this->get_settings( 'protection_captcha_reload_icon_size', 'global' );

		if ( ! empty( $reload_bg ) && preg_match( '/^#[a-fA-F0-9]{6}$/', $reload_bg ) ) {
			$reload_css .= 'background-color:' . esc_attr( $reload_bg ) . ';';
		}
		if ( is_numeric( $reload_pad ) ) {
			$reload_css .= 'padding:' . (int) $reload_pad . 'px;';
		}
		if ( is_numeric( $reload_radius ) ) {
			$reload_css .= 'border-radius:' . (int) $reload_radius . 'px;';
		}
		if ( ! empty( $reload_border ) && preg_match( '/^#[a-fA-F0-9]{6}$/', $reload_border ) ) {
			$reload_css .= 'border:1px solid ' . esc_attr( $reload_border ) . ';';
		}
		if ( ! empty( $reload_css ) ) {
			wp_add_inline_style( 'f12-cf7-captcha-style', '.f12-captcha .c-reload a {' . $reload_css . '}' );
		}
		if ( is_numeric( $reload_icon ) ) {
			wp_add_inline_style( 'f12-cf7-captcha-style', '.f12-captcha .c-reload a img {width:' . (int) $reload_icon . 'px !important;height:' . (int) $reload_icon . 'px !important;}' );
		}

		$this->logger->debug( "Frontend assets loaded", [
			'plugin'  => 'f12-cf7-captcha',
			'scripts' => [ 'f12-cf7-captcha-reload' ],
			'styles'  => [ 'f12-cf7-captcha-style' ],
			'context' => ( is_admin() ? 'admin' : ( is_user_logged_in() ? 'frontend-logged-in' : 'frontend-guest' ) )
		] );
	}

	public function load_admin_assets() {
		wp_enqueue_script(
			'f12-cf7-captcha-toggle',
			plugins_url( 'core/assets/toggle.js', __FILE__ ),
			array( 'jquery' ),
			FORGE12_CAPTCHA_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', '
jQuery(document).ready(function($){
	var $btn = $("#f12-reload-preview-btn");
	var $iconBlack = $("#f12-reload-preview-icon-black");
	var $iconWhite = $("#f12-reload-preview-icon-white");

	function updatePreview(){
		var bg = $("#protection_captcha_reload_bg_color").val() || "#2196f3";
		var bc = $("#protection_captcha_reload_border_color").val();
		var pad = $("#protection_captcha_reload_padding").val() || "3";
		var rad = $("#protection_captcha_reload_border_radius").val() || "3";
		var sz = $("#protection_captcha_reload_icon_size").val() || "16";
		$btn.css({
			"background-color": bg,
			"padding": pad + "px",
			"border-radius": rad + "px",
			"border": bc ? "1px solid " + bc : "none"
		});
		$iconBlack.add($iconWhite).css({"width": sz + "px", "height": sz + "px"});
		var isWhite = $("input[name=protection_captcha_reload_icon]:checked").val() === "white";
		$iconBlack.toggle(!isWhite);
		$iconWhite.toggle(isWhite);
	}

	$(".f12-color-picker").wpColorPicker({change: function(){ setTimeout(updatePreview, 50); }, clear: function(){ setTimeout(updatePreview, 50); }});

	$(document).on("input change", "#protection_captcha_reload_padding, #protection_captcha_reload_border_radius, #protection_captcha_reload_icon_size, input[name=protection_captcha_reload_icon]", updatePreview);
});
' );

		$this->logger->debug( "Admin assets loaded", [
			'plugin'  => 'f12-cf7-captcha',
			'scripts' => [ 'f12-cf7-captcha-toggle', 'wp-color-picker' ],
			'context' => ( is_admin() ? 'admin' : 'unknown' )
		] );
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

		$activated = in_array( $plugin, $this->plugins ) || array_key_exists( $plugin, $this->plugins );

		$this->logger->debug( "Plugin activation status checked", [
			'plugin'       => 'f12-cf7-captcha',
			'checked'      => $plugin,
			'is_activated' => $activated ? 'yes' : 'no'
		] );

		return $activated;
	}
}

/**
 * Init the contact form 7 captcha
 */
CF7Captcha::get_instance();

do_action( 'f12_cf7_captcha_init' );