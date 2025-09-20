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
			// Setze die UI_Manager-Instanz. Es ist eine gute Praxis, dies über einen Setter zu tun,
			// um die interne Verwaltung zu vereinheitlichen.
			$this->get_logger()->debug('UI_Manager-Instanz wurde gesetzt.');

			// Füge einen WordPress-Hook hinzu, um die 'render'-Methode aufzurufen.
			// Dieser Hook wird ausgelöst, wenn das Admin-Menü geladen wird.
			// Der Hook-Name ist dynamisch und hängt von der Domain des UI-Managers ab.
			add_action(
				$UI_Manager->get_domain() . '_admin_menu',
				array($this, 'render'), // Methode, die aufgerufen wird
				10, // Priorität (Standard)
				3   // Anzahl der Argumente, die der Hook akzeptiert (hier 3)
			);
			$this->get_logger()->debug('Hook "admin_menu" für die Methode "render" hinzugefügt.', [
				'hook_name' => $UI_Manager->get_domain() . '_admin_menu',
			]);

			$this->get_logger()->info('Konstruktor abgeschlossen.');
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
			$this->get_logger()->debug('UI_Manager-Instanz wurde erfolgreich gesetzt.');
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
			$this->get_logger()->info('Starte das Rendering des Admin-Menüs.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'active_slug' => $active_slug,
				'plugin_slug' => $plugin_slug,
			]);

			// Sicherstellen, dass $Page_Storage ein Array ist.
			if (!is_array($Page_Storage)) {
				$Page_Storage = array($Page_Storage);
				$this->get_logger()->debug('Page_Storage war kein Array und wurde konvertiert.');
			}

			?>
            <nav class="navbar">
                <ul class="navbar-nav">
					<?php
					$this->get_logger()->debug('Löse den Hook für das Pre-Menü aus.', ['hook' => 'before-forge12-plugin-menu-' . $plugin_slug]);
					do_action('before-forge12-plugin-menu-' . $plugin_slug);
					?>
					<?php foreach ($Page_Storage as /** @var UI_Page $Page */ $Page): ?>
						<?php
						$this->get_logger()->debug('Rendere Menüpunkt.', ['title' => $Page->get_title(), 'slug' => $Page->get_slug()]);

						$class = '';
						$slug = $plugin_slug . '_' . $Page->get_slug();

						// Spezielle Handhabung für das Dashboard-Menüelement.
						if ($Page->is_dashboard()) {
							$slug = $plugin_slug;
						}

						// Bestimme die 'active'-Klasse für den aktuell ausgewählten Menüpunkt.
						if ($Page->get_slug() == $active_slug || ($Page->is_dashboard() && empty($active_slug))) {
							$class = 'active';
							$this->get_logger()->debug('Menüpunkt ist aktiv.', ['slug' => $Page->get_slug()]);
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
					$this->get_logger()->debug('Löse den Hook für das Post-Menü aus.', ['hook' => 'after-forge12-plugin-menu-' . $plugin_slug]);
					do_action('after-forge12-plugin-menu-' . $plugin_slug);
					?>
                </ul>
            </nav>
			<?php
			$this->get_logger()->info('Rendering des Admin-Menüs abgeschlossen.');
		}
	}
}