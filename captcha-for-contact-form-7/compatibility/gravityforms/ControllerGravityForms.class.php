<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerGravityForms
 */
class ControllerGravityForms extends BaseController {
	protected string $name = 'GravityForms';
	protected string $id = 'gravityforms';
	protected string $settings_key = 'protection_gravityforms_enable';

	/**
	 * Cached spam check result to avoid calling is_spam() twice.
	 * The captcha hash is single-use and invalidated after the first check.
	 * GF calls gform_validation first, then gform_entry_is_spam,
	 * so we cache the result from wp_validation for wp_is_spam.
	 */
	private ?bool $spam_result = null;

	protected array $hooks = [
		['type' => 'filter', 'hook' => 'gform_get_form_filter', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 2],
		['type' => 'filter', 'hook' => 'gform_entry_is_spam', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 3],
		['type' => 'filter', 'hook' => 'gform_validation', 'method' => 'wp_validation', 'priority' => 10, 'args' => 3],
	];

	public function is_installed(): bool {
		$is_installed = class_exists( 'GFCommon' );
		$this->get_logger()->debug( 'Gravity Forms installed: ' . ( $is_installed ? 'Yes' : 'No' ) );
		return $is_installed;
	}

	public function wp_validation( $validation_result ) {
		$form       = $validation_result['form'];
		$form_id    = isset( $form['id'] ) ? (string) $form['id'] : null;
		$Protection = $this->Controller->get_module( 'protection' );

		$Protection->set_context( $this->id, $form_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Gravity Forms
		$this->spam_result = $Protection->is_spam( $_POST );
		$Protection->clear_context();

		if ( $this->spam_result ) {
			$this->get_logger()->warning( 'Spam detected in wp_validation - form will be blocked.' );

			$validation_result['is_valid'] = false;

			if ( ! empty( $form['fields'] ) ) {
				$last_index = array_key_last( $form['fields'] );
				if ( $last_index !== null ) {
					$form['fields'][ $last_index ]->failed_validation  = true;
					$form['fields'][ $last_index ]->validation_message = $Protection->get_message()
						?: __( 'Invalid input detected.', 'captcha-for-contact-form-7' );
				}
			}

			$validation_result['form'] = $form;
		}

		return $validation_result;
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function wp_add_spam_protection( ...$args ) {
		$this->get_logger()->info( 'Starting captcha code insertion into Gravity Forms form.' );

		$form_string = $args[0];
		$form        = $args[1] ?? null;
		$form_id     = is_array( $form ) && isset( $form['id'] ) ? (string) $form['id'] : null;

		$Protection = $this->Controller->get_module( 'protection' );
		$Protection->set_context( $this->id, $form_id );
		$captcha = $Protection->get_captcha();
		$Protection->clear_context();

		if ( empty( $captcha ) ) {
			return $form_string;
		}

		if ( strpos( $form_string, "<div class='gform_footer" ) !== false ) {
			$form_string = str_replace( "<div class='gform_footer", $captcha . "<div class='gform_footer", $form_string );
		} elseif ( strpos( $form_string, "<div class='gform-footer" ) !== false ) {
			$form_string = str_replace( "<div class='gform-footer", $captcha . "<div class='gform-footer", $form_string );
		} else {
			$form_string .= $captcha;
		}

		return $form_string;
	}

	/**
	 * @param mixed ...$args
	 * @return bool
	 */
	public function wp_is_spam( ...$args ) {
		$this->get_logger()->info( 'Starting spam check for Gravity Forms entry.' );

		$is_spam = $args[0];

		if ( $is_spam === true ) {
			return true;
		}

		// Use cached result from wp_validation() to avoid calling is_spam()
		// again (the captcha hash is single-use and already consumed).
		if ( $this->spam_result !== null ) {
			$result            = $this->spam_result;
			$this->spam_result = null;

			if ( $result ) {
				$this->get_logger()->warning( 'Spam detected! Marking entry as spam.' );
				return true;
			}

			return $is_spam;
		}

		// Fallback: if wp_validation didn't run (shouldn't happen)
		$Protection = $this->Controller->get_module( 'protection' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Gravity Forms
		if ( $Protection->is_spam( $_POST ) ) {
			$this->get_logger()->warning( 'Spam detected! Marking entry as spam.' );
			return true;
		}

		return $is_spam;
	}
}
