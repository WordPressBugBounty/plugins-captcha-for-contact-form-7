<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;

abstract class BaseModul {
	protected string $message = '';
	/**
	 * @var CF7Captcha
	 */
	protected CF7Captcha $Controller;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha controller object.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller) {
		$this->Controller = $Controller;
	}

	/**
	 * Retrieves the message stored in the object.
	 *
	 * @return string The message stored in the object.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Set the message.
	 *
	 * @param string $message The message to be set.
	 *
	 * @return void
	 */
	protected function set_message( string $message ): void {
		$this->message = $message;
	}
}