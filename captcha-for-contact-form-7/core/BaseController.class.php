<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;

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
	public function __construct( CF7Captcha $Controller, Log_WordPress $Logger ) {
		$this->Controller = $Controller;
		$this->Logger     = $Logger;

		add_action( 'f12_cf7_captcha_compatibilities_loaded', array( $this, 'wp_init' ) );
	}

	/**
	 * Get the name of the object.
	 *
	 * @return string The name of the object.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Returns the description of the object.
	 *
	 * @return string The description of the object.
	 */
	public function get_description():string{
		return $this->description;
	}

	/**
	 * Get the ID of the instance.
	 *
	 * @return string The ID of the instance.
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Initializes the WordPress plugin.
	 *
	 * This method checks if the plugin is enabled and then invokes the on_init() method.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		if ( $this->is_enabled() ) {
			$this->on_init();
		}
	}

	protected abstract function on_init(): void;

	public abstract function is_enabled(): bool;
}