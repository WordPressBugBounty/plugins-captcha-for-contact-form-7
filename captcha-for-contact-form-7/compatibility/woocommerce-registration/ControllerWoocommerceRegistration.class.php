<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerWoocommerce
 */
class ControllerWoocommerceRegistration extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'WooCommerce Registration';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'woocommerce_registration';

    /**
     * Check if the captcha is enabled for WooCommerce
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_woocommerce_registration', $this->is_installed() && (int)$this->Controller->get_settings('protection_woocommerce_registration_enable', 'global') === 1);
    }

    /**
     * Check if WooCommerce plugin is installed.
     *
     * @return bool True if WooCommerce is installed, false otherwise.
     */
    public function is_installed(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('WooCommerce Registration', 'captcha-for-contact-form-7');

        add_action('woocommerce_register_form', array($this, 'wp_add_spam_protection'));
        add_filter('woocommerce_process_registration_errors', array($this, 'wp_is_spam'), 10, 4);
    }

    /**
     * Add spam protection to the given content.
     *
     * This method adds spam protection to the given content by injecting a captcha field based on the specified
     * validation method.
     *
     * @param mixed ...$args Any number of arguments.
     *
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
    public function wp_add_spam_protection(...$args)
    {
        $Protection = $this->Controller->get_modul('protection');

        echo $Protection->get_captcha();
    }

    /**
     * Check if a post is considered as spam
     *
     * @param bool  $is_spam         Whether the post is considered as spam initially.
     * @param array $array_post_data The array containing the POST data.
     *
     * @return bool Whether the post is considered as spam.
     * @throws \Exception
     */
    public function wp_is_spam(...$args)
    {
        $errors = $args[0];

        $array_post_data = $_POST;

        $Protection = $this->Controller->get_modul('protection');
        if ($Protection->is_spam($array_post_data)) {
            if (is_object($errors)) {
                $errors->add('spam', sprintf(__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $Protection->get_message()));
            }
        }
        /*
         * Filter to ensure we do not double protect the login / registration for wordpress
         */
        add_filter('f12_cf7_captcha_login_login_validator', '__return_true');

        return $errors;
    }
}