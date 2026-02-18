<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWPForms
 */
class ControllerWPForms extends BaseController {
	protected string $name = 'WPForms';
	protected string $id = 'wpforms';
	protected string $settings_key = 'protection_wpforms_enable';

	protected array $hooks = [
		['type' => 'action', 'hook' => 'wpforms_frontend_output', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 5],
		['type' => 'filter', 'hook' => 'wpforms_process_initial_errors', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
	];

	public function is_installed(): bool
	{
		return class_exists('WPForms');
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function wp_add_spam_protection(...$args)
	{
		$form_data = $args[0] ?? null;
		$form_id   = is_array( $form_data ) && isset( $form_data['id'] ) ? (string) $form_data['id'] : null;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
		echo $this->get_captcha_html( $form_id );
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function wp_is_spam(...$args)
	{
		$errors = $args[0];
		$form_data = $args[1];

		if (!isset($form_data['id'])) {
			return $errors;
		}

		$form_id = (string) $form_data['id'];

		$spam_message = $this->check_spam( null, $form_id );

		if ($spam_message !== null) {
			$errors[$form_data['id']]['footer'] = $this->format_spam_message($spam_message);
		}

		return $errors;
	}
}
