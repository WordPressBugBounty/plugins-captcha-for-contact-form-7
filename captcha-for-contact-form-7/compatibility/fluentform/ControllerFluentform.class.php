<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerFluentform
 */
class ControllerFluentform extends BaseController
{
    protected string $name = 'Fluent Forms';
    protected string $id = 'fluentform';
    protected string $settings_key = 'protection_fluentform_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'fluentform/render_item_submit_button', 'method' => 'wp_add_spam_protection', 'priority' => 5, 'args' => 2],
        ['type' => 'filter', 'hook' => 'fluentform/validation_errors', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 4],
    ];

    public function is_installed(): bool
    {
        $is_installed = defined('FLUENTFORM');
        $this->get_logger()->debug('FluentForm installed: ' . ($is_installed ? 'Yes' : 'No'));
        return $is_installed;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_is_spam(...$args)
    {
        $this->get_logger()->info('Starting spam validation for a FluentForm form.');

        $errors = $args[0];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by Fluent Forms; sanitized after parse_str()
        $formData = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';

        $decodedFormData = urldecode($formData);
        parse_str($decodedFormData, $array_post_data);

        $Protection = $this->Controller->get_module('protection');

        if ($Protection->is_spam($array_post_data)) {
            $message = $Protection->get_message();
            $this->get_logger()->warning('Spam detected. Sending JSON error message.');

            wp_send_json(
                [
                    'errors' => [
                        'captcha-response' => [
                            sprintf(__('Captcha verification failed: %s', 'captcha-for-contact-form-7'), $message),
                        ],
                    ],
                ],
                422
            );
        }

        return $errors;
    }
}
