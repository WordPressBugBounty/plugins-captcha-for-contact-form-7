<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;

/**
 * Hotfix for compatibility issues if the same Class is loaded by another plugin.
 */
require_once('BaseController.class.php');
abstract class Validator extends \f12_cf7_captcha\core\BaseController
{
	public function __construct(CF7Captcha $Controller = null, Log_WordPress $Logger = null)
	{
		// Die Logger-Eigenschaft wird von der Elternklasse initialisiert.
		// Daher muss hier nur der Controller initialisiert werden.
		$this->Controller = $Controller;

		// Protokollierung des Konstruktorstarts.
		$this->get_logger()->info('Konstruktor mit optionalen Abhängigkeiten gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Wenn kein Controller übergeben wurde, hole die Singleton-Instanz.
		if (null === $Controller) {
			$Controller = CF7Captcha::get_instance();
			$this->get_logger()->debug('Keine Controller-Instanz übergeben. Singleton-Instanz abgerufen.');
		}

		// Wenn kein Logger übergeben wurde, hole die Singleton-Instanz.
		if (null === $Logger) {
			$Logger = Log_WordPress::get_instance();
			$this->get_logger()->debug('Keine Logger-Instanz übergeben. Singleton-Instanz abgerufen.');
		}

		// Übergabe der abhängigen Objekte an den Konstruktor der Elternklasse.
		parent::__construct($Controller, $Logger);

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

    public abstract function is_spam(): bool;

    public abstract function wp_is_spam(...$args);

    public abstract function wp_add_spam_protection(...$args);

    public abstract function wp_submitted(...$args);

    protected abstract function get_field_name(): string;

}