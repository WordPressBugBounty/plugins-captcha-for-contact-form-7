<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_WordPress' ) ) {
		/**
		 * Add the Pages to WordPress
		 */
		class UI_WordPress {
			private $UI_Manager = null;

			/**
			 * @param $UI_Manager
			 */
			public function __construct(UI_Manager $UI_Manager)
			{
				// Set the UI Manager instance.
				$this->set_ui_manager($UI_Manager);
				$this->get_logger()->debug('UI_Manager instance has been set.');

				// Add a WordPress hook to create the submenu pages.
				// 'admin_menu' is the standard hook to register menu items in the admin dashboard.
				add_action('admin_menu', [$this, 'add_submenu_pages']);
				$this->get_logger()->debug('Hook "admin_menu" registered for adding submenu pages.');

				// Add another hook to hide certain submenu pages in the admin menu,
				// while they remain accessible via their URL.
				add_action('admin_head', [$this, 'hide_submenu_pages']);
				$this->get_logger()->debug('Hook "admin_head" registered for hiding submenu pages.');

				$this->get_logger()->info('Constructor completed.');
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * @param UI_Manager $UI_Manager
			 *
			 * @return void
			 */
			private function set_ui_manager( UI_Manager $UI_Manager ) {
				$this->UI_Manager = $UI_Manager;
			}

			/**
			 * @return UI_Manager
			 */
			private function get_ui_manager(): UI_Manager {
				return $this->UI_Manager;
			}

			/**
			 * @return string
			 */
			private function get_title(): string
			{
				$this->get_logger()->info('Retrieving the UI page title by getting the name from the UI manager.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Call the get_ui_manager method to get the UI Manager instance.
				$ui_manager = $this->get_ui_manager();

				// Then call the get_name method on this instance to get the name.
				$title = $ui_manager->get_name();

				$this->get_logger()->debug('Title successfully retrieved from UI manager.', ['title' => $title]);

				// Return the title as a string.
				return $title;
			}

			/**
			 * @return string
			 */
			private function get_capability(): string
			{
				$this->get_logger()->info('Retrieving the required user capability from the UI manager.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Call the get_ui_manager method to get the UI Manager instance.
				$ui_manager = $this->get_ui_manager();

				// Call the get_capability method on this instance.
				$capability = $ui_manager->get_capability();

				$this->get_logger()->debug('User capability successfully retrieved from UI manager.', ['capability' => $capability]);

				return $capability;
			}

			/**
			 * @return string
			 */
			private function get_slug(): string
			{
				$this->get_logger()->info('Retrieving the page slug using the UI manager domain.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Retrieve the UI Manager instance.
				$UI_Manager = $this->get_ui_manager();

				// The get_domain() method of UI_Manager returns the unique identifier of the plugin.
				// This is used here as the menu page slug.
				$slug = $UI_Manager->get_domain();

				$this->get_logger()->debug('Slug successfully retrieved from UI manager.', ['slug' => $slug]);

				return $slug;
			}

			/**
			 * TODO ADD ICON
			 *
			 * @return string
			 */
			private function get_icon(): string
			{
				$this->get_logger()->info('Retrieving the admin menu icon from the UI manager.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Retrieve the UI Manager instance.
				$UI_Manager = $this->get_ui_manager();

				// The `get_icon()` method of UI_Manager returns the CSS class name or the icon URL.
				$icon = $UI_Manager->get_icon();

				$this->get_logger()->debug('Icon successfully retrieved from UI manager.', ['icon' => $icon]);

				return $icon;
			}

			/**
			 * Return the array containing all pages
			 *
			 * @return UI_Page[]
			 */
			private function get_page_storage(): array {
				return $this->get_ui_manager()->get_page_manager()->get_page_storage();
			}

			/**
			 * Add the WordPress Page for the Settings to the WordPress CMS
			 *
			 * @private WordPress Hook
			 */
			public function add_submenu_pages(): void
			{
				$this->get_logger()->info('Starting the process to add admin menu and submenu pages.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Register the main plugin page as a top-level menu page.
				// WordPress recommends this approach when the main page should serve as a dashboard.
				add_menu_page(
					$this->get_title(),          // Page title
					$this->get_title(),          // Menu title
					$this->get_capability(),     // Required user capability
					$this->get_slug(),           // Unique slug
					'',                          // Callback function (handled later in the submenu page)
					$this->get_icon()            // Icon URL or Dashicon class
				);
				$this->get_logger()->info('Main menu page successfully registered.', ['slug' => $this->get_slug()]);

				// Iterate through all registered UI pages in storage to add them as submenus.
				foreach ($this->get_page_storage() as /** @var UI_Page $Page */ $Page) {
					$this->get_logger()->debug('Processing submenu page.', ['page_slug' => $Page->get_slug()]);

					// Determine the slug for the subpage. The dashboard slug is the same as the main slug.
					if ($Page->is_dashboard()) {
						$slug = $this->get_slug();
					} else {
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					// Register the submenu page.
					add_submenu_page(
						$this->get_slug(),       // Parent page slug
						$Page->get_title(),      // Page title
						$Page->get_title(),      // Menu title
						$this->get_capability(), // Required capability
						$slug,                   // Unique subpage slug
						function () {            // Anonymous callback function to render the page
							$this->render_page();
						},
						$Page->get_position()    // Position in submenu
					);
					$this->get_logger()->info('Submenu page successfully registered.', ['slug' => $slug, 'title' => $Page->get_title()]);
				}

				$this->get_logger()->info('All menu and submenu pages have been registered.');
			}

			/**
			 * This will ensure that the menu page will be removed before the rendering.
			 * This needs to be called from add_action('admin_head', ''); to be working.
			 *
			 * @return void
			 */
			public function hide_submenu_pages(): void
			{
				$this->get_logger()->info('Starting the process to hide submenu pages.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Iterate through all UI pages stored in `Page_Storage`.
				foreach ($this->get_page_storage() as /** @var UI_Page $Page */ $Page) {
					$this->get_logger()->debug('Checking page visibility in menu.', ['page_slug' => $Page->get_slug()]);

					// Check if the page should be hidden in the menu.
					if (!$Page->hide_in_menu()) {
						$this->get_logger()->debug('Page does not need to be hidden. Skipping.', ['page_slug' => $Page->get_slug()]);
						continue;
					}

					// Determine the slug of the subpage to be hidden.
					if ($Page->is_dashboard()) {
						// Dashboard page has the same slug as the main page.
						$slug = $this->get_slug();
					} else {
						// Normal subpage.
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					// Use the WordPress function `remove_submenu_page()`
					// to remove the page from the admin menu.
					// The page remains accessible via its direct URL.
					remove_submenu_page($this->get_slug(), $slug);

					$this->get_logger()->info('Submenu page successfully removed from menu.', [
						'parent_slug' => $this->get_slug(),
						'removed_slug' => $slug,
					]);
				}

				$this->get_logger()->info('Process to hide submenu pages completed.');
			}

			/**
			 * Render the UI Page
			 *
			 * @return void
			 */
			public function render_page(): void
			{
				$this->get_logger()->info('Starting the rendering process for the admin page.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Determine the current page slug from the URL.
				$page = '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if (isset($_GET['page'])) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$page_full_slug = sanitize_text_field( wp_unslash( $_GET['page'] ) );
					$plugin_slug = $this->get_slug();

					// Remove the plugin slug and the following separator.
					// The substr() and explode() method can fail with different slugs.
					// A better approach would be to remove the plugin slug directly from the beginning of the string.
					if (strpos($page_full_slug, $plugin_slug) === 0) {
						$page = substr($page_full_slug, strlen($plugin_slug));
						// Remove the leading separator if present.
						$page = ltrim($page, '_');
					}
				}

				// If no slug or only the main slug is present, set the default slug.
				if (empty($page)) {
					$page = $this->get_slug();
				}
				$this->get_logger()->debug('Current page slug determined.', ['page_slug' => $page]);

				// Retrieve all registered UI pages.
				$Page_Storage = $this->get_page_storage();
				$Menu_Page_Storage = [];

				// Create a separate array with pages that should be displayed in the menu.
				foreach ($Page_Storage as $UI_Page) {
					if (!$UI_Page->hide_in_menu()) {
						$Menu_Page_Storage[] = $UI_Page;
					}
				}
				$this->get_logger()->debug('Number of pages in menu: ' . count($Menu_Page_Storage));

				// Begin rendering the HTML structure of the admin page.
				?>
                <div class="forge12-plugin <?php echo esc_attr('captcha-for-contact-form-7'); ?>">
                    <div class="forge12-plugin-header">
                        <div class="forge12-plugin-header-inner">
                            <img src="<?php echo esc_url($this->get_ui_manager()->get_plugin_dir_url() . 'ui/assets/icon-captcha-128x128.png'); ?>"
                                 alt="Forge12 Interactive GmbH" title="Forge12 Interactive GmbH"/>
                            <div class="title">
                                <h1>
									<?php esc_html_e('SilentShield – Captcha & Anti-Spam for WordPress', 'captcha-for-contact-form-7'); ?>
                                </h1>
                                <p><?php esc_html_e(' by Forge12 Interactive GmbH', 'captcha-for-contact-form-7'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="forge12-plugin-menu">
						<?php
						// Trigger the hook to render the menu.
						do_action($this->get_slug() . '_admin_menu', $Menu_Page_Storage, $page, $this->get_slug());
						$this->get_logger()->debug('Admin menu hook triggered.');
						?>
                    </div>
                    <div class="forge12-plugin-content">
                        <div class="forge12-plugin-content-main">
							<?php
							// Trigger the hook to render the content of the current page.
							do_action('forge12-plugin-content-' . $this->get_slug(), $this->get_slug(), $page);
							$this->get_logger()->debug('Page content hook triggered.');
							?>
                        </div>
                    </div>
                    <div class="forge12-plugin-footer">
                        <div class="forge12-plugin-footer-inner">
                            <img src="<?php echo esc_url($this->get_ui_manager()->get_plugin_dir_url() . 'ui/assets/logo-forge12-dark.png'); ?>"
                                 alt="Forge12 Interactive GmbH" title="Forge12 Interactive GmbH"/>
                        </div>
                    </div>
                </div>
				<?php
				$this->get_logger()->info('Admin page rendering completed.');
			}
		}
	}
}