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
    public function wp_add_spam_protection(...$args)
    {
        $form = $args[1] ?? null;
        $form_id = is_object( $form ) && isset( $form->id ) ? (string) $form->id : null;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
        echo $this->get_captcha_html( $form_id );
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_is_spam(...$args)
    {
        $this->get_logger()->info('Starting spam validation for a FluentForm form.');

        $errors  = $args[0];
        $form    = $args[2] ?? null;
        $form_id = is_object( $form ) && isset( $form->id ) ? (string) $form->id : null;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by Fluent Forms; sanitized after parse_str()
        $formData = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';

        $decodedFormData = urldecode($formData);
        parse_str($decodedFormData, $array_post_data);

        $Protection = $this->Controller->get_module('protection');

        $Protection->set_context( $this->id, $form_id );
        if ($Protection->is_spam($array_post_data)) {
            $message = $Protection->get_message();
            $Protection->clear_context();
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

        $Protection->clear_context();
        return $errors;
    }
}
