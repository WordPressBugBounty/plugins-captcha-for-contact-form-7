<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	public function __construct(CF7Captcha $Controller)
	{
		$this->Controller = $Controller;

		if (f12_is_debug()) {
			$this->get_logger()->info('Constructor completed.');
		}
	}

	public function get_logger() : LoggerInterface {
		return $this->Controller->get_logger();
	}

	/**
	 * Retrieves the message stored in the object.
	 *
	 * @return string The message stored in the object.
	 */
	public function get_message(): string
	{
		if (f12_is_debug()) {
			$this->get_logger()->debug('Retrieving message property.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'message' => $this->message,
			]);
		}

		return $this->message;
	}

	/**
	 * Set the message.
	 *
	 * @param string $message The message to be set.
	 *
	 * @return void
	 */
	protected function set_message(string $message): void
	{
		if (f12_is_debug()) {
			$this->get_logger()->info('Setting message property.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'old_message' => $this->message,
				'new_message' => $message,
			]);
		}

		$this->message = $message;

		if (f12_is_debug()) {
			$this->get_logger()->debug('Message set successfully.');
		}
	}

	/**
	 * Get a protection setting resolved through the current context hierarchy.
	 *
	 * Falls back to reading from global settings if no context is set on Protection.
	 *
	 * @param string $key The setting key.
	 *
	 * @return mixed
	 */
	protected function get_protection_setting( string $key ) {
		try {
			/** @var \f12_cf7_captcha\core\protection\Protection $protection */
			$protection = $this->Controller->get_module( 'protection' );
			return $protection->get_setting( $key );
		} catch ( \Throwable $e ) {
			// Fallback to direct global read if Protection module not yet available
			return $this->Controller->get_settings( $key, 'global' );
		}
	}
}