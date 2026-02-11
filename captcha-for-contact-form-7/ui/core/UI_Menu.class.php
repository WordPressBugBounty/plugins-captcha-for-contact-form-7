<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Show the UI Menu
	 */
	class UI_Menu {
		/**
		 * @var UI_Manager|null
		 */
		private $UI_Manager = null;

		/**
		 * UI constructor.
		 *
		 * @param UI_Manager $UI_Manager
		 */
		public function __construct(UI_Manager $UI_Manager)
		{
			$this->set_ui_manager($UI_Manager);
			// Set the UI Manager instance. It is good practice to do this via a setter
			// to unify internal management.
			$this->get_logger()->debug('UI_Manager instance has been set.');

			// Add a WordPress hook to call the 'render' method.
			// This hook is triggered when the admin menu is loaded.
			// The hook name is dynamic and depends on the UI Manager's domain.
			add_action(
				$UI_Manager->get_domain() . '_admin_menu',
				array($this, 'render'), // Method to be called
				10, // Priority (standard)
				3   // Number of arguments the hook accepts (here 3)
			);
			$this->get_logger()->debug('Hook "admin_menu" added for the "render" method.', [
				'hook_name' => $UI_Manager->get_domain() . '_admin_menu',
			]);

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
		private function set_ui_manager(UI_Manager $UI_Manager): void
		{
			$this->UI_Manager = $UI_Manager;
			$this->get_logger()->debug('UI_Manager instance has been successfully set.');
		}

		/**
		 *
		 * @param array<UI_Page> $Pages
		 * @param string         $active_slug
		 *
		 * @return void
		 */
		public function render($Page_Storage, string $active_slug, string $plugin_slug): void
		{
			$this->get_logger()->info('Starting the rendering of the admin menu.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'active_slug' => $active_slug,
				'plugin_slug' => $plugin_slug,
			]);

			// Ensure that $Page_Storage is an array.
			if (!is_array($Page_Storage)) {
				$Page_Storage = array($Page_Storage);
				$this->get_logger()->debug('Page_Storage was not an array and has been converted.');
			}

			?>
            <nav class="navbar">
                <ul class="navbar-nav">
					<?php
					$this->get_logger()->debug('Triggering the pre-menu hook.', ['hook' => 'before-forge12-plugin-menu-' . $plugin_slug]);
					do_action('before-forge12-plugin-menu-' . $plugin_slug);
					?>
					<?php foreach ($Page_Storage as /** @var UI_Page $Page */ $Page): ?>
						<?php
						$this->get_logger()->debug('Rendering menu item.', ['title' => $Page->get_title(), 'slug' => $Page->get_slug()]);

						$class = '';
						$slug = $plugin_slug . '_' . $Page->get_slug();

						// Special handling for the dashboard menu item.
						if ($Page->is_dashboard()) {
							$slug = $plugin_slug;
						}

						// Determine the 'active' class for the currently selected menu item.
						if ($Page->get_slug() == $active_slug || ($Page->is_dashboard() && empty($active_slug))) {
							$class = 'active';
							$this->get_logger()->debug('Menu item is active.', ['slug' => $Page->get_slug()]);
						}
						?>
                        <li class="forge12-plugin-menu-item">
                            <a href="<?php echo esc_url(admin_url('admin.php')); ?>?page=<?php echo esc_attr($slug); ?>"
                               title="<?php echo esc_attr($Page->get_title()); ?>"
                               class="<?php echo esc_attr($class) . ' ' . esc_attr($Page->get_class()); ?>">
								<?php echo esc_html($Page->get_title()); ?>
                            </a>
                        </li>
					<?php endforeach; ?>
					<?php
					$this->get_logger()->debug('Triggering the post-menu hook.', ['hook' => 'after-forge12-plugin-menu-' . $plugin_slug]);
					do_action('after-forge12-plugin-menu-' . $plugin_slug);
					?>
                </ul>
            </nav>
			<?php
			$this->get_logger()->info('Admin menu rendering completed.');
		}
	}
}