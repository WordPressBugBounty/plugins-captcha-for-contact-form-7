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
				// Setze die Instanz des UI-Managers.
				$this->set_ui_manager($UI_Manager);
				$this->get_logger()->debug('UI_Manager-Instanz wurde gesetzt.');

				// Füge einen WordPress-Hook hinzu, um die Untermenüseiten zu erstellen.
				// 'admin_menu' ist der Standard-Hook, um Menüelemente im Admin-Dashboard zu registrieren.
				add_action('admin_menu', [$this, 'add_submenu_pages']);
				$this->get_logger()->debug('Hook "admin_menu" für das Hinzufügen von Untermenüseiten registriert.');

				// Füge einen weiteren Hook hinzu, um bestimmte Untermenüseiten im Admin-Menü auszublenden,
				// während sie weiterhin über ihre URL erreichbar bleiben.
				add_action('admin_head', [$this, 'hide_submenu_pages']);
				$this->get_logger()->debug('Hook "admin_head" für das Ausblenden von Untermenüseiten registriert.');

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
				$this->get_logger()->info('Rufe den Titel der UI-Seite ab, indem ich den Namen vom UI-Manager beziehe.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Rufe die get_ui_manager-Methode auf, um die Instanz des UI-Managers zu erhalten.
				$ui_manager = $this->get_ui_manager();

				// Rufe dann die get_name-Methode auf dieser Instanz auf, um den Namen zu erhalten.
				$title = $ui_manager->get_name();

				$this->get_logger()->debug('Titel erfolgreich vom UI-Manager abgerufen.', ['title' => $title]);

				// Gib den Titel als String zurück.
				return $title;
			}

			/**
			 * @return string
			 */
			private function get_capability(): string
			{
				$this->get_logger()->info('Rufe die erforderliche Benutzerberechtigung vom UI-Manager ab.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Rufe die get_ui_manager-Methode auf, um die Instanz des UI-Managers zu erhalten.
				$ui_manager = $this->get_ui_manager();

				// Rufe die get_capability-Methode auf dieser Instanz auf.
				$capability = $ui_manager->get_capability();

				$this->get_logger()->debug('Benutzerberechtigung erfolgreich vom UI-Manager abgerufen.', ['capability' => $capability]);

				return $capability;
			}

			/**
			 * @return string
			 */
			private function get_slug(): string
			{
				$this->get_logger()->info('Rufe den Slug der Seite ab, indem die Domain des UI-Managers verwendet wird.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Rufe die Instanz des UI-Managers ab.
				$UI_Manager = $this->get_ui_manager();

				// Die get_domain()-Methode des UI_Managers liefert den eindeutigen Bezeichner des Plugins.
				// Dieser wird hier als Slug der Menüseite verwendet.
				$slug = $UI_Manager->get_domain();

				$this->get_logger()->debug('Slug erfolgreich vom UI-Manager erhalten.', ['slug' => $slug]);

				return $slug;
			}

			/**
			 * TODO ADD ICON
			 *
			 * @return string
			 */
			private function get_icon(): string
			{
				$this->get_logger()->info('Rufe das Admin-Menü-Icon vom UI-Manager ab.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Rufe die Instanz des UI-Managers ab.
				$UI_Manager = $this->get_ui_manager();

				// Die `get_icon()`-Methode des UI_Managers liefert den CSS-Klassen-Namen oder die URL des Icons.
				$icon = $UI_Manager->get_icon();

				$this->get_logger()->debug('Icon erfolgreich vom UI-Manager erhalten.', ['icon' => $icon]);

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
				$this->get_logger()->info('Starte den Prozess zum Hinzufügen der Admin-Menü- und Untermenüseiten.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Registriere die Hauptseite des Plugins als Menü-Seite der obersten Ebene.
				// WordPress empfiehlt diese Vorgehensweise, wenn die Hauptseite als Dashboard dienen soll.
				add_menu_page(
					$this->get_title(),          // Seite-Titel
					$this->get_title(),          // Menü-Titel
					$this->get_capability(),     // Erforderliche Benutzerberechtigung
					$this->get_slug(),           // Eindeutiger Slug
					'',                          // Callback-Funktion (wird später in der Unterseite gehandhabt)
					$this->get_icon()            // Icon-URL oder Dashicon-Klasse
				);
				$this->get_logger()->info('Haupt-Menüseite erfolgreich registriert.', ['slug' => $this->get_slug()]);

				// Durchlaufe alle im Speicher registrierten UI-Seiten, um sie als Untermenüs hinzuzufügen.
				foreach ($this->get_page_storage() as /** @var UI_Page $Page */ $Page) {
					$this->get_logger()->debug('Verarbeite Untermenüseite.', ['page_slug' => $Page->get_slug()]);

					// Bestimme den Slug für die Unterseite. Der Dashboard-Slug ist derselbe wie der Haupt-Slug.
					if ($Page->is_dashboard()) {
						$slug = $this->get_slug();
					} else {
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					// Registriere die Untermenüseite.
					add_submenu_page(
						$this->get_slug(),       // Slug der übergeordneten Seite
						$Page->get_title(),      // Seite-Titel
						$Page->get_title(),      // Menü-Titel
						$this->get_capability(), // Erforderliche Berechtigung
						$slug,                   // Eindeutiger Slug der Unterseite
						function () {            // Anonyme Funktion als Callback, um die Seite zu rendern
							$this->render_page();
						},
						$Page->get_position()    // Position im Untermenü
					);
					$this->get_logger()->info('Untermenüseite erfolgreich registriert.', ['slug' => $slug, 'title' => $Page->get_title()]);
				}

				$this->get_logger()->info('Alle Menü- und Untermenüseiten wurden registriert.');
			}

			/**
			 * This will ensure that the menu page will be removed before the rendering.
			 * This needs to be called from add_action('admin_head', ''); to be working.
			 *
			 * @return void
			 */
			public function hide_submenu_pages(): void
			{
				$this->get_logger()->info('Starte den Prozess zum Ausblenden von Untermenüseiten.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Durchlaufe alle im `Page_Storage` gespeicherten UI-Seiten.
				foreach ($this->get_page_storage() as /** @var UI_Page $Page */ $Page) {
					$this->get_logger()->debug('Überprüfe Seite auf Sichtbarkeit im Menü.', ['page_slug' => $Page->get_slug()]);

					// Überprüfe, ob die Seite im Menü versteckt werden soll.
					if (!$Page->hide_in_menu()) {
						$this->get_logger()->debug('Seite muss nicht versteckt werden. Überspringe.', ['page_slug' => $Page->get_slug()]);
						continue;
					}

					// Bestimme den Slug der Unterseite, die versteckt werden soll.
					if ($Page->is_dashboard()) {
						// Dashboard-Seite hat denselben Slug wie die Hauptseite.
						$slug = $this->get_slug();
					} else {
						// Normale Unterseite.
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					// Verwende die WordPress-Funktion `remove_submenu_page()`,
					// um die Seite aus dem Admin-Menü zu entfernen.
					// Die Seite bleibt über ihre direkte URL zugänglich.
					remove_submenu_page($this->get_slug(), $slug);

					$this->get_logger()->info('Untermenüseite erfolgreich aus dem Menü entfernt.', [
						'parent_slug' => $this->get_slug(),
						'removed_slug' => $slug,
					]);
				}

				$this->get_logger()->info('Prozess zum Ausblenden von Untermenüseiten abgeschlossen.');
			}

			/**
			 * Render the UI Page
			 *
			 * @return void
			 */
			public function render_page(): void
			{
				$this->get_logger()->info('Starte den Rendering-Prozess für die Admin-Seite.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Ermittle den aktuellen Seiten-Slug aus der URL.
				$page = '';
				if (isset($_GET['page'])) {
					$page_full_slug = sanitize_text_field($_GET['page']);
					$plugin_slug = $this->get_slug();

					// Entferne den Plugin-Slug und den folgenden Trennstrich.
					// Die substr() und explode() Methode kann bei unterschiedlichen Slugs fehlschlagen.
					// Ein besserer Weg wäre, den Plugin-Slug direkt am Anfang der Zeichenkette zu entfernen.
					if (strpos($page_full_slug, $plugin_slug) === 0) {
						$page = substr($page_full_slug, strlen($plugin_slug));
						// Entferne den führenden Trennstrich, falls vorhanden.
						$page = ltrim($page, '_');
					}
				}

				// Wenn kein oder nur der Haupt-Slug vorhanden ist, setze den Standard-Slug.
				if (empty($page)) {
					$page = $this->get_slug();
				}
				$this->get_logger()->debug('Aktueller Seiten-Slug ermittelt.', ['page_slug' => $page]);

				// Rufe alle registrierten UI-Seiten ab.
				$Page_Storage = $this->get_page_storage();
				$Menu_Page_Storage = [];

				// Erstelle ein separates Array mit Seiten, die im Menü angezeigt werden sollen.
				foreach ($Page_Storage as $UI_Page) {
					if (!$UI_Page->hide_in_menu()) {
						$Menu_Page_Storage[] = $UI_Page;
					}
				}
				$this->get_logger()->debug('Anzahl der Seiten im Menü: ' . count($Menu_Page_Storage));

				// Beginne mit dem Rendern des HTML-Gerüsts der Admin-Seite.
				?>
                <div class="forge12-plugin <?php echo esc_attr('captcha-for-contact-form-7'); ?>">
                    <div class="forge12-plugin-header">
                        <div class="forge12-plugin-header-inner">
                            <img src="<?php echo esc_url($this->get_ui_manager()->get_plugin_dir_url() . 'ui/assets/icon-captcha-128x128.png'); ?>"
                                 alt="Forge12 Interactive GmbH" title="Forge12 Interactive GmbH"/>
                            <div class="title">
                                <h1>
									<?php _e('SilentShield – Captcha & Anti-Spam for WordPress', 'captcha-for-contact-form-7'); ?>
                                </h1>
                                <p><?php _e(' by Forge12 Interactive GmbH', 'captcha-for-contact-form-7'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="forge12-plugin-menu">
						<?php
						// Löse den Hook aus, um das Menü zu rendern.
						do_action($this->get_slug() . '_admin_menu', $Menu_Page_Storage, $page, $this->get_slug());
						$this->get_logger()->debug('Admin-Menü-Hook ausgelöst.');
						?>
                    </div>
                    <div class="forge12-plugin-content">
                        <div class="forge12-plugin-content-main">
							<?php
							// Löse den Hook aus, um den Inhalt der aktuellen Seite zu rendern.
							do_action('forge12-plugin-content-' . $this->get_slug(), $this->get_slug(), $page);
							$this->get_logger()->debug('Seiten-Inhalt-Hook ausgelöst.');
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
				$this->get_logger()->info('Rendering der Admin-Seite abgeschlossen.');
			}
		}
	}
}