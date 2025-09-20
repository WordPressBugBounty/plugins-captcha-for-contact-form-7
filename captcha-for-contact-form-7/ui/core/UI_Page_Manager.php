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
				// Setze die UI_Manager-Instanz.
				$this->UI_Manager = $UI_Manager;
				$this->get_logger()->debug('UI_Manager-Instanz wurde gesetzt.');

				// Füge einen Hook hinzu, um die Seiten nach der Initialisierung aller Seiten zu sortieren.
				// Die hohe Priorität (999999999) stellt sicher, dass diese Methode sehr spät ausgeführt wird,
				// nachdem alle anderen Seiten-Definitionen geladen wurden.
				add_action(
					$this->get_domain() . '_ui_after_load_pages',
					array($this, 'sort_pages'), // Methode, die aufgerufen wird
					999999999, // Sehr hohe Priorität
					1 // Anzahl der Argumente, die an die Callback-Funktion übergeben werden
				);
				$this->get_logger()->debug('Hook "sort_pages" mit hoher Priorität hinzugefügt.', [
					'hook_name' => $this->get_domain() . '_ui_after_load_pages',
				]);

				$this->get_logger()->info('Konstruktor abgeschlossen.');
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * Sort the UI Pages by the Position
			 */
			public function sort_pages(UI_Manager $UI_Manager): void
			{
				$this->get_logger()->info('Starte den Sortiervorgang für die UI-Seiten.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Überprüfe, ob überhaupt Seiten zum Sortieren vorhanden sind.
				if (empty($this->Page_Storage)) {
					$this->get_logger()->debug('Keine Seiten zum Sortieren vorhanden. Vorgang beendet.');
					return;
				}

				$this->get_logger()->info('Beginne mit dem Sortieren der Seiten nach ihrer Position.');

				// Verwende `usort`, um das `Page_Storage`-Array basierend auf der Position jeder Seite zu sortieren.
				// Die benutzerdefinierte Callback-Funktion vergleicht die Positionen zweier UI-Seiten-Objekte ($a und $b).
				usort($this->Page_Storage, function ($a, $b) {
					$position_a = $a->get_position();
					$position_b = $b->get_position();

					$this->get_logger()->debug('Vergleiche Seitenpositionen.', ['position_a' => $position_a, 'position_b' => $position_b]);

					// Ein optimierter Vergleichsoperator (<=> oder 'spaceship operator')
					// wäre hier elegant, wird aber nicht verwendet, um die Kompatibilität mit älteren PHP-Versionen
					// (vor PHP 7) zu gewährleisten. Die folgende Logik erfüllt denselben Zweck.
					if ($position_a < $position_b) {
						return -1; // $a kommt vor $b
					} else if ($position_a > $position_b) {
						return 1; // $a kommt nach $b
					} else {
						return 0; // Die Reihenfolge bleibt unverändert
					}
				});

				$this->get_logger()->info('Sortiervorgang der UI-Seiten abgeschlossen.');
			}

			/**
			 * Add a page to the UI (addPage())
			 */
			public function add_page(UI_Page $UI_Page): void
			{
				$this->get_logger()->info('Füge eine neue UI-Seite zum Menü hinzu.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'page_slug' => $UI_Page->get_slug(),
					'page_title' => $UI_Page->get_title(),
				]);

				// Speichere das UI_Page-Objekt im `Page_Storage`-Array, wobei der Slug als Schlüssel dient.
				$this->Page_Storage[$UI_Page->get_slug()] = $UI_Page;
				$this->get_logger()->debug('UI-Seite erfolgreich im Speicher abgelegt.');

				// Füge einen WordPress-Hook hinzu, um den Inhalt der Seite zu rendern.
				// Der Hook-Name ist dynamisch und enthält die Domain des UI-Managers.
				add_action(
					'forge12-plugin-content-' . $this->get_domain(),
					[$UI_Page, 'render_content'],
					10,
					2
				);
				$this->get_logger()->debug('Hook für Seiten-Inhalt registriert.', ['hook' => 'forge12-plugin-content-' . $this->get_domain()]);

				// Füge einen weiteren Hook hinzu, um die Sidebar der Seite zu rendern.
				add_action(
					'forge12-plugin-sidebar-' . $this->get_domain(),
					[$UI_Page, 'render_sidebar'],
					10,
					2
				);
				$this->get_logger()->debug('Hook für Seiten-Sidebar registriert.', ['hook' => 'forge12-plugin-sidebar-' . $this->get_domain()]);

				$this->get_logger()->info('UI-Seite erfolgreich hinzugefügt und Hooks registriert.');
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
				$this->get_logger()->info('Versuche, eine UI-Seite anhand ihres Slugs zu finden.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'slug' => $slug,
				]);

				// Überprüfe, ob der Slug im `Page_Storage`-Array existiert.
				if (!isset($this->Page_Storage[$slug])) {
					$this->get_logger()->warning('UI-Seite nicht gefunden.', ['requested_slug' => $slug]);
					return null;
				}

				$page = $this->Page_Storage[$slug];

				$this->get_logger()->info('UI-Seite erfolgreich gefunden und abgerufen.', [
					'slug' => $slug,
					'title' => $page->get_title(),
				]);

				// Gib das gefundene UI_Page-Objekt zurück.
				return $page;
			}

			/**
			 * Return the Storage of the Pages (getPages())
			 *
			 * @return UI_Page[]
			 */
			public function get_page_storage(): array
			{
				$this->get_logger()->info('Rufe das Array mit den gespeicherten UI-Seiten ab.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Gib das private Array `Page_Storage` zurück, das alle UI-Seiten-Objekte enthält.
				// Dies ermöglicht den Zugriff auf die registrierten Seiten von außerhalb der Klasse.
				$page_storage = $this->Page_Storage;

				$this->get_logger()->debug('Die Anzahl der gespeicherten Seiten beträgt ' . count($page_storage) . '.');

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
				$this->get_logger()->info('Rufe die Domain vom UI-Manager ab.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);

				// Rufe die get_ui_manager-Methode auf, um die Instanz des UI-Managers zu erhalten.
				$UI_Manager = $this->get_ui_manager();

				// Rufe dann die get_domain-Methode auf dieser Instanz auf.
				$domain = $UI_Manager->get_domain();

				$this->get_logger()->debug('Domain erfolgreich vom UI-Manager erhalten.', ['domain' => $domain]);

				return $domain;
			}
		}
	}
}