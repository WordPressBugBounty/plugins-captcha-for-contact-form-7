<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jetpack Forms — the `jetpack/contact-form` block and its `[contact-form]` shortcode.
 *
 * Both render through the same method, so one pair of filters covers both.
 *
 * ## The one thing to know before touching this
 *
 * `jetpack_contact_form_is_spam` distinguishes two outcomes that most form plugins conflate:
 *
 * - returning `true` **flags** — the submission is still stored, prefixed `***SPAM***`, and the
 *   visitor is told nothing at all;
 * - returning a `WP_Error` **aborts**, and the message reaches the visitor.
 *
 * This returns the WP_Error. A captcha failure is usually a person who mistyped, and filing
 * their enquiry away as spam while showing them a success message is the worst of both
 * outcomes: they believe they have written to the site owner, and the site owner never reads
 * it. Flagging is right for a probabilistic judgement like Akismet's; a captcha is not one.
 *
 * ## No JavaScript module
 *
 * `dist/modules/form/view.js` submits with `new FormData( formElement )` to
 * `admin-ajax.php?action=grunion-contact-form`, and falls back to a plain `method="post"` form
 * when JavaScript does not run. Either way every field inside the form travels, so rendering
 * ours into the markup is the whole integration.
 *
 * ## The module has to be on, but the site does not have to be connected
 *
 * Jetpack renders this block as an empty wrapper `<div>` — no fields, no `<form>` — unless the
 * `contact-form` module is active. Module activation normally goes through the WordPress.com
 * connection, but the option alone is enough:
 *
 *     update_option( 'jetpack_active_modules', [ 'contact-form' ] );
 *
 * A site with no connection at all then renders a complete, working form. That is what makes
 * this integration testable in the e2e stack; see plan/integrations_backlog.md.
 */
class ControllerJetpack extends BaseController {

	protected string $id = 'jetpack';
	protected string $settings_key = 'protection_jetpack_enable';

	protected array $hooks = [
		[ 'type' => 'filter', 'hook' => 'jetpack_contact_form_html', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 1 ],
		[ 'type' => 'filter', 'hook' => 'jetpack_contact_form_is_spam', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2 ],
	];

	public function get_name(): string {
		return __( 'Jetpack Forms', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		$is_installed = class_exists( '\Automattic\Jetpack\Forms\ContactForm\Contact_Form' );

		$this->get_logger()->debug( 'Jetpack Forms installed: ' . ( $is_installed ? 'Yes' : 'No' ) );

		return $is_installed;
	}

	/**
	 * Render the captcha into the form.
	 *
	 * The filter hands over the whole rendered block: a wrapper `<div>`, the `<form>`, and
	 * Jetpack's own hidden fields last. Inserting before the closing `</form>` therefore lands
	 * after every field and before nothing that matters, which is both the simplest anchor and
	 * the only one that does not depend on how the form was built — a form assembled from
	 * blocks has no `</p>` wrapper, one from the shortcode does.
	 *
	 * @param mixed ...$args string $html The rendered contact form.
	 *
	 * @return string
	 */
	public function wp_add_spam_protection( ...$args ) {
		$html = (string) ( $args[0] ?? '' );

		// The filter also fires for markup that never became a form — the module renders an
		// empty wrapper when a block carries no fields.
		if ( stripos( $html, '</form>' ) === false ) {
			return $html;
		}

		// Jetpack renders every form on the page through this filter, and a page may hold more
		// than one. Guarding on our own marker keeps a second captcha out of a form that was
		// already given one, without needing to know which form this is.
		if ( strpos( $html, 'f12-captcha-wrapper' ) !== false ) {
			return $html;
		}

		$captcha = sprintf(
			'<div class="f12-captcha-wrapper grunion-field-wrap">%s</div>',
			$this->get_captcha_html( $this->extract_form_id( $html ) )
		);

		$close = strripos( $html, '</form>' );

		return substr_replace( $html, $captcha, $close, 0 );
	}

	/**
	 * Judge the submission.
	 *
	 * @param mixed ...$args bool|\WP_Error $is_spam, array $akismet_values.
	 *
	 * @return bool|\WP_Error `true` flags, a WP_Error aborts — see the class docblock.
	 */
	public function wp_is_spam( ...$args ) {
		$is_spam = $args[0] ?? false;

		// Somebody else has already judged this — Akismet and Jetpack's own blocklist both hang
		// on this filter. Running the protection stack again would spend a captcha session on a
		// submission that cannot succeed either way, and the first verdict is the one the
		// visitor should be told about.
		if ( $is_spam instanceof \WP_Error || $is_spam === true ) {
			return $is_spam;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Jetpack verifies its own nonce before this filter runs.
		$form_id = isset( $_POST['contact-form-id'] )
			? sanitize_text_field( wp_unslash( $_POST['contact-form-id'] ) )
			: null;

		$message = $this->check_spam( null, $form_id );

		if ( $message === null ) {
			return $is_spam;
		}

		$this->get_logger()->warning( 'Spam detected in a Jetpack form.', [
			'plugin'  => 'f12-cf7-captcha',
			'form_id' => $form_id,
		] );

		return new \WP_Error( 'f12-captcha', $this->format_spam_message( $message ) );
	}

	/**
	 * The form's own id, for per-form settings.
	 *
	 * Read out of the markup because the filter passes nothing else. Jetpack writes it as a
	 * hidden field immediately before the closing tag; absent it, per-form overrides simply do
	 * not apply and the global settings are used, which is the correct degradation.
	 */
	private function extract_form_id( string $html ): ?string {
		if ( preg_match( '~name=[\'"]contact-form-id[\'"]\s+value=[\'"]([^\'"]+)[\'"]~i', $html, $m ) ) {
			return $m[1];
		}

		return null;
	}
}
