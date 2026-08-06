<?php
/**
 * The optional "Spam protection by SilentShield" credit under protected forms.
 *
 * Off by default, and it has to stay that way. WordPress.org's plugin guidelines are explicit:
 * "All 'Powered By' or credit displays and links included in the plugin code must be optional
 * and default to *not* show on users' front-facing websites", and the choice has to be made
 * through "clearly stated and understandable choices, not buried in the terms of use or
 * documentation". A pre-enabled credit is grounds for removal from the directory, so the
 * default below is not a preference — it is the condition for being listed at all.
 *
 * Kept in its own file rather than added to the generators: the feature is one setting, one
 * URL and one line of markup, and holding it together makes it as easy to remove as it was to
 * add. It attaches through the same public filters a third party would use.
 */

namespace f12_cf7_captcha;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setting key inside `f12-cf7-captcha-settings['global']`.
 */
const CREDIT_SETTING_KEY = 'protection_credit_link';

/**
 * Whether the site owner has asked for the credit to be shown.
 *
 * Absent means off. Only an explicit 1 turns it on — the reverse of the plugin's other
 * defaults, deliberately.
 */
function is_credit_enabled(): bool {
	$settings = get_option( 'f12-cf7-captcha-settings', [] );

	if ( ! is_array( $settings ) || empty( $settings['global'] ) || ! is_array( $settings['global'] ) ) {
		return false;
	}

	return isset( $settings['global'][ CREDIT_SETTING_KEY ] )
		&& (int) $settings['global'][ CREDIT_SETTING_KEY ] === 1;
}

/**
 * Build a link to a page on silentshield.io.
 *
 * Two decisions worth recording, because both are hard to revise later — the credit URL in
 * particular ends up in other people's page footers and stays there until those sites update:
 *
 * - **Language segment.** silentshield.io serves /de and /en; the bare domain only offers a
 *   language chooser. Sending a German site owner to an English page at the exact moment they
 *   got curious wastes the click, so the segment follows the site's own locale. The privacy
 *   texts the plugin ships already link this way (silentshield.io/de/privacy).
 * - **utm_ rather than mtm_.** Matomo reads utm_source/utm_medium/utm_campaign by default and
 *   only *recommends* its own mtm_ prefix. utm_ is understood by Matomo and by everything
 *   else, so it keeps the analytics choice open for links that cannot be changed retroactively.
 *
 * @param string $path   Path below the language segment, without leading slash ('' = home).
 * @param string $medium utm_medium, so the entry points can be told apart.
 *
 * @return string
 */
function get_product_url( string $path = '', string $medium = 'plugin' ): string {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$lang   = product_lang();

	$url = add_query_arg(
		[
			'utm_source'   => 'wordpress',
			'utm_medium'   => $medium,
			'utm_campaign' => 'silentshield',
		],
		'https://silentshield.io/' . $lang . '/' . ltrim( $path, '/' )
	);

	/**
	 * Filter a link to the product site.
	 *
	 * @param string $url    The full URL including campaign parameters.
	 * @param string $path   The path that was requested.
	 * @param string $lang   The language segment that was chosen ('de' or 'en').
	 * @param string $locale The site locale it was derived from.
	 * @param string $medium Which entry point this is.
	 *
	 * @since 2.12.1
	 */
	return (string) apply_filters( 'f12-cf7-captcha-product-url', $url, $path, $lang, $locale, $medium );
}

/**
 * The documentation ("Help Center") on the product site.
 */
function get_docs_url( string $medium = 'admin-sidebar' ): string {
	return get_product_url( 'docs', $medium );
}

function get_credit_url( string $medium = 'plugin-credit' ): string {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$lang   = product_lang();

	/**
	 * Filter the destination of the credit link specifically.
	 *
	 * Kept alongside the general f12-cf7-captcha-product-url filter, which has already run by
	 * this point: someone overriding where the credit under their forms points should not have
	 * to special-case every other link to the product site as well.
	 *
	 * @param string $url    The full URL including campaign parameters.
	 * @param string $lang   The language segment that was chosen ('de' or 'en').
	 * @param string $locale The site locale it was derived from.
	 * @param string $medium Which link this is ('plugin-credit', 'admin-menu', …).
	 *
	 * @since 2.12.1
	 */
	return (string) apply_filters(
		'f12-cf7-captcha-credit-url',
		get_product_url( '', $medium ),
		$lang,
		$locale,
		$medium
	);
}

/**
 * The credit markup, or an empty string when it is switched off.
 *
 * `rel="nofollow"` on purpose. The point of the link is that a curious site owner can follow
 * it, not that it passes ranking signals from sites whose owners agreed to a small thank-you
 * — claiming the latter would make every installation look like a paid link scheme.
 *
 * @param bool $force Render even when the setting is off. Only for the preview in the admin
 *                    notice: someone deciding whether to switch this on is really asking
 *                    "will it make my site look cluttered", and the honest answer is to show
 *                    them the exact thing rather than describe it.
 *
 * @return string
 */
function get_credit_markup( bool $force = false ): string {
	if ( ! $force && ! is_credit_enabled() ) {
		return '';
	}

	/*
	 * Wording follows silentshield.io per language rather than translating one phrase
	 * literally: the English site labels its example form "Protected by SilentShield", the
	 * German one "Bot-Schutz: SilentShield". Someone who follows this link should land on a
	 * page that says the same thing they just clicked.
	 */
	return sprintf(
		'<p class="f12-captcha-credit"><a href="%s" target="_blank" rel="nofollow noopener">%s</a></p>',
		esc_url( get_credit_url() ),
		esc_html__( 'Protected by SilentShield', 'captcha-for-contact-form-7' )
	);
}

/**
 * Append the credit to a rendered captcha field.
 *
 * Typed as mixed rather than string on purpose: this hangs on a public filter, so another
 * plugin earlier in the chain can hand over anything at all. Passing that through untouched
 * is better than crashing a form over a credit link.
 *
 * @param mixed $html The captcha markup as the generator produced it.
 *
 * @return mixed
 */
function append_credit( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	$credit = get_credit_markup();

	return $credit === '' ? $html : $html . $credit;
}

// The three generators that render a visible field. The honeypot is left out: it is invisible
// by design, and hanging a link off it would advertise the trap.
add_filter( 'f12-cf7-captcha-get-form-field-math', __NAMESPACE__ . '\append_credit', 20 );
add_filter( 'f12-cf7-captcha-get-form-field-image', __NAMESPACE__ . '\append_credit', 20 );
