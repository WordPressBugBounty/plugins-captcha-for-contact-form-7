<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerWoocommerceRegistration
 */
class ControllerWoocommerceRegistration extends BaseController
{
    protected string $name = 'WooCommerce Registration';
    protected string $id = 'woocommerce_registration';
    protected string $settings_key = 'protection_woocommerce_registration_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'woocommerce_register_form', 'method' => 'wp_add_spam_protection'],
        ['type' => 'filter', 'hook' => 'woocommerce_process_registration_errors', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 4],
    ];

    public function is_installed(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_is_spam(...$args)
    {
        $errors = $args[0];

        $spam_message = $this->check_spam();

        if ($spam_message !== null && is_object($errors)) {
            $errors->add('spam', $this->format_spam_message($spam_message));
        }

        add_filter('f12_cf7_captcha_wc_registration_validated', '__return_true');

        return $errors;
    }
}
