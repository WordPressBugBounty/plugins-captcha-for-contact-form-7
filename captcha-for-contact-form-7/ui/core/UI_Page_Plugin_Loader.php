<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

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
			 * @var array<int, array{name: string, path: string}>
			 */
			private $Plugin_UI_Pages = [];

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				// Set the UI Manager instance.
				$this->UI_Manager = $UI_Manager;
				$this->get_logger()->debug( 'UI_Manager instance has been set.' );

				// Call the method that scans the plugin UI directory for pages.
				// This initializes the pages before they are registered in WordPress.
				$this->scan_for_plugin_ui_pages( $this->get_plugin_ui_path() );
				$this->get_logger()->info( 'Plugin UI directory scanned for pages.' );

				// Add a hook that registers the found pages in WordPress.
				// The high priority (999999990) ensures that this hook is triggered very late,
				// after all pages have been loaded (e.g., by other components), but before
				// the page sorting takes place (which has an even higher priority).
				add_action(
					$this->get_domain() . '_ui_after_load_pages',
					array( $this, 'register_plugin_ui_pages' ),
					999999990,
					1
				);
				$this->get_logger()->debug( 'Hook "register_plugin_ui_pages" added.', [
					'hook_name' => $this->get_domain() . '_ui_after_load_pages',
					'priority'  => 999999990,
				] );

				$this->get_logger()->info( 'Constructor completed.' );
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * Load and register the UI Pages
			 *
			 * @return void
			 */
			public function register_plugin_ui_pages(UI_Manager $UI_Manager): void
			{
				$this->get_logger()->info('Starting the registration of plugin UI pages.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Iterate through all pages found in the plugin UI storage.
				foreach ($this->Plugin_UI_Pages as $item) {
					$this->get_logger()->debug('Processing page for registration.', ['item' => $item]);

					// Skip invalid entries that are missing the path or name.
					if (!isset($item['path']) || !isset($item['name'])) {
						$this->get_logger()->warning('Invalid UI page entry skipped because "path" or "name" is missing.');
						continue;
					}

					try {
						// Load the UI page class file.
						require_once($item['path']);

						// Instantiate the UI page class.
						$UI_Page = new $item['name']($this->UI_Manager);

						// Add the newly instantiated page to the page manager.
						$this->get_page_manager()->add_page($UI_Page);

						$this->get_logger()->info('UI page successfully registered.', ['name' => $item['name']]);
					} catch (\Throwable $e) {
						$this->get_logger()->error('Error loading or instantiating a UI page.', [
							'name' => $item['name'],
							'path' => $item['path'],
							'error' => $e->getMessage(),
							'file' => $e->getFile(),
							'line' => $e->getLine(),
						]);
						// A critical error should not stop execution here,
						// to prevent the entire admin menu from failing.
					}
				}

				$this->get_logger()->info('Registration of all plugin UI pages completed.');
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
			private function scan_for_plugin_ui_pages(string $directory): bool
			{
				$this->get_logger()->info('Starting the scan for UI pages in the directory.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'directory' => $directory,
				]);

				// Check if the directory exists.
				if (!is_dir($directory)) {
					$this->get_logger()->warning('The specified directory does not exist.', ['directory' => $directory]);
					return false;
				}

				// Attempt to open the directory.
				$handle = opendir($directory);
				if (!$handle) {
					$this->get_logger()->error('The directory could not be opened. Check read access permissions.', ['directory' => $directory]);
					return false;
				}

				$this->get_logger()->debug('Directory successfully opened.');

				// Iterate through all entries in the directory.
				while (false !== ($entry = readdir($handle))) {
					// Skip the standard directory entries '.' and '..'.
					if ($entry === '.' || $entry === '..') {
						continue;
					}

					// Check if the filename matches the pattern `UI_[Name].php`.
					if (!preg_match('!UI_([a-zA-Z_0-9]+)\.php!', $entry, $matches)) {
						$this->get_logger()->debug('File does not match the naming pattern.', ['file' => $entry]);
						continue;
					}

					// Ensure that the second match (the page name) exists.
					if (!isset($matches[1])) {
						$this->get_logger()->warning('File name matches the pattern but could not extract the page name.', ['file' => $entry]);
						continue;
					}

					// Add the found UI page to the internal storage.
					$this->Plugin_UI_Pages[] = [
						'name' => $this->get_namespace() . '\UI_' . $matches[1],
						'path' => $directory . '/' . $entry,
					];

					$this->get_logger()->info('UI page found and added to the list.', [
						'class_name' => $this->get_namespace() . '\UI_' . $matches[1],
						'file_path' => $directory . '/' . $entry,
					]);
				}

				// Close the directory handle.
				closedir($handle);
				$this->get_logger()->info('Scan process completed.');

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