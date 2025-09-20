<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use Forge12\Shared\LoggerInterface;

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

		$this->get_logger()->info('Konstruktor abgeschlossen.');
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
		$this->get_logger()->debug('Rufe die Nachrichten-Eigenschaft ab.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'message' => $this->message,
		]);

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
		$this->get_logger()->info('Setze die Nachrichteneigenschaft.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'old_message' => $this->message,
			'new_message' => $message,
		]);

		$this->message = $message;

		$this->get_logger()->debug('Nachricht erfolgreich gesetzt.');
	}
}