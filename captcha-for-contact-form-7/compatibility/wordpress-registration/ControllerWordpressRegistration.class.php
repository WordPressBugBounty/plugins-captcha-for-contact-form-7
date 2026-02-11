<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWordpressRegistration
 */
class ControllerWordpressRegistration extends BaseController {
	protected string $name = 'WordPress Registration';
	protected string $id = 'wordpress_registration';
	protected string $settings_key = 'protection_wordpress_registration_enable';

	protected array $hooks = [
		['type' => 'action', 'hook' => 'register_form', 'method' => 'wp_add_spam_protection'],
		['type' => 'filter', 'hook' => 'registration_errors', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 3],
	];

	public function is_installed(): bool
	{
		return true;
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function wp_is_spam(...$args)
	{
		/** @var \WP_Error $error */
		$error = $args[0];

		if (apply_filters('f12_cf7_captcha_wc_registration_validated', false)) {
			return $error;
		}

		$spam_message = $this->check_spam();

		if ($spam_message !== null) {
			$error->add('spam', $this->format_spam_message($spam_message));
		}

		return $error;
	}
}
