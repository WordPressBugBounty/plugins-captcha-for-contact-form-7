<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ControllerJetForm
 */
class ControllerJetForm extends BaseController
{
	protected string $name = 'JetFormBuilder';
	protected string $id   = 'jetform';
	protected string $settings_key = 'protection_jetform_enable';

	/**
	 * Flag to prevent double injection when both hooks fire.
	 *
	 * @var bool
	 */
	private bool $captcha_injected = false;

	protected array $hooks = [
		['type' => 'filter', 'hook' => 'jet-form-builder/before-start-form',     'method' => 'wp_reset_captcha_flag'],
		['type' => 'action', 'hook' => 'jet-form-builder/before-start-form-row', 'method' => 'wp_add_spam_protection'],
		['type' => 'filter', 'hook' => 'jet-form-builder/before-end-form',       'method' => 'wp_add_spam_protection_fallback'],
		['type' => 'action', 'hook' => 'jet-form-builder/form-handler/before-send', 'method' => 'wp_validation'],
	];

	public function is_installed(): bool
	{
		$is_installed = class_exists('\Jet_Form_Builder\Plugin');
		$this->get_logger()->debug('JetFormBuilder installed: ' . ($is_installed ? 'Yes' : 'No'));
		return $is_installed;
	}

	/**
	 * Reset the captcha injection flag at the start of each form.
	 *
	 * @param string $html Existing HTML from filter chain.
	 * @return string
	 */
	public function wp_reset_captcha_flag( $html = '' )
	{
		$this->captcha_injected = false;
		return $html;
	}

	/**
	 * Add captcha to JetForm before submit button (legacy hook for older versions).
	 * This hook fires inside ob_start()/ob_get_clean(), so echo works here.
	 *
	 * @param mixed ...$args First argument is the form element.
	 */
	public function wp_add_spam_protection( ...$args ) {
		if ( $this->captcha_injected ) {
			return;
		}

		$formElement = $args[0] ?? null;

		try {
			if ( ! class_exists( '\Jet_Form_Builder\Blocks\Types\Action_Button' ) ) {
				return;
			}

			if ( ! ( $formElement instanceof \Jet_Form_Builder\Blocks\Types\Action_Button ) ) {
				return;
			}

			$block_attrs = $formElement->block_attrs ?? [];

			if ( ! isset( $block_attrs['action_type'] ) || $block_attrs['action_type'] !== 'submit' ) {
				return;
			}
		} catch ( \Throwable $e ) {
			$this->get_logger()->debug( 'Legacy hook check failed: ' . $e->getMessage() );
			return;
		}

		$captcha = $this->Controller->get_module( 'protection' )->get_captcha();

		if ( empty( $captcha ) ) {
			return;
		}

		$this->captcha_injected = true;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
		echo $captcha;
	}

	/**
	 * Fallback: inject captcha before form end (fires reliably in all JetFormBuilder versions).
	 * This is an apply_filters hook, so we must return the HTML string.
	 *
	 * @param string $html Existing HTML from filter chain.
	 * @return string
	 */
	public function wp_add_spam_protection_fallback( $html = '' )
	{
		if ( $this->captcha_injected ) {
			return $html;
		}

		$captcha = $this->Controller->get_module( 'protection' )->get_captcha();

		if ( empty( $captcha ) ) {
			return $html;
		}

		$this->captcha_injected = true;

		return $html . '<div class="f12-jetform-captcha-wrapper">' . $captcha . '</div>';
	}

	/**
	 * Validate form submission
	 *
	 * @param mixed $handler
	 */
	public function wp_validation($handler)
	{
		$this->get_logger()->info('Starting JetForm validation.');

		$form_id = null;
		if ( is_object( $handler ) && method_exists( $handler, 'get_form_id' ) ) {
			$form_id = (string) $handler->get_form_id();
		}

		$Protection = $this->Controller->get_module('protection');

		$Protection->set_context( $this->id, $form_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by JetFormBuilder
		if ($Protection->is_spam($_POST)) {
			$message = $Protection->get_message() ?: __('Invalid input detected.', 'captcha-for-contact-form-7');
			$Protection->clear_context();
			$this->get_logger()->warning('Spam detected, validation failed.');

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
			throw new \JFB_Modules\Security\Exceptions\Spam_Exception( $message );
		}
		$Protection->clear_context();
	}
}
