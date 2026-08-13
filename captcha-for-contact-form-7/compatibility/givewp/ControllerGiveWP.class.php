<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GiveWP — the classic donation form.
 *
 * Donation forms are not spammed the way a contact form is. They are used for **card testing**:
 * someone runs stolen card numbers through a small donation to find out which ones still work.
 * The site owner does not get junk in an inbox, they get chargebacks and a payment processor
 * asking questions. That is why this is worth protecting even on a site nobody would bother
 * sending spam to.
 *
 * ## This covers one of GiveWP's two form systems, deliberately
 *
 * GiveWP 3.0 introduced a second, React-rendered donation form which became the default, and
 * both ship in 4.x. This integration handles the **classic** form: it is rendered in PHP, so a
 * captcha can be echoed into it, and every protection module works, timing checks included.
 *
 * The new form cannot be covered the same way and is not covered here. Its payload is
 * serialised from React state rather than from the form element:
 *
 *     function yt(e){ const t = new FormData; for (const r in e) { … t.append(r, n) … } return t }
 *
 * so only fields registered in GiveWP's Fields API schema ever reach the server. A field can be
 * appended — GiveWP adds its own honeypot that way, through `givewp_donation_form_schema` — and
 * the flow can be aborted by throwing from `givewp_donate_form_data_validated`. What does not
 * survive is our JavaScript timing evidence: `js_start_time` and `js_end_time` are written into
 * DOM inputs that React never reads, so the strongest signal we have would silently be absent.
 * GiveWP's own `SecurityChallenge` field type is the intended seam, but rendering a custom field
 * in the donor-facing form needs a template registrar that is not part of the public
 * `window.givewp.form.*` API. See plan/integrations_backlog.md before attempting it.
 *
 * ## No JavaScript module
 *
 * The classic form posts the form element, by AJAX or as a plain submit, so anything rendered
 * inside it travels with the submission.
 */
class ControllerGiveWP extends BaseController {

	protected string $id = 'givewp';
	protected string $settings_key = 'protection_givewp_enable';

	protected array $hooks = [
		// Fires inside the form, immediately before the submit button.
		[ 'type' => 'action', 'hook' => 'give_donation_form_before_submit', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 1 ],

		// The last hook before GiveWP checks give_get_errors() and bails.
		[ 'type' => 'action', 'hook' => 'give_checkout_error_checks', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2 ],
	];

	public function get_name(): string {
		return __( 'GiveWP (Classic Donation Form)', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		$is_installed = class_exists( 'Give' );

		$this->get_logger()->debug( 'GiveWP installed: ' . ( $is_installed ? 'Yes' : 'No' ) );

		return $is_installed;
	}

	/**
	 * Render the captcha into the donation form.
	 *
	 * @param mixed ...$args int $form_id.
	 */
	public function wp_add_spam_protection( ...$args ) {
		$form_id = isset( $args[0] ) ? (string) $args[0] : null;

		printf(
			'<div class="f12-captcha-wrapper give-donation-form">%s</div>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally.
			$this->get_captcha_html( $form_id )
		);
	}

	/**
	 * Judge the donation and stop it by adding to GiveWP's error bag.
	 *
	 * GiveWP checks `give_get_errors()` on the line after this hook and returns — or, for an
	 * AJAX submission, fires `give_ajax_donation_errors` and dies. Either way the error is both
	 * the abort and the message, so there is nothing to return.
	 *
	 * @param mixed ...$args array|bool $valid_data, array $post_data (deprecated).
	 *
	 * @return void
	 */
	public function wp_is_spam( ...$args ) {
		if ( ! function_exists( 'give_set_error' ) ) {
			return;
		}

		// The donation is submitted twice: first as an AJAX dry run, which validates, answers
		// 'success' and dies without creating anything, then for real as a plain POST. Both
		// carry identical data and both reach this hook.
		//
		// A captcha session is single-use by design, so judging the dry run spends it and the
		// real submission — the one that counts — then finds nothing and fails. The symptom is
		// the worst kind there is: a *correct* answer rejected every time, while a wrong one
		// produces the same error and so looks perfectly consistent. Found by the e2e spec, not
		// by reading the code.
		//
		// So only the real submission is judged. The donor learns of a captcha failure a moment
		// later than of GiveWP's own field errors, which is the same order every other
		// integration here delivers.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- GiveWP verifies its own nonce before this hook runs.
		if ( isset( $_POST['give_ajax'] ) ) {
			return;
		}

		// GiveWP validates the amount, the donor and the gateway before this hook and records
		// any problem in the same bag. A donation that is going to be refused anyway must not
		// also spend a captcha session.
		if ( function_exists( 'give_get_errors' ) && give_get_errors() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- GiveWP verifies its own nonce before this hook runs.
		$form_id = isset( $_POST['give-form-id'] )
			? sanitize_text_field( wp_unslash( $_POST['give-form-id'] ) )
			: null;

		$message = $this->check_spam( null, $form_id );

		if ( $message === null ) {
			return;
		}

		$this->get_logger()->warning( 'Spam detected in a GiveWP donation form.', [
			'plugin'  => 'f12-cf7-captcha',
			'form_id' => $form_id,
		] );

		give_set_error( 'f12_captcha', $this->format_spam_message( $message ) );
	}
}
