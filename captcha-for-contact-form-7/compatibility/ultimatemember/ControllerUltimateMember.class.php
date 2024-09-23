<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerUltimateMember
 */
class ControllerUltimateMember extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Ultimate Member';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'ultimatemember';

    /**
     * Check if the captcha is enabled for ultimatemember
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_ultimatemember', $this->is_installed() && (int)$this->Controller->get_settings('protection_ultimatemember_enable', 'global') === 1);
    }

    /**
     * Check if the Ultimate Member plugin is installed
     *
     * This method checks if the "UM_Functions" class exists, which indicates that the Ultimate Member plugin is
     * installed.
     *
     * @return bool Returns true if the Ultimate Member plugin is installed, false otherwise.
     */
    public function is_installed(): bool
    {
        return class_exists('UM_Functions');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('Ultimate Member', 'captcha-for-contact-form-7');

        add_action('um_after_login_fields', [$this, 'wp_add_spam_protection']);
        add_action('um_after_register_fields', [$this, 'wp_add_spam_protection']);
        add_action('um_submit_form_errors_hook_login', [$this, 'wp_is_spam'], 5, 1);
        add_action('um_submit_form_errors_hook__registration', [$this, 'wp_is_spam'], 5, 1);
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

        /*
         * Check if Captcha is not correct
         */
        if (!empty($Protection->get_message()) && !empty($_POST)) {
            echo '<div class="um-field-error">' . sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $Protection->get_message()) . '</div>';
        }
    }

    /**
     * Check if a post is considered as spam
     *
     * @param bool  $is_spam         Whether the post is considered as spam initially.
     * @param array $array_post_data The array containing the POST data.
     *
     * @return bool Whether the post is considered as spam.
     */
    public function wp_is_spam(...$args)
    {
        $parameter = $args[0];

        $array_post_data = $_POST;

        $Protection = $this->Controller->get_modul('protection');
        if ($Protection->is_spam($array_post_data)) {
            $this->is_valid = false;

            if (function_exists('UM')) {
                UM()->form()->add_error('f12_captcha', __('This field is required', 'captcha-for-contact-form-7'));
            }

            return true;
        }
        /*
         * Filter to ensure we do not double protect the login / registration for wordpress
         */
        add_filter('f12_cf7_captcha_login_login_validator', '__return_true');

        return false;
    }

}