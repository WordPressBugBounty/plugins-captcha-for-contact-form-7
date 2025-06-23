<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Compatibility
 *
 * This class represents the compatibility module for CF7Captcha.
 * It loads and registers components from a given directory recursively.
 *
 */
class Compatibility extends BaseModul {
	/**
	 * @var array<string, string>
	 */
	private $components = array();
	/**
	 * @var Log_WordPress
	 */
	private Log_WordPress $Logger;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param CF7Captcha    $Controller The CF7Captcha object.
	 * @param Log_WordPress $Logger     The Log_WordPress object.
	 */
	public function __construct( CF7Captcha $Controller, Log_WordPress $Logger ) {
		parent::__construct($Controller);

		$this->Logger     = $Logger;

		$this->load( dirname( dirname( __FILE__ ) ) . '/compatibility', 0 );

		add_action( 'after_setup_theme', function () {

			add_action( 'f12_cf7_captcha_ui_after_load_compatibilities', array(
				$this,
				'wp_register_components'
			), 10, 1 );

			/**
			 * Hook to load all compatibilities after the Controller has been initiated
			 * and all compatibilities have been loaded
			 *
			 * Allows Developers to add custom compatibilities via script.
			 *
			 * @param Compatibility $Compatibility
			 *
			 * @since 1.0.0
			 */
			do_action( 'f12_cf7_captcha_ui_after_load_compatibilities', $this );

			/**
			 * Hook that triggers the validators after compatibilities have been loaded.
			 *
			 * @since 1.12.2
			 */
			do_action( 'f12_cf7_captcha_compatibilities_loaded' );
		} );
	}

	/**
	 * Retrieves the registered components.
	 *
	 * @formatter:off
     *
     * @return array {
     *      The array of registered components as another array
     *
     *      @type array {
     *          The Array containing the information about the components
     *
     *          @type string            $name   The Name of the Controller & Namespace
     *          @type string            $path   The Path to the Controller
     *          @type BaseController    $object The instance of the controller
     *      }
     * }
     *
     * @formatter:on
	 */
	public function get_components(): array {
		return $this->components;
	}

	/**
	 * Get a component by name.
	 *
	 * This method is used to retrieve a component by its name from the components array.
	 *
	 * @param string $name The name of the component to retrieve.
	 *
	 * @return BaseController The retrieved component if found, or null if not found.
	 */
	public function get_component( string $name ): BaseController {
		if ( ! isset( $this->components[ $name ] ) ) {
			throw new RuntimeException( sprintf( 'Component not found: %s. Available Components: %s', $name, implode( ",", array_keys( $this->components ) ) ) );
		}

		if ( ! isset( $this->components[ $name ]['object'] ) ) {
			throw new RuntimeException( sprintf( 'Component not yet initialized.' ) );
		}

		return $this->components[ $name ]['object'];
	}

	/**
	 * Registers components.
	 *
	 * @param Compatibility $Compatibility The Compatibility object.
	 *
	 * @throws RuntimeException If a component is not initialized correctly.
	 */
	public function wp_register_components( Compatibility $Compatibility ): void {
		foreach ( $this->components as $key => $component ) {
			if ( ! isset( $component['name'] ) || ! isset( $component['path'] ) ) {
				throw new \RuntimeException( sprintf( 'Component key: %s, name: %s, path: %s not initialized correct.', $key, $component['name'], $component['path'] ) );
			}

			require_once( $component['path'] );
			$this->components[ $key ]['object'] = new $component['name']( $this->Controller, $this->Logger );
		}
	}

	/**
	 * Load components from a directory recursively.
	 *
	 * This method is used to load components from a directory recursively.
	 * It searches for files matching the pattern Controller[a-zA-Z_0-9]+.class.php
	 * and adds them to the components array.
	 *
	 * @param string $directory The directory to load components from.
	 * @param int    $lvl       The current level of recursion.
	 *
	 * @return void
	 * @throws \RuntimeException If the directory does not exist or is not readable.
	 *
	 */
	private function load( $directory, $lvl ) {
		if ( ! is_dir( $directory ) ) {
			throw new \RuntimeException( sprintf( 'Directory %s does not exist.', $directory ) );
		}

		$handle = opendir( $directory );

		if ( ! $handle ) {
			throw new \RuntimeException( sprintf( 'Directory %s is not readable.', $directory ) );
		}

		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry == '.' || $entry == '..' ) {
				continue;
			}

			$current_directory = $directory . '/' . $entry;

			if ( is_dir( $current_directory ) && $lvl == 0 ) {
				$this->load( $current_directory, $lvl + 1 );
				continue;
			}

			if ( ! preg_match( '!Controller([a-zA-Z_0-9]+)\.class\.php!', $entry, $matches ) ) {
				continue;
			}

			if ( ! isset( $matches[1] ) ) {
				continue;
			}

			# Add the component
			$name = '\\f12_cf7_captcha\\compatibility\\Controller' . $matches[1];

			$this->components[ $name ] = [ 'name' => $name, 'path' => $current_directory ];
		}
	}
}