<?php

namespace f12_cf7_captcha\core\protection\gibberish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scores submitted text for machine-generated nonsense ("Cowqv Exnjznedy", "dQgJlwEhfxaLZSKYLy").
 *
 * This class is deliberately free of WordPress: no settings, no database, no globals. The
 * heuristic is the part of this feature that can be *wrong*, and a pure function is the only
 * shape in which it can be run against a few hundred real submissions to find out.
 *
 * ## No mbstring
 *
 * Unicode handling here goes through PCRE's `/u` mode — `\p{L}`, `\p{Latin}`, `\p{Lu}` — and
 * never through `mb_*`. mbstring is an optional PHP extension that WordPress itself does not
 * require: `wp-includes/compat.php` polyfills exactly `mb_substr` and `mb_strlen` and nothing
 * else. A host without the extension therefore ran this class happily until it reached the
 * first unpolyfilled call and killed the submission with a fatal, which is what happened to a
 * customer on 2026-08-14. Do not reintroduce an `mb_*` call: the polyfilled two are no safer
 * than the rest, because that polyfill is WordPress's and this class is tested without it.
 *
 * ## What it detects
 *
 * Exactly one thing: tokens produced by a random-character generator. It has no idea what a
 * language is and makes no attempt to find out. Grammatically plausible spam ("great site,
 * here is my SEO offer") sails straight through — that is the keyword blacklist's job.
 *
 * ## The three signals
 *
 * - **PRONOUNCE** — a low vowel ratio *or* a long consonant run. These two measurements are
 *   deliberately fused into one signal because they are not independent: both describe how
 *   pronounceable a token is, and German compounds trip both at once. Counting them separately
 *   was the first design and it flagged `Rechnungsanschrift`, an entirely ordinary word.
 * - **CASE_MIX** — upper/lower transitions inside a token. The cleanest marker by far:
 *   generators emit `dQgJlwEh` constantly, humans essentially never do. The threshold sits at
 *   four rather than three because CamelCase brand names ("WordPress", "McDonalds",
 *   "SilentShield") all score exactly three.
 * - **BIGRAM** — mean letter-pair plausibility against {@see BIGRAM_CLASSES}. This is the only
 *   signal that catches all-lowercase gibberish, which the other two miss by construction.
 *
 * Two of the three must agree. Measured on the sample that motivated this feature, the spam
 * tokens score 4.4–6.0 on BIGRAM while real words — including `Brzeczyszczykiewicz` and
 * `Krankenversicherung` — score 6.75–8.9.
 *
 * ## Why Latin-only
 *
 * A vowel ratio is meaningless for a script that does not write vowels, and a case switch is
 * meaningless for a script without case. Fed Chinese, Arabic or Hebrew, PRONOUNCE and BIGRAM
 * would fire on every single token and this class would block an entire language. Non-Latin
 * tokens are therefore skipped outright, which means Cyrillic gibberish is not detected
 * either. That is the honest trade: silence beats confidently blocking Japanese.
 */
class Gibberish_Scorer {

	/**
	 * Bumped whenever the thresholds, the signal set or the bigram table change.
	 *
	 * Recorded with every observation. Without it, rows scored under different rules look
	 * alike in the log and any later calibration silently mixes two measurement series.
	 *
	 * 3 — dropping mbstring rebuilt the vowel test around {@see FOLD}, which recognises
	 * `ı`, `İ`, `ā ē ī ō ū` and `ø` as vowels where the old hand-written list did not. Only
	 * those characters moved, and only towards a higher vowel ratio, so PRONOUNCE fires less
	 * often on Baltic, Turkish and Nordic names than it did under 2. Every other measurement is
	 * unchanged, verified token by token against the previous implementation.
	 */
	public const VERSION = 3;

	/** Signal identifiers, recorded per flagged field. */
	public const SIGNAL_PRONOUNCE = 'PRONOUNCE';
	public const SIGNAL_CASE_MIX  = 'CASE_MIX';
	public const SIGNAL_BIGRAM    = 'BIGRAM';

	/**
	 * Shortest token worth judging.
	 *
	 * Below this the signals are noise: a five-letter surname can look like anything, and
	 * short all-consonant strings ("GmbH", "SPD", "cf7") are ordinary abbreviations.
	 */
	public const MIN_TOKEN_LENGTH = 8;

	/** Vowel ratio strictly below this contributes to PRONOUNCE. */
	public const MAX_VOWEL_RATIO = 0.25;

	/** Consonants in a row that contribute to PRONOUNCE. */
	public const MIN_CONSONANT_RUN = 5;

	/** Upper/lower transitions inside one token before CASE_MIX fires. */
	public const MIN_CASE_SWITCHES = 4;

	/**
	 * Mean bigram class strictly below this fires BIGRAM.
	 *
	 * Sits in the measured gap between the worst real word (6.75) and the best spam token
	 * (6.00). Deliberately nearer the spam end: a missed spam costs one e-mail, a flagged
	 * surname costs a customer.
	 */
	public const MIN_BIGRAM_SCORE = 6.5;

	/** Signals that must agree before a token is called gibberish. */
	public const MIN_SIGNALS = 2;

	/**
	 * Share of a field's eligible tokens that must be gibberish before the field is flagged.
	 *
	 * A single odd token in a long message is a product code or a foreign name, not spam. Half
	 * of them being nonsense is a different matter.
	 */
	public const FIELD_FLAG_RATIO = 0.5;

	/**
	 * Signals required to flag a field whose *only* eligible token is the suspect one.
	 *
	 * The ratio above cannot defend a one-token field: with a single token the ratio is either
	 * 0.0 or 1.0, so the field is condemned by that one word. Most fields are one-token fields —
	 * a name, a town, or a German sentence whose only word of eight letters or more is
	 * `Rechnungsanschrift`. A reporter running 2.13.0 hit exactly that and spent hours on it.
	 *
	 * Raising {@see MIN_SIGNALS} everywhere would be the crude version of this fix and would
	 * cost real detection: two of the eleven tokens in the two spam runs this module was built
	 * against trip exactly two signals. Requiring the full set *only where there is no second
	 * token to corroborate* keeps those two counted by {@see MIN_GIBBERISH_TOKENS} while giving
	 * a lone real word the widest margin the three signals can offer.
	 *
	 * Measured over 47 deliberately hostile real tokens — German compounds with five-consonant
	 * runs, Polish and Icelandic surnames, CamelCase brand names like `WooCommerce` and
	 * `LaserJet`: none reaches two signals, and none reaches three. The nine spam tokens that
	 * carry a field on their own all trip three.
	 */
	public const MIN_SIGNALS_SINGLE_TOKEN = 3;

	/**
	 * Generated tokens anywhere in a submission that condemn it regardless of the field ratio.
	 *
	 * The ratio alone can be diluted away. A real spam run against a wine merchant's order
	 * form posted a genuine 25-word product list — "Sangiovese", "Morellino di Scansano",
	 * "Chardonnay", all real — with the generated contact block appended. Had the form used a
	 * single textarea rather than separate fields, five generated tokens against twenty-five
	 * real ones would have scored 0.17 and passed, because the spammer supplied enough real
	 * language to drown them.
	 *
	 * Five is chosen against the false-positive side: every token has to clear two of three
	 * signals on its own, and across the 64 hard real-world tokens this heuristic is tested on
	 * — German compounds, Polish and Icelandic surnames, Italian wine names — not one does.
	 * Five independent such tokens in one submission is not a person with unusual vocabulary.
	 */
	public const MIN_GIBBERISH_TOKENS = 5;

	/**
	 * Treated as vowels, including `y`.
	 *
	 * `y` is a vowel in Polish, Czech and Welsh, and counting it raises the ratio of exactly
	 * the tokens we are least sure about — it makes the scorer more forgiving, which is the
	 * direction an error should point.
	 *
	 * Keyed on the *folded* character, so this short ASCII list covers every accented form
	 * {@see FOLD} knows: `ä`, `Ä`, `à` and `ā` all arrive here as `a`. The two-letter keys are
	 * the ligatures FOLD expands — `æ` and `Æ` become `ae`, `œ` and `Œ` become `oe`.
	 */
	private const VOWELS = [
		'a'  => true,
		'e'  => true,
		'i'  => true,
		'o'  => true,
		'u'  => true,
		'y'  => true,
		'ae' => true,
		'oe' => true,
	];

	/**
	 * Letter-pair plausibility, 676 digits for `aa`…`zz` in row-major order.
	 *
	 * Each digit is a rarity class from 0 (never observed) to 9 (very common), quantised from
	 * log10 P(bigram) over a corpus of 1,017,568 letter pairs — the plugin's own translations
	 * in all 27 shipped locales, which is real human prose in every Latin-script language we
	 * support. Bigram statistics are a property of a language rather than of a vocabulary, so
	 * a table built from admin strings scores surnames and street names just as well:
	 * "Wollersheim" lands at 8.20, "Rheinbabenstrasse" at 8.06.
	 *
	 * Regenerate with `languages/_build_bigrams.php` and bump {@see VERSION} when you do.
	 */
	private const BIGRAM_CLASSES =
		'7889668687898969599988657787658245743876831764733064916185098586068007687200668447' .
		'9444844666870775754166878987878779895769996767677412754283051383074673205374068366' .
		'8337567407767430639344921384464685074775406087889876678989985899683427822581307354' .
		'3573046674100383548243857745830678742260954894659758669634788641839633934484467587' .
		'1475831154957897969776478635897644766788777677888977698988824784558555843855870968' .
		'7405434402301010010020203270110096789665967787963788766167858495589577668845898650' .
		'6795849549956666953888865177777776747668786748886655269243903184551674056464006471' .
		'4470078035257604745121624050402150010035000620030045656544546565760477355045855582' .
		'44765567750456755066';

	/**
	 * Accent folding, so accented input still finds its row in the ASCII bigram table.
	 *
	 * Both cases are listed. Folding used to run after `mb_strtolower()`, which made the
	 * uppercase half unnecessary — but this class must not depend on mbstring (see the class
	 * docblock), and byte-wise `strtolower()` cannot lower a multibyte `Ä`. Without a key for
	 * it, `Ärztekammer` would fold to `rztekammer` and score a word that was never submitted.
	 * ASCII `A`–`Z` are deliberately absent: `strtolower()` handles those correctly.
	 */
	private const FOLD = [
		'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a',
		'ã' => 'a', 'å' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i',
		'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
		'õ' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ç' => 'c',
		'ć' => 'c', 'č' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ś' => 's', 'š' => 's',
		'ż' => 'z', 'ź' => 'z', 'ž' => 'z', 'ā' => 'a', 'ē' => 'e', 'ī' => 'i', 'ū' => 'u',
		'ō' => 'o', 'ğ' => 'g', 'ı' => 'i', 'ş' => 's', 'ř' => 'r', 'ď' => 'd', 'ť' => 't',
		'ň' => 'n', 'ů' => 'u', 'ė' => 'e', 'į' => 'i', 'ų' => 'u', 'ą' => 'a', 'ě' => 'e',
		'ő' => 'o', 'ű' => 'u', 'æ' => 'ae', 'ø' => 'o', 'œ' => 'oe', 'þ' => 't', 'ð' => 'd',

		'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u', 'ẞ' => 'ss', 'À' => 'a', 'Á' => 'a', 'Â' => 'a',
		'Ã' => 'a', 'Å' => 'a', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Ì' => 'i',
		'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'Ñ' => 'n', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o',
		'Õ' => 'o', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ý' => 'y', 'Ÿ' => 'y', 'Ç' => 'c',
		'Ć' => 'c', 'Č' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n', 'Ś' => 's', 'Š' => 's',
		'Ż' => 'z', 'Ź' => 'z', 'Ž' => 'z', 'Ā' => 'a', 'Ē' => 'e', 'Ī' => 'i', 'Ū' => 'u',
		'Ō' => 'o', 'Ğ' => 'g', 'İ' => 'i', 'Ş' => 's', 'Ř' => 'r', 'Ď' => 'd', 'Ť' => 't',
		'Ň' => 'n', 'Ů' => 'u', 'Ė' => 'e', 'Į' => 'i', 'Ų' => 'u', 'Ą' => 'a', 'Ě' => 'e',
		'Ő' => 'o', 'Ű' => 'u', 'Æ' => 'ae', 'Ø' => 'o', 'Œ' => 'oe', 'Þ' => 't', 'Ð' => 'd',
	];

	/**
	 * Score a whole submission.
	 *
	 * @param array<string, mixed> $fields Field name => submitted value, already stripped of
	 *                                     the plugin's own hidden fields.
	 *
	 * `tokens_flagged` counts generated tokens across *every* analysed field, including fields
	 * that stayed below the ratio. It is what catches a submission that hides its generated
	 * block inside enough real prose to dilute the per-field score.
	 *
	 * @return array{
	 *     fields_analyzed:int,
	 *     fields_flagged:int,
	 *     tokens_analyzed:int,
	 *     tokens_flagged:int,
	 *     ratio:float,
	 *     flagged:array<int, array<string, mixed>>,
	 *     scorer_version:int
	 * } Feature counts only — never any submitted text.
	 */
	public function score_submission( array $fields ): array {
		$analyzed        = 0;
		$flagged         = [];
		$tokens_total    = 0;
		$tokens_flagged  = 0;

		foreach ( $fields as $name => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$result = $this->score_value( (string) $value );

			if ( $result === null ) {
				continue;
			}

			$analyzed ++;
			$tokens_total   += $result['summary']['tokens'];
			$tokens_flagged += $result['summary']['tokens_flagged'];

			if ( $result['is_gibberish'] ) {
				$flagged[] = [ 'field' => (string) $name ] + $result['summary'];
			}
		}

		return [
			'fields_analyzed' => $analyzed,
			'fields_flagged'  => count( $flagged ),
			'tokens_analyzed' => $tokens_total,
			'tokens_flagged'  => $tokens_flagged,
			'ratio'           => $analyzed > 0 ? round( count( $flagged ) / $analyzed, 3 ) : 0.0,
			'flagged'         => $flagged,
			'scorer_version'  => self::VERSION,
		];
	}

	/**
	 * Score one field value.
	 *
	 * The summary carries the raw measurements, not just the verdict, so that a later
	 * calibration pass can replay different thresholds against logged observations without
	 * ever needing the original text back.
	 *
	 * @return array{is_gibberish:bool, summary:array<string, mixed>}|null
	 *         Null when the value holds nothing long enough to judge.
	 */
	public function score_value( string $value ): ?array {
		$tokens = $this->tokenize( $value );

		if ( empty( $tokens ) ) {
			return null;
		}

		$gibberish = 0;
		$strongest = 0;
		$signals   = [];
		$worst     = [ 'vowel_ratio' => 1.0, 'cons_run' => 0, 'case_switches' => 0, 'bigram' => 9.0 ];

		foreach ( $tokens as $token ) {
			$token_signals = $this->score_token( $token );

			// Tracked for every token, including the ones below the bar: the single-token rule
			// below needs to know how strong the best case against this field actually is.
			$strongest = max( $strongest, count( $token_signals ) );

			if ( count( $token_signals ) < self::MIN_SIGNALS ) {
				continue;
			}

			$gibberish ++;
			$signals = array_merge( $signals, $token_signals );

			$worst['vowel_ratio']   = min( $worst['vowel_ratio'], round( $this->vowel_ratio( $token ), 3 ) );
			$worst['cons_run']      = max( $worst['cons_run'], $this->max_consonant_run( $token ) );
			$worst['case_switches'] = max( $worst['case_switches'], $this->case_switches( $token ) );
			$worst['bigram']        = min( $worst['bigram'], round( $this->bigram_score( $token ), 2 ) );
		}

		$ratio = $gibberish / count( $tokens );

		// With one token there is no ratio to speak of — 0.0 or 1.0 — so the verdict rests on
		// that token alone and it has to clear the higher bar. See MIN_SIGNALS_SINGLE_TOKEN.
		$is_gibberish = count( $tokens ) === 1
			? $strongest >= self::MIN_SIGNALS_SINGLE_TOKEN
			: $ratio >= self::FIELD_FLAG_RATIO;

		return [
			'is_gibberish' => $is_gibberish,
			'summary'      => [
				'len'            => $this->length( $value ),
				'tokens'         => count( $tokens ),
				'tokens_flagged' => $gibberish,
				'signals'        => array_values( array_unique( $signals ) ),
				'worst'          => $gibberish > 0 ? $worst : null,
			],
		];
	}

	/**
	 * The signals a single token trips.
	 *
	 * @return string[] Zero to three SIGNAL_* identifiers.
	 */
	public function score_token( string $token ): array {
		if ( ! $this->is_eligible( $token ) ) {
			return [];
		}

		$signals = [];

		if ( $this->vowel_ratio( $token ) < self::MAX_VOWEL_RATIO
		     || $this->max_consonant_run( $token ) >= self::MIN_CONSONANT_RUN ) {
			$signals[] = self::SIGNAL_PRONOUNCE;
		}

		if ( $this->case_switches( $token ) >= self::MIN_CASE_SWITCHES ) {
			$signals[] = self::SIGNAL_CASE_MIX;
		}

		if ( $this->bigram_score( $token ) < self::MIN_BIGRAM_SCORE ) {
			$signals[] = self::SIGNAL_BIGRAM;
		}

		return $signals;
	}

	/**
	 * Split a value into judgeable tokens.
	 *
	 * URLs and e-mail addresses are removed first: their host and path parts are not prose and
	 * would be judged as if they were. The address itself is scored separately by the
	 * validator, which knows which field it came from.
	 *
	 * @return string[]
	 */
	public function tokenize( string $value ): array {
		$value = preg_replace( '#\b[a-z][a-z0-9+.-]*://\S+#iu', ' ', $value ) ?? $value;
		$value = preg_replace( '#\S+@\S+#u', ' ', $value ) ?? $value;

		// Split on everything that is not a letter. Digits are separators, not content: a token
		// like "AB12cd" is a reference number, and reference numbers are not prose.
		$parts = preg_split( '/[^\p{L}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $parts ) ) {
			return [];
		}

		return array_values( array_filter( $parts, [ $this, 'is_eligible' ] ) );
	}

	/**
	 * Whether a token is long enough and written in a script these signals apply to.
	 */
	public function is_eligible( string $token ): bool {
		if ( $this->length( $token ) < self::MIN_TOKEN_LENGTH ) {
			return false;
		}

		// Latin script only — see the class docblock.
		return (bool) preg_match( '/^\p{Latin}+$/u', $token );
	}

	/**
	 * Share of characters that are vowels, 0.0 to 1.0.
	 */
	public function vowel_ratio( string $token ): float {
		$chars  = $this->characters( $token );
		$length = count( $chars );

		if ( $length === 0 ) {
			return 0.0;
		}

		$vowels = 0;

		foreach ( $chars as $char ) {
			if ( $this->is_vowel( $char ) ) {
				$vowels ++;
			}
		}

		return $vowels / $length;
	}

	/**
	 * Length of the longest run of consecutive non-vowels.
	 */
	public function max_consonant_run( string $token ): int {
		$longest = 0;
		$current = 0;

		foreach ( $this->characters( $token ) as $char ) {
			if ( $this->is_vowel( $char ) ) {
				$current = 0;
				continue;
			}

			$current ++;

			if ( $current > $longest ) {
				$longest = $current;
			}
		}

		return $longest;
	}

	/**
	 * Number of upper→lower or lower→upper transitions between adjacent characters.
	 *
	 * A leading capital produces none, so ordinary names score zero.
	 */
	public function case_switches( string $token ): int {
		$chars    = $this->characters( $token );
		$switches = 0;

		for ( $i = 1, $count = count( $chars ); $i < $count; $i ++ ) {
			$previous = $this->case_of( $chars[ $i - 1 ] );
			$current  = $this->case_of( $chars[ $i ] );

			// Caseless characters break the comparison rather than counting as a switch.
			if ( $previous === null || $current === null ) {
				continue;
			}

			if ( $previous !== $current ) {
				$switches ++;
			}
		}

		return $switches;
	}

	/**
	 * Mean bigram rarity class over the token, 0.0 (implausible) to 9.0 (ordinary prose).
	 *
	 * Tokens shorter than three letters after folding return 9.0 — too little evidence to
	 * accuse them of anything.
	 */
	public function bigram_score( string $token ): float {
		$folded = preg_replace( '/[^a-z]/', '', $this->fold( $token ) );

		if ( $folded === null || strlen( $folded ) < 3 ) {
			return 9.0;
		}

		$sum = 0;
		$n   = 0;

		for ( $i = 1, $length = strlen( $folded ); $i < $length; $i ++ ) {
			$row = ord( $folded[ $i - 1 ] ) - 97;
			$col = ord( $folded[ $i ] ) - 97;

			$sum += (int) self::BIGRAM_CLASSES[ $row * 26 + $col ];
			$n ++;
		}

		// The length guard above means at least two pairs were scored.
		return $sum / $n;
	}

	/**
	 * 'upper', 'lower', or null when the character has no case at all.
	 */
	private function case_of( string $char ): ?string {
		if ( preg_match( '/^\p{Lu}$/u', $char ) === 1 ) {
			return 'upper';
		}

		if ( preg_match( '/^\p{Ll}$/u', $char ) === 1 ) {
			return 'lower';
		}

		return null;
	}

	private function is_vowel( string $char ): bool {
		return isset( self::VOWELS[ $this->fold( $char ) ] );
	}

	/**
	 * Lowercase ASCII, as far as {@see FOLD} can get us.
	 *
	 * `strtolower()` is byte-wise and touches only `A`–`Z`; every multibyte letter is mapped by
	 * FOLD instead, which lists both cases for that reason. Anything neither knows survives
	 * unchanged and is dropped by the callers' `[^a-z]` filter — the same outcome the previous
	 * `mb_strtolower()` produced for scripts the bigram table has no row for.
	 */
	private function fold( string $text ): string {
		return strtolower( strtr( $text, self::FOLD ) );
	}

	/**
	 * Length in characters rather than bytes.
	 */
	private function length( string $text ): int {
		return (int) preg_match_all( '/./us', $text );
	}

	/**
	 * @return string[]
	 */
	private function characters( string $token ): array {
		$chars = preg_split( '//u', $token, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $chars ) ? $chars : [];
	}
}
