<?php

namespace f12_cf7_captcha\core\protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by protection modules that have something to record even when they do not block.
 *
 * A validator answers one question — spam, yes or no — and that answer is all the block log
 * ever saw. Which is enough to measure false positives and nothing else: the submissions that
 * got through leave no trace, so there is no way to tell afterwards what was missed.
 *
 * A module implementing this interface returns a second, quieter result alongside its verdict:
 * what it measured, and what it would have done. {@see Protection::maybe_log_observations()}
 * writes those rows with a `verdict` of `monitored` (would have blocked) or `scored` (passed,
 * measured anyway), giving both classes of the data needed to calibrate a heuristic.
 *
 * Implementations must return anonymous features only — counts, ratios, signal names. Never
 * submitted text, and never a value that identifies a person. These rows exist to be analysed
 * away from the site that produced them.
 */
interface Observation_Provider {

	/**
	 * The observation for the request just validated.
	 *
	 * Called once per submission, after is_spam(). Returning null means there is nothing worth
	 * recording — the module was disabled, collection is switched off, or this submission fell
	 * outside the sample.
	 *
	 * @return array{
	 *     verdict:string,
	 *     reason_code:string,
	 *     reason_detail:string,
	 *     score:float|null,
	 *     reason_codes:array<int, string>,
	 *     meta:array<string, mixed>
	 * }|null
	 */
	public function get_observation(): ?array;
}
