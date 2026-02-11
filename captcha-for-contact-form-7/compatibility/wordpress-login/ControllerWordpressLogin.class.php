<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ControllerWordpressLogin
 */
class ControllerWordpressLogin extends BaseController {
	protected string $name = 'WordPress Login';
	protected string $id = 'wordpress_login';
	protected string $settings_key = 'protection_wordpress_login_enable';

	protected array $hooks = [
		['type' => 'action', 'hook' => 'login_form', 'method' => 'wp_add_spam_protection'],
		['type' => 'filter', 'hook' => 'wp_authenticate_user', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
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
		$user = $args[0];

		if (apply_filters('f12_cf7_captcha_wc_login_validated', false)) {
			return $user;
		}

		$spam_message = $this->check_spam();

		if ($spam_message !== null) {
			return new \WP_Error('spam', $this->format_spam_message($spam_message));
		}

		return $user;
	}
}
