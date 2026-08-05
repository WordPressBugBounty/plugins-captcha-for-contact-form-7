<?php
/**
 * Agent Enforcer — server-side enforcement of the SilentShield agent policy
 * (plan/64 Inc3). The counterpart to Agent_Observer: where the observer only
 * REPORTS which AI agents visit (fire-and-forget, never blocks), the enforcer
 * actually BLOCKS disallowed bots — a 403 for "deny" rules, a 429 for "throttle"
 * rules — so a "Block" rule set in the dashboard takes real effect.
 *
 * How it works:
 *  - Fetches the signed policy bundle from `GET {F12_CAPTCHA_API_URL}/agent/policy`
 *    (header `x-api-key`) and verifies its Ed25519 signature against the pinned
 *    keys from `/.well-known/silentshield-agent-keys` (libsodium).
 *  - Caches the verified bundle in a transient and refreshes it OFF the request
 *    path (on `shutdown`, after the response is delivered) — the hot path only
 *    reads the cached bundle, never blocks on a network fetch.
 *  - On each front-end request it identifies the bot (longest User-Agent token
 *    match; "verified" iff the source IP is in that agent's published range,
 *    "spoof" iff it sits outside every published range of the same address
 *    family AND the source IP is conclusive), evaluates the ordered rules
 *    against the bot's category AND its behaviours (first match wins), and
 *    enforces the action.
 *
 * Safety guarantees (mirror the enforce spec — see api/enforcers/goagent):
 *  - **Monitor mode never blocks.** Only `mode == "enforce"` bundles can block.
 *  - **Fail-open everywhere.** Missing/expired/invalid bundle, no libsodium, any
 *    exception → the request passes. Enforcement must never take a site down.
 *  - **Default off + kill-switch.** Local toggle `global.agent_enforce` (default
 *    OFF) + the `SILENTSHIELD_ENFORCER` override constant; the server also forces
 *    monitor mode when the `agent_gateway_enforce` flag is off.
 *  - **Blocks are reported only with observation on.** The block report shares
 *    the observer's consent gate (`global.agent_observe` /
 *    `SILENTSHIELD_OBSERVER`); switching telemetry off stops the reporting
 *    without stopping the blocking.
 *
 * @package f12_cf7_captcha\core\enforce
 */

namespace f12_cf7_captcha\core\enforce;

use f12_cf7_captcha\core\observe\Agent_Observer;
use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agent_Enforcer {
	/** Settings key under $settings['global'] (default OFF). */
	const SETTING_KEY = 'agent_enforce';

	/**
	 * Capabilities reported to the dashboard on every bundle fetch: which kinds
	 * of rule THIS build can actually carry out. Extend this list in the same
	 * commit that teaches the class a new match type — the two are one
	 * statement, and a list that lags behind is how the dashboard ends up
	 * showing a rule as active that nothing applies.
	 *
	 * Behaviour matching and the spoof match type arrived in 2.10.0; before
	 * that both were silently ignored.
	 */
	const ENFORCER_CAPS = 'behaviors,spoof';

	/**
	 * Headers `F12_TRUSTED_PROXY_HEADER` may name. Same allow-list as
	 * UserData::get_ip_address — an unknown value is ignored, not trusted.
	 */
	const TRUSTED_PROXY_HEADERS = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP' ];

	/**
	 * Headers whose mere presence means REMOTE_ADDR is a proxy hop rather than
	 * the request's origin. Used only to WITHHOLD a spoof accusation, never to
	 * grant trust — so a client that forges them can make us more cautious, never
	 * less. Superset of TRUSTED_PROXY_HEADERS: an unconfigured CDN still counts.
	 */
	const FORWARDING_HEADERS = [
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CLIENT_IP',
		'HTTP_FORWARDED',
	];

	/** Verified policy bundle (decoded array), cached 1h. */
	const BUNDLE_TRANSIENT = 'f12_ss_policy_bundle';
	/** ETag of the last fetched bundle, for conditional refresh. */
	const BUNDLE_ETAG = 'f12_ss_policy_etag';
	/** Refresh claim: at most one bundle refresh per this window. */
	const BUNDLE_FRESH = 'f12_ss_policy_fresh';
	/** kid → raw Ed25519 public key map, cached 1h. */
	const KEYS_TRANSIENT = 'f12_ss_policy_keys';

	/** Bundle refresh cadence (matches the Go enforcer's 5-minute loop). */
	const REFRESH_INTERVAL = 300;
	/** How long a cached bundle stays usable if refreshes stop (fail-open). */
	const BUNDLE_TTL = 3600;

	/** @var bool */
	private static $booted = false;
	/** @var bool Guards the one shutdown-refresh registration per request. */
	private static $refresh_hooked = false;

	/**
	 * Register hooks. Runs the enforcement check EARLY (plugins_loaded) so a
	 * blocked bot is turned away before WordPress renders anything.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		// Priority 1: as early as we can while WordPress core (options,
		// transients, HTTP API) is available.
		add_action( 'plugins_loaded', [ self::class, 'maybe_enforce' ], 1 );
	}

	/**
	 * The enforcement decision for the current request. Fail-open throughout.
	 */
	public static function maybe_enforce(): void {
		try {
			// Never touch wp-admin, cron or CLI — enforcement is for public
			// front-end bot traffic only.
			if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return;
			}
			if ( ! self::is_enabled() ) {
				return;
			}
			// Without libsodium we cannot verify the bundle signature — fail-open
			// rather than trust an unverified policy.
			if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return;
			}

			// Keep the cached bundle warm off the critical path. Registered even
			// when we don't block, so the policy stays fresh with traffic.
			self::hook_refresh();

			$bundle = get_transient( self::BUNDLE_TRANSIENT );
			if ( ! is_array( $bundle ) ) {
				// No verified bundle yet → allow this request; the shutdown
				// refresh will warm the cache for the next one.
				return;
			}
			// Monitor mode (or the server forcing it) never blocks.
			if ( ( $bundle['mode'] ?? 'monitor' ) !== 'enforce' ) {
				return;
			}

			$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
			$path   = self::request_path();

			$input           = self::identify( $ua, $bundle );
			$input['ua']     = $ua;
			$input['path']   = $path;
			$input['method'] = $method;

			$rule = self::evaluate( isset( $bundle['rules'] ) && is_array( $bundle['rules'] ) ? $bundle['rules'] : [], $input );
			if ( $rule === null ) {
				return; // no rule matched → allow (default posture)
			}

			$slug     = $input['slug'];
			$category = $input['category'];

			$action = isset( $rule['action'] ) ? (string) $rule['action'] : 'allow';
			if ( $action === 'deny' ) {
				self::block( 403, 0, $input );
			}
			if ( $action === 'throttle' && ! empty( $bundle['quota_enabled'] ) && ! empty( $rule['rate_limit'] ) ) {
				$key = $slug !== '' ? $slug : ( $category !== '' ? 'cat:' . $category : 'any' );
				list( $ok, $retry ) = self::throttle_ok( $key, $rule['rate_limit'] );
				if ( ! $ok ) {
					self::block( 429, $retry, $input );
				}
			}
			// allow / partial → pass (response-field filtering is not enforced).
		} catch ( \Throwable $e ) {
			// Fail-open: enforcement must NEVER be able to break the site.
		}
	}

	/**
	 * Local enablement gate. Default OFF — enforcement is an explicit choice.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'SILENTSHIELD_ENFORCER' ) && SILENTSHIELD_ENFORCER === false ) {
			return false;
		}
		$settings = get_option( 'f12-cf7-captcha-settings', [] );
		$global   = ( is_array( $settings ) && isset( $settings['global'] ) && is_array( $settings['global'] ) )
			? $settings['global'] : [];
		if ( empty( $global[ self::SETTING_KEY ] ) || (int) $global[ self::SETTING_KEY ] !== 1 ) {
			return false; // default off
		}
		return self::api_key( $settings ) !== '';
	}

	/**
	 * Identify the calling agent from the bundle's agent snapshot: the LONGEST
	 * matching User-Agent token wins; "verified" is true only when the source IP
	 * falls inside that agent's published CIDR ranges (UA strings are trivially
	 * spoofable, IP ranges are not). Web Bot Auth signature verification is a
	 * later increment — until then unsigned rules treat these bots accordingly.
	 *
	 * Returns the same decision context the Go and TypeScript cores build
	 * (agentpolicy.Input), so the three implementations can be read side by side.
	 * The source IP travels in it so the block report does not have to re-derive
	 * it from $_SERVER.
	 *
	 * @return array{slug:string,category:string,behaviors:string[],verified:bool,spoof:bool,ip:string}
	 */
	private static function identify( string $ua, array $bundle ): array {
		$ua_lc     = strtolower( $ua );
		$slug      = '';
		$cat       = '';
		$behaviors = [];
		$best      = 0;
		$agents    = ( isset( $bundle['agents'] ) && is_array( $bundle['agents'] ) ) ? $bundle['agents'] : [];

		$matched_cidrs = [];
		foreach ( $agents as $a ) {
			$tokens = ( isset( $a['ua_tokens'] ) && is_array( $a['ua_tokens'] ) ) ? $a['ua_tokens'] : [];
			foreach ( $tokens as $tok ) {
				$tl = strtolower( (string) $tok );
				if ( $tl !== '' && strpos( $ua_lc, $tl ) !== false && strlen( $tl ) > $best ) {
					$best = strlen( $tl );
					$slug = isset( $a['slug'] ) ? (string) $a['slug'] : '';
					$cat  = isset( $a['category'] ) ? (string) $a['category'] : '';
					// Multi-valued behaviour set. Absent in bundles served before
					// the server started sending it — an empty set keeps the old
					// category-only matching, which is what makes that rollout
					// reversible without touching this plugin.
					$behaviors     = ( isset( $a['behaviors'] ) && is_array( $a['behaviors'] ) ) ? array_map( 'strval', $a['behaviors'] ) : [];
					$matched_cidrs = ( isset( $a['cidrs'] ) && is_array( $a['cidrs'] ) ) ? $a['cidrs'] : [];
				}
			}
		}

		$ip       = self::remote_ip();
		$verified = false;
		$spoof    = false;
		if ( $slug !== '' && ! empty( $matched_cidrs ) ) {
			foreach ( $matched_cidrs as $cidr ) {
				if ( self::ip_in_cidr( $ip, (string) $cidr ) ) {
					$verified = true;
					break;
				}
			}
			// Definitively refuted, not merely unconfirmed: the operator publishes
			// ranges FOR THIS ADDRESS FAMILY and the source sits outside all of
			// them, so the User-Agent is a lie. Three things must hold before we
			// say that, because a spoof rule BLOCKS:
			//  - the address we hold is the origin, not a proxy hop
			//    (see ip_is_conclusive — this is what keeps a site behind a CDN
			//    from turning away the real Googlebot);
			//  - the operator publishes ranges of this address family — accusing
			//    an IPv6 Googlebot because we only hold its IPv4 ranges would
			//    block the real bot;
			//  - it is outside all of them.
			// Anything we cannot check stays unsigned, never spoof: never accuse
			// on doubt.
			if ( ! $verified && self::ip_is_conclusive() && self::has_same_family( $matched_cidrs, $ip ) ) {
				$spoof = true;
			}
		}

		return [
			'slug'      => $slug,
			'category'  => $cat,
			'behaviors' => $behaviors,
			'verified'  => $verified,
			'spoof'     => $spoof,
			'ip'        => $ip,
		];
	}

	/**
	 * Whether the address from remote_ip() is the request's true origin, and may
	 * therefore be used to REFUTE a User-Agent (never to grant "verified" — that
	 * direction is governed by remote_ip() alone).
	 *
	 * True in two cases: the operator declared a trusted proxy header — then we
	 * know how to find the real client when it came through the proxy, and when
	 * it did not, REMOTE_ADDR is the peer that connected — or the request carries
	 * no forwarding header at all, which says the same thing.
	 *
	 * Anything else means REMOTE_ADDR is a proxy hop. On a site behind Cloudflare
	 * or an nginx front-end WITHOUT `F12_TRUSTED_PROXY_HEADER`, every crawler
	 * would otherwise appear outside its operator's published ranges — and a
	 * "block forged identities" rule would turn away the real Googlebot,
	 * GPTBot and every other well-behaved bot on the site.
	 *
	 * The trade-off is deliberate: a bot that forges its User-Agent can also send
	 * an X-Forwarded-For header to escape a spoof rule. It still gets caught by
	 * `unsigned` rules, because it is still not verified. Missing one forgery is
	 * recoverable; blocking every real crawler on every CDN-fronted site is not.
	 */
	private static function ip_is_conclusive(): bool {
		if ( self::trusted_proxy_header() !== '' ) {
			return true;
		}
		foreach ( self::FORWARDING_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether the agent publishes at least one range of the SAME address family
	 * as this source IP — the precondition for calling a mismatch a forgery.
	 *
	 * @param array<int,string> $cidrs
	 */
	private static function has_same_family( array $cidrs, string $ip ): bool {
		$ip_bin = @inet_pton( $ip );
		if ( $ip_bin === false ) {
			return false;
		}
		foreach ( $cidrs as $cidr ) {
			$parts = explode( '/', (string) $cidr, 2 );
			$net   = @inet_pton( $parts[0] );
			if ( $net !== false && strlen( $net ) === strlen( $ip_bin ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Evaluate the ordered rule list, first match wins (mirrors
	 * agentpolicy.Evaluate). Returns the matching rule or null (→ allow).
	 *
	 * The rules come out of a decoded remote policy document, so an entry can be any JSON
	 * value at all — hence array<int,mixed> rather than a promise about its shape, and hence
	 * the is_array() guard on each one below. Declaring the shape here would make that guard
	 * look redundant while the data can still arrive malformed.
	 *
	 * @param array<int,mixed>                                                   $rules
	 * @param array{slug:string,category:string,behaviors:string[],verified:bool,spoof:bool,path?:string,method?:string} $input
	 */
	private static function evaluate( array $rules, array $input ): ?array {
		$path   = isset( $input['path'] ) ? (string) $input['path'] : '/';
		$method = isset( $input['method'] ) ? (string) $input['method'] : 'GET';

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$match = ( isset( $rule['match'] ) && is_array( $rule['match'] ) ) ? $rule['match'] : [];
			if ( ! self::match_applies( $match, $input ) ) {
				continue;
			}
			$pattern = isset( $rule['path_pattern'] ) ? (string) $rule['path_pattern'] : '';
			if ( ! self::path_matches( $pattern, $path ) ) {
				continue;
			}
			$methods = ( isset( $rule['methods'] ) && is_array( $rule['methods'] ) ) ? $rule['methods'] : [];
			if ( ! self::method_matches( $methods, $method ) ) {
				continue;
			}
			return $rule;
		}
		return null;
	}

	/**
	 * Rule match-type semantics (policy.go matchApplies).
	 *
	 * Two of these were missing, and both made the dashboard promise protection
	 * this plugin did not deliver:
	 *
	 *  - `spoof` fell through to the default and never matched, so a site that
	 *    chose to turn away forged identities kept serving them. The Go enforcer
	 *    honoured the same rule from the same bundle.
	 *  - `agent_category` compared the legacy category only. A rule is written
	 *    against a BEHAVIOUR ("agent"), while an agent-class bot carries the
	 *    category "user-fetch" — so "block AI agents" matched nothing at all.
	 *
	 * An unknown match type still returns false: a rule this plugin cannot
	 * understand must not apply, which is the fail-open direction.
	 *
	 * @param array{slug:string,category:string,behaviors:string[],verified:bool,spoof:bool} $input
	 */
	private static function match_applies( array $match, array $input ): bool {
		$type  = isset( $match['type'] ) ? (string) $match['type'] : '';
		$value = isset( $match['value'] ) ? (string) $match['value'] : '';
		switch ( $type ) {
			case 'any':
				return true;
			case 'agent_slug':
				return $input['slug'] !== '' && $input['slug'] === $value;
			case 'agent_category':
				// Category OR any behaviour, exactly like the Go and TS cores:
				// a multi-behaviour bot is hit by the rule for ANY of its
				// behaviours, which gives strictest-wins together with the
				// compiler's blocks-before-throttles order.
				if ( $input['category'] !== '' && $input['category'] === $value ) {
					return true;
				}
				foreach ( $input['behaviors'] as $behavior ) {
					if ( $behavior !== '' && $behavior === $value ) {
						return true;
					}
				}
				return false;
			case 'unsigned':
				return ! $input['verified'];
			case 'spoof':
				return ! empty( $input['spoof'] );
			default:
				return false;
		}
	}

	/**
	 * Path glob: "" or "*" match everything; a trailing "*" is a prefix match;
	 * anything else is exact (policy.go pathMatches). PHP 7.4-safe (no
	 * str_starts_with / str_ends_with).
	 */
	private static function path_matches( string $pattern, string $path ): bool {
		if ( $pattern === '' || $pattern === '*' ) {
			return true;
		}
		if ( substr( $pattern, -1 ) === '*' ) {
			$prefix = substr( $pattern, 0, -1 );
			return strncmp( $path, $prefix, strlen( $prefix ) ) === 0;
		}
		return $path === $pattern;
	}

	/**
	 * Method filter: empty list = any; else case-insensitive membership.
	 *
	 * @param array<int,string> $methods
	 */
	private static function method_matches( array $methods, string $method ): bool {
		if ( empty( $methods ) ) {
			return true;
		}
		$m = strtoupper( trim( $method ) );
		foreach ( $methods as $allowed ) {
			if ( strtoupper( trim( (string) $allowed ) ) === $m ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fixed-window throttle counter backed by transients. Best-effort: transient
	 * get/set is not atomic, so under heavy concurrency the count can lag — good
	 * enough for a single-site limiter, and it always fails OPEN on error.
	 *
	 * @param array{requests?:int,window_sec?:int} $rl
	 * @return array{0:bool,1:int} [allowed, retryAfterSeconds]
	 */
	private static function throttle_ok( string $key, array $rl ): array {
		$requests = isset( $rl['requests'] ) ? (int) $rl['requests'] : 0;
		$window   = isset( $rl['window_sec'] ) ? (int) $rl['window_sec'] : 0;
		if ( $requests <= 0 || $window <= 0 ) {
			return [ true, 0 ];
		}
		$now    = time();
		$bucket = (int) floor( $now / $window );
		$tkey   = 'f12_ss_thr_' . md5( $key ) . '_' . $bucket;

		$count = (int) get_transient( $tkey );
		$count++;
		set_transient( $tkey, $count, $window * 2 );

		if ( $count > $requests ) {
			$retry = ( ( $bucket + 1 ) * $window ) - $now;
			return [ false, $retry < 1 ? 1 : $retry ];
		}
		return [ true, 0 ];
	}

	/**
	 * Send the block response and stop. text/plain, no-cache, and (for 429) a
	 * Retry-After header.
	 *
	 * @param array<string,mixed> $input Decision context, for the block report.
	 */
	private static function block( int $status, int $retry, array $input = [] ): void {
		if ( function_exists( 'status_header' ) ) {
			status_header( $status );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( $retry > 0 && ! headers_sent() ) {
			header( 'Retry-After: ' . $retry );
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
		}
		echo $status === 429 ? 'Too Many Requests' : 'Forbidden';

		// Hand the response to the visitor BEFORE the report goes out — the same
		// order Agent_Observer::flush uses, and the only way the telemetry is
		// genuinely off the critical path. "Non-blocking" still costs a DNS
		// lookup and a TCP connect, and a block path is exactly where requests
		// arrive in bursts.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request();
		}
		self::report_block( $status, $input );
		exit;
	}

	/**
	 * Report that this request was blocked (plan/65), feeding the dashboard's
	 * "blocked bots" report. Called after the response has been flushed.
	 *
	 * Gated on the observation setting: "Observe AI crawlers" is the operator's
	 * single answer to "may this site send crawler sightings to the dashboard",
	 * and a block report is a sighting. Turning observation off therefore stops
	 * the reporting — enforcement itself keeps working, blocks simply stop being
	 * reported. Without this a site that switched telemetry off would still have
	 * transmitted the access data of every blocked request.
	 *
	 * Best-effort — every error is swallowed (fail-open).
	 *
	 * @param array<string,mixed> $input Decision context from identify().
	 */
	private static function report_block( int $status, array $input ): void {
		try {
			if ( ! Agent_Observer::is_enabled() ) {
				return;
			}

			$base = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
			$url  = rtrim( $base, '/' ) . '/agent/telemetry';

			// Everything here was already resolved on the decision path — no
			// second read of $_SERVER, and the report describes exactly the
			// request that was judged.
			$sighting = [
				'ua'      => isset( $input['ua'] ) ? (string) $input['ua'] : '',
				'ip'      => isset( $input['ip'] ) ? (string) $input['ip'] : '',
				'path'    => isset( $input['path'] ) ? (string) $input['path'] : '/',
				'method'  => isset( $input['method'] ) ? (string) $input['method'] : 'GET',
				'outcome' => $status === 429 ? 'throttle' : 'deny',
			];

			wp_remote_post( $url, [
				'timeout'  => 3,
				'blocking' => false, // the response is already out; do not linger
				'headers'  => [ 'Content-Type' => 'application/json', 'x-api-key' => self::api_key() ],
				'body'     => wp_json_encode( [ 'sightings' => [ $sighting ] ] ),
			] );
		} catch ( \Throwable $e ) {
			// best-effort — reporting must never break the block path
		}
	}

	// --- bundle refresh (off the request path) ------------------------------

	/** Register the one-shot shutdown refresh for this request. */
	private static function hook_refresh(): void {
		if ( self::$refresh_hooked ) {
			return;
		}
		self::$refresh_hooked = true;
		add_action( 'shutdown', [ self::class, 'maybe_refresh_bundle' ], PHP_INT_MAX );
	}

	/**
	 * Refresh the verified bundle at most once per REFRESH_INTERVAL, AFTER the
	 * response has been delivered (fastcgi_finish_request), so the blocking
	 * fetch never adds to the visitor's wait. Fail-open: any error keeps the
	 * previously cached bundle.
	 */
	public static function maybe_refresh_bundle(): void {
		try {
			if ( ! self::is_enabled() || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return;
			}
			if ( get_transient( self::BUNDLE_FRESH ) !== false ) {
				return; // refreshed recently
			}
			// Claim the slot first so concurrent requests don't all fetch.
			set_transient( self::BUNDLE_FRESH, 1, self::REFRESH_INTERVAL );

			if ( function_exists( 'fastcgi_finish_request' ) ) {
				@fastcgi_finish_request();
			}

			$keys = self::signing_keys();
			if ( empty( $keys ) ) {
				return;
			}

			$base = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
			$url  = rtrim( $base, '/' ) . '/agent/policy';

			$headers = [
				'x-api-key'               => self::api_key(),
				'X-SilentShield-Enforcer' => self::enforcer_header(),
			];
			$etag    = get_transient( self::BUNDLE_ETAG );
			if ( is_string( $etag ) && $etag !== '' ) {
				$headers['If-None-Match'] = $etag;
			}

			$response = wp_remote_get( $url, [ 'timeout' => 5, 'headers' => $headers ] );
			if ( is_wp_error( $response ) ) {
				return;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code === 304 ) {
				return; // unchanged — keep the cached bundle
			}
			if ( $code !== 200 ) {
				return;
			}

			$bundle = self::verify_bundle( wp_remote_retrieve_body( $response ), $keys );
			if ( ! is_array( $bundle ) ) {
				return;
			}
			set_transient( self::BUNDLE_TRANSIENT, $bundle, self::BUNDLE_TTL );
			$new_etag = wp_remote_retrieve_header( $response, 'etag' );
			if ( is_string( $new_etag ) && $new_etag !== '' ) {
				set_transient( self::BUNDLE_ETAG, $new_etag, self::BUNDLE_TTL );
			}
		} catch ( \Throwable $e ) {
			Logger::getInstance()->debug( 'Agent enforce refresh failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $e->getMessage(),
			] );
		}
	}

	/**
	 * Fetch + cache the bundle signing keys from the public well-known endpoint
	 * (host root, NOT under /api/v1). Returns kid → raw 32-byte Ed25519 key.
	 *
	 * @return array<string,string>
	 */
	private static function signing_keys(): array {
		$cached = get_transient( self::KEYS_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$base = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		// Strip a trailing /api/vN so the well-known path sits at the host root.
		$host = preg_replace( '#/api/v\d+/?$#', '', rtrim( $base, '/' ) );
		$url  = rtrim( (string) $host, '/' ) . '/.well-known/silentshield-agent-keys';

		$response = wp_remote_get( $url, [ 'timeout' => 5 ] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['keys'] ) || ! is_array( $data['keys'] ) ) {
			return [];
		}

		$keys = [];
		foreach ( $data['keys'] as $k ) {
			$kid = isset( $k['kid'] ) ? (string) $k['kid'] : '';
			$b64 = isset( $k['key'] ) ? (string) $k['key'] : '';
			if ( $kid === '' || $b64 === '' ) {
				continue;
			}
			$raw = base64_decode( $b64, true );
			if ( $raw !== false && strlen( $raw ) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) {
				$keys[ $kid ] = $raw;
			}
		}
		if ( ! empty( $keys ) ) {
			set_transient( self::KEYS_TRANSIENT, $keys, self::BUNDLE_TTL );
		}
		return $keys;
	}

	/**
	 * Verify a signed-bundle envelope and return the decoded bundle, or null.
	 * Envelope: {payload: base64(JSON), alg: "ed25519", kid, sig: base64(64B)}.
	 * The signed bytes are the raw payload JSON (no canonicalisation).
	 *
	 * @param array<string,string> $keys kid → raw public key
	 */
	private static function verify_bundle( string $body, array $keys ): ?array {
		$env = json_decode( $body, true );
		if ( ! is_array( $env ) ) {
			return null;
		}
		if ( ( $env['alg'] ?? '' ) !== 'ed25519' ) {
			return null;
		}
		$kid = isset( $env['kid'] ) ? (string) $env['kid'] : '';
		if ( $kid === '' || ! isset( $keys[ $kid ] ) ) {
			return null;
		}
		$payload = base64_decode( (string) ( $env['payload'] ?? '' ), true );
		$sig     = base64_decode( (string) ( $env['sig'] ?? '' ), true );
		if ( $payload === false || $sig === false || strlen( $sig ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
			return null;
		}
		if ( ! sodium_crypto_sign_verify_detached( $sig, $payload, $keys[ $kid ] ) ) {
			return null;
		}
		$bundle = json_decode( $payload, true );
		if ( ! is_array( $bundle ) ) {
			return null;
		}
		// Reject a newer wire format we can't be sure we understand.
		if ( (int) ( $bundle['format_version'] ?? 1 ) > 1 ) {
			return null;
		}
		return $bundle;
	}

	// --- helpers ------------------------------------------------------------

	/**
	 * The `X-SilentShield-Enforcer` value sent with every bundle fetch: say who we
	 * are and what we can carry out, so the dashboard can stop reporting a rule as
	 * active on an installation that cannot execute it. The plugin version travels
	 * with it, so the operator sees which build is actually running on that site.
	 */
	private static function enforcer_header(): string {
		$version = defined( 'FORGE12_CAPTCHA_VERSION' ) ? (string) FORGE12_CAPTCHA_VERSION : '';
		return 'wordpress/' . $version . ' caps=' . self::ENFORCER_CAPS;
	}

	/** The publishable site key used as x-api-key. */
	private static function api_key( $settings = null ): string {
		if ( $settings === null ) {
			$settings = get_option( 'f12-cf7-captcha-settings', [] );
		}
		if ( is_array( $settings ) && ! empty( $settings['beta']['beta_captcha_api_key'] ) ) {
			return (string) $settings['beta']['beta_captcha_api_key'];
		}
		return '';
	}

	/**
	 * Source IP for CIDR verification. REMOTE_ADDR by default — forwarded headers
	 * are spoofable, and a spoofable IP must never be able to upgrade a bot to
	 * "verified".
	 *
	 * The one exception is the plugin-wide `F12_TRUSTED_PROXY_HEADER` contract
	 * (see UserData::get_ip_address and docs/configuration.md): defining that
	 * constant IS the operator declaring that the header comes from a proxy they
	 * control. Honouring it here is what makes verification work at all behind a
	 * CDN, where REMOTE_ADDR is the edge and never a crawler's published range.
	 */
	private static function remote_ip(): string {
		$trusted = self::trusted_proxy_header();
		if ( $trusted !== '' && ! empty( $_SERVER[ $trusted ] ) ) {
			// sanitize_text_field, like UserData::get_ip_address — the value is an
			// address list, and it arrives from a header rather than the socket.
			$client = self::first_in_chain( sanitize_text_field( wp_unslash( $_SERVER[ $trusted ] ) ) );
			if ( $client !== '' ) {
				return $client;
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * First entry of a forwarding chain — X-Forwarded-For grows by appending, so
	 * the leftmost entry is the original client.
	 */
	private static function first_in_chain( string $value ): string {
		$chain = explode( ',', $value );
		return trim( (string) reset( $chain ) );
	}

	/**
	 * The configured trusted proxy header, or '' when none is set. Mirrors
	 * UserData::get_ip_address's allow-list so both agree on what "trusted" means.
	 */
	private static function trusted_proxy_header(): string {
		if ( ! defined( 'F12_TRUSTED_PROXY_HEADER' ) ) {
			return '';
		}
		$header = (string) F12_TRUSTED_PROXY_HEADER;
		return in_array( $header, self::TRUSTED_PROXY_HEADERS, true ) ? $header : '';
	}

	/** Request path with the query string stripped. */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return ( is_string( $path ) && $path !== '' ) ? $path : '/';
	}

	/**
	 * Whether $ip is inside $cidr. Supports IPv4 and IPv6 via inet_pton bitmask.
	 */
	private static function ip_in_cidr( string $ip, string $cidr ): bool {
		if ( $ip === '' || strpos( $cidr, '/' ) === false ) {
			return false;
		}
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$bits = (int) $bits;

		$ip_bin     = @inet_pton( $ip );
		$subnet_bin = @inet_pton( $subnet );
		if ( $ip_bin === false || $subnet_bin === false || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false; // family mismatch (v4 vs v6) or invalid
		}

		$bytes = strlen( $ip_bin );
		if ( $bits < 0 || $bits > $bytes * 8 ) {
			return false;
		}

		$full = intdiv( $bits, 8 );
		if ( $full > 0 && strncmp( $ip_bin, $subnet_bin, $full ) !== 0 ) {
			return false;
		}
		$rem = $bits % 8;
		if ( $rem === 0 ) {
			return true;
		}
		$mask     = chr( 0xff << ( 8 - $rem ) & 0xff );
		return ( ord( $ip_bin[ $full ] ) & ord( $mask ) ) === ( ord( $subnet_bin[ $full ] ) & ord( $mask ) );
	}
}
