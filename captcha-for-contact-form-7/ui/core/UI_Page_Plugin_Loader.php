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
			 * @var array<<string, string>> e.g.: [0 => [ name => string, path => string] , ...]
			 */
			private $Plugin_UI_Pages = [];

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				// Setze die UI_Manager-Instanz.
				$this->UI_Manager = $UI_Manager;
				$this->get_logger()->debug( 'UI_Manager-Instanz wurde gesetzt.' );

				// Rufe die Methode auf, die das Plugin-UI-Verzeichnis nach Seiten durchsucht.
				// Dies initialisiert die Seiten, bevor sie in WordPress registriert werden.
				$this->scan_for_plugin_ui_pages( $this->get_plugin_ui_path() );
				$this->get_logger()->info( 'Plugin-UI-Verzeichnis nach Seiten gescannt.' );

				// Füge einen Hook hinzu, der die gefundenen Seiten in WordPress registriert.
				// Die hohe Priorität (999999990) stellt sicher, dass dieser Hook sehr spät ausgelöst wird,
				// nachdem alle Seiten geladen wurden (z.B. durch andere Komponenten), aber bevor
				// die Seiten-Sortierung stattfindet (die eine noch höhere Priorität hat).
				add_action(
					$this->get_domain() . '_ui_after_load_pages',
					array( $this, 'register_plugin_ui_pages' ),
					999999990,
					1
				);
				$this->get_logger()->debug( 'Hook "register_plugin_ui_pages" hinzugefügt.', [
					'hook_name' => $this->get_domain() . '_ui_after_load_pages',
					'priority'  => 999999990,
				] );

				$this->get_logger()->info( 'Konstruktor abgeschlossen.' );
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
				$this->get_logger()->info('Starte die Registrierung der Plugin-UI-Seiten.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Durchlaufe alle im Plugin-UI-Speicher gefundenen Seiten.
				foreach ($this->Plugin_UI_Pages as $item) {
					$this->get_logger()->debug('Verarbeite Seite für die Registrierung.', ['item' => $item]);

					// Überspringe ungültige Einträge, denen der Pfad oder Name fehlt.
					if (!isset($item['path']) || !isset($item['name'])) {
						$this->get_logger()->warning('Ungültiger UI-Seiten-Eintrag übersprungen, da "path" oder "name" fehlt.');
						continue;
					}

					try {
						// Lade die Klassendatei der UI-Seite.
						require_once($item['path']);

						// Instanziiere die UI-Seite-Klasse.
						$UI_Page = new $item['name']($this->UI_Manager);

						// Füge die neu instanziierte Seite dem Seiten-Manager hinzu.
						$this->get_page_manager()->add_page($UI_Page);

						$this->get_logger()->info('UI-Seite erfolgreich registriert.', ['name' => $item['name']]);
					} catch (\Throwable $e) {
						$this->get_logger()->error('Fehler beim Laden oder Instanziieren einer UI-Seite.', [
							'name' => $item['name'],
							'path' => $item['path'],
							'error' => $e->getMessage(),
							'file' => $e->getFile(),
							'line' => $e->getLine(),
						]);
						// Ein kritischer Fehler sollte hier die Ausführung nicht stoppen,
						// um zu verhindern, dass das gesamte Admin-Menü ausfällt.
					}
				}

				$this->get_logger()->info('Registrierung aller Plugin-UI-Seiten abgeschlossen.');
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
				$this->get_logger()->info('Starte den Scan nach UI-Seiten im Verzeichnis.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'directory' => $directory,
				]);

				// Überprüfe, ob das Verzeichnis existiert.
				if (!is_dir($directory)) {
					$this->get_logger()->warning('Das angegebene Verzeichnis existiert nicht.', ['directory' => $directory]);
					return false;
				}

				// Versuche, das Verzeichnis zu öffnen.
				$handle = opendir($directory);
				if (!$handle) {
					$this->get_logger()->error('Das Verzeichnis konnte nicht geöffnet werden. Überprüfe die Lesezugriffsrechte.', ['directory' => $directory]);
					return false;
				}

				$this->get_logger()->debug('Verzeichnis erfolgreich geöffnet.');

				// Iteriere durch alle Einträge im Verzeichnis.
				while (false !== ($entry = readdir($handle))) {
					// Überspringe die Standard-Verzeichniseinträge '.' und '..'.
					if ($entry === '.' || $entry === '..') {
						continue;
					}

					// Überprüfe, ob der Dateiname dem Muster `UI_[Name].php` entspricht.
					if (!preg_match('!UI_([a-zA-Z_0-9]+)\.php!', $entry, $matches)) {
						$this->get_logger()->debug('Datei entspricht nicht dem Namensmuster.', ['file' => $entry]);
						continue;
					}

					// Stelle sicher, dass der zweite Match-Treffer (der Seitenname) existiert.
					if (!isset($matches[1])) {
						$this->get_logger()->warning('Dateiname entspricht zwar dem Muster, konnte aber den Seitennamen nicht extrahieren.', ['file' => $entry]);
						continue;
					}

					// Füge die gefundene UI-Seite zum internen Speicher hinzu.
					$this->Plugin_UI_Pages[] = [
						'name' => $this->get_namespace() . '\UI_' . $matches[1],
						'path' => $directory . '/' . $entry,
					];

					$this->get_logger()->info('UI-Seite gefunden und zur Liste hinzugefügt.', [
						'class_name' => $this->get_namespace() . '\UI_' . $matches[1],
						'file_path' => $directory . '/' . $entry,
					]);
				}

				// Schließe das Verzeichnis-Handle.
				closedir($handle);
				$this->get_logger()->info('Scan-Vorgang abgeschlossen.');

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