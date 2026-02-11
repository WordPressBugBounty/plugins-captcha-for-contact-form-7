<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_Asset_Handler' ) ) {
		/**
		 * Handles all Assets required for the UI
		 */
		class UI_Asset_Handler {
			/**
			 * @var array $Script_Storage Store all scripts loaded
			 */
			private $Script_Storage = [];

			/**
			 * @var array $Style_Storage Store all styles laoded
			 */
			private $Style_Storage = [];

			/**
			 * @var ?UI_Manager $UI_Manager
			 */
			private $UI_Manager = null;

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				$this->UI_Manager = $UI_Manager;

				$this->get_logger()->info( 'UI Asset Handler constructor started.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				] );

				// Add hooks to load scripts and styles in the WordPress admin area.
				add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
				$this->get_logger()->debug( 'Hook "admin_enqueue_scripts" added for loading scripts.' );

				add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
				$this->get_logger()->debug( 'Hook "admin_enqueue_scripts" added for loading styles.' );

				// Register the default styles.
				$this->register_style(
					'f12-ui-admin-styles',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-style.css'
				);
				$this->get_logger()->debug( 'CSS file "f12-ui-admin-styles" registered.' );

				// Register the default scripts.
				$this->register_script(
					'f12-ui-admin-toggle',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/toggle.js',
					array( 'jquery' ),
					'1.0'
				);
				$this->get_logger()->debug( 'JavaScript file "f12-ui-admin-toggle" registered.' );

				$this->register_script(
					'f12-ui-admin-clipboard',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/copy-to-clipboard.js',
					array( 'jquery' ),
					'1.0'
				);
				$this->get_logger()->debug( 'JavaScript file "f12-ui-admin-clipboard" registered.' );

				$this->register_script(
					'f12-ui-admin',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-captcha.js',
					[ 'jquery' ]
				);
				$this->get_logger()->debug( 'JavaScript file "f12-ui-admin" registered.' );

				$this->get_logger()->info( 'UI Asset Handler constructor completed. Styles and scripts are ready for registration.' );
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * Use to register a custom script for the UI
			 *
			 * @param string $handle
			 * @param string $src
			 * @param array  $deps
			 * @param        $ver
			 * @param bool   $in_footer
			 *
			 * @return void
			 */
			public function register_script(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void
			{
				$this->get_logger()->info('Registering a new script for enqueue.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'handle' => $handle,
					'src' => $src,
				]);

				// Define the script data in an associative array.
				$script_data = [
					'handle' => $handle,
					'src' => $src,
					'deps' => $deps,
					'ver' => $ver,
					'in_footer' => $in_footer
				];

				// Add the script data to the internal storage.
				$this->Script_Storage[] = $script_data;

				$this->get_logger()->info('Script data successfully stored.');
				$this->get_logger()->debug('Stored script data:', $script_data);
			}

			/**
			 * Use to register a custom style for the UI
			 *
			 * @param string $handle
			 * @param string $src
			 * @param array  $deps
			 * @param        $ver
			 * @param string $media
			 *
			 * @return void
			 */
			public function register_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
			{
				$this->get_logger()->info('Registering a new style for enqueue.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'handle' => $handle,
					'src' => $src,
				]);

				// Define the style data in an associative array.
				$style_data = [
					'handle' => $handle,
					'src' => $src,
					'deps' => $deps,
					'ver' => $ver,
					'media' => $media,
				];

				// Add the style data to the internal storage.
				$this->Style_Storage[] = $style_data;

				$this->get_logger()->info('Style data successfully stored.');
				$this->get_logger()->debug('Stored style data:', $style_data);
			}

			/**
			 * Load all registered scripts
			 *
			 * @return void
			 */
			public function load_scripts()
			{
				$this->get_logger()->info('Starting the enqueue process for all stored scripts.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Iterate through all scripts stored in the `Script_Storage` array.
				foreach ($this->Script_Storage as $script) {
					$this->get_logger()->debug('Adding script to load list.', [
						'handle' => $script['handle'],
						'src'    => $script['src'],
					]);

					// Call the WordPress function `wp_enqueue_script()` to load the script.
					// The parameters are taken directly from the `script` array.
					wp_enqueue_script(
						$script['handle'],
						$script['src'],
						$script['deps'],
						$script['ver'],
						$script['in_footer']
					);
				}

				$this->get_logger()->info('All scripts have been enqueued for loading in WordPress.');
			}

			/**
			 * Load all registered styles
			 *
			 * @return void
			 */
			public function load_styles()
			{
				$this->get_logger()->info('Starting the enqueue process for all stored styles.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Iterate through all style definitions stored in the `Style_Storage` array.
				foreach ($this->Style_Storage as $style) {
					$this->get_logger()->debug('Adding style to load list.', [
						'handle' => $style['handle'],
						'src'    => $style['src'],
					]);

					// Call the WordPress function `wp_enqueue_style()` to load the stylesheet.
					// The parameters are taken directly from the `style` array.
					wp_enqueue_style(
						$style['handle'],
						$style['src'],
						$style['deps'],
						$style['ver'],
						$style['media']
					);
				}

				$this->get_logger()->info('All styles have been enqueued for loading in WordPress.');
			}

		}
	}
}