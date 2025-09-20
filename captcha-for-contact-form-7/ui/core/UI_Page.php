<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	abstract class UI_Page {
		/**
		 * @var UI_Manager|null
		 */
		protected $UI_Manager = null;
		/**
		 * @var string
		 */
		protected $domain;
		/**
		 * @var string
		 */
		protected $slug;
		/**
		 * @var string
		 */
		protected $title;
		/**
		 * @var string
		 */
		protected $class;
		/**
		 * @var int
		 */
		protected $position = 0;

		/**
		 * Constructor
		 *
		 * @param UI     $UI
		 * @param string $domain
		 */
		public function __construct( UI_Manager $UI_Manager, $slug, $title, $position = 10, $class = '' ) {
			$this->UI_Manager = $UI_Manager;
			$this->get_logger()->info( 'Konstruktor der UI-Seite gestartet.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'slug'     => $slug,
				'title'    => $title,
				'position' => $position,
			] );

			// Setze die Klassen-Eigenschaften mit den übergebenen Werten.
			$this->slug     = $slug;
			$this->title    = $title;
			$this->class    = $class;
			$this->position = $position;
			$this->get_logger()->debug( 'Eigenschaften der UI-Seite wurden gesetzt.' );

			// Füge einen Filter hinzu, um die Einstellungen der Seite zu laden.
			// Der Hook-Tag ist dynamisch und basiert auf der Domain des UI-Managers.
			add_filter(
				$UI_Manager->get_domain() . '_settings', // Name des Filters
				array( $this, 'get_settings' ), // Callback-Methode dieser Klasse
				10, // Priorität des Filters
				1  // Anzahl der erwarteten Argumente (hier das $settings-Array)
			);
			$this->get_logger()->debug( 'Filter "ui_settings" hinzugefügt.', [ 'hook' => $UI_Manager->get_domain() . '_settings' ] );

			$this->get_logger()->info( 'Konstruktor abgeschlossen.' );
		}

		public function get_logger(): LoggerInterface {
			return $this->UI_Manager->get_logger();
		}

		protected function get_ui_manager(): UI_Manager {
			return $this->UI_Manager;
		}

		public function hide_in_menu(): bool {
			$this->get_logger()->info( 'Überprüfe, ob die UI-Seite im Menü versteckt werden soll.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Diese Methode gibt standardmäßig 'false' zurück, was bedeutet, dass
			// die Seite im WordPress-Admin-Menü sichtbar sein sollte.
			$should_hide = false;
			$this->get_logger()->debug( 'Die Seite wird im Menü angezeigt.', [ 'result' => $should_hide ] );

			return $should_hide;
		}

		public function get_position() {
			$this->get_logger()->info( 'Rufe die Menüposition der UI-Seite ab.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'position' => $this->position,
			] );

			// Gib die gespeicherte Position zurück.
			return $this->position;
		}

		public function is_dashboard(): bool {
			$this->get_logger()->info( 'Überprüfe, ob die Seite das Dashboard ist.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'position' => $this->get_position(),
			] );

			// Die Methode get_position() liefert die Position im Menü.
			// Standardmäßig wird das Dashboard an der Position 0 registriert.
			$is_dashboard = $this->get_position() === 0;

			$this->get_logger()->debug( 'Ergebnis der Dashboard-Überprüfung.', [
				'is_dashboard' => $is_dashboard,
			] );

			// Gib einen booleschen Wert zurück, der angibt, ob die Seite das Dashboard ist.
			return $is_dashboard;
		}

		public function get_domain(): string {
			$this->get_logger()->info( 'Rufe die Domain vom UI-Manager ab.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Rufe die get_ui_manager-Methode auf, um die Instanz des UI-Managers zu erhalten.
			$ui_manager = $this->get_ui_manager();

			// Rufe dann die get_domain-Methode auf dieser Instanz auf.
			$domain = $ui_manager->get_domain();

			$this->get_logger()->debug( 'Domain erfolgreich vom UI-Manager erhalten.', [ 'domain' => $domain ] );

			return $domain;
		}

		public function get_slug(): string {
			$this->get_logger()->info( 'Rufe den Slug der UI-Seite ab.', [
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'slug'   => $this->slug,
			] );

			// Gib den gespeicherten Slug zurück.
			return $this->slug;
		}

		public function get_title(): string {
			return $this->title;
		}

		public function get_class(): string {
			$this->get_logger()->info( 'Rufe die CSS-Klasse der UI-Seite ab.', [
				'plugin' => 'f12-cf7-captcha',
				'class'      => __CLASS__,
				'method'     => __METHOD__,
				'class_name' => $this->class,
			] );

			// Gib den gespeicherten Klassennamen zurück.
			return $this->class;
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public abstract function get_settings( $settings );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function the_sidebar( $slug, $page );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function the_content( $slug, $page, $settings );

		/**
		 * @return UI_Message
		 */
		private function get_ui_message(): UI_Message {
			return $this->get_ui_manager()->get_ui_message();
		}

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function render_content( string $slug, string $page ): void {
			$this->get_logger()->info( 'Starte das Rendering des Seiteninhalts.', [
				'plugin' => 'f12-cf7-captcha',
				'class'          => __CLASS__,
				'method'         => __METHOD__,
				'requested_slug' => $slug,
				'page_slug'      => $page,
				'expected_slug'  => $this->slug,
			] );

			// Überprüfe, ob der übergebene Seiten-Slug mit dem der aktuellen Seite übereinstimmt.
			// Wenn nicht, wird das Rendering abgebrochen.
			if ( $this->slug !== $page ) {
				$this->get_logger()->debug( 'Die angeforderte Seite stimmt nicht mit der aktuellen überein. Rendering wird übersprungen.' );

				return;
			}

			$this->get_logger()->info( 'Rendering-Prozess gestartet. Hole die Einstellungen und rendere die Nachrichten.' );

			// Rufe die globalen Einstellungen über einen Filter ab.
			// So können andere Module ihre Standardeinstellungen hinzufügen.
			$settings = apply_filters( $this->get_domain() . '_get_settings', [] );
			$this->get_logger()->debug( 'Einstellungen über Filter abgerufen.' );

			// Rende die UI-Nachrichten (z.B. Erfolgs- oder Fehlermeldungen).
			$this->get_ui_message()->render();

			// Löse einen Hook aus, der vor dem Box-Container liegt.
			do_action( $this->get_domain() . '_ui_' . $page . '_before_box' );
			$this->get_logger()->debug( 'Hook "before_box" ausgelöst.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_before_box' ] );

			?>
            <div class="box">
				<?php
				// Löse einen Hook aus, der vor dem Hauptinhalt der Seite liegt.
				do_action( $this->get_domain() . '_ui_' . $page . '_before_content', $settings );
				$this->get_logger()->debug( 'Hook "before_content" ausgelöst.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_before_content' ] );

				// Rende den eigentlichen Inhalt der Seite.
				$this->the_content( $slug, $page, $settings );

				// Löse einen Hook aus, der nach dem Hauptinhalt liegt.
				do_action( $this->get_domain() . '_ui_' . $page . '_after_content', $settings );
				$this->get_logger()->debug( 'Hook "after_content" ausgelöst.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_after_content' ] );
				?>
            </div>
			<?php
			// Löse einen Hook aus, der nach dem Box-Container liegt.
			do_action( $this->get_domain() . '_ui_' . $page . '_after_box' );
			$this->get_logger()->debug( 'Hook "after_box" ausgelöst.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_after_box' ] );

			$this->get_logger()->info( 'Rendering des Seiteninhalts abgeschlossen.' );
		}

		/**
		 * @param string $slug
		 * @param string $page
		 *
		 * @return void
		 * @private WordPress Hook
		 */
		public function render_sidebar( $slug, $page ) {
			if ( $this->slug != $page ) {
				return;
			}
			$this->the_sidebar( $slug, $page );
		}
	}
}