<?php

namespace f12_cf7_captcha\core\protection\rules;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'Rule.class.php' );
require_once( 'RulesAjax.class.php' );
require_once( 'RuleRegex.class.php' );
require_once( 'RuleSearch.class.php' );

/**
 * Handle Filters that will be used to validate input fields.
 */
class RulesHandler extends BaseProtection {
	/**
	 * @var array<Rule>
	 */
	private $rules = [];

	/**
	 * @var array<Rule>
	 */
	private $spam = [];

	/**
	 * __construct method for initializing the object.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha instance (optional).
	 *
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Laden von Submodulen
		$this->get_logger()->info('Lade Submodul: RulesAjax.');
		new RulesAjax($Controller);

		// Hinzufügen des Filters für die Anzeige von Nachrichten
		add_filter('wpcf7_display_message', [$this, 'get_spam_message'], 10, 2);
		$this->get_logger()->debug('Filter "wpcf7_display_message" für die Methode "get_spam_message" hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

	/**
	 * Determines if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = true;

		$this->get_logger()->info('Überprüfe, ob das Modul aktiviert ist. Es ist standardmäßig aktiviert.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'is_enabled' => $is_enabled,
		]);

		return $is_enabled;
	}

	/**
	 * get_captcha method for getting the captcha string.
	 *
	 * @param mixed ...$args The arguments (optional).
	 *
	 * @return string The captcha string.
	 */
	public function get_captcha(...$args): string
	{
		$this->get_logger()->info('Rufe die Methode get_captcha auf.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Da die Methode nur einen leeren String zurückgibt,
		// gibt es hier keine spezifische Logik, die eine Rückmeldung erfordert.
		// Die Rückgabe eines leeren Strings könnte darauf hindeuten, dass
		// dieses Modul keine sichtbaren Captcha-Felder hinzufügt, sondern
		// nur im Hintergrund Validierungen durchführt.

		$this->get_logger()->debug('Die Methode gibt einen leeren String zurück.');

		return '';
	}

	/**
	 * Loads the rules for spam filtering.
	 *
	 * This method adds various rules for spam filtering, such as URL checking, BBCode checking, and blacklist
	 * checking.
	 *
	 * @return void
	 */
	private function maybe_load_rules(): void
	{
		$this->get_logger()->info('Versuche, die Schutzregeln zu laden.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Lade die URL-Regeln
		$this->add_rule_url();
		$this->get_logger()->debug('URL-Regel hinzugefügt.');

		// Lade die BBCode-Regeln
		$this->add_rule_bbcode();
		$this->get_logger()->debug('BBCode-Regel hinzugefügt.');

		// Lade die Blacklist-Regeln
		$this->add_rule_blacklist();
		$this->get_logger()->debug('Blacklist-Regel hinzugefügt.');

		$this->get_logger()->info('Alle Schutzregeln geladen.');
	}

	/**
	 * Reset the rules array.
	 *
	 * @return void
	 */
	public function reset_rules(): void
	{
		$this->get_logger()->info('Setze die Regeln und den Spam-Status zurück.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->rules = [];
		$this->spam  = [];

		$this->get_logger()->debug('Regel- und Spam-Arrays sind jetzt leer.');
	}

	/**
	 * Adds a rule to the blacklist.
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function add_rule_blacklist()
	{
		$this->get_logger()->info('Versuche, die Blacklist-Regel hinzuzufügen.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_blacklist_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('Blacklist-Regel ist deaktiviert. Überspringe Hinzufügung.');
			return;
		}

		// Überspringe, wenn die Regel bereits geladen wurde
		if (isset($this->rules['blacklist'])) {
			$this->get_logger()->warning('Blacklist-Regel ist bereits geladen. Überspringe Hinzufügung.');
			return;
		}

		$rule_value = get_option('disallowed_keys');

		if (empty($rule_value)) {
			$this->get_logger()->warning('Keine Blacklist-Wörter in den Optionen gefunden.');
			return;
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_blacklist', 'global');
		if (empty($error_message)) {
			$error_message = __('The word %s is blacklisted. Please remove it to continue.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Standard-Fehlermeldung für Blacklist verwendet.');
		}

		$rule_greedy = $this->Controller->get_settings('protection_rules_blacklist_greedy', 'global');
		if (!is_numeric($rule_greedy)) {
			$rule_greedy = 0;
			$this->get_logger()->debug('Greedy-Einstellung ist keine Zahl. Standardwert 0 verwendet.');
		}

		// Konvertiere Zeilenumbrüche in ein Array von Wörtern
		$words = preg_split('/\r\n|[\r\n]/', $rule_value, -1, PREG_SPLIT_NO_EMPTY);

		if (empty($words)) {
			$this->get_logger()->warning('Das Blacklist-Feld enthält keine gültigen Wörter nach dem Split.');
			return;
		}

		$this->get_logger()->info('Erstelle eine neue RuleSearch-Instanz für die Blacklist-Regel.', [
			'words_count' => count($words),
			'greedy' => (int)$rule_greedy,
		]);

		$Rule = new RuleSearch($this->get_logger(), $words, $error_message, (int)$rule_greedy);
		$this->add_rule('blacklist', $Rule);

		$this->get_logger()->info('Blacklist-Regel erfolgreich hinzugefügt.');
	}


	/**
	 * Adds a rule for detecting BBCode in a message.
	 *
	 * Checks if the rule for BBCode is enabled in the settings. If it is not enabled, the method returns early.
	 *
	 * Retrieves the error message for the BBCode rule from the settings. If the error message is empty, a default
	 * error message is used.
	 *
	 * Creates a new RuleRegex object for detecting BBCode in the message, with the regex pattern
	 * '\[url=(.+)\](.+)\[\/url\]', the minimum match count of 0, and the error message obtained from the settings.
	 *
	 * Adds the created rule to the rule collection.
	 *
	 * This method does not return any value.
	 */
	private function add_rule_bbcode()
	{
		$this->get_logger()->info('Versuche, die BBCode-Regel hinzuzufügen.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_bbcode_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('BBCode-Regel ist deaktiviert. Überspringe Hinzufügung.');
			return;
		}

		// Überspringe, wenn die Regel bereits geladen wurde
		if (isset($this->rules['bbcode'])) {
			$this->get_logger()->warning('BBCode-Regel ist bereits geladen. Überspringe Hinzufügung.');
			return;
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_bbcode', 'global');

		if (empty($error_message)) {
			$error_message = __('BBCode is not allowed.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Standard-Fehlermeldung für BBCode verwendet.');
		}

		$this->get_logger()->info('Erstelle eine neue RuleRegex-Instanz für die BBCode-Regel.', [
			'regex' => '\[url=(.+)\](.+)\[\/url\]',
			'limit' => 0,
		]);

		$Rule = new RuleRegex($this->get_logger(), '\[url=(.+)\](.+)\[\/url\]', 0, $error_message);
		$this->add_rule('bbcode', $Rule);

		$this->get_logger()->info('BBCode-Regel erfolgreich hinzugefügt.');
	}

	/**
	 * Adds a rule for URL validation.
	 *
	 * This method adds a rule for URL validation if the corresponding setting is enabled.
	 * It retrieves the rule limit from the settings, and if the limit is not a number, it sets it to 0.
	 * It also retrieves the error message from the settings, and if it is empty, it assigns a default error
	 * message. Finally, it creates a new RuleRegex object with the URL regex pattern, the rule limit, and the
	 * error message, and adds the rule to the instance of CF7Captcha.
	 *
	 * @return void
	 */
	private function add_rule_url()
	{
		$this->get_logger()->info('Versuche, die URL-Regel hinzuzufügen.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_url_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('URL-Regel ist deaktiviert. Überspringe Hinzufügung.');
			return;
		}

		// Überspringe, wenn die Regel bereits geladen wurde
		if (isset($this->rules['url'])) {
			$this->get_logger()->warning('URL-Regel ist bereits geladen. Überspringe Hinzufügung.');
			return;
		}

		$rule_limit = $this->Controller->get_settings('protection_rules_url_limit', 'global');

		if (!is_numeric($rule_limit)) {
			$rule_limit = 0;
			$this->get_logger()->debug('Limit-Einstellung ist keine Zahl. Standardwert 0 verwendet.');
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_url', 'global');

		if (empty($error_message)) {
			$error_message = __('The Limit %d for URLs has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Standard-Fehlermeldung für URLs verwendet.');
		}

		$this->get_logger()->info('Erstelle eine neue RuleRegex-Instanz für die URL-Regel.', [
			'regex' => '(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?',
			'limit' => $rule_limit,
		]);

		$Rule = new RuleRegex($this->get_logger(), '(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?', $rule_limit, $error_message);
		$this->add_rule('url', $Rule);

		$this->get_logger()->info('URL-Regel erfolgreich hinzugefügt.');
	}

	/**
	 * Adds a rule to the list of rules.
	 *
	 * @param Rule $Rule The rule to add.
	 *
	 * @return void
	 */
	private function add_rule(string $name, $Rule)
	{
		$this->get_logger()->info("Füge eine neue Regel zum Regel-Array hinzu.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'rule_name' => $name,
			'rule_class' => get_class($Rule),
		]);

		$this->rules[$name] = $Rule;

		$this->get_logger()->debug("Regel '{$name}' erfolgreich hinzugefügt. Anzahl der Regeln: " . count($this->rules));
	}

	/**
	 * Retrieves the spam.
	 *
	 * @return mixed The spam data.
	 */
	private function get_spam()
	{
		$this->get_logger()->info('Rufe die gesammelten Spam-Meldungen ab.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->spam)) {
			$this->get_logger()->debug('Das Spam-Array ist leer.');
		} else {
			$this->get_logger()->debug('Spam-Meldungen erfolgreich abgerufen.', [
				'total_messages' => count($this->spam),
			]);
		}

		return $this->spam;
	}

	/**
	 * Retrieves the spam message based on the given message and status.
	 *
	 * @param string $message The original message to check for spam.
	 * @param string $status  The status of the message.
	 *
	 * @return string The spam message if found, otherwise the original message.
	 */
	public function get_spam_message($message, $status)
	{
		$this->get_logger()->info('Führe den Filter "wpcf7_display_message" aus, um die Spam-Nachricht abzurufen.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'current_status' => $status,
		]);

		$spam_rules = $this->get_spam();

		if (empty($spam_rules)) {
			$this->get_logger()->debug('Keine Spam-Regeln wurden ausgelöst. Gebe die ursprüngliche Nachricht zurück.');
			return $message;
		}

		$response = '';

		foreach ($spam_rules as $Rule) {
			// Überprüfe, ob die Methode get_messages() existiert, um Fehler zu vermeiden
			if (method_exists($Rule, 'get_messages')) {
				$messages = $Rule->get_messages();
				$response .= $messages;
				$this->get_logger()->debug('Nachrichten von einer ausgelösten Regel hinzugefügt.', [
					'rule_class' => get_class($Rule),
					'messages' => $messages,
				]);
			} else {
				$this->get_logger()->error('Die Regel-Instanz hat keine get_messages()-Methode.', [
					'rule_class' => get_class($Rule),
				]);
			}
		}

		$this->get_logger()->info('Spam-Nachrichten erfolgreich aggregiert und für die Ausgabe vorbereitet.', [
			'final_response_length' => strlen($response),
		]);

		return $response;
	}

	/**
	 * Determines if a value is considered spam based on a set of rules.
	 *
	 * @param mixed $value The value to check for spam.
	 *
	 * @return bool Returns true if the value is considered spam, false otherwise.
	 */
	public function is_spam($value): bool
	{
		$this->get_logger()->info('Starte die allgemeine Spam-Überprüfung basierend auf allen Regeln.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'input_type' => is_array($value) ? 'array' : 'string',
		]);

		// Setze den Spam-Status vor der Ausführung zurück
		$this->get_logger()->debug('Spam-Array wurde zurückgesetzt.');

		// Lade die konfigurierten Regeln
		$this->maybe_load_rules();
		$this->get_logger()->debug('Regeln wurden geladen. Anzahl der Regeln: ' . count($this->rules));

		// Iteriere über jede geladene Regel
		foreach ($this->rules as $key => $Rule) {
			$this->get_logger()->info("Überprüfe Wert gegen Regel: {$key}", [
				'rule_class' => get_class($Rule),
			]);

			$is_spam = false;
			if (is_array($value)) {
				// Wenn der Wert ein Array ist, überprüfe jedes Element einzeln
				foreach ($value as $skey => $svalue) {
					if ($Rule->is_spam($svalue)) {
						$is_spam = true;
						break; // Ein Element ist Spam, beende innere Schleife
					}
				}
			} else {
				// Wenn der Wert ein String ist, überprüfe ihn direkt
				if ($Rule->is_spam($value)) {
					$is_spam = true;
				}
			}

			if ($is_spam) {
				$this->get_logger()->warning("Regel '{$key}' hat Spam gefunden.", [
					'input_value' => is_array($value) ? 'Array' : $value,
				]);

				$message = $Rule->get_messages();
				$this->set_message(sprintf(__('rule-protection: %s', 'captcha-for-contact-form-7'), $message));
				$this->spam[] = $Rule;

				// Sobald eine Regel zuschlägt, betrachten wir das gesamte Formular als Spam
				return true;
			}
		}

		$this->get_logger()->info('Keine der Regeln hat Spam gefunden.');

		return false;
	}

	public function success(): void
	{
		$this->get_logger()->info('Erfolgreiche Validierung der Regel-Überprüfung.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implementieren Sie hier die Logik, die nach einer erfolgreichen Überprüfung ausgeführt werden soll.
		// In diesem Kontext ist es unwahrscheinlich, dass zusätzliche Aktionen erforderlich sind,
		// da die Überprüfung primär in der is_spam()-Methode stattfindet.
	}
}