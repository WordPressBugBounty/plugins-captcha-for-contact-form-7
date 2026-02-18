<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_Page_Manager' ) ) {
		/**
		 * Handles all Pages of the given Object
		 */
		class UI_Page_Manager {
			/**
			 * @var ?UI_Manager $UI_Manager
			 */
			private $UI_Manager = null;

			/**
			 * @var array<UI_Page> $Page_Storage ;
			 */
			private $Page_Storage = [];

			/**
			 * Constructor
			 */
			public function __construct(UI_Manager $UI_Manager)
			{
				// Set the UI Manager instance.
				$this->UI_Manager = $UI_Manager;
				$this->get_logger()->debug('UI_Manager instance has been set.');

				// Add a hook to sort the pages after all pages have been initialized.
				// The high priority (999999999) ensures that this method is executed very late,
				// after all other page definitions have been loaded.
				add_action(
					$this->get_domain() . '_ui_after_load_pages',
					array($this, 'sort_pages'), // Method to be called
					999999999, // Very high priority
					1 // Number of arguments passed to the callback function
				);
				$this->get_logger()->debug('Hook "sort_pages" added with high priority.', [
					'hook_name' => $this->get_domain() . '_ui_after_load_pages',
				]);

				$this->get_logger()->info('Constructor completed.');
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * Sort the UI Pages by the Position
			 */
			public function sort_pages(UI_Manager $UI_Manager): void
			{
				$this->get_logger()->info('Starting the sorting process for UI pages.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Check if there are any pages to sort.
				if (empty($this->Page_Storage)) {
					$this->get_logger()->debug('No pages to sort. Process ended.');
					return;
				}

				$this->get_logger()->info('Starting to sort pages by their position.');

				// Use `usort` to sort the `Page_Storage` array based on each page's position.
				// The custom callback function compares the positions of two UI page objects ($a and $b).
				usort($this->Page_Storage, function ($a, $b) {
					$position_a = $a->get_position();
					$position_b = $b->get_position();

					$this->get_logger()->debug('Comparing page positions.', ['position_a' => $position_a, 'position_b' => $position_b]);

					// An optimized comparison operator (<=> or 'spaceship operator')
					// would be elegant here, but is not used to maintain compatibility with older PHP versions
					// (before PHP 7). The following logic serves the same purpose.
					if ($position_a < $position_b) {
						return -1; // $a comes before $b
					} else if ($position_a > $position_b) {
						return 1; // $a comes after $b
					} else {
						return 0; // The order remains unchanged
					}
				});

				$this->get_logger()->info('UI pages sorting process completed.');
			}

			/**
			 * Add a page to the UI (addPage())
			 */
			public function add_page(UI_Page $UI_Page): void
			{
				$this->get_logger()->info('Adding a new UI page to the menu.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'page_slug' => $UI_Page->get_slug(),
				]);

				// Store the UI_Page object in the `Page_Storage` array, using the slug as the key.
				$this->Page_Storage[$UI_Page->get_slug()] = $UI_Page;
				$this->get_logger()->debug('UI page successfully stored.');

				// Add a WordPress hook to render the page content.
				// The hook name is dynamic and includes the UI Manager's domain.
				add_action(
					'forge12-plugin-content-' . $this->get_domain(),
					[$UI_Page, 'render_content'],
					10,
					2
				);
				$this->get_logger()->debug('Hook for page content registered.', ['hook' => 'forge12-plugin-content-' . $this->get_domain()]);

				// Add another hook to render the page sidebar.
				add_action(
					'forge12-plugin-sidebar-' . $this->get_domain(),
					[$UI_Page, 'render_sidebar'],
					10,
					2
				);
				$this->get_logger()->debug('Hook for page sidebar registered.', ['hook' => 'forge12-plugin-sidebar-' . $this->get_domain()]);

				$this->get_logger()->info('UI page successfully added and hooks registered.');
			}

			/**
			 * Get Page By Slug (get())
			 *
			 * @param string $slug
			 *
			 * @return UI_Page|null
			 */
			private function get_page_by_slug(string $slug): ?UI_Page
			{
				$this->get_logger()->info('Attempting to find a UI page by its slug.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'slug' => $slug,
				]);

				// Check if the slug exists in the `Page_Storage` array.
				if (!isset($this->Page_Storage[$slug])) {
					$this->get_logger()->warning('UI page not found.', ['requested_slug' => $slug]);
					return null;
				}

				$page = $this->Page_Storage[$slug];

				$this->get_logger()->info('UI page successfully found and retrieved.', [
					'slug' => $slug,
				]);

				// Return the found UI_Page object.
				return $page;
			}

			/**
			 * Return the Storage of the Pages (getPages())
			 *
			 * @return UI_Page[]
			 */
			public function get_page_storage(): array
			{
				$this->get_logger()->info('Retrieving the array of stored UI pages.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Return the private `Page_Storage` array that contains all UI page objects.
				// This allows access to the registered pages from outside the class.
				$page_storage = $this->Page_Storage;

				$this->get_logger()->debug('The number of stored pages is ' . count($page_storage) . '.');

				return $page_storage;
			}

			/**
			 * Get the UI Manager
			 *
			 * @return UI_Manager
			 */
			private function get_ui_manager(): UI_Manager {
				return $this->UI_Manager;
			}

			/**
			 * Return the Domain of the UI Instance
			 *
			 * @return string
			 */
			private function get_domain(): string
			{
				$this->get_logger()->info('Retrieving the domain from the UI manager.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Call the get_ui_manager method to get the UI Manager instance.
				$UI_Manager = $this->get_ui_manager();

				// Then call the get_domain method on this instance.
				$domain = $UI_Manager->get_domain();

				$this->get_logger()->debug('Domain successfully retrieved from UI manager.', ['domain' => $domain]);

				return $domain;
			}
		}
	}
}