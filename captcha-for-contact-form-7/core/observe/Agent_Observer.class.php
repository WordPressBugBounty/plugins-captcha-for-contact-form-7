<?php
/**
 * Agent Observer — passive, server-side AI-agent observation (plan/54 Inc1).
 *
 * Records which AI agents/crawlers (GPTBot, ClaudeBot, PerplexityBot, …) hit the
 * site and forwards a minimal sighting to the SilentShield ingest
 * (`POST {F12_CAPTCHA_API_URL}/agent/telemetry`, header `x-api-key`). It reuses
 * the publishable, domain-bound `beta_captcha_api_key` the plugin already stores —
 * the customer installs nothing extra.
 *
 * Design guarantees:
 *  - **Never touches TTFB / never blocks:** the send happens on `shutdown` AFTER
 *    `fastcgi_finish_request()` has delivered the response to the visitor. On
 *    non-FPM stacks it falls back to a non-blocking request.
 *  - **Fail-open:** any error is swallowed; observation can never disrupt a page,
 *    a form, or the captcha.
 *  - **Data minimisation / GDPR:** only *bot-candidate* requests are ever sent
 *    (UA matches a known agent OR the request carries a Web Bot Auth signature) —
 *    human traffic produces no POST. IP/UA are hashed server-side (never stored
 *    raw). Lawful basis: legitimate interest (Art. 6(1)(f)); no cookie/consent.
 *  - **Kill-switch:** local toggle `global.agent_observe` (default on) + the
 *    `SILENTSHIELD_OBSERVER` override constant + the server-side ingest flag
 *    (`agent_gateway_observe.enabled`) which drops everything until we ramp it up.
 *
 * @package f12_cf7_captcha\core\observe
 */

namespace f12_cf7_captcha\core\observe;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agent_Observer {
	/** Settings key under $settings['global']. */
	const SETTING_KEY = 'agent_observe';

	/** Transient holding the fetched bot-directory UA tokens (7 days). */
	const DIR_TRANSIENT = 'f12_ss_bot_directory';

	/** Transient marking the directory as recently refreshed (1 day). */
	const DIR_FRESH = 'f12_ss_bot_directory_fresh';

	/** @var array<int,array<string,mixed>> Request-local sighting buffer. */
	private static $buffer = [];

	/** @var bool */
	private static $booted = false;

	/**
	 * Embedded fallback list of AI-agent User-Agent tokens (case-insensitive
	 * substring match), used until the control-plane bot directory has been
	 * fetched (see tokens() / maybe_refresh_directory()). `Google-Extended` is
	 * intentionally omitted — it is a robots.txt policy token that makes no
	 * requests, so it can never appear in traffic.
	 *
	 * @var string[]
	 */
	private static $bot_tokens = [
		'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User',
		'anthropic-ai', 'PerplexityBot', 'Perplexity-User', 'Google-Agent',
		'Bingbot', 'Applebot', 'Amazonbot', 'CCBot', 'Bytespider',
		'meta-externalagent', 'DuckAssistBot', 'cohere-ai', 'YouBot',
		'Diffbot', 'ImagesiftBot', 'Timpibot', 'Mistral',
	];

	/**
	 * Register hooks. Safe to call at plugin bootstrap; only registers callbacks.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Capture as early as safely possible on front-end requests.
		add_action( 'template_redirect', [ self::class, 'capture' ], 1 );
		// Flush AFTER other shutdown handlers so we can end the response and send
		// telemetry without adding to the visitor's wait.
		add_action( 'shutdown', [ self::class, 'flush' ], PHP_INT_MAX );
		// Transparency (plan/54 Inc6): one-time, dismissible admin notice about
		// the new observation feature.
		add_action( 'admin_init', [ self::class, 'maybe_dismiss_notice' ] );
		add_action( 'admin_notices', [ self::class, 'maybe_render_notice' ] );
	}

	/** Option flag: the admin has dismissed the observation announcement. */
	const NOTICE_OPTION = 'f12_ss_observe_notice_dismissed';

	/**
	 * Persist dismissal of the observation notice (nonce-protected link click).
	 */
	public static function maybe_dismiss_notice(): void {
		try {
			if ( empty( $_GET['f12_ss_observe_dismiss'] ) ) {
				return;
			}
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'f12_ss_observe_dismiss' ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			update_option( self::NOTICE_OPTION, 1 );
		} catch ( \Throwable $e ) {
			// Fail-open: never break wp-admin.
		}
	}

	/**
	 * Announce AI-agent observation once, with how to turn it off — the standard
	 * transparency practice for a new server-side telemetry feature.
	 */
	public static function maybe_render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || get_option( self::NOTICE_OPTION ) ) {
			return;
		}
		if ( ! defined( 'FORGE12_CAPTCHA_SLUG' ) ) {
			return;
		}
		$settings_url = admin_url( 'admin.php?page=' . FORGE12_CAPTCHA_SLUG );
		$dismiss_url  = wp_nonce_url(
			add_query_arg( 'f12_ss_observe_dismiss', 1 ),
			'f12_ss_observe_dismiss'
		);
		?>
		<div class="notice notice-info">
			<p>
				<strong>SilentShield:</strong>
				<?php esc_html_e( 'This update can now show you which AI crawlers (GPTBot, ClaudeBot, PerplexityBot …) visit your site. It runs server-side, sets no cookies, blocks nothing, and only records detected bots — never your human visitors. IP addresses are pseudonymised. Legal basis: legitimate interest (Art. 6(1)(f) GDPR).', 'captcha-for-contact-form-7' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Manage in settings', 'captcha-for-contact-form-7' ); ?></a>
				&middot;
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'captcha-for-contact-form-7' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether observation is currently enabled (local gates only; the server-side
	 * ingest flag is the ultimate kill-switch and drops batches when off).
	 */
	public static function is_enabled(): bool {
		if ( defined( 'SILENTSHIELD_OBSERVER' ) && SILENTSHIELD_OBSERVER === false ) {
			return false;
		}
		$settings = get_option( 'f12-cf7-captcha-settings', [] );
		$global   = ( is_array( $settings ) && isset( $settings['global'] ) && is_array( $settings['global'] ) )
			? $settings['global'] : [];
		// Default ON: only an explicit 0 disables.
		if ( isset( $global[ self::SETTING_KEY ] ) && (int) $global[ self::SETTING_KEY ] === 0 ) {
			return false;
		}
		return self::api_key( $settings ) !== '';
	}

	/**
	 * The publishable, domain-bound site key used as `x-api-key`.
	 *
	 * @param array|null $settings Optional pre-read settings.
	 */
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
	 * Observe the current request if it looks like an AI agent. Runs on
	 * `template_redirect` (front-end only). Buffers at most one sighting.
	 */
	public static function capture(): void {
		try {
		// Only real, unauthenticated front-end GET/HEAD requests. Bots are
		// anonymous GETs; skip admin/cron/ajax/rest/login + logged-in humans.
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_user_logged_in() ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( $method !== 'GET' && $method !== 'HEAD' ) {
			return;
		}

		$ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$signature = isset( $_SERVER['HTTP_SIGNATURE'] ) ? (string) wp_unslash( $_SERVER['HTTP_SIGNATURE'] ) : '';

		// Bot-candidate filter — never emit for human traffic.
		if ( ! self::is_candidate( $ua, $signature ) ) {
			return;
		}
		// Cheap gate before buffering.
		if ( ! self::is_enabled() ) {
			return;
		}

		$sighting = [
			'ua'     => $ua,                    // hashed server-side, never stored raw
			'ip'     => self::client_ip(),      // hashed server-side
			'path'   => self::request_path(),   // query string dropped
			'method' => $method,
		];

		// Forward Web Bot Auth signature material only when the request is signed.
		if ( $signature !== '' ) {
			$sighting['signature'] = $signature;
			if ( ! empty( $_SERVER['HTTP_SIGNATURE_INPUT'] ) ) {
				$sighting['signature_input'] = (string) wp_unslash( $_SERVER['HTTP_SIGNATURE_INPUT'] );
			}
			if ( ! empty( $_SERVER['HTTP_SIGNATURE_AGENT'] ) ) {
				$sighting['signature_agent'] = (string) wp_unslash( $_SERVER['HTTP_SIGNATURE_AGENT'] );
			}
			$sighting['authority'] = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
			$sighting['scheme']    = is_ssl() ? 'https' : 'http';
		}

		self::$buffer[] = $sighting;
		} catch ( \Throwable $e ) {
			// Fail-open: observation must NEVER be able to break a page.
		}
	}

	/**
	 * Is this a known agent (by UA token) or a signed bot request?
	 */
	private static function is_candidate( string $ua, string $signature ): bool {
		if ( $signature !== '' ) {
			return true;
		}
		if ( $ua === '' ) {
			return false;
		}
		foreach ( self::tokens() as $token ) {
			if ( stripos( $ua, $token ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The active UA-token list: the control-plane bot directory when present
	 * (cheap transient read, no HTTP on the request path), else the embedded
	 * fallback. Refreshed off the critical path in maybe_refresh_directory().
	 *
	 * @return string[]
	 */
	private static function tokens(): array {
		$cached = get_transient( self::DIR_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		return self::$bot_tokens;
	}

	/**
	 * Refresh the bot directory from the control plane at most once per day.
	 * Called from flush() — i.e. AFTER fastcgi_finish_request() — so the blocking
	 * fetch never touches the visitor's request. Fail-open: any error keeps the
	 * previous cache (or the embedded fallback).
	 */
	private static function maybe_refresh_directory(): void {
		// Refreshed recently → nothing to do.
		if ( get_transient( self::DIR_FRESH ) !== false ) {
			return;
		}
		// Claim the daily slot first so concurrent bot requests don't all fetch.
		set_transient( self::DIR_FRESH, 1, DAY_IN_SECONDS );

		$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		$url      = rtrim( $base_url, '/' ) . '/agent/bot-directory';

		$response = wp_remote_get( $url, [ 'timeout' => 5 ] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['agents'] ) || ! is_array( $data['agents'] ) ) {
			return;
		}

		$tokens = [];
		foreach ( $data['agents'] as $agent ) {
			if ( empty( $agent['ua_tokens'] ) || ! is_array( $agent['ua_tokens'] ) ) {
				continue;
			}
			foreach ( $agent['ua_tokens'] as $token ) {
				$token = trim( (string) $token );
				if ( $token !== '' ) {
					$tokens[] = $token;
				}
			}
		}

		if ( ! empty( $tokens ) ) {
			set_transient( self::DIR_TRANSIENT, array_values( array_unique( $tokens ) ), 7 * DAY_IN_SECONDS );
		}
	}

	/**
	 * Deliver the response first, then POST the buffered sightings. Best-effort.
	 */
	public static function flush(): void {
		if ( empty( self::$buffer ) ) {
			return;
		}

		// End the response to the visitor BEFORE any network I/O — telemetry must
		// never add to TTFB. On PHP-FPM this closes the connection; PHP keeps
		// running so the POST below is off the critical path.
		$finished = false;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request();
			$finished = true;
		}

		$batch        = self::$buffer;
		self::$buffer = [];

		if ( ! self::is_enabled() ) {
			return;
		}
		$key = self::api_key();
		if ( $key === '' ) {
			return;
		}

		$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		$url      = rtrim( $base_url, '/' ) . '/agent/telemetry';

		try {
			$response = wp_remote_post( $url, [
				'headers'  => [
					'Content-Type' => 'application/json; charset=utf-8',
					'x-api-key'    => $key,
				],
				'body'     => wp_json_encode( [ 'sightings' => $batch ] ),
				// Once the response is flushed we can block for reliable delivery
				// at no TTFB cost; otherwise stay non-blocking / short.
				'timeout'  => $finished ? 5 : 1,
				'blocking' => $finished,
			] );

			if ( is_wp_error( $response ) ) {
				Logger::getInstance()->debug( 'Agent observe send failed', [
					'plugin' => 'f12-cf7-captcha',
					'error'  => $response->get_error_message(),
				] );
			}
		} catch ( \Throwable $e ) {
			// Fail-open: swallow everything.
			Logger::getInstance()->debug( 'Agent observe send exception', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $e->getMessage(),
			] );
		}

		// Keep the bot directory fresh (at most once/day) — off the critical path,
		// after the response has already been delivered above.
		self::maybe_refresh_directory();
	}

	/**
	 * Client IP as seen by the site. Sent raw to the ingest, which hashes it
	 * (keyed daily salt) and never stores it raw.
	 */
	private static function client_ip(): string {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$value = (string) wp_unslash( $_SERVER[ $header ] );
				$parts = explode( ',', $value );
				$ip    = trim( $parts[0] );
				if ( $ip !== '' ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Request path with the query string stripped.
	 */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return ( is_string( $path ) && $path !== '' ) ? $path : '/';
	}
}
