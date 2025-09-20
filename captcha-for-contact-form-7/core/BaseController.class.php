<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use Forge12\Shared\LoggerInterface;

abstract class BaseController {
	/**
	 * Represents a variable to store the name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = '';

	/**
	 * @var string $description Description
	 */
	protected string $description = '';

	/**
	 * @var CF7Captcha|null The instance of the CF7 Controller
	 */
	protected ?CF7Captcha $Controller;
	/**
	 * @var Log_WordPress|null The instance of the logger used for logging messages.
	 */
	protected ?Log_WordPress $Logger;

	/**
	 * Constructor for the class.
	 *
	 * @param CF7Captcha    $Controller The CF7Captcha object that will be assigned to $this->Controller.
	 * @param Log_WordPress $Logger     The Log_WordPress object that will be assigned to $this->Logger.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller, Log_WordPress $Logger)
	{
		// Die Basisklasse des Controllers hat wahrscheinlich bereits eine Methode zum Abrufen des Loggers.
		// Daher ist es besser, diese zu verwenden, anstatt einen neuen Logger als Parameter zu übergeben.
		// Aber basierend auf dem bereitgestellten Code, passen wir die Implementierung an.

		$this->Controller = $Controller;
		$this->Logger = $Logger;

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		add_action('f12_cf7_captcha_compatibilities_loaded', array($this, 'wp_init'));
		$this->get_logger()->debug('Hook "f12_cf7_captcha_compatibilities_loaded" für die Methode "wp_init" hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

	/**
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->Controller->get_logger();
	}

	/**
	 * Get the name of the object.
	 *
	 * @return string The name of the object.
	 */
	public function get_name(): string
	{
		$this->get_logger()->debug('Rufe den Namen der Modul-Instanz ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'name' => $this->name,
		]);

		return $this->name;
	}

	/**
	 * Returns the description of the object.
	 *
	 * @return string The description of the object.
	 */
	public function get_description(): string
	{
		$this->get_logger()->debug('Rufe die Beschreibung des Moduls ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'description' => $this->description,
		]);

		return $this->description;
	}

	/**
	 * Get the ID of the instance.
	 *
	 * @return string The ID of the instance.
	 */
	public function get_id(): string
	{
		$this->get_logger()->debug('Rufe die ID des Objekts ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'id' => $this->id,
		]);

		return $this->id;
	}

	/**
	 * Initializes the WordPress plugin.
	 *
	 * This method checks if the plugin is enabled and then invokes the on_init() method.
	 *
	 * @return void
	 */
	public function wp_init(): void
	{
		$this->get_logger()->info('Führe die WordPress-Initialisierungsmethode aus.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Überprüfe, ob die Funktionalität über die Einstellungen aktiviert ist.
		if ($this->is_enabled()) {
			$this->get_logger()->debug('Funktionalität ist aktiviert. Starte die Initialisierung.');
			// Rufe die on_init-Methode auf, die die Hauptinitialisierungslogik enthält.
			$this->on_init();
		} else {
			$this->get_logger()->info('Funktionalität ist deaktiviert. Initialisierung wird übersprungen.');
		}

		$this->get_logger()->info('WordPress-Initialisierungsmethode abgeschlossen.');
	}

	protected abstract function on_init(): void;

	public abstract function is_enabled(): bool;
}