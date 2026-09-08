<?php

namespace f12_cf7_captcha\core\protection\api;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\log\AuditLog;
use f12_cf7_captcha\core\log\BlockLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends BaseProtection {
	private string $api_endpoint;

	/**
	 * Last API response data (available after is_spam() call).
	 * Used by the block log to capture reason codes and score breakdown.
	 */
	private ?array $last_api_response = null;

	/**
	 * Whether this submission was put to the SilentShield server.
	 *
	 * The server writes a telemetry row keyed by the nonce as soon as it hears about a
	 * submission, so anything reported on top of that would be a second row for the same
	 * event — and that number is what the customer is shown and eventually billed for.
	 * Protection reads this before reporting a local block; see report_local_block().
	 *
	 * True from the moment the verify request is *sent*, not when it succeeds: an unreachable
	 * API does not undo the telemetry the behaviour library already delivered.
	 */
	private bool $server_contacted = false;

	/**
	 * The form this submission belongs to, as `<integration>:<form id>`.
	 *
	 * Set by Protection, which owns the render context. Empty when the integration could not
	 * name the form — the report is still sent, just without saying which form was hit.
	 */
	private string $form_key = '';

	/**
	 * How much of a form key the endpoint accepts.
	 */
	private const FORM_KEY_MAX_LENGTH = 64;

	/**
	 * Labels the surrounding form for behavior-captcha.js.
	 *
	 * The library only collects behaviour for a form carrying data-ss-init="1", and no
	 * behaviour means no nonce, which is_spam() answers with API_NO_NONCE. It has to be
	 * inline and self-locating (document.currentScript) because the markup is injected into
	 * forms this plugin does not own and therefore cannot select afterwards.
	 */
	private const MARKER_SCRIPT = '<script>
(function(){
	var el = document.currentScript;
	if (!el) return;
	var form = el.closest("form");
	if (!form || form.dataset.ssInit === "1") return;
	form.dataset.ssInit = "1";
	form.dataset.ssMode = "protect";
	form.dataset.ssSource = "rule";
})();
</script>';

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );
		$this->set_message_on_init( function () {
			return __( 'behavior-protection', 'captcha-for-contact-form-7' );
		} );

		$base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
		$this->api_endpoint = rtrim( $base_url, '/' ) . '/captcha/verify-nonce';
	}

	/**
	 * Get the last API response data (reason_codes, score, score_breakdown).
	 */
	public function get_last_api_response(): ?array {
		return $this->last_api_response;
	}

	public function is_enabled(): bool {
		$raw_setting = $this->Controller->get_settings( 'beta_captcha_enable', 'beta' );

		if ( $raw_setting === '' || $raw_setting === null ) {
			$raw_setting = 0;
		}

		$is_enabled = (int) $raw_setting === 1;

		return (bool) apply_filters( 'f12-cf7-captcha-skip-validation-api', $is_enabled );
	}

	public function success(): void {
	}

	/**
	 * Render the field this module validates.
	 *
	 * is_spam() refuses every submission that arrives without a `behavior_nonce`
	 * (API_NO_NONCE), and the nonce is only ever produced for a form the behaviour library
	 * was told to track. Both halves of that come from here.
	 *
	 * This lived in BaseController::get_captcha_html() until 2.15.4. Five renderers fetched
	 * Protection::get_captcha() directly, never reached that method, and shipped forms the
	 * API could only ever reject — every genuine visitor answered as a bot (ticket #7).
	 * Rendering from the module that validates removes the bypass: no render path can obtain
	 * a captcha without obtaining this too, and one added tomorrow inherits that for free.
	 *
	 * The two guards below are is_spam()'s own, in the same order and deliberately so:
	 * whatever makes the validator demand the nonce has to be exactly what makes the
	 * renderer emit it, or the two drift apart again.
	 *
	 * @param mixed ...$args Unused. Part of the module render signature.
	 *
	 * @return string The nonce field and the marker script, or '' when the API is not
	 *                validating anything.
	 */
	public function get_captcha( ...$args ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

		if ( $api_key === '' || $api_key === null ) {
			return '';
		}

		return '<input type="hidden" name="behavior_nonce" value="" />' . self::MARKER_SCRIPT;
	}

    public function is_spam(): bool {

        $this->server_contacted = false;

        if ( ! $this->is_enabled() ) {
            return false;
        }

        $api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

        if ( $api_key === '' || $api_key === null ) {
            return false;
        }

        // Determine behavior_nonce (directly or from formData)
        $nonce = null;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        if ( ! empty( $_POST['behavior_nonce'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
            $nonce = sanitize_text_field( wp_unslash( $_POST['behavior_nonce'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
        } elseif ( ! empty( $_POST['formData'] ) || ! empty( $_POST['data'] ) ) {
			// Avada & Fluent Forms special cases. Avada uses "formData". FluentForms uses "data"
	        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by the calling compatibility controller; sanitized below
	        $raw = isset( $_POST['formData'] ) ? wp_unslash( $_POST['formData'] ) : wp_unslash( $_POST['data'] );

	        // Extract only behavior_nonce instead of parsing the entire string (prevents DoS via deeply nested keys)
	        if ( is_string( $raw ) && preg_match( '/(?:^|&)behavior_nonce=([^&]*)/', $raw, $matches ) ) {
		        $nonce = sanitize_text_field( urldecode( $matches[1] ) );
	        }
        }

        if ( empty( $nonce ) ) {
            $is_spam = true; // no nonce -> suspicious / block

            // Log missing nonce to block log
            $this->maybe_log_api_block( 'API_NO_NONCE', 'No behavior nonce was submitted with the form' );

            // Audit it as well. The block log above is silently dropped unless
            // protection_detailed_tracking is on, which it is not by default —
            // so without this the most common block reason leaves no trace at
            // all, and a site whose client.js never loaded looks identical to
            // one that is merely being hit by no-JS bots.
            AuditLog::log(
                AuditLog::TYPE_API,
                'API_NO_NONCE',
                AuditLog::SEVERITY_WARNING,
                'Submission blocked: no behavior nonce was submitted. Either the visitor ran no JavaScript, or client.js failed to load from the SilentShield API.',
                [ 'endpoint' => $this->api_endpoint ]
            );

            // Report the block to the API so no-JS / no-nonce bots — which never
            // load the widget and therefore never emit any telemetry — are still
            // counted in the "bots blocked" statistics. Fire-and-forget: the
            // submission is already blocked locally, so we neither wait for nor
            // depend on the response.
            $this->report_block_to_api( $api_key, 'no_nonce', $this->form_key );
        } else {
            // Use verbose=1 when detailed tracking is enabled to get score breakdown
            $endpoint = $this->api_endpoint;
            if ( BlockLog::is_enabled() ) {
                $endpoint .= ( strpos( $endpoint, '?' ) === false ? '?' : '&' ) . 'verbose=1';
            }

            // The page the form was submitted from. Sent so the dashboard can
            // name a form the server checks but no scan ever found — this
            // server-to-server call carries no Referer of its own, so this is
            // the only way that page reaches SilentShield. It never affects the
            // verdict. wp_get_referer() checks the _wp_http_referer field and
            // the HTTP_REFERER header; false (stripped / direct POST) simply
            // means no page is sent.
            $verify_body = [ 'nonce' => $nonce ];
            $page_url    = wp_get_referer();
            if ( is_string( $page_url ) && $page_url !== '' ) {
                $verify_body['page_url'] = mb_substr( $page_url, 0, 1024 );
            }

            // From here on the server knows about this submission — it has the nonce, and the
            // behaviour library that produced the nonce has already delivered its telemetry.
            // Set before the call, not after: whether the request succeeds changes nothing
            // about what the server already holds, and a second report would double the count.
            $this->server_contacted = true;

            $response = wp_remote_post( $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key'      => $api_key,
                ],
                'body'    => wp_json_encode( $verify_body ),
                'timeout' => 5,
            ] );

            if ( is_wp_error( $response ) ) {
                $fail_closed = apply_filters( 'f12-cf7-captcha-api-fail-closed', false );

                $this->get_logger()->error( 'API request failed', [
                    'plugin'    => 'f12-cf7-captcha',
                    'error'     => $response->get_error_message(),
                    'fail_mode' => $fail_closed ? 'closed' : 'open',
                ] );
                $is_spam = (bool) $fail_closed;

                AuditLog::log(
                    AuditLog::TYPE_API,
                    'API_VERIFY_UNREACHABLE',
                    AuditLog::SEVERITY_ERROR,
                    sprintf( 'SilentShield verify API unreachable: %s (fail-%s)', $response->get_error_message(), $fail_closed ? 'closed' : 'open' ),
                    [ 'endpoint' => $this->api_endpoint, 'error' => $response->get_error_message(), 'fail_closed' => $fail_closed ]
                );

                if ( $is_spam ) {
                    $this->maybe_log_api_block( 'API_UNREACHABLE', 'SilentShield API unreachable (fail-closed mode)' );
                }
            } else {
                $raw_body  = wp_remote_retrieve_body( $response );
                $data      = json_decode( $raw_body, true );
                $http_code = wp_remote_retrieve_response_code( $response );
                $this->last_api_response = $data;

                // Failed calls are always audited — they are rare, and the body is
                // the only thing that explains them. Successful ones are opt-in via
                // protection_api_log_responses, because that would otherwise write a
                // row per submission and bury the audit log on a busy site.
                //
                // Either way the body is recorded: the block and mail logs keep a
                // whitelist of fields, so a verdict cannot be reconstructed from them
                // once the API starts returning something the whitelist does not know.
                $is_error = ( $http_code >= 400 || $data === null );

                if ( $is_error || self::should_log_responses() ) {
                    AuditLog::log(
                        AuditLog::TYPE_API,
                        $is_error ? 'API_VERIFY_ERROR_RESPONSE' : 'API_VERIFY_RESPONSE',
                        $is_error
                            ? ( $http_code >= 500 ? AuditLog::SEVERITY_ERROR : AuditLog::SEVERITY_WARNING )
                            : AuditLog::SEVERITY_INFO,
                        $is_error
                            ? sprintf( 'SilentShield verify API returned HTTP %d', $http_code )
                            : sprintf(
                                'SilentShield verify API returned HTTP %d, verdict=%s',
                                $http_code,
                                isset( $data['verdict'] ) ? (string) $data['verdict'] : 'unknown'
                            ),
                        [
                            'endpoint'      => $this->api_endpoint,
                            'http_code'     => $http_code,
                            'body_null'     => $data === null,
                            'response_body' => self::truncate_body( $raw_body ),
                        ]
                    );
                }

                if ( empty( $data['ok'] ) || $data['verdict'] !== 'human' ) {
                    $this->get_logger()->info( 'Spam-Check completed. Found spam.', [
                        'plugin'       => 'f12-cf7-captcha',
                        'protection'   => 'API',
                        'data'         => $data,
                        'api-endpoint' => $this->api_endpoint,
                        'api-key'      => substr( $api_key, 0, 4 ) . '***',
                    ] );
                    $is_spam = true;

                    // Log with API details
                    $verdict     = $data['verdict'] ?? 'unknown';
                    $score       = $data['confidence'] ?? 0.0;
                    $reason_code = $verdict === 'bot' ? 'API_VERDICT_BOT' : 'API_VERDICT_SUSPICIOUS';
                    $detail      = sprintf( 'SilentShield API: verdict=%s, score=%.3f', $verdict, $score );

                    $extra = [
                        'score'        => $score,
                        'reason_codes' => $data['reason_codes'] ?? [],
                        'verdict'      => $verdict,
                    ];
                    if ( isset( $data['score_breakdown'] ) ) {
                        $extra['meta'] = [ 'score_breakdown' => $data['score_breakdown'] ];
                    }

                    $this->maybe_log_api_block( $reason_code, $detail, $extra );
                } else {
                    $is_spam = false;
                }
            }
        }

        return $is_spam;
    }

    /**
     * Whether this submission was put to the SilentShield server.
     *
     * Protection asks before reporting a local block. True means the server already holds a
     * telemetry row for this submission, so a report would be a second row for one event.
     */
    public function has_contacted_server(): bool {
        return $this->server_contacted;
    }

    /**
     * Name the form this submission belongs to, for the next report.
     *
     * Protection owns the render context and hands it over before the modules run, because a
     * report written from inside this module has no other way of knowing which form was hit.
     *
     * @param string $form_key `<integration>:<form id>`, or '' when the form cannot be named.
     */
    public function set_form_key( string $form_key ): void {
        $this->form_key = mb_substr( $form_key, 0, self::FORM_KEY_MAX_LENGTH );
    }

    /**
     * Report a block that one of the *local* protection modules decided.
     *
     * The caller is Protection, which owns the decision and the reason. Two things are checked
     * here rather than there, so no future caller can get them wrong:
     *
     *  - a key must be configured, or there is nobody to report to;
     *  - the server must not already know about this submission (see has_contacted_server()).
     *
     * The second is the one that matters. Reporting a block on a submission the server has
     * already seen writes a second row for a single event, and that count is what the customer
     * is shown and eventually billed on. Under-reporting costs a line in a dashboard;
     * over-reporting costs money and is nearly impossible to spot afterwards.
     *
     * @param string $reason   A value from the server's reason whitelist.
     * @param string $form_key Which form was hit, or '' when it cannot be named.
     */
    public function report_local_block( string $reason, string $form_key = '' ): void {
        if ( $this->server_contacted ) {
            return;
        }

        $api_key = $this->Controller->get_settings( 'beta_captcha_api_key', 'beta' );

        if ( ! is_string( $api_key ) || $api_key === '' ) {
            return;
        }

        $this->report_block_to_api( $api_key, $reason, $form_key );
    }

    /**
     * Assemble the report body.
     *
     * `form_key` is omitted rather than sent empty: an absent field and an empty string are
     * the same thing to the server, and leaving it out keeps the payload identical to what
     * older plugin versions send when the form cannot be named.
     *
     * @param string $reason   A value from the server's reason whitelist.
     * @param string $page_url The page the form was submitted from, possibly ''.
     * @param string $form_key Which form was hit, or ''.
     *
     * @return array<string, string> The JSON body.
     */
    private function build_report_body( string $reason, string $page_url, string $form_key ): array {
        $body = [
            'reason'   => $reason,
            'page_url' => $page_url,
        ];

        if ( $form_key !== '' ) {
            $body['form_key'] = mb_substr( $form_key, 0, self::FORM_KEY_MAX_LENGTH );
        }

        return $body;
    }

    /**
     * Report a locally-blocked submission to the SilentShield API.
     *
     * Used for the no-nonce case: a bot that submits a protected form without
     * ever loading the widget JS. Such requests carry no behavior nonce, are
     * blocked locally, and would otherwise be invisible to the SilentShield
     * "bots blocked" statistics. Fire-and-forget (non-blocking) so it never
     * delays the request — the submission is already rejected regardless.
     *
     * @param string $api_key  The API key.
     * @param string $reason   Machine-readable block reason from the server's whitelist.
     * @param string $form_key  Which form was hit, or '' when the integration could not say.
     */
    public function report_block_to_api( string $api_key, string $reason, string $form_key = '' ): void {
        $base_url = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
        $endpoint = rtrim( $base_url, '/' ) . '/captcha/report-block';

        $page_url = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only stats report; no state change
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw sanitizes
            $page_url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        }

        wp_remote_post( $endpoint, [
            'headers'  => [
                'Content-Type' => 'application/json',
                'api-key'      => $api_key,
            ],
            'body'     => wp_json_encode( $this->build_report_body( $reason, $page_url, $form_key ) ),
            'timeout'  => 1,
            'blocking' => false,
        ] );
    }

    /**
     * Log an API block to the detailed block log (if enabled).
     *
     * @param string $reason_code Machine-readable reason code.
     * @param string $detail      Human-readable explanation.
     * @param array  $extra       Optional extra data (score, reason_codes, meta).
     */
    private function maybe_log_api_block( string $reason_code, string $detail, array $extra = [] ): void {
        if ( ! BlockLog::is_enabled() ) {
            return;
        }

        $block_log = new BlockLog( $this->get_logger() );
        $block_log->log( 'api', $reason_code, $detail, $extra );
    }

    /**
     * Whether successful API responses should be written to the audit log.
     *
     * @return bool
     */
    private static function should_log_responses(): bool {
        $enabled = CF7Captcha::get_instance()->get_settings( 'protection_api_log_responses', 'global' );

        return (int) $enabled === 1;
    }

    /**
     * Cap an API response body to a size that is safe to store.
     *
     * The audit log's `context` column is TEXT and the whole context is written
     * as one JSON blob, so an unbounded body could push the row over the limit
     * and take the surrounding fields down with it. A kilobyte comfortably holds
     * a verdict with its score breakdown, which is what anyone reads this for.
     *
     * @param string $body Raw response body.
     *
     * @return string Body, truncated with a marker if it was too long.
     */
    private static function truncate_body( string $body ): string {
        $limit = 1024;

        if ( strlen( $body ) <= $limit ) {
            return $body;
        }

        return substr( $body, 0, $limit ) . '… [truncated]';
    }

}