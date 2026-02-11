<?php

namespace f12_cf7_captcha\core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use f12_cf7_captcha\CF7Captcha;

abstract class Validator extends \f12_cf7_captcha\core\BaseController
{
	public function __construct(CF7Captcha $Controller = null, Log_WordPress_Interface $Logger = null)
	{
		// The Logger property is initialized by the parent class.
		// Therefore, only the Controller needs to be initialized here.
		$this->Controller = $Controller;

		// Log the start of the constructor.
		$this->get_logger()->info('Constructor with optional dependencies started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// If no controller was passed, get the singleton instance.
		if (null === $Controller) {
			$Controller = CF7Captcha::get_instance();
			$this->get_logger()->debug('No controller instance provided. Singleton instance retrieved.');
		}

		// If no logger was passed, get the singleton instance.
		if (null === $Logger) {
			$Logger = Log_WordPress::get_instance();
			$this->get_logger()->debug('No logger instance provided. Singleton instance retrieved.');
		}

		// Pass the dependent objects to the parent class constructor.
		parent::__construct($Controller, $Logger);

		$this->get_logger()->info('Constructor completed.');
	}

    public abstract function is_spam(): bool;

    public abstract function wp_is_spam(...$args);

    public abstract function wp_add_spam_protection(...$args);

    public abstract function wp_submitted(...$args);

    protected abstract function get_field_name(): string;

}