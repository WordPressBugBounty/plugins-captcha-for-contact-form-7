<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}


	if ( ! class_exists( 'forge12\ui\UI_Manager' ) ) {
		/**
		 * Load dependencies
		 */
		require_once( 'core/UI_Message.php' );
		require_once( 'core/UI_Asset_Handler.php' );
		require_once( 'core/UI_Page_Plugin_Loader.php' );;
		require_once( 'core/UI_Page_Manager.php' );
		require_once( 'core/UI_Menu.class.php' );
		require_once( 'core/UI_Page.php' );
		require_once( 'core/UI_Page_Form.php' );
		require_once( 'core/UI_WordPress.php' );

		/**
		 * UI Manager
		 */
		class UI_Manager {
			private LoggerInterface $logger;

			/**
			 * The Domain / Slug of the plugin. Must be unique.
			 *
			 * @var string $domain
			 */
			private $domain = '';

			/**
			 * The name for the Menu of the plugin
			 *
			 * @var string $name
			 */
			private $name = '';

			/**
			 * Set the Icon of the Menu
			 */
			private $icon = '';

			/**
			 * The capability that is required to see the ui. @see https://wordpress.org/documentation/article/roles-and-capabilities/
			 *
			 * @var string
			 */
			private $capability = 'manage_options';

			/**
			 * Responsible to manage all Pages for the UI
			 *
			 * @var UI_Page_Manager|null
			 */
			private $UI_Page_Manager = null;

			/**
			 * Responsible to handle all Assets required for the UI
			 *
			 * @var UI_Asset_Handler|null
			 */
			private $UI_Asset_Handler = null;

			/**
			 * Responsible for the Message Handling of the UI Interface
			 *
			 * @var ?UI_Message
			 */
			private $UI_Message = null;

			/**
			 * The WordPress Menu Integration
			 *
			 * @var UI_WordPress|null
			 */
			private $UI_WordPress = null;

			/**
			 * Array containing all instances for the different ui managers
			 *
			 * @param array<UI_Manager> $_instance
			 */
			private static $_instance = array();

			/**
			 * Stores the URL to the Plugin Directory. e.g: https://domain.de/wp-content/plugins/plugin-xy/
			 *
			 * @var string
			 */
			private $plugin_dir_url = '';

			/**
			 * Stores the path to the plugin directory. e.g.: \var\www\html\wp-content\plugins\plugin-xy\
			 *
			 * @var string
			 */
			private $plugin_dir_path = '';

			/**
			 * The namespace of the plugin
			 */
			private $namespace;

			/**
			 * @var UI_Menu
			 */
			private $UI_Menu = null;

			/**
			 * @var UI_Page_Plugin_Loader
			 */
			private $UI_Page_Plugin_Loader = null;

			/**
			 * Get an instance of the object. If it doesn't exist, create one.
			 *
			 * @return ?UI_Manager
			 */
			public static function get_instance( string $domain ) {
				if ( ! isset( self::$_instance[ $domain ] ) ) {
					return null;
				}

				return self::$_instance[ $domain ];
			}

			/**
			 * Register a new Instance of a UI. Returns true on success, return false on failure.
			 *
			 * @param string $domain
			 * @param string $plugin_dir_url
			 * @param string $plugin_dir_path
			 * @param string $name
			 * @param string $capability
			 *
			 * @return bool
			 */
			public static function register_instance( LoggerInterface $logger, $domain, string $plugin_dir_url, string $plugin_dir_path, string $namespace, string $name = '', string $capability = 'manage_options', string $icon = '' ): bool {
				if ( isset( self::$_instance[ $domain ] ) ) {
					return false;
				}

				self::$_instance[ $domain ] = new UI_Manager( $logger, $domain, $plugin_dir_url, $plugin_dir_path, $namespace, $name, $capability, $icon );

				return true;
			}

			/**
			 * Ensure that there will only be one instance ob the object.
			 *
			 * @see get_instance()
			 */
			private function __construct( LoggerInterface $logger, string $domain, string $plugin_dir_url, string $plugin_dir_path, string $namespace, string $name, string $capability, string $icon ) {
				$this->logger = $logger;
				$this->set_domain( $domain );
				$this->set_name( $name );
				$this->set_capability( $capability );
				$this->set_plugin_dir_url( $plugin_dir_url );
				$this->set_plugin_dir_path( $plugin_dir_path );
				$this->set_namespace( $namespace );
				$this->set_icon( $icon );

				$this->UI_WordPress          = new UI_WordPress( $this );
				$this->UI_Page_Plugin_Loader = new UI_Page_Plugin_Loader( $this );
				$this->UI_Page_Manager       = new UI_Page_Manager( $this );
				$this->UI_Asset_Handler      = new UI_Asset_Handler( $this );
				$this->UI_Message            = new UI_Message( $this );
				$this->UI_Menu               = new UI_Menu( $this );

				// Called after all Pages have been initialized

				do_action( $this->get_domain() . '_ui_after_load_pages', $this );

				// Add Filter to get all settings
				add_filter( $this->get_domain() . '_get_settings', [ $this, 'get_settings' ] );
			}

			public function get_logger(): LoggerInterface {
				return $this->logger;
			}

			/**
			 * Return all UI Settings
			 *
			 * @return array
			 */
			public function get_settings(): array {
				/**
				 * Preload the Settings
				 *
				 * @param array $settings The default settings to be loaded
				 *
				 * @since 1.0.0
				 *
				 */
				$default = apply_filters( $this->get_domain() . '_settings', [] );

				$settings = get_option( $this->get_domain() . '-settings' );

				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				foreach ( $default as $key => $data ) {
					if ( isset( $settings[ $key ] ) ) {
						if ( is_array( $data ) ) {
							$default[ $key ] = array_merge( $data, $settings[ $key ] );
						} else {
							$default[ $key ] = $settings[ $key ];
						}
					}
				}

				/**
				 * Filters the title tag content for an admin page.
				 *
				 * @param array $settings The loaded settings as array
				 *
				 * @since 2.0.4
				 *
				 */
				return apply_filters( $this->get_domain() . '_settings_loaded', $default );
			}

			/**
			 * @return UI_Page_Manager
			 */
			public function get_page_manager(): UI_Page_Manager {
				return $this->UI_Page_Manager;
			}

			public function get_ui_message(): UI_Message {
				return $this->UI_Message;
			}

			/**
			 * @return UI_Asset_Handler
			 */
			public function get_asset_handler(): UI_Asset_Handler {
				return $this->UI_Asset_Handler;
			}

			private function set_plugin_dir_url( string $plugin_dir_url ) {
				$this->plugin_dir_url = $plugin_dir_url;
			}

			public function get_plugin_dir_url(): string {
				return $this->plugin_dir_url;
			}

			private function set_plugin_dir_path( string $plugin_dir_path ) {
				$this->plugin_dir_path = $plugin_dir_path;
			}

			public function get_plugin_dir_path(): string {
				return $this->plugin_dir_path;
			}

			private function set_domain( string $domain ) {
				$this->domain = $domain;
			}

			public function get_domain(): string {
				return $this->domain;
			}

			private function set_name( string $name ) {
				$this->name = $name;
			}

			public function get_name(): string {
				return $this->name;
			}

			private function set_capability( string $capability ) {
				$this->capability = $capability;
			}

			public function get_capability(): string {
				return $this->capability;
			}

			private function set_namespace( string $namespace ) {
				$this->namespace = $namespace;
			}

			public function get_namespace(): string {
				return $this->namespace;
			}

			private function set_icon( string $icon ) {
				$this->icon = $icon;
			}

			public function get_icon(): string {
				return $this->icon;
			}
		}
	}
}