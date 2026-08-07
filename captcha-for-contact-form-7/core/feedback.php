<?php
/**
 * Where the plugin sends people who want to tell us something.
 *
 * One place for both destinations, because they are linked from four different spots and a
 * URL that has to be changed in four files is a URL that ends up inconsistent.
 */

namespace f12_cf7_captcha;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Feedback lives on silentshield.io, support on forge12.com. That split is deliberate but not
 * free, so it is worth writing down.
 *
 * These URLs ship inside every installed copy and stay there until the site updates, which for
 * a WordPress plugin can be years. They therefore have to be addresses that can be redirected
 * rather than addresses that happen to point at today's helpdesk — and the support board is a
 * deep path (`/shop/wordpress-captcha/support`), which is exactly the kind of URL a shop
 * restructure tends to move. If that path ever changes, a redirect has to be left behind: the
 * old copies will keep asking for it.
 *
 * The brands differ across the two links, which is a real cost. Someone reporting a bug from
 * the sidebar lands on silentshield.io, someone asking for help lands on forge12.com. That is
 * survivable because support is where an existing customer expects the company, but if the
 * feedback form ever moves too, both should end up on the same domain.
 */

/**
 * Feedback form — for "this could be better" and "this is broken for me".
 *
 * Reachable without an account: most of the people with something to say are free
 * wordpress.org users who have never logged in anywhere.
 */
/**
 * The language segment silentshield.io expects.
 *
 * The site serves /de and /en; the bare domain only offers a chooser. Shared by every link
 * the plugin builds to the product site so they cannot drift apart — which they had, with the
 * sidebar showing two links that respected the site language next to one that did not.
 *
 * @return string 'de' or 'en'.
 */
function product_lang(): string {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	return strpos( (string) $locale, 'de' ) === 0 ? 'de' : 'en';
}

const FEEDBACK_URL = 'https://silentshield.io/%s/feedback';

/**
 * Support — for "I need help with my installation".
 *
 * The captcha's own board on forge12.com rather than the general support hub: someone arriving
 * from inside the plugin already knows which product they are asking about, and a hub would
 * make them pick it again. Served per language, same as everything else here.
 */
const SUPPORT_URL = 'https://www.forge12.com/%s/shop/wordpress-captcha/support';

/**
 * Build a feedback link.
 *
 * Carries the plugin version and nothing else: enough to tell a report about 2.11 from one
 * about 2.4, without saying anything about the site it came from. The `from` value names the
 * entry point so we can see which of the four people actually use.
 *
 * @param string $from Which entry point the click came from (e.g. 'review-notice').
 *
 * @return string
 */
function get_feedback_url( string $from = '' ): string {
	$args = [ 'v' => FORGE12_CAPTCHA_VERSION ];

	if ( $from !== '' ) {
		$args['from'] = $from;
	}

	return add_query_arg( $args, sprintf( FEEDBACK_URL, product_lang() ) );
}

/**
 * Build a support link. Same rules as get_feedback_url().
 *
 * @param string $from Which entry point the click came from.
 *
 * @return string
 */
function get_support_url( string $from = '' ): string {
	$args = [ 'v' => FORGE12_CAPTCHA_VERSION ];

	if ( $from !== '' ) {
		$args['from'] = $from;
	}

	return add_query_arg( $args, sprintf( SUPPORT_URL, product_lang() ) );
}
