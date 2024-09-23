<?php

namespace f12_cf7_captcha\core;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class TemplateController
 */
class TemplateController extends BaseModul {
	/**
	 * Load a plugin template file.
	 *
	 * @param string $filename The name of the template file to load.
	 * @param array  $params   Optional. An associative array of parameters to pass to the template.
	 *
	 * @return void
	 */
	function load_plugin_template( string $filename, array $params = array() ): void {
		// Set the path of the template file
		$template_path = plugin_dir_path( dirname( __FILE__ ) ) . "templates/$filename.php";

		// Check if the template file exists
		if ( file_exists( $template_path ) ) {
			// Extract params to variables
			extract( $params );
			// Load the template file
			include( $template_path );
		} else {
			// Error handling in case the template file does not exist
			error_log( "Template not found: " . $template_path );
		}
	}

	/**
	 * Retrieves the content of a plugin template file as a string.
	 *
	 * @param string $filename The name of the template file to load.
	 * @param array  $params   Optional. An array of parameters to pass to the template file. Defaults to an empty
	 *                         array.
	 *
	 * @return string The content of the plugin template file.
	 */
	function get_plugin_template( string $filename, array $params = array() ): string {
		ob_start();
		$this->load_plugin_template( $filename, $params );

		return ob_get_clean();
	}
}