<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\Protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerAvada
 */
class ControllerAvada extends BaseController {
	protected string $name = 'Avada';
	protected string $id = 'avada';
	protected string $settings_key = 'protection_avada_enable';

	protected array $hooks = [
		['type' => 'filter', 'hook' => 'fusion_element_form_content', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 2],
		['type' => 'filter', 'hook' => 'fusion_form_demo_mode', 'method' => 'wp_is_spam'],
	];

	public function is_installed(): bool {
		$is_installed = function_exists( 'Avada' );
		$this->get_logger()->debug( 'Avada installed: ' . ( $is_installed ? 'Yes' : 'No' ) );
		return $is_installed;
	}

	/**
	 * Adds spam protection to the given HTML form
	 *
	 * @param mixed ...$args First argument is the HTML form content string.
	 *
	 * @return string The modified HTML form content with spam protection added
	 */
	public function wp_add_spam_protection( ...$args ): string {
		$html    = (string) ($args[0] ?? '');
		$args_el = $args[1] ?? [];
		$form_id = is_array( $args_el ) && isset( $args_el['form_id'] ) ? (string) $args_el['form_id'] : null;
		$this->get_logger()->info( 'Adding spam protection elements to Avada form HTML.' );

		$Protection = $this->Controller->get_module( 'protection' );
		$Protection->set_context( $this->id, $form_id );
		$captcha_html = $Protection->get_captcha();
		$Protection->clear_context();

		$is_captcha_added = false;

		if ( strpos( $html, '<div class="fusion-form-field fusion-form-submit-field' ) !== false ) {
			$html             = str_replace(
				'<div class="fusion-form-field fusion-form-submit-field',
				$captcha_html . '<div class="fusion-form-field fusion-form-submit-field',
				$html
			);
			$is_captcha_added = true;
		}

		if ( ! $is_captcha_added && strpos( $html, '</form>' ) !== false ) {
			$html             = str_replace(
				'</form>',
				$captcha_html . '</form>',
				$html
			);
			$is_captcha_added = true;
		}

		if ( ! $is_captcha_added ) {
			$html .= $captcha_html;
		}

		return $html;
	}

	/**
	 * Converts form data to an associative array
	 *
	 * @param string $data The form data to be converted
	 *
	 * @return array The associative array representation of the form data
	 */
	protected function form_data_to_arary( string $data ): array {
		$unslashed_data = wp_unslash( $data );
		$value = [];
		parse_str( $unslashed_data, $value );
		return $value;
	}

	/**
	 * Checks if the submitted form data is considered as spam
	 *
	 * @param mixed ...$args The arguments passed to the function
	 *
	 * @return mixed The original value if not spam
	 */
	public function wp_is_spam( ...$args ) {
		$this->get_logger()->info( 'Starting spam check for Avada.' );

		$value = $args[0];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Avada theme
		if ( ! isset( $_POST['formData'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by Avada theme; sanitized after parse_str()
		$array_post_data = $this->form_data_to_arary( wp_unslash( $_POST['formData'] ) );
		$form_id         = isset( $array_post_data['form-id'] ) ? (string) $array_post_data['form-id'] : null;

		/** @var Protection $Protection */
		$Protection = $this->Controller->get_module( 'protection' );

		$Protection->set_context( $this->id, $form_id );
		if ( ! $Protection->is_spam( $array_post_data ) ) {
			$Protection->clear_context();
			return $value;
		}

		$message = $Protection->get_message() ?: __( 'Invalid input detected.', 'captcha-for-contact-form-7' );
		$Protection->clear_context();
		$this->get_logger()->warning( 'Spam detected!' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by Avada theme; sanitized after parse_str()
		parse_str( wp_unslash( $_POST['formData'] ), $fields_array );
		$fields_array = array_map( 'sanitize_text_field', $fields_array );

		$last_visible_key = null;
		$skip_patterns    = [
			'hidden',
			'nonce',
			'submit',
			'fusion-fields-hold-private-data',
			'form-id',
			'f12_multiple_submission_protection',
			'js_start_time',
			'js_end_time',
			'php_start_time',
			'php_end_time'
		];

		foreach ( array_reverse( $fields_array, true ) as $key => $val ) {
			$skip = false;
			foreach ( $skip_patterns as $pattern ) {
				if ( stripos( $key, $pattern ) !== false ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$last_visible_key = $key;
			break;
		}

		if ( ! $last_visible_key ) {
			$last_visible_key = 'general';
		}

		wp_send_json( [
			'status' => 'error',
			'errors' => [
				$last_visible_key => $message,
			],
		] );
		return true;
	}
}
