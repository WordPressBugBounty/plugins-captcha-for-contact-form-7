<?php

namespace f12_cf7_captcha\core\protection\rules;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
abstract class Rule {
	/**
	 * @var string
	 */
	protected $error_message = '';

	/**
	 * @var array<string>
	 */
	private $messages = [];
	private LoggerInterface $logger;

	/**
	 * @param $value
	 *
	 * @return bool
	 */
	public abstract function is_spam( $value );

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;

		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->get_logger()->info('Constructor completed.', [
			'class' => __CLASS__,
		]);
	}

	public function get_logger(): LoggerInterface
	{
		return $this->logger;
	}

	/**
	 * Adds a message to the list of messages.
	 *
	 * @param string $message The message to be added.
	 *
	 * @return void
	 */
	public function add_message(string $message): void
	{
		$this->get_logger()->info('Adding a new message to the message stack.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'message_content' => $message,
		]);

		$this->messages[] = $message;

		$this->get_logger()->debug('Message added successfully.', [
			'total_messages' => count($this->messages),
		]);
	}

	/**
	 * Retrieves all the messages as a string.
	 *
	 * @return string The messages joined by "<br/>".
	 */
	public function get_messages(): string
	{
		$this->get_logger()->info('Retrieving all collected messages and formatting for output.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'total_messages' => count($this->messages),
		]);

		if (empty($this->messages)) {
			$this->get_logger()->warning('No messages available to retrieve.');
			return '';
		}

		$formatted_messages = implode("<br/>", $this->messages);

		$this->get_logger()->debug('Messages successfully joined into HTML string.', [
			'formatted_string_length' => strlen($formatted_messages),
		]);

		return $formatted_messages;
	}

	/**
	 * Retrieves the error message.
	 *
	 * This method returns the error message as a string.
	 *
	 * @return string The error message.
	 */
	public function get_error_message(): string
	{
		$this->get_logger()->info('Retrieving current error message.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->error_message)) {
			$this->get_logger()->warning('Error message is empty.');
		} else {
			$this->get_logger()->debug('Error message retrieved successfully.', [
				'message' => $this->error_message,
			]);
		}

		return $this->error_message;
	}
}