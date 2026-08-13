<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * bbPress — new topics and new replies.
 *
 * Both forms are plain `method="post"`; bbPress ships no JavaScript that assembles a payload,
 * so anything rendered inside the form is submitted with it and there is no JS module here.
 *
 * ## Where the error goes
 *
 * bbPress collects errors in a shared bag and checks it immediately after the extras hook:
 *
 *     do_action( 'bbp_new_topic_pre_extras', $forum_id );
 *     if ( bbp_has_errors() ) { return; }
 *
 * So `bbp_add_error()` is both the abort and the message — there is no separate "reject"
 * filter, and nothing else needs to be returned. The topic is never created and the visitor
 * gets their text back in the form, which is the behaviour a person who mistyped a captcha
 * needs.
 *
 * ## Why both anonymous and logged-in posting are covered
 *
 * The forms render for both, and so does the protection. Sites that allow anonymous posting are
 * the ones that get hit, but a compromised account posting spam is not rare either, and the
 * plugin's own "skip logged-in users" setting already decides that question globally rather
 * than here.
 */
class ControllerBBPress extends BaseController {

	protected string $id = 'bbpress';
	protected string $settings_key = 'protection_bbpress_enable';

	protected array $hooks = [
		// Rendering: bbPress fires these inside the form, just before the submit wrapper.
		[ 'type' => 'action', 'hook' => 'bbp_theme_before_topic_form_submit_wrapper', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 0 ],
		[ 'type' => 'action', 'hook' => 'bbp_theme_before_reply_form_submit_wrapper', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 0 ],

		// Validation: the last hook before bbPress checks its error bag.
		[ 'type' => 'action', 'hook' => 'bbp_new_topic_pre_extras', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 1 ],
		[ 'type' => 'action', 'hook' => 'bbp_new_reply_pre_extras', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 1 ],
	];

	public function get_name(): string {
		return __( 'bbPress (Forums)', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		$is_installed = class_exists( 'bbPress' );

		$this->get_logger()->debug( 'bbPress installed: ' . ( $is_installed ? 'Yes' : 'No' ) );

		return $is_installed;
	}

	/**
	 * Render the captcha into the topic or reply form.
	 *
	 * @param mixed ...$args Unused; bbPress passes nothing to these hooks.
	 */
	public function wp_add_spam_protection( ...$args ) {
		printf(
			'<div class="f12-captcha-wrapper bbp-form">%s</div>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally.
			$this->get_captcha_html()
		);
	}

	/**
	 * Judge the submission and stop it by adding to bbPress's error bag.
	 *
	 * @param mixed ...$args int $forum_id for a topic, int $topic_id for a reply.
	 *
	 * @return void
	 */
	public function wp_is_spam( ...$args ) {
		if ( ! function_exists( 'bbp_add_error' ) ) {
			return;
		}

		// bbPress runs its own checks — throttling, duplicate detection, the blocklist — before
		// this hook and records them in the same bag. Judging again would spend a captcha
		// session on a submission that is already going to be refused.
		if ( function_exists( 'bbp_has_errors' ) && bbp_has_errors() ) {
			return;
		}

		$context_id = isset( $args[0] ) ? (string) $args[0] : null;
		$message    = $this->check_spam( null, $context_id );

		if ( $message === null ) {
			return;
		}

		$this->get_logger()->warning( 'Spam detected in a bbPress form.', [
			'plugin'     => 'f12-cf7-captcha',
			'context_id' => $context_id,
		] );

		bbp_add_error( 'f12_captcha', $this->format_spam_message( $message ) );
	}
}
