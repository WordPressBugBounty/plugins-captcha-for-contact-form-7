<?php

namespace f12_cf7_captcha\core\protection\rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
class RuleRegex extends Rule {
	private $regex = '';
	private $limit = 0;

	public function __construct( $regex, $limit = 0, $error_message = '' ) {
		$this->error_message = $error_message;
		$this->regex         = $regex;
		$this->limit         = $limit;

		add_filter( 'f12-cf7-captcha-ruleregex-exclusion-counter', [ $this, 'wp_add_exclusions' ], 10, 2 );
	}

	/**
	 * Adds exclusions to the WordPress system.
	 *
	 * @param int   $counter The counter value for the exclusions.
	 * @param array $matches An array of matches to be excluded.
	 *
	 * @return int The modified counter value after exclusions are added.
	 */
	public function wp_add_exclusions( int $counter, array $matches ): int {
		# Exclude current sites
		$site_url = get_site_url();

		if ( ! is_array( $matches[0] ) ) {
			return $counter;
		}

		foreach ( $matches[0] as $match ) {
			if ( str_contains( $match, $site_url ) ) {
				$counter --;
			}
		}

		return $counter;
	}

	/**
	 * Determines if the given value is considered spam based on a regular expression match.
	 *
	 * @param string $value The value to be checked for spam.
	 *
	 * @return bool Returns true if the value is considered spam, false otherwise.
	 */
	public function is_spam( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $keyword ) {
				if ( $this->is_spam( $keyword ) ) {
					return true;
				}
			}

			return false;
		}

		$error_message = $this->get_error_message();

		$pattern = "!" . $this->regex . "!im";

		$count = preg_match_all( $pattern, $value, $matches );

		/**
		 * Count / Exclusion manipulator
		 *
		 * This function allows developers to exclude certain regex values from the captcha protection.
		 *
		 * @param int   $count   The number of matches for the specific regex
		 * @param array $matches The matches for the specific regex.
		 *
		 * @since 1.0.0
		 */
		$count = apply_filters( 'f12-cf7-captcha-ruleregex-exclusion-counter', $count, $matches );

		if ( $count > $this->limit ) {
			$urls = array_map( 'esc_url', $matches[0] );
			$this->add_message( sprintf( $error_message, (int) $this->limit, implode( ',', $urls ) ) );

			// If one Rule has matched or the limit is reached
			return true;
		}

		return false;
	}

	/**
	 * @param $value
	 *
	 * @return bool
	 * @deprecated
	 */
	public function isSpam( $value ) {
		return $this->is_spam( $value );
	}
}