<?php

namespace f12_cf7_captcha\core\protection\api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page a submission came from, decided by the server rather than by the sender.
 *
 * SilentShield is told which page was hit so the customer's dashboard can name it. Until now
 * that value was the Referer — `$_SERVER['HTTP_REFERER']` for a block report, wp_get_referer()
 * for the verify call, which reads the same header plus a `_wp_http_referer` form field. Every
 * one of those is chosen by whoever sends the request. A bot that forges one names a page on
 * somebody else's domain, the server drops the address because it does not belong to the key's
 * domain, and the customer is left with a block they cannot place. Sending no Referer at all
 * achieves the same thing more cheaply. The block itself still counts either way — what is
 * lost is the one field that says *where* to go and fix something.
 *
 * The plugin does not have to ask. It runs on the server that serves the page, so for a plain
 * form post the requested URI *is* the page, and the site's own home URL supplies the scheme
 * and host — which leaves the sender no say over the domain the address names.
 *
 * The exception is an AJAX or REST submission: there the request went to admin-ajax.php or a
 * REST route, and that is emphatically not the page carrying the form. Form plugins hand the
 * containing post along in the payload for their own reasons — Contact Form 7's
 * `_wpcf7_container_post`, the comment form's `comment_post_ID`, Elementor's `post_id` — and
 * get_permalink() turns such an id into an address this site actually serves. The id is still
 * the sender's to choose, but it can only ever name another page of the same site, which is a
 * different order of problem from naming another site.
 *
 * A Referer remains the last resort, for an AJAX integration whose payload names no post. It
 * has to survive wp_validate_redirect() first, so a foreign host is dropped here rather than
 * by the server: an address that would be discarded anyway is not worth sending, and one that
 * survives is at least on the customer's own site.
 *
 * Nothing at all is sent when even that fails. That is the house rule applied to a field: an
 * unplaceable block is still a block, and no report is ever held back over a missing page.
 */
final class Page_Url {

	/**
	 * How much of an address the endpoints accept.
	 */
	private const MAX_LENGTH = 1024;

	/**
	 * Fields through which a form plugin names the post its form is embedded in.
	 *
	 * Read only when the request itself cannot answer the question — see resolve(). Each is
	 * resolved through get_permalink() rather than used as an address, so the worst a forged
	 * value can do is name a different page of the same site.
	 */
	private const CONTAINER_POST_FIELDS = [
		'_wpcf7_container_post', // Contact Form 7, on its REST feedback route.
		'comment_post_ID',       // The comment form, posting to wp-comments-post.php.
		'post_id',               // Elementor Pro, and several others through admin-ajax.php.
	];

	/**
	 * Request targets that are never the page a form lives on.
	 *
	 * wp_doing_ajax() and REST_REQUEST cover most of it; these are the endpoints a browser
	 * posts a form to directly, where the requested URI names the handler rather than the page.
	 */
	private const NOT_A_FORM_PAGE = [
		'admin-ajax.php',
		'admin-post.php',
		'wp-comments-post.php',
		'xmlrpc.php',
	];

	/**
	 * The page this submission was made from, or '' when the server cannot say.
	 *
	 * Most-trusted source first: what this server was asked for, then the post the payload
	 * names, then a Referer that at least points at this site.
	 */
	public static function resolve(): string {
		$url = self::from_request_uri();

		if ( $url === '' ) {
			$url = self::from_container_post();
		}

		if ( $url === '' ) {
			$url = self::from_referer();
		}

		return mb_substr( $url, 0, self::MAX_LENGTH );
	}

	/**
	 * The URL this request asked for, when that is the page carrying the form.
	 *
	 * The host never comes from the request: only the path and query survive, and they are
	 * reattached to the site's own origin. A `Host` header or an absolute URI smuggled into
	 * REQUEST_URI therefore cannot move the address onto another domain.
	 */
	private static function from_request_uri(): string {
		$uri = self::request_uri();

		if ( $uri === '' || ! self::request_is_the_form_page( $uri ) ) {
			return '';
		}

		$parts = wp_parse_url( $uri );
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

		return self::on_this_site( $path . $query );
	}

	/**
	 * The permalink of the post the payload says the form sits on.
	 *
	 * Only reached for AJAX and REST submissions, where the request itself names the handler
	 * rather than the page.
	 */
	private static function from_container_post(): string {
		foreach ( self::CONTAINER_POST_FIELDS as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only; the value is resolved through get_permalink() rather than trusted as an address
			if ( empty( $_POST[ $field ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint() is the sanitizer
			$post_id = absint( wp_unslash( $_POST[ $field ] ) );

			if ( $post_id === 0 ) {
				continue;
			}

			$permalink = get_permalink( $post_id );

			if ( is_string( $permalink ) && $permalink !== '' ) {
				return $permalink;
			}
		}

		return '';
	}

	/**
	 * A Referer, but only one pointing at this site.
	 *
	 * wp_validate_redirect() is core's own answer to "is this address ours", the check behind
	 * wp_safe_redirect(). Anything else answers ''.
	 */
	private static function from_referer(): string {
		$referer = wp_get_referer();

		if ( ! is_string( $referer ) || $referer === '' ) {
			return '';
		}

		return (string) wp_validate_redirect( $referer, '' );
	}

	/**
	 * Whether the current request is the page a form was submitted from.
	 *
	 * @param string $uri The raw REQUEST_URI.
	 */
	private static function request_is_the_form_page( string $uri ): bool {
		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		$path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		foreach ( self::NOT_A_FORM_PAGE as $endpoint ) {
			if ( substr( $path, - strlen( $endpoint ) ) === $endpoint ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Attach a path and query to this site's own origin.
	 *
	 * home_url() is not called with the path: on an installation whose home and site URLs
	 * differ, WordPress lives under a directory the path already contains, and concatenating
	 * the two doubles it. Only the scheme, host and port are taken from it.
	 *
	 * @param string $path_and_query Root-relative, as REQUEST_URI is.
	 */
	private static function on_this_site( string $path_and_query ): string {
		$home = wp_parse_url( home_url() );

		if ( empty( $home['host'] ) ) {
			return '';
		}

		$origin = ( $home['scheme'] ?? 'https' ) . '://' . $home['host'];

		if ( isset( $home['port'] ) ) {
			$origin .= ':' . $home['port'];
		}

		if ( strpos( $path_and_query, '/' ) !== 0 ) {
			$path_and_query = '/' . $path_and_query;
		}

		return (string) esc_url_raw( $origin . $path_and_query );
	}

	/**
	 * The raw REQUEST_URI, unslashed and nothing more — from_request_uri() takes it apart.
	 */
	private static function request_uri(): string {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Taken apart by wp_parse_url() and reassembled onto this site's own origin
		return (string) wp_unslash( $_SERVER['REQUEST_URI'] );
	}
}
