<?php

namespace f12_cf7_captcha\ui {
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

				// Add Scripts
				add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

				// Add Styles
				add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );

				/*
				 * Load defaults Scripts
				 */
				$this->register_style( 'f12-ui-admin-styles', $UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-style.css' );
				$this->register_script( 'f12-ui-admin-toggle', $UI_Manager->get_plugin_dir_url() . 'ui/assets/toggle.js', array( 'jquery' ), '1.0' );
				$this->register_script( 'f12-ui-admin-clipboard', $UI_Manager->get_plugin_dir_url() . 'ui/assets/copy-to-clipboard.js', array( 'jquery' ), '1.0' );
				$this->register_script( 'f12-ui-admin', $UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-captcha.js', [ 'jquery' ] );
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
			public function register_script( string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false ) {
				$this->Script_Storage[] = [
					'handle'    => $handle,
					'src'       => $src,
					'deps'      => $deps,
					'ver'       => $ver,
					'in_footer' => $in_footer
				];
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
			public function register_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ) {
				$this->Style_Storage[] = [
					'handle' => $handle,
					'src'    => $src,
					'deps'   => $deps,
					'ver'    => $ver,
					'media'  => $media
				];
			}

			/**
			 * Load all registered scripts
			 *
			 * @return void
			 */
			public function load_scripts() {
				foreach ( $this->Script_Storage as $script ) {
					/*
					 * enqueue the script
					 */
					wp_enqueue_script( $script['handle'], $script['src'], $script['deps'], $script['ver'], $script['in_footer'] );
				}
			}

			/**
			 * Load all registered styles
			 *
			 * @return void
			 */
			public function load_styles() {
				foreach ( $this->Style_Storage as $style ) {
					/*
					 * enqueue the style
					 */
					wp_enqueue_style( $style['handle'], $style['src'], $style['deps'], $style['ver'], $style['media'] );
				}

			}

		}
	}
}