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
    protected string $name = 'Ultimate Member';
    protected string $id = 'ultimatemember';
    protected string $settings_key = 'protection_ultimatemember_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'um_after_login_fields', 'method' => 'wp_add_spam_protection'],
        ['type' => 'action', 'hook' => 'um_after_register_fields', 'method' => 'wp_add_spam_protection'],
        ['type' => 'action', 'hook' => 'um_submit_form_errors_hook_login', 'method' => 'wp_is_spam', 'priority' => 5],
        ['type' => 'action', 'hook' => 'um_submit_form_errors_hook__registration', 'method' => 'wp_is_spam', 'priority' => 5],
    ];

    public function is_installed(): bool
    {
        $is_installed = class_exists('UM_Functions');
        $this->get_logger()->debug('Ultimate Member installed: ' . ($is_installed ? 'Yes' : 'No'));
        return $is_installed;
    }

    /**
     * @param mixed ...$args
     * @return void
     */
    public function wp_add_spam_protection(...$args)
    {
        $this->get_logger()->info('Starting captcha code output for Ultimate Member forms.');

        $Protection = $this->Controller->get_module('protection');
        $captcha = $Protection->get_captcha();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
        echo $captcha;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Ultimate Member
        if (!empty($Protection->get_message()) && !empty($_POST)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
            echo '<div class="um-field-error">' . sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $Protection->get_message()) . '</div>';
        }
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function wp_is_spam(...$args)
    {
        $this->get_logger()->info('Starting spam check for Ultimate Member.');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Ultimate Member
        $array_post_data = $_POST;

        $Protection = $this->Controller->get_module('protection');

        if ($Protection->is_spam($array_post_data)) {
            $this->get_logger()->warning('Spam detected!');
            $this->is_valid = false;

            if (function_exists('UM')) {
                $message = $Protection->get_message();
                UM()->form()->add_error('f12_captcha', sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $message));
            }

            return true;
        }

        add_filter('f12_cf7_captcha_wc_login_validated', '__return_true');
        add_filter('f12_cf7_captcha_wc_registration_validated', '__return_true');

        return false;
    }
}
