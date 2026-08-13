<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MC4WP: Mailchimp for WordPress — newsletter signup forms.
 *
 * Signup forms are a heavier spam target than contact forms and the damage is different: a
 * contact form delivers junk to an inbox, a signup form injects junk addresses into a mailing
 * list, where hard bounces and spam-trap hits degrade the sender reputation of every later
 * campaign. MC4WP ships a honeypot of its own (`_mc4wp_honeypot`) and a timestamp, which stop
 * the bots that fill every field blindly and nothing else.
 *
 * No JavaScript module is needed. MC4WP's frontend posts the form natively — only its admin
 * scripts use XHR — so the global fallback in JavaScriptProtection stamps `js_end_time` on
 * submit and on submit-button click without any help from here.
 */
class ControllerMC4WP extends BaseController {

	protected string $id = 'mc4wp';
	protected string $settings_key = 'protection_mc4wp_enable';

	/**
	 * Error key pushed onto MC4WP's error list.
	 *
	 * MC4WP keeps errors as message *keys* and looks the text up per form, so this has to be
	 * registered through `mc4wp_form_messages` as well or the visitor gets the generic
	 * "Oops. Something went wrong." fallback.
	 */
	private const ERROR_KEY = 'f12_captcha';

	protected array $hooks = [
		[ 'type' => 'filter', 'hook' => 'mc4wp_form_content', 'method' => 'wp_add_spam_protection', 'priority' => 100, 'args' => 3 ],
		[ 'type' => 'filter', 'hook' => 'mc4wp_form_errors', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 2 ],
		[ 'type' => 'filter', 'hook' => 'mc4wp_form_messages', 'method' => 'wp_register_message', 'priority' => 10, 'args' => 1 ],
	];

	public function get_name(): string {
		return __( 'MC4WP: Mailchimp for WordPress', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		return class_exists( 'MC4WP_Form' );
	}

	/**
	 * Matches the block element wrapping a submit control, so the captcha can be placed before
	 * the whole paragraph rather than inside it.
	 *
	 * MC4WP's default form body is `<p><input type="submit" value="Sign up"></p>`. Inserting
	 * directly before the button therefore lands *inside* that paragraph — and the captcha
	 * markup is a `<div>`, which a browser is not allowed to keep inside a `<p>`: it closes
	 * the paragraph early and the button ends up orphaned outside the field group.
	 *
	 * Delimited with `~` rather than the `!` used elsewhere in this plugin, because the
	 * negative lookahead `(?!` contains an exclamation mark — PHP would end the pattern there
	 * and report "Unknown modifier '<'" on the remainder.
	 */
	private const SUBMIT_BLOCK = '~<(p|div)\b[^>]*>(?:(?!</\1>)[\s\S])*?<(?:input|button)[^>]*type=[\'"]submit[\'"][\s\S]*?</\1>~i';

	/** Matches a bare submit control, for form bodies that do not wrap it. */
	private const SUBMIT_CONTROL = '~<(?:input|button)[^>]*type=[\'"]submit[\'"]~i';

	/**
	 * Inject the captcha into the form body.
	 *
	 * The content is whatever the site owner wrote in MC4WP's form editor, submit button
	 * included, so appending would put the captcha *below* the button.
	 *
	 * Unlike the inherited version this is a filter, not an action: MC4WP takes the form body
	 * as a return value rather than reading whatever was echoed.
	 *
	 * @param mixed ...$args string $content, MC4WP_Form $form, MC4WP_Form_Element $element.
	 *
	 * @return string The form body with the captcha inserted.
	 */
	public function wp_add_spam_protection( ...$args ) {
		$content = (string) ( $args[0] ?? '' );
		$form    = $args[1] ?? null;

		$captcha = sprintf(
			'<div class="f12-captcha-wrapper">%s</div>',
			$this->get_captcha_html( $this->get_form_id( $form ) )
		);

		return $this->insert_before_submit( $content, $captcha );
	}

	/**
	 * Place the captcha above the submit control without breaking the surrounding markup.
	 *
	 * Split out from the filter so it can be tested against the shapes real form bodies take,
	 * which is the part that actually goes wrong.
	 */
	public function insert_before_submit( string $content, string $captcha ): string {
		// Preferred: before the paragraph or div that holds the button.
		if ( preg_match( self::SUBMIT_BLOCK, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return substr_replace( $content, $captcha, $matches[0][1], 0 );
		}

		// The button stands on its own, so inserting in front of it is safe.
		if ( preg_match( self::SUBMIT_CONTROL, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return substr_replace( $content, $captcha, $matches[0][1], 0 );
		}

		// No recognisable button — some themes submit via script. Better below the fields than
		// not at all.
		return $content . $captcha;
	}

	/**
	 * Judge a submission.
	 *
	 * MC4WP filters its error list down to strings, so only the key may be added here — the
	 * text comes from wp_register_message().
	 *
	 * @param mixed ...$args array $errors, MC4WP_Form $form.
	 *
	 * @return array
	 */
	public function wp_is_spam( ...$args ) {
		$errors = is_array( $args[0] ?? null ) ? $args[0] : [];
		$form   = $args[1] ?? null;

		// MC4WP runs its own checks first and this filter fires either way. Judging a
		// submission that is already rejected would burn a captcha session and log a block for
		// a request that was never going to succeed.
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		if ( $this->check_spam( null, $this->get_form_id( $form ) ) !== null ) {
			$errors[] = self::ERROR_KEY;
		}

		return $errors;
	}

	/**
	 * Register the text shown for our error key.
	 *
	 * Deliberately static rather than the specific reason from the protection module.
	 * MC4WP_Form::__construct() calls load_messages(), so this filter has already run by the
	 * time validation happens and there is nothing to inject the live reason into. Making the
	 * message depend on whether the form object happened to be built before or after the check
	 * would show visitors different wording for the same rejection. The precise reason is in
	 * the block log, which is where it is useful anyway.
	 *
	 * @param mixed ...$args array $messages.
	 *
	 * @return array
	 */
	public function wp_register_message( ...$args ) {
		$messages = is_array( $args[0] ?? null ) ? $args[0] : [];

		$messages[ self::ERROR_KEY ] = [
			'type' => 'error',
			'text' => esc_html__(
				'Your sign-up could not be verified. Please check the captcha and try again.',
				'captcha-for-contact-form-7'
			),
		];

		return $messages;
	}

	/**
	 * The post ID of the form being rendered or validated, for per-form settings.
	 *
	 * @param mixed $form Expected to be an MC4WP_Form.
	 */
	private function get_form_id( $form ): ?string {
		if ( is_object( $form ) && isset( $form->ID ) ) {
			return (string) $form->ID;
		}

		return null;
	}
}
