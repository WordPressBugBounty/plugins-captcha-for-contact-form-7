<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ControllerWoocommerceCheckout
 */
class ControllerWoocommerceCheckout extends BaseController
{
	protected string $name = 'WooCommerce Checkout';
	protected string $id   = 'woocommerce_checkout';
	protected string $settings_key = 'protection_woocommerce_checkout_enable';

	protected array $hooks = [
		['type' => 'action', 'hook' => 'woocommerce_review_order_before_submit', 'method' => 'wp_add_spam_protection'],
		['type' => 'action', 'hook' => 'woocommerce_after_checkout_validation', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
	];

	public function is_installed(): bool
	{
		return class_exists('WooCommerce');
	}

	/**
	 * @param mixed ...$args First argument is the checkout object.
	 */
	public function wp_add_spam_protection(...$args)
	{
		$captcha = $this->get_captcha_html();

		if (!empty($captcha)) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
			echo '<div class="f12-cf7-captcha-checkout">' . $captcha . '</div>';
		}
	}

	/**
	 * @param array     $data   Checkout data
	 * @param \WP_Error $errors Error object
	 * @return mixed
	 */
	public function wp_is_spam($data, $errors)
	{
		$spam_message = $this->check_spam();

		if ($spam_message !== null) {
			$errors->add('spam', $this->format_spam_message($spam_message));
		}

		return $errors;
	}
}
