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
	 * @var Log_WordPress_Interface
	 */
	private Log_WordPress_Interface $Logger;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param CF7Captcha    $Controller The CF7Captcha object.
	 * @param Log_WordPress_Interface $Logger     The Log_WordPress object.
	 */
	public function __construct(CF7Captcha $Controller, Log_WordPress_Interface $Logger)
	{
		parent::__construct($Controller);

		// Logging the instantiation.
		// Note: The logger instance is taken directly from the constructor parameter here.
		// This can lead to issues if parent::__construct() also sets a logger.
		// It is better to use a consistent method for logger management.
		$this->Logger = $Logger;
		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Load the compatibility files from the specified directory.
		// The second parameter '0' indicates that subdirectories are not searched recursively.
		$this->load(dirname(dirname(__FILE__)) . '/compatibility', 0);
		$this->get_logger()->debug('Compatibilities loaded.');

		// Add an anonymous hook to 'after_setup_theme'.
		add_action('after_setup_theme', function () {

			// Add another hook that calls the 'wp_register_components' method.
			add_action('f12_cf7_captcha_ui_after_load_compatibilities', array(
				$this,
				'wp_register_components'
			), 10, 1);
			$this->get_logger()->debug('Hook "f12_cf7_captcha_ui_after_load_compatibilities" added for component registration.');

			// Triggers the 'f12_cf7_captcha_ui_after_load_compatibilities' action.
			// This allows developers to add their own compatibility hooks.
			do_action('f12_cf7_captcha_ui_after_load_compatibilities', $this);
			$this->get_logger()->debug('Action "f12_cf7_captcha_ui_after_load_compatibilities" triggered.');

			// Triggers the 'f12_cf7_captcha_compatibilities_loaded' action.
			// Signals to validators that compatibilities have been loaded.
			do_action('f12_cf7_captcha_compatibilities_loaded');
			$this->get_logger()->debug('Action "f12_cf7_captcha_compatibilities_loaded" triggered.');
		});
		$this->get_logger()->info('Constructor completed.');
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
	public function get_components(): array
	{
		$this->get_logger()->info('Retrieving registered components.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Count the number of components and output them in the debug log.
		$component_count = count($this->components);
		$this->get_logger()->debug("{$component_count} components found.");

		return $this->components;
	}

	public function get_active_component_names(): array {
		$active = [];

		foreach ($this->components as $name => $component) {
			if (!isset($component['object']) || !$component['object'] instanceof BaseController) {
				continue;
			}

			$object = $component['object'];

			try {
				// Check via is_enabled() if the method exists
				if (method_exists($object, 'is_enabled') && $object->is_enabled()) {
					$active[] = basename(str_replace('\\', '/', $name));
				}
			} catch (\Throwable $e) {
				// If a controller throws errors (e.g., missing plugin), log it and skip it
				$this->get_logger()->warning(
					sprintf('Error checking is_enabled() in %s: %s', $name, $e->getMessage()),
					['file' => $e->getFile(), 'line' => $e->getLine()]
				);
			}
		}

		return $active;
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
	public function get_component(string $name): BaseController
	{
		$this->get_logger()->info('Attempting to retrieve a component by name.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'requested_component' => $name,
		]);

		// Check if the component exists at all.
		if (!isset($this->components[$name])) {
			$available_components = implode(", ", array_keys($this->components));
			$error_message = sprintf('Component not found: %s. Available components: %s', $name, $available_components);

			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new RuntimeException( $error_message );
		}

		// Check if the component has already been instantiated.
		if (!isset($this->components[$name]['object'])) {
			$error_message = sprintf('Component "%s" has not been initialized yet.', $name);

			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new RuntimeException( $error_message );
		}

		$this->get_logger()->info('Component successfully retrieved.', [
			'component_name' => $name,
		]);

		return $this->components[$name]['object'];
	}

	/**
	 * Registers components.
	 *
	 * @param Compatibility $Compatibility The Compatibility object.
	 *
	 * @throws RuntimeException If a component is not initialized correctly.
	 */
	public function wp_register_components(Compatibility $Compatibility): void
	{
		$this->get_logger()->info('Starting registration of compatibility components.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		foreach ($this->components as $key => $component) {
			// Ensure that the necessary array keys exist before accessing them.
			if (!isset($component['name']) || !isset($component['path'])) {
				$error_message = sprintf(
					'Component key: %s, Name: %s, Path: %s not correctly initialized.',
					$key,
					$component['name'] ?? 'not defined', // Use Null Coalescing Operator for safe access
					$component['path'] ?? 'not defined'
				);
				$this->get_logger()->error($error_message);
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
				throw new \RuntimeException( $error_message );
			}

			$this->get_logger()->debug('Registering component.', ['name' => $component['name'], 'path' => $component['path']]);

			try {
				// Load the component file
				require_once($component['path']);

				// Instantiate the component and store the object
				$this->components[$key]['object'] = new $component['name']($this->Controller, $this->Logger);
				$this->get_logger()->info('Component successfully instantiated.', ['name' => $component['name']]);

			} catch (\Throwable $e) {
				$error_message = sprintf('Error loading or instantiating component "%s".', $component['name']);
				$this->get_logger()->critical($error_message, [
					'error' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				]);
				// A critical error that should terminate execution to avoid further problems.
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
				throw new \RuntimeException( $error_message );
			}
		}

		$this->get_logger()->info('Registration of all compatibility components completed.');
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
	private function load($directory, $lvl)
	{
		$this->get_logger()->info('Starting component loading process in a directory.', [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'directory' => $directory,
			'level'     => $lvl,
		]);

		// Check if the directory exists.
		if (!is_dir($directory)) {
			$error_message = sprintf('Directory %s does not exist.', $directory);
			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new \RuntimeException( $error_message );
		}

		// Try to open the directory.
		$handle = @opendir($directory); // Use @ to suppress PHP warnings if opendir fails.

		if ($handle === false) {
			$error_message = sprintf('Directory %s is not readable.', $directory);
			$this->get_logger()->error($error_message);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new \RuntimeException( $error_message );
		}

		$this->get_logger()->debug('Directory successfully opened.');

		// Iterate through the entries in the directory.
		while (false !== ($entry = readdir($handle))) {
			// Skip the '.' and '..' entries.
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$current_directory = $directory . '/' . $entry;

			// If the entry is a subdirectory and the level is 0, load recursively.
			if (is_dir($current_directory) && $lvl === 0) {
				$this->get_logger()->debug('Switching to subdirectory.', ['subdir' => $current_directory]);
				$this->load($current_directory, $lvl + 1);
				continue;
			}

			// Find files that match the naming pattern 'Controller[Name].class.php'.
			if (!preg_match('!Controller([a-zA-Z_0-9]+)\.class\.php!', $entry, $matches)) {
				$this->get_logger()->debug('File does not match the naming pattern.', ['file' => $entry]);
				continue;
			}

			// Ensure that the second match result exists.
			if (!isset($matches[1])) {
				$this->get_logger()->warning('No class name found in filename.', ['file' => $entry]);
				continue;
			}

			$class_name_part = $matches[1];

			// Determine the full namespace for the class.
			// The namespace should be correctly formed depending on the path.
			$namespace = 'f12_cf7_captcha\\compatibility';
			/*if ($lvl > 0) {
				// If in a subdirectory, add the subdirectory name to the namespace.
				$sub_dir_name = basename($directory);
				$namespace .= '\\' . $sub_dir_name;
			}*/

			$name = '\\' . $namespace . '\\Controller' . $class_name_part;

			$this->get_logger()->debug('Component added for registration.', [
				'class_name' => $name,
				'file_path'  => $current_directory,
			]);

			// Add the component to the list.
			$this->components[$name] = [
				'name' => $name,
				'path' => $current_directory
			];
		}

		closedir($handle);
		$this->get_logger()->info('Loading process completed.');
	}
}