<?php

namespace f12_cf7_captcha\core\protection\rules;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
class RuleRegex extends Rule {
	private $regex = '';
	private $limit = 0;

	public function __construct(LoggerInterface $logger, $regex, $limit = 0, $error_message = '')
	{
		parent::__construct($logger);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->error_message = $error_message;
		$this->regex = $regex;
		$this->limit = $limit;

		$this->get_logger()->debug('Initialisiere Regex- und Limit-Werte.', [
			'regex' => $this->regex,
			'limit' => $this->limit,
			'error_message' => $this->error_message,
		]);

		add_filter('f12-cf7-captcha-ruleregex-exclusion-counter', [$this, 'wp_add_exclusions'], 10, 2);

		$this->get_logger()->info('Konstruktor abgeschlossen. Filter "f12-cf7-captcha-ruleregex-exclusion-counter" hinzugefügt.');
	}

	/**
	 * Adds exclusions to the WordPress system.
	 *
	 * @param int   $counter The counter value for the exclusions.
	 * @param array $matches An array of matches to be excluded.
	 *
	 * @return int The modified counter value after exclusions are added.
	 */
	public function wp_add_exclusions(int $counter, array $matches): int
	{
		$this->get_logger()->info('Führe wp_add_exclusions-Filter aus, um die Übereinstimmungszählung anzupassen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'initial_counter' => $counter,
		]);

		// Stelle sicher, dass $matches[0] ein Array ist
		if (!isset($matches[0]) || !is_array($matches[0])) {
			$this->get_logger()->warning('Ungültiges Format für Übereinstimmungen. Gib den ursprünglichen Zähler zurück.', [
				'matches_type' => gettype($matches[0]),
			]);
			return $counter;
		}

		$site_url = get_site_url();
		$this->get_logger()->debug('Aktuelle Site-URL: ' . $site_url);

		foreach ($matches[0] as $match) {
			if (str_contains($match, $site_url)) {
				$counter--;
				$this->get_logger()->debug('Übereinstimmung mit der aktuellen Site-URL gefunden und Zähler dekrementiert.', [
					'match' => $match,
					'new_counter' => $counter,
				]);
			}
		}

		$this->get_logger()->info('wp_add_exclusions-Filter abgeschlossen. Endgültiger Zählerstand.', [
			'final_counter' => $counter,
		]);

		return $counter;
	}

	/**
	 * Determines if the given value is considered spam based on a regular expression match.
	 *
	 * @param string $value The value to be checked for spam.
	 *
	 * @return bool Returns true if the value is considered spam, false otherwise.
	 */
	public function is_spam($value): bool
	{
		$this->get_logger()->info('Starte die Spam-Überprüfung basierend auf Regex-Regeln.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'input_type' => is_array($value) ? 'array' : 'string',
		]);

		// Rekursive Überprüfung für Arrays
		if (is_array($value)) {
			foreach ($value as $keyword) {
				if ($this->is_spam($keyword)) {
					$this->get_logger()->debug('Spam in einem Unterelement des Arrays gefunden.');
					return true;
				}
			}
			$this->get_logger()->debug('Kein Spam in den Array-Elementen gefunden.');
			return false;
		}

		$error_message = $this->get_error_message();
		$pattern = "!" . $this->regex . "!im";

		$this->get_logger()->debug('Führe Regex-Überprüfung durch.', [
			'pattern' => $pattern,
			'limit' => $this->limit,
		]);

		$count = preg_match_all($pattern, $value, $matches);

		// Filter zur Anpassung des Zählers
		$count = apply_filters('f12-cf7-captcha-ruleregex-exclusion-counter', $count, $matches);
		$this->get_logger()->debug('Regex-Übereinstimmungen gezählt.', [
			'raw_count' => count($matches[0] ?? []),
			'filtered_count' => $count,
		]);

		// Überprüfung des Limits
		if ($count > $this->limit) {
			$this->get_logger()->warning('Das Limit für Regex-Übereinstimmungen wurde überschritten.', [
				'count' => $count,
				'limit' => $this->limit,
			]);

			$urls = array_map('esc_url', $matches[0] ?? []);
			$formatted_message = sprintf($error_message, (int)$this->limit, implode(', ', $urls));
			$this->add_message($formatted_message);

			return true;
		}

		$this->get_logger()->info('Kein Spam basierend auf dieser Regel gefunden.');
		return false;
	}
}