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
	public function load_plugin_template(string $filename, array $params = []): void
	{
		$this->get_logger()->info('Attempting to load a plugin template file.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'filename' => $filename,
		]);

		// Define the full path to the template file.
		// The function dirname( __FILE__ ) returns the parent directory of the current script.
		$template_path = plugin_dir_path(dirname(__FILE__)) . "templates/$filename.php";

		$this->get_logger()->debug('Template path determined.', ['path' => $template_path]);

		// Check if the template file exists.
		if (file_exists($template_path)) {
			// Extract the passed parameters into local variables.
			// Warning: 'extract()' can pose security risks if the data
			// comes from an untrusted source, as variables could be overwritten.
			// Since the function is assumed to be 'private' or 'protected', the risk is low.
			extract($params);

			$this->get_logger()->info('Template file found. Loading file.');

			// Include the template file.
			include($template_path);

			$this->get_logger()->debug('Template file successfully loaded.');
		} else {
			// Log an error if the template file does not exist.
			$error_message = sprintf('Template file not found: %s', $template_path);
			$this->get_logger()->error($error_message);

			// Use 'error_log' for direct error logging, which is common in WordPress.
			error_log($error_message);
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
	public function get_plugin_template(string $filename, array $params = []): string
	{
		$this->get_logger()->info('Starting buffering process to load a plugin template file and return its content.', [
			'class'    => __CLASS__,
			'method'   => __METHOD__,
			'filename' => $filename,
		]);

		// Start output buffering. All subsequent 'echo' or 'print' calls
		// will not be output directly, but written to an internal buffer.
		ob_start();

		// Load the template file, which causes its content to be written to the buffer.
		// The load_plugin_template() method will find the file, extract the parameters,
		// and then include it via 'include'.
		try {
			$this->load_plugin_template($filename, $params);
			$this->get_logger()->debug('Template content successfully loaded into buffer.');
		} catch (\Throwable $e) {
			$this->get_logger()->error('Error loading template file.', [
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);
			// Clear the buffer and end buffering without returning the content.
			ob_end_clean();
			return '';
		}

		// Get the buffer content and end buffering.
		// The buffer is cleared and its content is returned as a string.
		$template_content = ob_get_clean();

		$this->get_logger()->info('Template content successfully retrieved from buffer and returned.');

		return $template_content;
	}
}