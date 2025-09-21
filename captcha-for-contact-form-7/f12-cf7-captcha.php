<?php
/**
 * Plugin Name: SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce)
 * Plugin URI: https://www.forge12.com/product/wordpress-captcha/
 * Description: SilentShield is an all-in-one spam protection plugin. Protects WordPress login, registration, comments, and popular form plugins (CF7, WPForms, Elementor, WooCommerce) with captcha, honeypot, blacklist, IP blocking, and whitelisting for logged-in users.
 * Version: 2.2.42
 * Requires PHP: 7.4
 * Author: Forge12 Interactive GmbH
 * Author URI: https://www.forge12.com
 * Text Domain: captcha-for-contact-form-7
 * Domain Path: /languages
 */
namespace f12_cf7_captcha;


define( 'FORGE12_CAPTCHA_VERSION', '2.2.42' );
define( 'FORGE12_CAPTCHA_SLUG', 'f12-cf7-captcha' );
define( 'FORGE12_CAPTCHA_BASENAME', plugin_basename( __FILE__ ) );


use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Compatibility;
use f12_cf7_captcha\core\log\Log_Cleaner;
use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\Support;
use f12_cf7_captcha\core\TemplateController;
use f12_cf7_captcha\core\timer\Timer_Controller;
use f12_cf7_captcha\core\UserData;
use f12_cf7_captcha\ui\UI_Manager;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

/**
 * Dependencies
 */
require_once( 'logger/logger.php' );
require_once( 'core/helpers/uuid.php' );
require_once( 'core/bootstrap.php' );

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
require_once( 'ui/UI_Manager.php' );
require_once( 'core/Support.class.php' );

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
		$default = array();

		$default = apply_filters( 'f12-cf7-captcha_settings', $default );
		$this->logger->debug( "Default Settings geladen", [
			'plugin'  => 'f12-cf7-captcha',
			'default' => $default
		] );

		$settings = get_option( 'f12-cf7-captcha-settings' );

		if ( ! is_array( $settings ) ) {
			$this->logger->debug( "Keine Settings gefunden, benutze leeres Array", [
				'plugin' => 'f12-cf7-captcha'
			] );
			$settings = array();
		} else {
			$this->logger->debug( "Settings geladen", [ 'plugin' => 'f12-cf7-captcha', 'settings' => $settings ] );
		}

		// Load Settings for Blacklist
		$settings['global']['protection_rules_blacklist_value'] = get_option( 'disallowed_keys', '' );

		foreach ( $default as $key => $data ) {
			if ( isset( $settings[ $key ] ) ) {
				$default[ $key ] = array_merge( $data, $settings[ $key ] );
			}
		}

		$settings = $default;

		// Komplettes Setting zurückgeben
		if ( empty( $single ) && $container == null ) {
			$this->logger->debug( "Alle Settings geladen", [
				'plugin' => 'f12-cf7-captcha',
				'keys'   => array_keys( $settings )
			] );

			return $settings;
		}

		// Container zurückgeben
		if ( empty( $single ) && $container != null ) {
			if ( isset( $settings[ $container ] ) ) {
				$this->logger->debug( "Settings-Container geladen", [
					'plugin'    => 'f12-cf7-captcha',
					'container' => $container,
					'keys'      => array_keys( $settings[ $container ] )
				] );

				return $settings[ $container ];
			} else {
				$this->logger->debug( "Settings-Container nicht gefunden", [
					'plugin'    => 'f12-cf7-captcha',
					'container' => $container
				] );
			}
		}

		// Einzelnes Setting zurückgeben
		if ( ! empty( $single ) && $container != null ) {
			if ( isset( $settings[ $container ][ $single ] ) ) {
				$this->logger->debug( "Einzelnes Setting geladen", [
					'plugin'    => 'f12-cf7-captcha',
					'container' => $container,
					'setting'   => $single,
					'value'     => is_scalar( $settings[ $container ][ $single ] ) ? $settings[ $container ][ $single ] : 'complex'
				] );

				return $settings[ $container ][ $single ];
			} else {
				$this->logger->debug( "Einzelnes Setting nicht gefunden", [
					'plugin'    => 'f12-cf7-captcha',
					'container' => $container,
					'setting'   => $single
				] );
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

		if ( null === $container ) {
			$settings[ $single ] = $value;
			$this->logger->info( "Setting aktualisiert", [
				'plugin'  => 'f12-cf7-captcha',
				'setting' => $single,
				'value'   => $value
			] );
		} else {
			$settings[ $container ][ $single ] = $value;
			$this->logger->info( "Setting im Container aktualisiert", [
				'plugin'    => 'f12-cf7-captcha',
				'container' => $container,
				'setting'   => $single,
				'value'     => $value
			] );
		}

		$updated = update_option( 'f12-cf7-captcha-settings', $settings );

		if ( ! $updated ) {
			$this->logger->error( "Fehler beim Speichern der Settings", [
				'plugin'    => 'f12-cf7-captcha',
				'container' => $container ?: 'root',
				'setting'   => $single,
				'value'     => $value
			] );
		}
	}


	/**
	 * Initializes the modules for the software.
	 *
	 * This method initializes the modules required for the software to function properly.
	 *
	 * @return void
	 */
	private function init_moduls(): void {
		$this->_moduls = [];

		$modules = [
			'template'      => TemplateController::class,
			'log-cleaner'   => Log_Cleaner::class,
			'compatibility' => Compatibility::class,
			'support'       => Support::class,
			'user-data'     => UserData::class,
			'timer'         => Timer_Controller::class,
			'protection'    => Protection::class,
		];

		foreach ( $modules as $key => $class ) {
			try {
				// Manche brauchen Logger als Dependency
				if ( in_array( $key, [ 'log-cleaner', 'compatibility', 'protection' ], true ) ) {
					$this->_moduls[ $key ] = new $class( $this, Log_WordPress::get_instance() );
				} else {
					$this->_moduls[ $key ] = new $class( $this );
				}

				$this->logger->info( "Modul initialisiert", [
					'plugin' => 'f12-cf7-captcha',
					'module' => $key,
					'class'  => $class
				] );

			} catch ( \Throwable $e ) {
				$this->logger->error( "Fehler beim Initialisieren eines Moduls", [
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
	public function get_modul( string $name ): BaseModul {
		if ( ! isset( $this->_moduls[ $name ] ) ) {
			$this->logger->error( "Angefordertes Modul existiert nicht", [
				'plugin'         => 'f12-cf7-captcha',
				'module'         => $name,
				'loaded_modules' => array_keys( $this->_moduls )
			] );

			throw new \Exception( sprintf( 'Modul %s does not exist.', $name ) );
		}

		$this->logger->debug( "Modul erfolgreich abgerufen", [
			'plugin' => 'f12-cf7-captcha',
			'module' => $name,
			'class'  => get_class( $this->_moduls[ $name ] )
		] );

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
		// Forge12 Logger initialisieren
		$this->logger = Logger::getInstance();

		$this->logger->info( "Plugin gestartet", [
			'plugin'  => 'f12-cf7-captcha',
			'version' => FORGE12_CAPTCHA_VERSION
		] );

		$this->init_moduls();
		$this->logger->debug( "Module initialisiert", [ 'plugin' => 'f12-cf7-captcha' ] );

		// Remove Filter which will not work with our filter list
		add_action( 'init', function () {
			remove_filter( 'wpcf7_spam', 'wpcf7_disallowed_list', 10 );
			$this->logger->debug( "Spam-Filter entfernt", [ 'plugin' => 'f12-cf7-captcha', 'filter' => 'wpcf7_spam' ] );
		} );

		add_action( 'init', function () {
			load_plugin_textdomain( 'captcha-for-contact-form-7', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			$this->logger->debug( "Textdomain geladen", [ 'plugin' => 'f12-cf7-captcha' ] );
		} );

		// Filter for Blacklist
		add_filter( 'f12-cf7-captcha_settings_loaded', [ $this, 'wp_load_blacklist' ] );
		$this->logger->debug( "Blacklist-Filter hinzugefügt", [ 'plugin' => 'f12-cf7-captcha' ] );

		$UI_Manager = UI_Manager::register_instance( $this->logger, 'f12-cf7-captcha',
			plugin_dir_url( __FILE__ ),
			plugin_dir_path( __FILE__ ),
			__NAMESPACE__,
			'SilentShield',
			'manage_options',
			plugins_url( 'ui/assets/icon-captcha-20x20.png', __FILE__ )
		);
		$this->logger->debug( "UI Manager registriert", [ 'plugin' => 'f12-cf7-captcha' ] );

		// Load assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_frontend_assets' ) );
		$this->logger->debug( "Asset-Loader Hooks gesetzt", [ 'plugin' => 'f12-cf7-captcha' ] );

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
				$this->logger->debug( "Blacklist geladen, aber leer", [
					'plugin' => 'f12-cf7-captcha'
				] );
			} else {
				$this->logger->info( "Blacklist erfolgreich geladen", [
					'plugin'   => 'f12-cf7-captcha',
					'keywords' => substr( $blacklist, 0, 50 ) . ( strlen( $blacklist ) > 50 ? '...' : '' )
				] );
			}
		} else {
			$this->logger->debug( "Blacklist-Option nicht im Settings-Array vorhanden", [
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

		$this->logger->debug( "Plugin Action-Links hinzugefügt", [
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
				wpautop( $notice )
			);

			// Log upgrade notice (gekürzt, um Log nicht zu überfluten)
			$this->logger->info( "Upgrade-Hinweis angezeigt", [
				'plugin' => 'f12-cf7-captcha',
				'notice' => substr( strip_tags( $notice ), 0, 100 ) . ( strlen( $notice ) > 100 ? '...' : '' )
			] );
		} else {
			$this->logger->debug( "Kein Upgrade-Hinweis vorhanden", [
				'plugin' => 'f12-cf7-captcha'
			] );
		}
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
		$atts = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		wp_enqueue_script(
			'f12-cf7-captcha-reload',
			plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha-cf7.js',
			array( 'jquery' ),
			null,
			true
		);

		wp_localize_script(
			'f12-cf7-captcha-reload',
			'f12_cf7_captcha',
			$atts
		);

		wp_enqueue_style(
			'f12-cf7-captcha-style',
			plugin_dir_url( __FILE__ ) . 'core/assets/f12-cf7-captcha.css'
		);

		$this->logger->debug( "Frontend-Assets geladen", [
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
			'1.0'
		);

		$this->logger->debug( "Admin-Assets geladen", [
			'plugin'  => 'f12-cf7-captcha',
			'scripts' => [ 'f12-cf7-captcha-toggle' ],
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

		$this->logger->debug( "Plugin-Aktivierungsstatus geprüft", [
			'plugin'       => 'f12-cf7-captcha',
			'checked'      => $plugin,
			'is_activated' => $activated ? 'yes' : 'no'
		] );

		return $activated;
	}
}

/**
 * Helper Function
 */
/*add_action( 'load_textdomain', function( $domain ) {
	if ( $domain === 'captcha-for-contact-form-7' && did_action( 'init' ) === 0 ) {
		error_log( "❌  Textdomain zu früh geladen" );
		error_log( print_r( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 ), true ) );
	}
}, 10, 1 );*/


/**
 * Init the contact form 7 captcha
 */
CF7Captcha::get_instance();

do_action( 'f12_cf7_captcha_init' );