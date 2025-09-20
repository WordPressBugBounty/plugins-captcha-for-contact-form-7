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

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->error_message = $error_message;
		$this->words = $words;
		$this->greedy = $greedy;

		$this->get_logger()->debug('Initialisiere Schimpfwortfilter.', [
			'words_count' => count($this->words),
			'greedy_level' => $this->greedy,
			'error_message' => $this->error_message,
		]);

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

	/**
	 * Determines if a given value is considered spam based on a list of words.
	 *
	 * @param string $value The input value to be checked for spam.
	 *
	 * @return bool Returns true if the value is considered spam, otherwise false.
	 */
	public function is_spam($value): bool
	{
		$this->get_logger()->info('Starte die Spam-Überprüfung basierend auf Schimpfwortlisten.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'input_type' => is_array($value) ? 'array' : 'string',
		]);

		// Rekursive Überprüfung, falls der Wert ein Array ist
		if (is_array($value)) {
			foreach ($value as $item) {
				if ($this->is_spam($item)) {
					$this->get_logger()->debug('Spam-Element im Array gefunden.');
					return true;
				}
			}
			$this->get_logger()->debug('Keine Spam-Elemente im Array gefunden.');
			return false;
		}

		$error_message = $this->get_error_message();

		if ($this->greedy == 1) {
			// "Greedy" Modus: Matcht das Wort, auch wenn es an andere Zeichen angehängt ist.
			$this->get_logger()->debug('Überprüfung im "Greedy"-Modus.');
			foreach ($this->words as $word) {
				$regex = "!([^a-zA-Z0-9]+|^)" . preg_quote($word, '!') . "([a-zA-Z0-9]+|$)!";
				if (preg_match($regex, $value)) {
					$this->get_logger()->warning('Greedy-Regel ausgelöst.', ['matched_word' => $word]);
					$this->add_message(sprintf($error_message, $word));
					return true;
				}
			}
		} else {
			// "Non-Greedy" Modus: Matcht nur ganze Wörter.
			$this->get_logger()->debug('Überprüfung im "Non-Greedy"-Modus.');
			foreach ($this->words as $word) {
				$regex = "!(^|\s)" . preg_quote($word, '!') . "(\s|$)!";
				if (preg_match($regex, $value)) {
					$this->get_logger()->warning('Non-Greedy-Regel ausgelöst.', ['matched_word' => $word]);
					$this->add_message(sprintf($error_message, $word));
					return true;
				}
			}
		}

		$this->get_logger()->info('Kein Spam basierend auf Schimpfwortliste gefunden.');

		return false;
	}
}