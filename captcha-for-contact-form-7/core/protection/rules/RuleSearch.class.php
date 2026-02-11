<?php

namespace f12_cf7_captcha\core\protection\rules;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
class RuleSearch extends Rule {
	/**
	 * @var array
	 */
	private $words = [];

	/**
	 * Greedy or Non Greedy search
	 */
	private $greedy = 1;

	/**
	 * Pre-compiled regex patterns for performance.
	 *
	 * @var array<string, string>
	 */
	private array $compiled_patterns = [];

	/**
	 * Constructor method for the class.
	 *
	 * @param mixed  $words         The input words to be processed.
	 * @param string $error_message (Optional) The error message to be displayed in case of any errors. Default is
	 *                              empty string.
	 * @param int    $greedy        (Optional) Flag to indicate whether to match all words or only the first one.
	 *                              Default 1 (greedy).
	 *
	 * @return void
	 */
	public function __construct(LoggerInterface $logger, array $words, string $error_message = '', int $greedy = 1)
	{
		parent::__construct($logger);

		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->error_message = $error_message;
		$this->words = $words;
		$this->greedy = $greedy;

		// Pre-compile regex patterns for all words
		$this->compile_patterns();

		$this->get_logger()->debug('Initializing profanity filter.', [
			'words_count' => count($this->words),
			'greedy_level' => $this->greedy,
			'patterns_compiled' => count($this->compiled_patterns),
		]);

		$this->get_logger()->info('Constructor completed.');
	}

	/**
	 * Pre-compiles regex patterns for all words.
	 *
	 * Compiling patterns once in the constructor avoids
	 * repeated regex compilation during validation.
	 *
	 * @return void
	 */
	private function compile_patterns(): void {
		foreach ( $this->words as $word ) {
			if ( empty( $word ) ) {
				continue;
			}

			$quoted = preg_quote( $word, '!' );

			if ( $this->greedy == 1 ) {
				// "Greedy" mode: Matches word even when attached to other characters
				$this->compiled_patterns[ $word ] = "!([^a-zA-Z0-9]+|^)" . $quoted . "([a-zA-Z0-9]+|$)!i";
			} else {
				// "Non-Greedy" mode: Matches only whole words
				$this->compiled_patterns[ $word ] = "!(^|\s)" . $quoted . "(\s|$)!i";
			}
		}
	}

	/**
	 * Determines if a given value is considered spam based on a list of words.
	 *
	 * Uses pre-compiled regex patterns for improved performance.
	 *
	 * @param string $value The input value to be checked for spam.
	 *
	 * @return bool Returns true if the value is considered spam, otherwise false.
	 */
	public function is_spam($value): bool
	{
		$this->get_logger()->info('Starting spam check based on profanity lists.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'input_type' => is_array($value) ? 'array' : 'string',
		]);

		// Recursive check if value is an array
		if (is_array($value)) {
			foreach ($value as $item) {
				if ($this->is_spam($item)) {
					$this->get_logger()->debug('Spam element found in array.');
					return true;
				}
			}
			$this->get_logger()->debug('No spam elements found in array.');
			return false;
		}

		$error_message = $this->get_error_message();
		$mode = $this->greedy == 1 ? 'Greedy' : 'Non-Greedy';

		$this->get_logger()->debug("Checking in \"{$mode}\" mode with pre-compiled patterns.", [
			'patterns_count' => count($this->compiled_patterns),
		]);

		// Use pre-compiled patterns for performance
		foreach ( $this->compiled_patterns as $word => $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				$this->get_logger()->warning( "{$mode} rule triggered.", [ 'matched_word' => $word ] );
				$this->add_message( sprintf( $error_message, $word ) );
				return true;
			}
		}

		$this->get_logger()->info('No spam found based on profanity list.');

		return false;
	}
}