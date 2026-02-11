<?php

namespace f12_cf7_captcha\core\protection\rules;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
class RulesHandler extends BaseProtection {
	/**
	 * Cache group for rule caching.
	 */
	private const CACHE_GROUP = 'f12-captcha-rules';

	/**
	 * Cache TTL in seconds (5 minutes).
	 */
	private const CACHE_TTL = 300;

	/**
	 * @var array<Rule>
	 */
	private $rules = [];

	/**
	 * @var array<Rule>
	 */
	private $spam = [];

	private RulesAjax $_Rules_Ajax;

	/**
	 * __construct method for initializing the object.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha instance (optional).
	 *
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Load submodules
		$this->get_logger()->info('Loading submodule: RulesAjax.');
		$this->_Rules_Ajax = new RulesAjax($Controller);

		// Add filter for displaying messages
		add_filter('wpcf7_display_message', [$this, 'get_spam_message'], 10, 2);
		$this->get_logger()->debug('Filter "wpcf7_display_message" added for method "get_spam_message".');

		$this->get_logger()->info('Constructor completed.');
	}

	/**
	 * Retrieves the instance of the RulesAjax submodule.
	 *
	 * @return RulesAjax
	 */
	public function get_rules_ajax(): RulesAjax
	{
		return $this->_Rules_Ajax;
	}

	/**
	 * Determines if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool
	{
		$is_enabled = true;

		$this->get_logger()->info('Checking if module is enabled. Enabled by default.', [
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
		$this->get_logger()->info('Calling get_captcha method.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Since the method only returns an empty string,
		// there is no specific logic here that requires feedback.
		// Returning an empty string could indicate that
		// this module does not add visible captcha fields, but
		// only performs validations in the background.

		$this->get_logger()->debug('Method returns empty string.');

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
		$this->get_logger()->info('Attempting to load protection rules.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Load the URL rules
		$this->add_rule_url();
		$this->get_logger()->debug('URL rule added.');

		// Load the BBCode rules
		$this->add_rule_bbcode();
		$this->get_logger()->debug('BBCode rule added.');

		// Load the blacklist rules
		$this->add_rule_blacklist();
		$this->get_logger()->debug('Blacklist rule added.');

		$this->get_logger()->info('All protection rules loaded.');
	}

	/**
	 * Reset the rules array.
	 *
	 * @return void
	 */
	public function reset_rules(): void
	{
		$this->get_logger()->info('Resetting rules and spam status.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->rules = [];
		$this->spam  = [];

		$this->get_logger()->debug('Rule and spam arrays are now empty.');
	}

	/**
	 * Retrieves cached blacklist words or loads them from database.
	 *
	 * Uses WordPress object cache to avoid repeated database calls
	 * and string processing on every validation.
	 *
	 * @return array Array of blacklist words, empty array if none.
	 */
	private function get_cached_blacklist(): array {
		$cache_key = 'blacklist_words';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $cached !== false ) {
			$this->get_logger()->debug( 'Blacklist loaded from cache.', [
				'words_count' => count( $cached ),
			] );
			return $cached;
		}

		$rule_value = get_option( 'disallowed_keys', '' );

		if ( empty( $rule_value ) ) {
			$words = [];
		} else {
			// Convert line breaks to an array of words
			$words = preg_split( '/\r\n|[\r\n]/', $rule_value, -1, PREG_SPLIT_NO_EMPTY );
			// Trim each word and filter empty ones
			$words = array_filter( array_map( 'trim', $words ) );
		}

		wp_cache_set( $cache_key, $words, self::CACHE_GROUP, self::CACHE_TTL );

		$this->get_logger()->debug( 'Blacklist cached from database.', [
			'words_count' => count( $words ),
			'ttl'         => self::CACHE_TTL,
		] );

		return $words;
	}

	/**
	 * Adds a rule to the blacklist.
	 *
	 * Uses caching to improve performance by avoiding repeated
	 * database calls and string processing.
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function add_rule_blacklist()
	{
		$this->get_logger()->info('Attempting to add blacklist rule.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_blacklist_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('Blacklist rule is disabled. Skipping addition.');
			return;
		}

		// Skip if the rule is already loaded
		if (isset($this->rules['blacklist'])) {
			$this->get_logger()->warning('Blacklist rule is already loaded. Skipping addition.');
			return;
		}

		// Use cached blacklist words
		$words = $this->get_cached_blacklist();

		if (empty($words)) {
			$this->get_logger()->warning('No blacklist words found.');
			return;
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_blacklist', 'global');
		if (empty($error_message)) {
			$error_message = __('The word %s is blacklisted. Please remove it to continue.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Default error message for blacklist used.');
		}

		$rule_greedy = $this->Controller->get_settings('protection_rules_blacklist_greedy', 'global');
		if (!is_numeric($rule_greedy)) {
			$rule_greedy = 0;
			$this->get_logger()->debug('Greedy setting is not a number. Default value 0 used.');
		}

		$this->get_logger()->info('Creating new RuleSearch instance for blacklist rule.', [
			'words_count' => count($words),
			'greedy' => (int)$rule_greedy,
		]);

		$Rule = new RuleSearch($this->get_logger(), $words, $error_message, (int)$rule_greedy);
		$this->add_rule('blacklist', $Rule);

		$this->get_logger()->info('Blacklist rule successfully added.');
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
		$this->get_logger()->info('Attempting to add BBCode rule.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_bbcode_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('BBCode rule is disabled. Skipping addition.');
			return;
		}

		// Skip if the rule is already loaded
		if (isset($this->rules['bbcode'])) {
			$this->get_logger()->warning('BBCode rule is already loaded. Skipping addition.');
			return;
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_bbcode', 'global');

		if (empty($error_message)) {
			$error_message = __('BBCode is not allowed.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Default error message for BBCode used.');
		}

		$this->get_logger()->info('Creating new RuleRegex instance for BBCode rule.', [
			'regex' => '\[url=(.+)\](.+)\[\/url\]',
			'limit' => 0,
		]);

		$Rule = new RuleRegex($this->get_logger(), '\[url=(.+)\](.+)\[\/url\]', 0, $error_message);
		$this->add_rule('bbcode', $Rule);

		$this->get_logger()->info('BBCode rule successfully added.');
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
		$this->get_logger()->info('Attempting to add URL rule.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$rule_enabled = (int)$this->Controller->get_settings('protection_rules_url_enable', 'global');

		if ($rule_enabled !== 1) {
			$this->get_logger()->warning('URL rule is disabled. Skipping addition.');
			return;
		}

		// Skip if the rule is already loaded
		if (isset($this->rules['url'])) {
			$this->get_logger()->warning('URL rule is already loaded. Skipping addition.');
			return;
		}

		$rule_limit = $this->Controller->get_settings('protection_rules_url_limit', 'global');

		if (!is_numeric($rule_limit)) {
			$rule_limit = 0;
			$this->get_logger()->debug('Limit setting is not a number. Default value 0 used.');
		}

		$error_message = $this->Controller->get_settings('protection_rules_error_message_url', 'global');

		if (empty($error_message)) {
			$error_message = __('The Limit %d for URLs has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7');
			$this->get_logger()->debug('Default error message for URLs used.');
		}

		$this->get_logger()->info('Creating new RuleRegex instance for URL rule.', [
			'regex' => '(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?',
			'limit' => $rule_limit,
		]);

		$Rule = new RuleRegex($this->get_logger(), '(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?', $rule_limit, $error_message);
		$this->add_rule('url', $Rule);

		$this->get_logger()->info('URL rule successfully added.');
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
		$this->get_logger()->info("Adding new rule to rule array.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'rule_name' => $name,
			'rule_class' => get_class($Rule),
		]);

		$this->rules[$name] = $Rule;

		$this->get_logger()->debug("Rule '{$name}' successfully added. Number of rules: " . count($this->rules));
	}

	/**
	 * Retrieves the spam.
	 *
	 * @return mixed The spam data.
	 */
	private function get_spam()
	{
		$this->get_logger()->info('Retrieving collected spam messages.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		if (empty($this->spam)) {
			$this->get_logger()->debug('Spam array is empty.');
		} else {
			$this->get_logger()->debug('Spam messages successfully retrieved.', [
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
		$this->get_logger()->info('Executing filter "wpcf7_display_message" to retrieve spam message.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'current_status' => $status,
		]);

		$spam_rules = $this->get_spam();

		if (empty($spam_rules)) {
			$this->get_logger()->debug('No spam rules were triggered. Returning original message.');
			return $message;
		}

		$response = '';

		foreach ($spam_rules as $Rule) {
			// Check if the get_messages() method exists to avoid errors
			if (method_exists($Rule, 'get_messages')) {
				$messages = $Rule->get_messages();
				$response .= $messages;
				$this->get_logger()->debug('Messages from triggered rule added.', [
					'rule_class' => get_class($Rule),
					'messages' => $messages,
				]);
			} else {
				$this->get_logger()->error('Rule instance does not have get_messages() method.', [
					'rule_class' => get_class($Rule),
				]);
			}
		}

		$this->get_logger()->info('Spam messages successfully aggregated and prepared for output.', [
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
		$this->get_logger()->info('Starting general spam check based on all rules.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'input_type' => is_array($value) ? 'array' : 'string',
		]);

		// Reset the spam status before execution
		$this->get_logger()->debug('Spam array was reset.');

		// Load the configured rules
		$this->maybe_load_rules();
		$this->get_logger()->debug('Rules loaded. Number of rules: ' . count($this->rules));

		// Iterate over each loaded rule
		foreach ($this->rules as $key => $Rule) {
			$this->get_logger()->info("Checking value against rule: {$key}", [
				'rule_class' => get_class($Rule),
			]);

			$is_spam = false;
			if (is_array($value)) {
				// If the value is an array, check each element individually
				foreach ($value as $skey => $svalue) {
					if ($Rule->is_spam($svalue)) {
						$is_spam = true;
						break; // One element is spam, end inner loop
					}
				}
			} else {
				// If the value is a string, check it directly
				if ($Rule->is_spam($value)) {
					$is_spam = true;
				}
			}

			if ($is_spam) {
				$this->get_logger()->warning("Rule '{$key}' found spam.", [
					'input_value' => is_array($value) ? 'Array' : $value,
				]);

				$message = $Rule->get_messages();
				$this->set_message(sprintf(__('rule-protection: %s', 'captcha-for-contact-form-7'), $message));
				$this->spam[] = $Rule;

				// As soon as one rule triggers, we consider the entire form as spam
				return true;
			}
		}

		$this->get_logger()->info('None of the rules found spam.');

		return false;
	}

	public function success(): void
	{
		$this->get_logger()->info('Successful rule validation.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// TODO: Implement the logic here that should be executed after a successful check.
		// In this context, it is unlikely that additional actions are required,
		// since the check primarily takes place in the is_spam() method.
	}
}