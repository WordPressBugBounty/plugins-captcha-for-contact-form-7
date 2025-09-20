<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Messages
	 */
	class UI_Message {
		private $UI_Manager;

		/**
		 * @var array
		 */
		private $messages = [];

		public function __construct(UI_Manager $UI_Manager)
		{
			// Setze die UI_Manager-Instanz über eine private/protected Methode.
			// Dies stellt sicher, dass die Logik für das Setzen der Eigenschaft gekapselt ist.
			$this->set_ui_manager($UI_Manager);
			$this->get_logger()->debug('UI_Manager-Instanz wurde gesetzt.');

			$this->get_logger()->info('Konstruktor abgeschlossen.');
		}

		public function get_logger(): LoggerInterface {
			return $this->UI_Manager->get_logger();
		}

		private function set_ui_manager( UI_Manager $UI_Manager ) {
			$this->UI_Manager = $UI_Manager;
		}

		public function get_ui_manager(): UI_Manager
		{
			$this->get_logger()->info('Rufe die UI_Manager-Instanz ab.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);

			// Gib die privat gespeicherte UI_Manager-Instanz zurück.
			$ui_manager = $this->UI_Manager;

			if (!$ui_manager instanceof UI_Manager) {
				$this->get_logger()->critical('Die UI_Manager-Instanz ist nicht verfügbar oder vom falschen Typ.', [
					'type' => gettype($this->UI_Manager)
				]);
				// Optional: Hier könnte eine Ausnahme ausgelöst werden, wenn die Instanz unbedingt benötigt wird.
			}

			$this->get_logger()->debug('UI_Manager-Instanz erfolgreich abgerufen.');

			return $ui_manager;
		}

		/**
		 * getAll function.
		 *
		 * @access public
		 * @return void
		 */
		public function render(): void
		{
			$this->get_logger()->info('Starte das Rendering aller gespeicherten Nachrichten.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);

			// Durchlaufe alle im `messages`-Array gespeicherten HTML-Nachrichten.
			foreach ($this->messages as $key => $value) {
				$this->get_logger()->debug('Rendere Nachricht.', ['key' => $key, 'message_length' => strlen($value)]);

				// Verwende wp_kses(), um die Nachricht vor Cross-Site-Scripting (XSS) zu schützen.
				// Die erlaubten HTML-Tags sind 'div' mit den Attributen 'class' und 'role'.
				// PHP_EOL stellt sicher, dass jede Nachricht in einer neuen Zeile ausgegeben wird,
				// was die Lesbarkeit des generierten HTML-Quellcodes verbessert.
				echo wp_kses($value, [
						'div' => [
							'class' => [],
							'role'  => []
						]
					]) . PHP_EOL;
			}

			$this->get_logger()->info('Rendering aller Nachrichten abgeschlossen.');
		}

		/**
		 * add function.
		 *
		 * @access public
		 *
		 * @param mixed $message
		 * @param mixed $type
		 *
		 * @return void
		 */
		public function add(string $message, string $type): void
		{
			$this->get_logger()->info('Füge eine neue UI-Nachricht hinzu.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'type' => $type,
			]);

			// Definiere die Zuordnung von Nachrichtentypen zu CSS-Klassen in einem Array.
			// Dies ist effizienter und leichter zu pflegen als eine lange elseif-Kette.
			$type_map = [
				'error'    => 'alert-danger',
				'success'  => 'alert-success',
				'info'     => 'alert-info',
				'warning'  => 'alert-warning',
				'offer'    => 'alert-offer',
				'critical' => 'alert-critical',
			];

			// Verwende den Null Coalescing Operator, um den Typ zuzuordnen.
			// Wenn der übergebene $type in der Map nicht existiert, wird der ursprüngliche Wert verwendet.
			$css_class = $type_map[$type] ?? $type;

			$this->get_logger()->debug('CSS-Klasse für Nachrichtentyp ermittelt.', [
				'original_type' => $type,
				'css_class'     => $css_class,
			]);

			// Erstelle den HTML-String.
			// Die WordPress-Funktionen esc_attr() und esc_html() sind wichtig,
			// um Cross-Site-Scripting (XSS) zu verhindern.
			$html_message = sprintf(
				'<div class="box %s" role="alert">%s</div>',
				esc_attr($css_class),
				esc_html($message)
			);

			// Füge die generierte HTML-Nachricht dem Nachrichten-Array hinzu.
			$this->messages[] = $html_message;

			$this->get_logger()->info('Nachricht erfolgreich dem Nachrichten-Array hinzugefügt.');
		}
	}
}