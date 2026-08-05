<?php

namespace f12_cf7_captcha\core\protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signed, per-render token that anchors the timing protection and rotates field names.
 *
 * Before this existed, `php_start_time`, `js_start_time`, `js_end_time` and the honeypot
 * were plain hidden inputs under fixed, publicly known names. A bot could POST arbitrary
 * values for all of them without ever loading the page, and nothing tied the values to
 * this server or this render. This class fixes both halves:
 *
 *  - **Signature** — the token carries the server-side issue time, signed with an HMAC over
 *    a site secret. `php_start_time` is no longer trusted as the age anchor; `get_age()` is,
 *    and it cannot be forged or back-dated without the secret.
 *  - **Rotation** — every protected field gets a name derived from the token via HMAC, so the
 *    names differ per render and cannot be hardcoded by a spam script.
 *
 * Deliberate non-goals:
 *
 *  - This is *not* a replay guard. The same rendered form can legitimately be submitted from
 *    a full-page cache by many visitors, so the token is intentionally not single-use.
 *    One-time validation is the Timer module's job (it keeps a DB row per render).
 *  - Rotation does not stop a bot that parses the delivered HTML — it stops bots that POST
 *    against remembered field names. Treat it as raising cost, not as a barrier.
 */
class Field_Token {

	/**
	 * Name of the hidden field carrying the token itself.
	 *
	 * This one cannot rotate — the server has to find it before it can derive anything else.
	 */
	public const TOKEN_FIELD = 'f12_t';

	/** Slot identifiers for the rotated fields. */
	public const SLOT_PHP_START = 'php_start_time';
	public const SLOT_JS_START  = 'js_start_time';
	public const SLOT_JS_END    = 'js_end_time';
	public const SLOT_HONEYPOT  = 'honeypot';

	/**
	 * Default upper bound for a token's age, in seconds.
	 *
	 * Generous on purpose: on sites with full-page caching the form HTML — and with it the
	 * token — is frozen at cache-generation time, so every visitor submits a token as old as
	 * the cache entry. A tight window would reject real people. 24h still forces a spam run
	 * to re-fetch the form daily instead of replaying one harvested copy indefinitely.
	 */
	public const DEFAULT_MAX_AGE = 86400;

	private int $issued_at;
	private string $nonce;

	/**
	 * Memoized token for the current request, so that every module rendering into the same
	 * form (JS timing fields, honeypot, …) agrees on one set of field names.
	 */
	private static ?Field_Token $current = null;

	private function __construct( int $issued_at, string $nonce ) {
		$this->issued_at = $issued_at;
		$this->nonce     = $nonce;
	}

	/**
	 * The token for the current render, created on first use.
	 */
	public static function current(): Field_Token {
		if ( self::$current === null ) {
			self::$current = self::issue();
		}

		return self::$current;
	}

	/**
	 * Mint a fresh token.
	 */
	public static function issue(): Field_Token {
		try {
			$nonce = bin2hex( random_bytes( 6 ) );
		} catch ( \Exception $e ) {
			// random_bytes() only throws when no CSPRNG is available at all. The nonce exists
			// to make names unpredictable across renders; a weaker source still beats a fixed
			// name, and the signature keeps its strength from the site secret either way.
			$nonce = substr( md5( uniqid( 'f12', true ) ), 0, 12 );
		}

		return new self( time(), $nonce );
	}

	/**
	 * Build a token with a specific issue time.
	 *
	 * @internal Exposed so tests can produce correctly signed tokens of a known age without
	 *           reimplementing the signature.
	 */
	public static function create( int $issued_at, string $nonce = 'abcdef123456' ): Field_Token {
		return new self( $issued_at, $nonce );
	}

	/**
	 * Parse and verify a token out of submitted data.
	 *
	 * @param array $post The submitted fields.
	 *
	 * @return Field_Token|null The verified token, or null when absent, malformed or unsigned.
	 */
	public static function from_request( array $post ): ?Field_Token {
		if ( ! isset( $post[ self::TOKEN_FIELD ] ) || ! is_string( $post[ self::TOKEN_FIELD ] ) ) {
			return null;
		}

		$parts = explode( '.', $post[ self::TOKEN_FIELD ] );

		if ( count( $parts ) !== 3 ) {
			return null;
		}

		list( $issued_at, $nonce, $signature ) = $parts;

		if ( ! ctype_digit( $issued_at ) || ! ctype_xdigit( $nonce ) || ! ctype_xdigit( $signature ) ) {
			return null;
		}

		$token = new self( (int) $issued_at, $nonce );

		if ( ! hash_equals( $token->signature(), $signature ) ) {
			return null;
		}

		return $token;
	}

	/**
	 * Reset the memoized request token (used by tests).
	 *
	 * @internal
	 */
	public static function reset_current(): void {
		self::$current = null;
	}

	/**
	 * The token string to embed in the form.
	 */
	public function get_token(): string {
		return $this->issued_at . '.' . $this->nonce . '.' . $this->signature();
	}

	/**
	 * The rotated `name` attribute for a given slot.
	 *
	 * @param string $slot One of the SLOT_* constants.
	 */
	public function field_name( string $slot ): string {
		return 'f12_' . substr( hash_hmac( 'sha256', $this->issued_at . '|' . $this->nonce . '|' . $slot, self::secret() ), 0, 16 );
	}

	/**
	 * Server-side age of this token in seconds.
	 *
	 * Unlike the submitted `php_start_time`, this is derived from a signed value and can be
	 * trusted. Negative when the server clock moved backwards between render and submit.
	 */
	public function get_age(): int {
		return time() - $this->issued_at;
	}

	public function get_issued_at(): int {
		return $this->issued_at;
	}

	/**
	 * The hidden input carrying the token.
	 */
	public function get_field(): string {
		return sprintf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( self::TOKEN_FIELD ),
			esc_attr( $this->get_token() )
		);
	}

	private function signature(): string {
		return substr( hash_hmac( 'sha256', $this->issued_at . '|' . $this->nonce, self::secret() ), 0, 32 );
	}

	/**
	 * Site secret backing both the signature and the name derivation.
	 *
	 * wp_salt() is per-site and not reachable from the outside, which is exactly what the
	 * signature needs. Rotating the WordPress salts invalidates outstanding tokens — visitors
	 * with a form already open then fall back to the legacy field names rather than being
	 * blocked (see Javascript_Validator::resolve_field_names()).
	 */
	private static function secret(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' );
		}

		// Only reached outside WordPress (unit tests).
		return defined( 'AUTH_SALT' ) ? AUTH_SALT : 'f12-cf7-captcha-fallback-secret';
	}
}
