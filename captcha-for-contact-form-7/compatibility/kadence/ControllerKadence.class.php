<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kadence Blocks — Advanced Form.
 *
 * Kadence ships two form blocks and this integration covers exactly one of them, deliberately.
 *
 * The **Advanced Form** (`kadence/advanced-form`) offers
 * `kadence_blocks_advanced_form_submission_reject`, a filter built for precisely this purpose,
 * with a companion filter for the message shown to the visitor.
 *
 * The **classic Form block** offers nothing comparable. Its AJAX handler exposes only
 * `kadence_blocks_form_verify_nonce`, which decides *whether* the nonce is checked and cannot
 * make the submission fail — `$valid` is a local that no filter can reach. Protecting it would
 * mean patching Kadence. That is not the loss it first appears: Kadence's own changelog says
 * "Begin sunsetting classic form block — hide in block appender, classic form blocks still
 * usable", so the block with no rejection hook is the one being retired. Sites still running it
 * get no captcha and no error, which is worth knowing before someone reports it as a bug.
 *
 * No JavaScript module: the Advanced Form script serialises the form element with
 * `new FormData()`, so fields rendered into the markup are part of the submission already.
 */
class ControllerKadence extends BaseController {

	protected string $name = 'Kadence Blocks (Advanced Form)';
	protected string $id = 'kadence';
	protected string $settings_key = 'protection_kadence_enable';

	/** The block this integration renders into. */
	private const BLOCK_NAME = 'kadence/advanced-form';

	/**
	 * The verdict for the request being validated.
	 *
	 * Kadence asks two separate questions — "reject?" and then "with what message?" — so the
	 * answer has to survive between the two filters. The protection stack must run once, not
	 * once per question.
	 */
	private ?string $rejection_message = null;

	protected array $hooks = [
		[ 'type' => 'filter', 'hook' => 'render_block', 'method' => 'wp_add_spam_protection', 'priority' => 100, 'args' => 2 ],
		[ 'type' => 'filter', 'hook' => 'kadence_blocks_advanced_form_submission_reject', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 4 ],
		[ 'type' => 'filter', 'hook' => 'kadence_blocks_advanced_form_submission_reject_message', 'method' => 'wp_rejection_message', 'priority' => 100, 'args' => 1 ],
	];

	public function is_installed(): bool {
		return class_exists( 'Kadence_Blocks_Frontend' ) || defined( 'KADENCE_BLOCKS_VERSION' );
	}

	/**
	 * Matches the wrapper around the submit button, so the captcha can go before the whole
	 * field rather than between the button and its container.
	 */
	private const SUBMIT_BLOCK = '~<div[^>]*class="[^"]*kb-(?:adv-)?form-field[^"]*submit-field[^"]*"[^>]*>~i';

	/** Matches the submit control itself, for markup that does not wrap it as expected. */
	private const SUBMIT_CONTROL = '~<button[^>]*class="[^"]*kb-(?:adv-form-)?submit[^"]*"~i';

	/**
	 * Inject the captcha into the rendered Advanced Form block.
	 *
	 * Kadence has no render filter of its own for the form body, so this rides on WordPress's
	 * `render_block` — the standard seam for any block-based form, and the reason this needs no
	 * knowledge of Kadence's internals beyond where the submit button sits.
	 *
	 * @param mixed ...$args string $content, array $block.
	 *
	 * @return string
	 */
	public function wp_add_spam_protection( ...$args ) {
		$content = (string) ( $args[0] ?? '' );
		$block   = $args[1] ?? null;

		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== self::BLOCK_NAME ) {
			return $content;
		}

		// render_block fires for every block on the page; without this the captcha would be
		// rendered into a form that already has one when blocks nest.
		if ( strpos( $content, 'f12-captcha-wrapper' ) !== false ) {
			return $content;
		}

		$form_id = isset( $block['attrs']['id'] ) ? (string) $block['attrs']['id'] : null;
		$captcha = sprintf(
			'<div class="f12-captcha-wrapper kb-form-field">%s</div>',
			$this->get_captcha_html( $form_id )
		);

		foreach ( [ self::SUBMIT_BLOCK, self::SUBMIT_CONTROL ] as $pattern ) {
			if ( preg_match( $pattern, $content, $m, PREG_OFFSET_CAPTURE ) ) {
				return substr_replace( $content, $captcha, $m[0][1], 0 );
			}
		}

		// No recognisable submit control. Before the closing form tag still puts the captcha
		// inside the form, which is what matters for it being submitted at all.
		$close = strripos( $content, '</form>' );

		return $close !== false ? substr_replace( $content, $captcha, $close, 0 ) : $content . $captcha;
	}

	/**
	 * Judge the submission.
	 *
	 * @param mixed ...$args bool $rejected, array $form_args, array $processed_fields, int $post_id.
	 *
	 * @return bool
	 */
	public function wp_is_spam( ...$args ) {
		$rejected = (bool) ( $args[0] ?? false );

		$this->rejection_message = null;

		// Something else already rejected this; judging it again would consume a captcha
		// session for a submission that cannot succeed.
		if ( $rejected ) {
			return true;
		}

		$post_id = isset( $args[3] ) ? (string) $args[3] : null;
		$message = $this->check_spam( null, $post_id );

		if ( $message === null ) {
			return false;
		}

		$this->rejection_message = $this->format_spam_message( $message );

		return true;
	}

	/**
	 * Supply the text for the rejection we caused.
	 *
	 * Kadence uses one message filter for every rejection, so a rejection somebody else caused
	 * must keep its own wording rather than being relabelled a captcha failure.
	 *
	 * @param mixed ...$args string $message.
	 *
	 * @return mixed
	 */
	public function wp_rejection_message( ...$args ) {
		return $this->rejection_message ?? ( $args[0] ?? '' );
	}
}
