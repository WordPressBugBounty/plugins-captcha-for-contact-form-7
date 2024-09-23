<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_Page_Plugin_Loader' ) ) {
		/**
		 * This class handles to loading of the custom plugin pages for the UI
		 */
		class UI_Page_Plugin_Loader {
			/**
			 * @var ?UI_Manager $UI_Manager
			 */
			private $UI_Manager = null;

			/**
			 * Stores all found UI Pages
			 *
			 * @var array<<string, string>> e.g.: [0 => [ name => string, path => string] , ...]
			 */
			private $Plugin_UI_Pages = [];

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				$this->UI_Manager = $UI_Manager;

				$this->scan_for_plugin_ui_pages( $this->get_plugin_ui_path() );

				// Load Components after the Pages have been initialized
				add_action( $this->get_domain() . '_ui_after_load_pages', array(
					$this,
					'register_plugin_ui_pages'
				), 999999990, 1 );

			}

			/**
			 * Load and register the UI Pages
			 *
			 * @return void
			 */
			public function register_plugin_ui_pages(UI_Manager $UI_Manager) {
				foreach ( $this->Plugin_UI_Pages as $item ) {
					if ( ! isset( $item['path'] ) || ! isset( $item['name'] ) ) {
						continue;
					}

					require_once( $item['path'] );
					$UI_Page = new $item['name']( $this->UI_Manager );

					$this->get_page_manager()->add_page( $UI_Page );
				}
			}

			private function get_page_manager(): UI_Page_Manager {
				return $this->UI_Manager->get_page_manager();
			}

			/**
			 * Return the domain of the current plugin.
			 *
			 * @return string
			 */
			private function get_domain(): string {
				return $this->UI_Manager->get_domain();
			}

			/**
			 * Returns the path to the ui elements for the plugin.
			 *
			 * @return string
			 */
			private function get_plugin_ui_path(): string {
				return $this->UI_Manager->get_plugin_dir_path() . 'ui/controller';
			}

			/**
			 * This will load the Custom UI of the Plugin - e.g UI pages only available for this plugin.
			 */
			private function scan_for_plugin_ui_pages( $directory ): bool {
				if ( ! is_dir( $directory ) ) {
					return false;
				}

				$handle = opendir( $directory );

				if ( ! $handle ) {
					return false;
				}

				while ( false !== ( $entry = readdir( $handle ) ) ) {
					if ( $entry == '.' || $entry == '..' ) {
						continue;
					}

					if ( ! preg_match( '!UI_([a-zA-Z_0-9]+)\.php!', $entry, $matches ) ) {
						continue;
					}

					if ( ! isset( $matches[1] ) ) {
						continue;
					}

					$this->Plugin_UI_Pages[] = [
						'name' => $this->get_namespace() . '\UI_' . $matches[1],
						'path' => $directory . '/' . $entry
					];
				}

				return true;
			}

			/**
			 * Return the Namespace of the Plugin
			 *
			 * @return string
			 */
			private function get_namespace(): string {
				return $this->UI_Manager->get_namespace();
			}
		}
	}
}