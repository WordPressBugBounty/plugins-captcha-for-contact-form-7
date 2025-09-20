<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_Asset_Handler' ) ) {
		/**
		 * Handles all Assets required for the UI
		 */
		class UI_Asset_Handler {
			/**
			 * @var array $Script_Storage Store all scripts loaded
			 */
			private $Script_Storage = [];

			/**
			 * @var array $Style_Storage Store all styles laoded
			 */
			private $Style_Storage = [];

			/**
			 * @var ?UI_Manager $UI_Manager
			 */
			private $UI_Manager = null;

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				$this->UI_Manager = $UI_Manager;

				$this->get_logger()->info( 'Konstruktor der UI-Seite gestartet.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				] );

				// Füge Hooks hinzu, um Skripte und Stile im WordPress-Adminbereich zu laden.
				add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
				$this->get_logger()->debug( 'Hook "admin_enqueue_scripts" für das Laden der Skripte hinzugefügt.' );

				add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
				$this->get_logger()->debug( 'Hook "admin_enqueue_scripts" für das Laden der Stile hinzugefügt.' );

				// Registriere die Standard-Styles.
				$this->register_style(
					'f12-ui-admin-styles',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-style.css'
				);
				$this->get_logger()->debug( 'CSS-Datei "f12-ui-admin-styles" registriert.' );

				// Registriere die Standard-Skripte.
				$this->register_script(
					'f12-ui-admin-toggle',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/toggle.js',
					array( 'jquery' ),
					'1.0'
				);
				$this->get_logger()->debug( 'JavaScript-Datei "f12-ui-admin-toggle" registriert.' );

				$this->register_script(
					'f12-ui-admin-clipboard',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/copy-to-clipboard.js',
					array( 'jquery' ),
					'1.0'
				);
				$this->get_logger()->debug( 'JavaScript-Datei "f12-ui-admin-clipboard" registriert.' );

				$this->register_script(
					'f12-ui-admin',
					$UI_Manager->get_plugin_dir_url() . 'ui/assets/admin-captcha.js',
					[ 'jquery' ]
				);
				$this->get_logger()->debug( 'JavaScript-Datei "f12-ui-admin" registriert.' );

				$this->get_logger()->info( 'Konstruktor der UI-Seite abgeschlossen. Stile und Skripte sind für die Registrierung bereit.' );
			}

			public function get_logger(): LoggerInterface {
				return $this->UI_Manager->get_logger();
			}

			/**
			 * Use to register a custom script for the UI
			 *
			 * @param string $handle
			 * @param string $src
			 * @param array  $deps
			 * @param        $ver
			 * @param bool   $in_footer
			 *
			 * @return void
			 */
			public function register_script(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void
			{
				$this->get_logger()->info('Registriere ein neues Skript für das Enqueue.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'handle' => $handle,
					'src' => $src,
				]);

				// Definiere die Skript-Daten in einem assoziativen Array.
				$script_data = [
					'handle' => $handle,
					'src' => $src,
					'deps' => $deps,
					'ver' => $ver,
					'in_footer' => $in_footer
				];

				// Füge die Skript-Daten dem internen Speicher hinzu.
				$this->Script_Storage[] = $script_data;

				$this->get_logger()->info('Skript-Daten erfolgreich im Speicher abgelegt.');
				$this->get_logger()->debug('Gespeicherte Skript-Daten:', $script_data);
			}

			/**
			 * Use to register a custom style for the UI
			 *
			 * @param string $handle
			 * @param string $src
			 * @param array  $deps
			 * @param        $ver
			 * @param string $media
			 *
			 * @return void
			 */
			public function register_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
			{
				$this->get_logger()->info('Registriere einen neuen Stil für das Enqueue.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
					'handle' => $handle,
					'src' => $src,
				]);

				// Definiere die Stil-Daten in einem assoziativen Array.
				$style_data = [
					'handle' => $handle,
					'src' => $src,
					'deps' => $deps,
					'ver' => $ver,
					'media' => $media,
				];

				// Füge die Stil-Daten dem internen Speicher hinzu.
				$this->Style_Storage[] = $style_data;

				$this->get_logger()->info('Stil-Daten erfolgreich im Speicher abgelegt.');
				$this->get_logger()->debug('Gespeicherte Stil-Daten:', $style_data);
			}

			/**
			 * Load all registered scripts
			 *
			 * @return void
			 */
			public function load_scripts()
			{
				$this->get_logger()->info('Starte den Enqueue-Prozess für alle gespeicherten Skripte.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Durchlaufe alle im `Script_Storage`-Array gespeicherten Skripte.
				foreach ($this->Script_Storage as $script) {
					$this->get_logger()->debug('Füge Skript zur Ladeliste hinzu.', [
						'handle' => $script['handle'],
						'src'    => $script['src'],
					]);

					// Rufe die WordPress-Funktion `wp_enqueue_script()` auf, um das Skript zu laden.
					// Die Parameter werden direkt aus dem `script`-Array übernommen.
					wp_enqueue_script(
						$script['handle'],
						$script['src'],
						$script['deps'],
						$script['ver'],
						$script['in_footer']
					);
				}

				$this->get_logger()->info('Alle Skripte wurden für das Laden in WordPress in die Warteschlange gestellt.');
			}

			/**
			 * Load all registered styles
			 *
			 * @return void
			 */
			public function load_styles()
			{
				$this->get_logger()->info('Starte den Enqueue-Prozess für alle gespeicherten Stile.', [
					'class'  => __CLASS__,
					'method' => __METHOD__,
				]);

				// Durchlaufe alle im `Style_Storage`-Array gespeicherten Stil-Definitionen.
				foreach ($this->Style_Storage as $style) {
					$this->get_logger()->debug('Füge Stil zur Ladeliste hinzu.', [
						'handle' => $style['handle'],
						'src'    => $style['src'],
					]);

					// Rufe die WordPress-Funktion `wp_enqueue_style()` auf, um das Stylesheet zu laden.
					// Die Parameter werden direkt aus dem `style`-Array übernommen.
					wp_enqueue_style(
						$style['handle'],
						$style['src'],
						$style['deps'],
						$style['ver'],
						$style['media']
					);
				}

				$this->get_logger()->info('Alle Stile wurden für das Laden in WordPress in die Warteschlange gestellt.');
			}

		}
	}
}