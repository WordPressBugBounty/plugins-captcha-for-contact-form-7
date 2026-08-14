<?php

namespace f12_cf7_captcha\core\protection\gibberish;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\log\ObservationLog;
use f12_cf7_captcha\core\log\Pseudonymizer;
use f12_cf7_captcha\core\protection\Observation_Provider;
use f12_cf7_captcha\core\protection\Protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects submissions whose text is machine-generated nonsense.
 *
 * Every other module in this plugin verifies something the *client* was asked to return — a
 * captcha answer, a timestamp, a signed token, an untouched honeypot. All of them share one
 * assumption: that a bot cannot read the page it is submitting. A bot driving a real browser
 * breaks that assumption for all of them at once, which is exactly what happened on the
 * installation that prompted this module: the honeypot was skipped because it sits off-screen,
 * and the maths captcha was solved because it is rendered as text in the DOM.
 *
 * This module looks at what the bot *produced* instead. Emitting random strings is the point
 * of a filler-spam run, so it cannot be optimised away without writing real prose.
 *
 * ## Modes
 *
 * `monitor` (the default) scores every submission and records what it would have done, without
 * affecting the outcome. `block` rejects. Monitoring first is deliberate: a wrongly blocked
 * enquiry costs a customer, and the thresholds deserve real traffic before they are trusted.
 *
 * @see Gibberish_Scorer for the heuristic itself and why it is shaped the way it is.
 */
class Gibberish_Validator extends BaseProtection implements Observation_Provider {

	public const MODE_MONITOR = 'monitor';
	public const MODE_BLOCK   = 'block';

	/** Reason code recorded in the block log. */
	public const REASON_CODE = 'GIBBERISH_CONTENT';

	/** Flagged fields required before a submission counts as gibberish. */
	public const DEFAULT_MIN_FIELDS = 3;

	/** One in N passing submissions is recorded as a negative sample. */
	public const DEFAULT_SAMPLE_RATE = 20;

	/**
	 * Field names that never carry prose, whatever the form plugin calls them.
	 *
	 * Protection::strip_internal_fields() already removes this plugin's own hidden fields;
	 * this catches the form plugins' bookkeeping that would otherwise be scored as if a person
	 * had typed it.
	 */
	private const IGNORED_FIELDS = [
		'action', 'post_id', 'form_id', 'referer', 'referrer', 'redirect_to', 'submit',
		'wpforms', 'et_pb_contact_email_fields', 'g-recaptcha-response', 'h-captcha-response',
		'cf-turnstile-response', 'ajaxurl', 'nonce', 'security', 'locale', 'timezone',
	];

	private Gibberish_Scorer $Scorer;

	/**
	 * Observation for the current request, built during is_spam().
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $observation = null;

	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->Scorer = new Gibberish_Scorer();

		$this->set_message_on_init( function () {
			return __( 'gibberish-protection', 'captcha-for-contact-form-7' );
		} );
	}

	protected function is_enabled(): bool {
		$raw = $this->get_protection_setting( 'protection_gibberish_enable' );

		// Absent means "never saved since the update", not "off" — this module ships enabled.
		if ( $raw === '' || $raw === null ) {
			$raw = 1;
		}

		$is_enabled = (int) $raw === 1;

		return (bool) apply_filters( 'f12-cf7-captcha-skip-validation-gibberish', $is_enabled );
	}

	/**
	 * Score the submission, and block only when configured to.
	 *
	 * @param mixed ...$args The submitted data.
	 */
	public function is_spam( ...$args ): bool {
		$this->observation = null;

		if ( ! isset( $args[0] ) || ! is_array( $args[0] ) ) {
			return false;
		}

		if ( ! $this->is_enabled() ) {
			return false;
		}

		$fields = $this->collect_text_fields( $args[0] );

		if ( empty( $fields ) ) {
			return false;
		}

		// The scorer is a heuristic, and a heuristic must never be able to take a submission
		// down with it. It ships in `monitor` mode, where it changes no outcome at all — yet an
		// undefined mb_* function inside it turned every Avada submit on one customer's host
		// into a 500 (2026-08-14). That specific cause is gone, but the shape of the failure is
		// the point: whatever the next one is, a lost enquiry costs more than a missed spam.
		try {
			$result = $this->Scorer->score_submission( $fields );
		} catch ( \Throwable $e ) {
			$this->get_logger()->error( 'Gibberish scoring failed, submission passed through.', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $e->getMessage(),
				'file'   => $e->getFile(),
				'line'   => $e->getLine(),
			] );

			return false;
		}

		$min_fields = $this->get_min_fields();

		// Two ways in. The field count is the primary rule and the one that resists a single
		// odd surname. The token count is the backstop for a submission that puts everything
		// in one textarea, where a generated block padded with enough real prose scores below
		// the per-field ratio and would otherwise walk straight through.
		$flagged  = $result['fields_flagged'] >= $min_fields
		            || $result['tokens_flagged'] >= Gibberish_Scorer::MIN_GIBBERISH_TOKENS;
		$blocking = $flagged && $this->get_mode() === self::MODE_BLOCK;

		$this->observation = $this->build_observation( $result, $args[0], $flagged, $blocking );

		if ( ! $flagged ) {
			return false;
		}

		$this->get_logger()->warning( 'Gibberish content detected.', [
			'plugin'         => 'f12-cf7-captcha',
			'fields_flagged' => $result['fields_flagged'],
			'fields_total'   => $result['fields_analyzed'],
			'mode'           => $this->get_mode(),
		] );

		return $blocking;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_observation(): ?array {
		return $this->observation;
	}

	public function success(): void {
		// Nothing to clean up: this module keeps no per-submission state beyond the request.
	}

	/**
	 * Assemble the row that will be written to the block log.
	 *
	 * Carries measurements, never content. The raw per-field numbers are included on purpose:
	 * they are what makes it possible to replay a different threshold against logged rows
	 * later, without ever needing the original submission back.
	 *
	 * @param array<string, mixed> $result    Scorer output.
	 * @param array<string, mixed> $post_data The full submitted data, for the e-mail features.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_observation( array $result, array $post_data, bool $flagged, bool $blocking ): ?array {
		if ( ! $this->is_collecting() ) {
			return null;
		}

		if ( $blocking ) {
			$verdict = 'blocked';
		} elseif ( $flagged ) {
			$verdict = 'monitored';
		} else {
			// Passing submissions are the negative class. Recording all of them would put one
			// row in the table per legitimate enquiry, so only a sample is kept.
			if ( ! $this->is_sampled() ) {
				return null;
			}

			$verdict = 'scored';
		}

		$signals = [];
		foreach ( $result['flagged'] as $field ) {
			$signals = array_merge( $signals, $field['signals'] ?? [] );
		}

		return [
			'verdict'       => $verdict,
			'reason_code'   => self::REASON_CODE,
			'reason_detail' => sprintf(
				'%s. %d of %d text fields and %d of %d long words (%d+ letters, Latin script) '
				. 'scored as machine-generated (thresholds: %d fields or %d long words, mode %s)',
				self::describe_outcome( $verdict ),
				$result['fields_flagged'],
				$result['fields_analyzed'],
				$result['tokens_flagged'],
				$result['tokens_analyzed'],
				Gibberish_Scorer::MIN_TOKEN_LENGTH,
				$this->get_min_fields(),
				Gibberish_Scorer::MIN_GIBBERISH_TOKENS,
				$this->get_mode()
			),
			'score'         => (float) $result['ratio'],
			'reason_codes'  => array_values( array_unique( $signals ) ),
			'meta'          => [
				'mode'            => $this->get_mode(),
				'scorer_version'  => $result['scorer_version'],
				'min_fields'      => $this->get_min_fields(),
				'min_tokens'      => Gibberish_Scorer::MIN_GIBBERISH_TOKENS,
				'fields_analyzed' => $result['fields_analyzed'],
				'fields_flagged'  => $result['fields_flagged'],
				'tokens_analyzed' => $result['tokens_analyzed'],
				'tokens_flagged'  => $result['tokens_flagged'],
				'flagged'         => $result['flagged'],
				'email'           => Pseudonymizer::describe_email( $this->find_email( $post_data ) ),
			],
		];
	}

	/**
	 * Say in words what happened to the submission.
	 *
	 * The verdict column alone was read the wrong way round in the field: a site owner found a
	 * `scored` row for a submission that another module had rejected, took `scored` to mean
	 * "this module scored it as spam", and spent hours on the wrong module. `scored` means the
	 * opposite — the submission passed and was kept as a negative sample. The detail column is
	 * the one place where that can be said in a sentence rather than in a column value, so it
	 * says it.
	 */
	private static function describe_outcome( string $verdict ): string {
		switch ( $verdict ) {
			case 'blocked':
				return 'Submission rejected by this module';
			case 'monitored':
				return 'Submission passed; this module would have rejected it in block mode';
			default:
				return 'Submission passed; recorded as a calibration sample';
		}
	}

	/**
	 * Reduce the submitted data to the values a person would have typed.
	 *
	 * @param array<string, mixed> $post_data
	 *
	 * @return array<string, string>
	 */
	private function collect_text_fields( array $post_data ): array {
		$fields = Protection::strip_internal_fields( $post_data );
		$text   = [];

		foreach ( $fields as $key => $value ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}

			// Form plugins prefix their own bookkeeping with an underscore.
			if ( $key[0] === '_' ) {
				continue;
			}

			if ( in_array( strtolower( $key ), self::IGNORED_FIELDS, true ) ) {
				continue;
			}

			// Checkbox and multi-select groups arrive as arrays of chosen option labels; those
			// come from the form's own markup, not from the sender.
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( $value === '' ) {
				continue;
			}

			$text[ $key ] = $value;
		}

		return $text;
	}

	/**
	 * The first value that looks like an e-mail address.
	 *
	 * Found by shape rather than by field name, because every form plugin names the field
	 * differently and site owners rename it again.
	 *
	 * @param array<string, mixed> $post_data
	 */
	private function find_email( array $post_data ): string {
		foreach ( $post_data as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( $value !== '' && is_email( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Whether anonymous observations may be built at all.
	 *
	 * Deliberately its own setting rather than riding on `protection_detailed_tracking`: that
	 * switch turns on an IP-linked log for the site owner's own use, which is a different
	 * purpose from anonymous measurements, and two purposes should not share one consent.
	 *
	 * The answer comes from {@see ObservationLog} rather than being worked out again here. Both
	 * ends read the same option and both have to treat "never saved" as on; two copies of that
	 * rule is one copy too many for a switch that decides whether data is collected.
	 */
	private function is_collecting(): bool {
		return ObservationLog::is_enabled();
	}

	private function is_sampled(): bool {
		$rate = (int) $this->Controller->get_settings( 'protection_gibberish_sample_rate', 'global' );

		if ( $rate < 1 ) {
			$rate = self::DEFAULT_SAMPLE_RATE;
		}

		return $rate === 1 || wp_rand( 1, $rate ) === 1;
	}

	/**
	 * `monitor` or `block`.
	 */
	public function get_mode(): string {
		$mode = (string) $this->get_protection_setting( 'protection_gibberish_mode' );

		return $mode === self::MODE_BLOCK ? self::MODE_BLOCK : self::MODE_MONITOR;
	}

	public function get_min_fields(): int {
		$raw = $this->get_protection_setting( 'protection_gibberish_min_fields' );
		$min = is_numeric( $raw ) ? (int) $raw : self::DEFAULT_MIN_FIELDS;

		// Below two this stops being a cross-field judgement and starts condemning submissions
		// on a single odd word, which is what the field threshold exists to prevent.
		return max( 2, $min );
	}
}
