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
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		/**
		 * Load submoduls
		 */
		new RulesAjax( $Controller );

		add_filter( 'wpcf7_display_message', [ $this, 'get_spam_message' ], 10, 2 );
	}

	/**
	 * Determines if the feature is enabled.
	 *
	 * @return bool Returns true if the feature is enabled, false otherwise.
	 */
	protected function is_enabled(): bool {
		return true;
	}

	/**
	 * get_captcha method for getting the captcha string.
	 *
	 * @param mixed ...$args The arguments (optional).
	 *
	 * @return string The captcha string.
	 */
	public function get_captcha( ...$args ): string {
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
	private function maybe_load_rules(): void {
		$this->add_rule_url();
		$this->add_rule_bbcode();
		$this->add_rule_blacklist();
	}

	/**
	 * Reset the rules array.
	 *
	 * @return void
	 */
	public function reset_rules(): void {
		$this->rules = [];
		$this->spam  = [];
	}

	/**
	 * Adds a rule to the blacklist.
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function add_rule_blacklist() {
		$rule_enabled = (int) $this->Controller->get_settings( 'protection_rules_blacklist_enable', 'global' );

		if ( $rule_enabled !== 1 ) {
			return;
		}

		# skip if the rule has been loaded already
		if ( isset( $this->rules['blacklist'] ) ) {
			return;
		}

		$rule_value = get_option( 'disallowed_keys' );

		if ( empty( $rule_value ) ) {
			return;
		}

		$error_message = $this->Controller->get_settings( 'protection_rules_error_message_blacklist', 'global' );

		if ( empty( $error_message ) ) {
			$error_message = __( 'The word %s is blacklisted. Please remove it to continue.', 'captcha-for-contact-form-7' );
		}

		$rule_greedy = $this->Controller->get_settings( 'protection_rules_blacklist_greedy', 'global' );

		if ( ! is_numeric( $rule_greedy ) ) {
			$rule_greedy = 0;
		}

		// Convert new lines to | -> naked|sex|test|abc...
		$words = preg_split( '/\r\n|[\r\n]/', $rule_value );

		$Rule = new RuleSearch( $words, $error_message, (int) $rule_greedy );
		$this->add_rule( 'blacklist', $Rule );
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
	private function add_rule_bbcode() {
		$rule_enabled = (int) $this->Controller->get_settings( 'protection_rules_bbcode_enable', 'global' );

		if ( $rule_enabled !== 1 ) {
			return;
		}

		# skip if the rule has been loaded already
		if ( isset( $this->rules['bbcode'] ) ) {
			return;
		}

		$error_message = $this->Controller->get_settings( 'protection_rules_error_message_bbcode', 'global' );

		if ( empty( $error_message ) ) {
			$error_message = __( 'BBCode is not allowed.', 'captcha-for-contact-form-7' );
		}

		$Rule = new RuleRegex( '\[url=(.+)\](.+)\[\/url\]', 0, $error_message );
		$this->add_rule( 'bbcode', $Rule );
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
	private function add_rule_url() {
		$rule_enabled = (int) $this->Controller->get_settings( 'protection_rules_url_enable', 'global' );

		if ( $rule_enabled !== 1 ) {
			return;
		}

		# skip if the rule has been loaded already
		if ( isset( $this->rules['url'] ) ) {
			return;
		}

		$rule_limit = $this->Controller->get_settings( 'protection_rules_url_limit', 'global' );

		if ( ! is_numeric( $rule_limit ) ) {
			$rule_limit = 0;
		}

		$error_message = $this->Controller->get_settings( 'protection_rules_error_message_url', 'global' );

		if ( empty( $error_message ) ) {
			$error_message = __( 'The Limit %d for URLs has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' );
		}

		$Rule = new RuleRegex( '(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}(\/\S*)?', $rule_limit, $error_message );
		$this->add_rule( 'url', $Rule );
	}

	/**
	 * Adds a rule to the list of rules.
	 *
	 * @param Rule $Rule The rule to add.
	 *
	 * @return void
	 */
	private function add_rule( string $name, $Rule ) {
		$this->rules[ $name ] = $Rule;
	}

	/**
	 * Retrieves the spam.
	 *
	 * @return mixed The spam data.
	 */
	private function get_spam() {
		return $this->spam;
	}

	/**
	 * @param $message
	 * @param $status
	 *
	 * @return string
	 * @deprecated
	 *
	 */
	public function getSpamMessage( $message, $status ) {
		return $this->get_spam_message( $message, $status );
	}

	/**
	 * Retrieves the spam message based on the given message and status.
	 *
	 * @param string $message The original message to check for spam.
	 * @param string $status  The status of the message.
	 *
	 * @return string The spam message if found, otherwise the original message.
	 */
	public function get_spam_message( $message, $status ) {
		$spam = $this->get_spam();

		if ( empty( $spam ) ) {
			return $message;
		}

		$response = '';

		foreach ( $spam as $Rule ) {
			$response .= $Rule->get_messages();
		}

		return $response;
	}

	/**
	 * Check for spam
	 *
	 * @param $value
	 *
	 * @return bool
	 * @deprecated
	 */
	public function isSpam( $value ) {
		return $this->is_spam( $value );
	}

	/**
	 * Determines if a value is considered spam based on a set of rules.
	 *
	 * @param mixed $value The value to check for spam.
	 *
	 * @return bool Returns true if the value is considered spam, false otherwise.
	 */
	public function is_spam( $value ) {
		# reset spam
		$this->spam = [];

		# load rules
		$this->maybe_load_rules();

		foreach ( $this->rules as $key => $Rule /** @var Rule $Rule */ ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $skey => $svalue ) {
					if ( $Rule->is_spam( $svalue ) ) {
						$message = $Rule->get_messages();
						$this->set_message( 'rule-protection: ' . $message );
						$this->spam[] = $Rule;

						return true;
					}
				}
			} else {
				if ( $Rule->is_spam( $value ) ) {
					$message = $Rule->get_messages();
					$this->set_message( 'rule-protection: ' . $message );
					$this->spam[] = $Rule;

					return true;
				}
			}
		}

		return false;
	}

	public function success(): void {
		// TODO: Implement success() method.
	}
}