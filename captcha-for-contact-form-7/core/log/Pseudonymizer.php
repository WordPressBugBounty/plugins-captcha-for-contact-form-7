<?php

namespace f12_cf7_captcha\core\log;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\ip\Salt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns identifying values into stable, non-reversible tokens.
 *
 * ## Why not plain SHA-256
 *
 * The block log used to store `hash('sha256', $ip)`. An unsalted hash of an IP address is not
 * a pseudonym: IPv4 has 2^32 possible inputs, so the whole space can be enumerated on a GPU in
 * seconds and every hash reversed. The same holds for e-mail addresses against a leaked list.
 *
 * Hashing against a secret the attacker does not have removes that shortcut entirely. This
 * class prefers the rotating salt already used by the IP protection ({@see Salt}: HMAC-SHA512
 * against 512 random bytes, replaced every 30 days) and falls back to a site-lifetime HMAC
 * keyed on `wp_salt('auth')` when the salt table is unavailable — never to a bare hash.
 *
 * ## What rotation costs
 *
 * Because the salt is replaced every 30 days, the same IP produces a different token after a
 * rotation. Correlation therefore works *within* a window, not across one. That is the right
 * trade for data intended to leave the site: it bounds how long any pseudonym stays linkable,
 * and the block log's own retention is 30 days anyway.
 */
class Pseudonymizer {

	/**
	 * Free mailbox providers.
	 *
	 * Recorded as a class rather than as the domain itself, because a custom domain can
	 * identify a person on its own ("john@johnsmith.example") while "this came from a free
	 * mailbox" cannot. Not exhaustive and not meant to be — an unlisted provider simply
	 * classifies as `custom`, which is the harmless direction to be wrong in.
	 */
	private const FREEMAIL = [
		'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'yahoo.de', 'ymail.com',
		'hotmail.com', 'hotmail.de', 'hotmail.co.uk', 'hotmail.fr', 'outlook.com', 'outlook.de',
		'live.com', 'live.de', 'msn.com', 'aol.com', 'gmx.de', 'gmx.net', 'gmx.at', 'gmx.ch',
		'gmx.com', 'web.de', 't-online.de', 'freenet.de', 'arcor.de', 'icloud.com', 'me.com',
		'mac.com', 'protonmail.com', 'proton.me', 'zoho.com', 'yandex.ru', 'mail.ru',
		'mail.com', 'inbox.com', 'fastmail.com', 'tutanota.com', 'seznam.cz', 'wp.pl',
		'onet.pl', 'interia.pl', 'libero.it', 'virgilio.it', 'alice.it', 'orange.fr',
		'wanadoo.fr', 'free.fr', 'laposte.net', 'bluewin.ch', 'sfr.fr', 'ziggo.nl',
	];

	/**
	 * Throwaway-address providers.
	 *
	 * A short, deliberately incomplete list of the best-known ones. Maintaining a full
	 * disposable-domain list is a project of its own; this exists so the obvious cases carry a
	 * useful label rather than being lumped in with real mailboxes.
	 */
	private const DISPOSABLE = [
		'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', '10minutemail.com',
		'temp-mail.org', 'tempmail.com', 'throwawaymail.com', 'yopmail.com', 'trashmail.com',
		'trashmail.de', 'sharklasers.com', 'getnada.com', 'maildrop.cc', 'dispostable.com',
		'fakeinbox.com', 'mohmal.com', 'emailondeck.com', 'mailnesia.com', 'spam4.me',
	];

	/**
	 * Memoised salt for this request. Resolving it hits the database, and a single submission
	 * pseudonymises several values.
	 *
	 * @var Salt|null|false False means "tried and failed", so we do not retry per value.
	 */
	private static $salt = null;

	/**
	 * Pseudonymise an arbitrary value.
	 *
	 * @param string $value Raw value. Empty input yields an empty string rather than the hash
	 *                      of nothing, so "absent" stays distinguishable from "present".
	 *
	 * @return string Hex digest, or '' when there was nothing to hash.
	 */
	public static function hash( string $value ): string {
		$value = trim( $value );

		if ( $value === '' ) {
			return '';
		}

		$salt = self::get_salt();

		if ( $salt instanceof Salt ) {
			// Truncated to 64 characters to fit the log tables' varchar(64) hash columns.
			return substr( $salt->get_salted( $value ), 0, 64 );
		}

		// Fallback: still keyed, just not rotating.
		return hash_hmac( 'sha256', $value, self::fallback_key() );
	}

	/**
	 * Pseudonymise the IP address of the current request.
	 */
	public static function hash_ip( string $ip ): string {
		return self::hash( $ip );
	}

	/**
	 * Break an e-mail address into training signal without keeping the address.
	 *
	 * The hash lets the same sender be recognised across submissions; the class and TLD carry
	 * the part that is actually predictive. The domain itself is deliberately never stored.
	 *
	 * @return array{hash:string, domain_class:string, tld:string, local_len:int, local_digits:int}
	 */
	public static function describe_email( string $email ): array {
		$email = strtolower( trim( $email ) );

		$empty = [ 'hash' => '', 'domain_class' => 'none', 'tld' => '', 'local_len' => 0, 'local_digits' => 0 ];

		if ( $email === '' || ! str_contains( $email, '@' ) ) {
			return $empty;
		}

		$at     = strrpos( $email, '@' );
		$local  = substr( $email, 0, $at );
		$domain = substr( $email, $at + 1 );

		if ( $local === '' || $domain === '' ) {
			return $empty;
		}

		// Plus-addressing is an alias of the same mailbox, so "a+spam@x" and "a@x" should
		// produce one pseudonym rather than two.
		$canonical_local = explode( '+', $local )[0];

		$dot = strrpos( $domain, '.' );

		return [
			'hash'         => self::hash( $canonical_local . '@' . $domain ),
			'domain_class' => self::classify_domain( $domain ),
			'tld'          => $dot !== false ? substr( $domain, $dot + 1 ) : '',
			'local_len'    => strlen( $local ),
			'local_digits' => (int) preg_match_all( '/\d/', $local ),
		];
	}

	/**
	 * `freemail`, `disposable` or `custom`.
	 */
	public static function classify_domain( string $domain ): string {
		$domain = strtolower( ltrim( trim( $domain ), '@' ) );

		if ( in_array( $domain, self::DISPOSABLE, true ) ) {
			return 'disposable';
		}

		if ( in_array( $domain, self::FREEMAIL, true ) ) {
			return 'freemail';
		}

		return 'custom';
	}

	/**
	 * Drop the memoised salt (used by tests, and after a salt rotation within one request).
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$salt = null;
	}

	/**
	 * @return Salt|false
	 */
	private static function get_salt() {
		if ( self::$salt !== null ) {
			return self::$salt;
		}

		try {
			$logger = CF7Captcha::get_instance()->get_logger();
			$salt   = ( new Salt( $logger ) )->get_last();

			self::$salt = $salt instanceof Salt ? $salt : false;
		} catch ( \Throwable $e ) {
			// get_last() throws when $wpdb is missing and can fail on a broken salt table.
			// Falling back is correct here: an unsalted value must never be written, but a
			// missing salt must not take a form submission down either.
			self::$salt = false;
		}

		return self::$salt;
	}

	private static function fallback_key(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' );
		}

		return defined( 'AUTH_SALT' ) ? AUTH_SALT : 'f12-cf7-captcha-pseudonymizer-fallback';
	}
}
