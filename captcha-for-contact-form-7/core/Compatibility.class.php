<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Compatibility
 *
 * This class represents the compatibility module for CF7Captcha.
 * It loads and registers components from a given directory recursively.
 *
 */
class Compatibility extends BaseModul {
	/**
	 * @var array<string, string>
	 */
	private $components = array();
	/**
	 * @var Log_WordPress
	 */
	private Log_WordPress $Logger;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param CF7Captcha    $Controller The CF7Captcha object.
	 * @param Log_WordPress $Logger     The Log_WordPress object.
	 */
	public function __construct(CF7Captcha $Controller, Log_WordPress $Logger)
	{
		parent::__construct($Controller);

		// Protokollierung der Instanziierung.
		// Anmerkung: Die Logger-Instanz wird hier direkt vom Konstruktor-Parameter übernommen.
		// Dies kann zu Problemen führen, wenn parent::__construct() auch einen Logger setzt.
		// Es ist besser, eine konsistente Methode zur Logger-Verwaltung zu verwenden.
		$this->Logger = $Logger;
		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Lade die Kompatibilitäts-Dateien aus dem angegebenen Verzeichnis.
		// Der zweite Parameter '0' deutet an, dass die Unterverzeichnisse nicht rekursiv durchsucht werden.
		$this->load(dirname(dirname(__FILE__)) . '/compatibility', 0);
		$this->get_logger()->debug('Kompatibilitäten geladen.');

		// Füge einen anonymen Hook zu 'after_setup_theme' hinzu.
		add_action('after_setup_theme', function () {

			// Füge einen weiteren Hook hinzu, der die Methode 'wp_register_components' aufruft.
			add_action('f12_cf7_captcha_ui_after_load_compatibilities', array(
				$this,
				'wp_register_components'
			), 10, 1);
			$this->get_logger()->debug('Hook "f12_cf7_captcha_ui_after_load_compatibilities" für die Komponentenregistrierung hinzugefügt.');

			// Löst die 'f12_cf7_captcha_ui_after_load_compatibilities'-Aktion aus.
			// Dies ermöglicht es Entwicklern, eigene Kompatibilitäts-Hooks hinzuzufügen.
			do_action('f12_cf7_captcha_ui_after_load_compatibilities', $this);
			$this->get_logger()->debug('Aktion "f12_cf7_captcha_ui_after_load_compatibilities" ausgelöst.');

			// Löst die 'f12_cf7_captcha_compatibilities_loaded'-Aktion aus.
			// Signalisiert den Validatoren, dass die Kompatibilitäten geladen wurden.
			do_action('f12_cf7_captcha_compatibilities_loaded');
			$this->get_logger()->debug('Aktion "f12_cf7_captcha_compatibilities_loaded" ausgelöst.');
		});
		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

	/**
	 * Retrieves the registered components.
	 *
	 * @formatter:off
     *
     * @return array {
     *      The array of registered components as another array
     *
     *      @type array {
     *          The Array containing the information about the components
     *
     *          @type string            $name   The Name of the Controller & Namespace
     *          @type string            $path   The Path to the Controller
     *          @type BaseController    $object The instance of the controller
     *      }
     * }
     *
     * @formatter:on
	 */
	public function get_components(): array
	{
		$this->get_logger()->info('Rufe die registrierten Komponenten ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Zähle die Anzahl der Komponenten und gib sie im Debug-Log aus.
		$component_count = count($this->components);
		$this->get_logger()->debug("Es wurden {$component_count} Komponenten gefunden.");

		return $this->components;
	}

	public function get_active_component_names(): array {
		$active = [];

		foreach ($this->components as $name => $component) {
			if (!isset($component['object']) || !$component['object'] instanceof BaseController) {
				continue;
			}

			$object = $component['object'];

			try {
				// Prüfe über is_enabled(), falls die Methode existiert
				if (method_exists($object, 'is_enabled') && $object->is_enabled()) {
					$active[] = basename(str_replace('\\', '/', $name));
				}
			} catch (\Throwable $e) {
				// Falls ein Controller Fehler wirft (z. B. fehlendes Plugin), logge das und überspringe ihn
				$this->get_logger()->warning(
					sprintf('Fehler beim Prüfen von is_enabled() in %s: %s', $name, $e->getMessage()),
					['file' => $e->getFile(), 'line' => $e->getLine()]
				);
			}
		}

		return $active;
	}


	/**
	 * Get a component by name.
	 *
	 * This method is used to retrieve a component by its name from the components array.
	 *
	 * @param string $name The name of the component to retrieve.
	 *
	 * @return BaseController The retrieved component if found, or null if not found.
	 */
	public function get_component(string $name): BaseController
	{
		$this->get_logger()->info('Versuche, eine Komponente nach ihrem Namen abzurufen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'requested_component' => $name,
		]);

		// Überprüfe, ob die Komponente überhaupt existiert.
		if (!isset($this->components[$name])) {
			$available_components = implode(", ", array_keys($this->components));
			$error_message = sprintf('Komponente nicht gefunden: %s. Verfügbare Komponenten: %s', $name, $available_components);

			$this->get_logger()->error($error_message);
			throw new RuntimeException($error_message);
		}

		// Überprüfe, ob die Komponente bereits instanziiert wurde.
		if (!isset($this->components[$name]['object'])) {
			$error_message = sprintf('Komponente "%s" wurde noch nicht initialisiert.', $name);

			$this->get_logger()->error($error_message);
			throw new RuntimeException($error_message);
		}

		$this->get_logger()->info('Komponente erfolgreich abgerufen.', [
			'component_name' => $name,
		]);

		return $this->components[$name]['object'];
	}

	/**
	 * Registers components.
	 *
	 * @param Compatibility $Compatibility The Compatibility object.
	 *
	 * @throws RuntimeException If a component is not initialized correctly.
	 */
	public function wp_register_components(Compatibility $Compatibility): void
	{
		$this->get_logger()->info('Starte die Registrierung der Kompatibilitätskomponenten.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		foreach ($this->components as $key => $component) {
			// Sicherstellen, dass die notwendigen Array-Schlüssel existieren, bevor darauf zugegriffen wird.
			if (!isset($component['name']) || !isset($component['path'])) {
				$error_message = sprintf(
					'Komponenten-Schlüssel: %s, Name: %s, Pfad: %s nicht korrekt initialisiert.',
					$key,
					$component['name'] ?? 'nicht definiert', // Verwende Null Coalescing Operator für sicheren Zugriff
					$component['path'] ?? 'nicht definiert'
				);
				$this->get_logger()->error($error_message);
				throw new \RuntimeException($error_message);
			}

			$this->get_logger()->debug('Registriere Komponente.', ['name' => $component['name'], 'path' => $component['path']]);

			try {
				// Lade die Datei der Komponente
				require_once($component['path']);

				// Instanziiere die Komponente und speichere das Objekt
				$this->components[$key]['object'] = new $component['name']($this->Controller, $this->Logger);
				$this->get_logger()->info('Komponente erfolgreich instanziiert.', ['name' => $component['name']]);

			} catch (\Throwable $e) {
				$error_message = sprintf('Fehler beim Laden oder Instanziieren der Komponente "%s".', $component['name']);
				$this->get_logger()->critical($error_message, [
					'error' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				]);
				// Ein kritischer Fehler, der die Ausführung beenden sollte, um weitere Probleme zu vermeiden.
				throw new \RuntimeException($error_message);
			}
		}

		$this->get_logger()->info('Registrierung aller Kompatibilitätskomponenten abgeschlossen.');
	}

	/**
	 * Load components from a directory recursively.
	 *
	 * This method is used to load components from a directory recursively.
	 * It searches for files matching the pattern Controller[a-zA-Z_0-9]+.class.php
	 * and adds them to the components array.
	 *
	 * @param string $directory The directory to load components from.
	 * @param int    $lvl       The current level of recursion.
	 *
	 * @return void
	 * @throws \RuntimeException If the directory does not exist or is not readable.
	 *
	 */
	private function load($directory, $lvl)
	{
		$this->get_logger()->info('Starte den Ladevorgang für Komponenten in einem Verzeichnis.', [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'directory' => $directory,
			'level'     => $lvl,
		]);

		// Überprüfe, ob das Verzeichnis existiert.
		if (!is_dir($directory)) {
			$error_message = sprintf('Verzeichnis %s existiert nicht.', $directory);
			$this->get_logger()->error($error_message);
			throw new \RuntimeException($error_message);
		}

		// Versuche, das Verzeichnis zu öffnen.
		$handle = @opendir($directory); // Nutze @, um PHP-Warnungen zu unterdrücken, wenn opendir fehlschlägt.

		if ($handle === false) {
			$error_message = sprintf('Verzeichnis %s ist nicht lesbar.', $directory);
			$this->get_logger()->error($error_message);
			throw new \RuntimeException($error_message);
		}

		$this->get_logger()->debug('Verzeichnis erfolgreich geöffnet.');

		// Iteriere durch die Einträge im Verzeichnis.
		while (false !== ($entry = readdir($handle))) {
			// Überspringe die '.' und '..'-Einträge.
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$current_directory = $directory . '/' . $entry;

			// Wenn der Eintrag ein Unterverzeichnis ist und der Level 0 ist, lade rekursiv.
			if (is_dir($current_directory) && $lvl === 0) {
				$this->get_logger()->debug('Wechsle in Unterverzeichnis.', ['subdir' => $current_directory]);
				$this->load($current_directory, $lvl + 1);
				continue;
			}

			// Finde Dateien, die dem Namensmuster 'Controller[Name].class.php' entsprechen.
			if (!preg_match('!Controller([a-zA-Z_0-9]+)\.class\.php!', $entry, $matches)) {
				$this->get_logger()->debug('Datei entspricht nicht dem Namensmuster.', ['file' => $entry]);
				continue;
			}

			// Stelle sicher, dass der zweite Match-Treffer existiert.
			if (!isset($matches[1])) {
				$this->get_logger()->warning('Kein Klassenname im Dateinamen gefunden.', ['file' => $entry]);
				continue;
			}

			$class_name_part = $matches[1];

			// Bestimme den vollständigen Namespace für die Klasse.
			// Der Namespace sollte abhängig vom Pfad korrekt gebildet werden.
			$namespace = 'f12_cf7_captcha\\compatibility';
			/*if ($lvl > 0) {
				// Wenn in einem Unterverzeichnis, füge den Namen des Unterverzeichnisses zum Namespace hinzu.
				$sub_dir_name = basename($directory);
				$namespace .= '\\' . $sub_dir_name;
			}*/

			$name = '\\' . $namespace . '\\Controller' . $class_name_part;

			$this->get_logger()->debug('Komponente zur Registrierung hinzugefügt.', [
				'class_name' => $name,
				'file_path'  => $current_directory,
			]);

			// Füge die Komponente zur Liste hinzu.
			$this->components[$name] = [
				'name' => $name,
				'path' => $current_directory
			];
		}

		closedir($handle);
		$this->get_logger()->info('Ladevorgang abgeschlossen.');
	}
}