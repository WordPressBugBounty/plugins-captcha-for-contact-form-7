<?php
/**
 * Warns an administrator that the SilentShield API protection cannot work over plain http.
 *
 * The behaviour library that produces the `behavior_nonce` needs the Web Crypto API, which
 * browsers only expose in a secure context. On an http site there is no nonce, the API module
 * answers every submission with API_NO_NONCE, and the visitor gets no explanation — the form
 * simply does not go through. localhost counts as a secure context and is left alone, so a local
 * development site does not nag.
 *
 * Display only: nothing is switched off, in keeping with the fail-open house rule.
 */

add_action( 'admin_notices', 'f12_cf7_captcha_maybe_show_insecure_context_notice' );

/**
 * Whether this site's front end is served from a context that has no Web Crypto.
 *
 * @param string $home_url The site's front-end URL.
 *
 * @return bool
 */
function f12_cf7_captcha_is_insecure_context( string $home_url ): bool {
	$scheme = strtolower( (string) wp_parse_url( $home_url, PHP_URL_SCHEME ) );
	$host   = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );

	if ( $scheme === 'https' || $scheme === '' ) {
		return false;
	}

	// Browsers treat these as secure contexts even over http.
	if ( $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || substr( $host, -10 ) === '.localhost' ) {
		return false;
	}

	return true;
}

/**
 * Render the notice when the API protection is on but the site is served over http.
 */
function f12_cf7_captcha_maybe_show_insecure_context_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! class_exists( '\f12_cf7_captcha\CF7Captcha' ) ) {
		return;
	}

	try {
		$controller = \f12_cf7_captcha\CF7Captcha::get_instance();
		$enabled    = (int) $controller->get_settings( 'beta_captcha_enable', 'beta' ) === 1;
		$api_key    = $controller->get_settings( 'beta_captcha_api_key', 'beta' );
	} catch ( \Throwable $e ) {
		// A notice is never worth breaking wp-admin over.
		return;
	}

	if ( ! $enabled || empty( $api_key ) || ! f12_cf7_captcha_is_insecure_context( home_url() ) ) {
		return;
	}

	?>
	<div class="notice notice-warning f12-cf7-captcha-insecure-notice">
		<p>
			<?php echo wp_kses(
				__( '<strong>SilentShield API protection needs https.</strong> Browsers only provide the Web Crypto API on secure pages, so on this http site no behavior token is created and protected forms cannot be submitted — visitors get no message.', 'captcha-for-contact-form-7' ),
				[ 'strong' => [] ]
			); ?>
		</p>
		<p>
			<?php esc_html_e( 'Serve the site over https, or switch the API protection off until you do.', 'captcha-for-contact-form-7' ); ?>
		</p>
	</div>
	<?php
}
