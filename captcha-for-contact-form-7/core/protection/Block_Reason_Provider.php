<?php

namespace f12_cf7_captcha\core\protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by protection modules that can name *why* they blocked, not just that they did.
 *
 * {@see Protection::$block_reason_map} gives one code per module. That is enough for a module
 * with one way to fail, and actively misleading for a module with several: the multiple-
 * submission validator rejects a submission when its field is missing, when its token is
 * unknown, and when the form came back too fast — and all three were logged as
 * `DUPLICATE_SUBMIT`, "Duplicate submission detected".
 *
 * The cost of that was not cosmetic. The most common of the three is a token the browser never
 * refreshed, which is a bug on our side and not a duplicate at all; sites reported it as one,
 * and the log agreed with them. A module implementing this interface replaces the mapped code
 * for the request it just judged, so the mail log and the block log name the actual cause.
 *
 * Implementations must return a stable, machine-readable code — it is what the analytics screen
 * groups by, and what support asks for. Put anything variable in the detail.
 */
interface Block_Reason_Provider {

	/**
	 * The reason this module rejected the request it just judged.
	 *
	 * Called after is_spam() returned true. Returning null falls back to the module's entry in
	 * {@see Protection::$block_reason_map}, which is the right answer whenever the module has
	 * nothing more specific to say.
	 *
	 * @return array{code:string, detail:string}|null
	 */
	public function get_block_reason(): ?array;
}
