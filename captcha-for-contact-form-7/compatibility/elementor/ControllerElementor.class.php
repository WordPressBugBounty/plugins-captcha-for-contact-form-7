<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerElementor
 */
class ControllerElementor extends BaseController
{
    protected string $name = 'Elementor';
    protected string $id = 'elementor';
    protected string $settings_key = 'protection_elementor_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'elementor_pro/forms/validation', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
        ['type' => 'filter', 'hook' => 'elementor_pro/forms/render/item', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 3],
    ];

    public function is_installed(): bool
    {
        $is_installed = defined('ELEMENTOR_VERSION');
        $this->get_logger()->debug('Elementor installed: ' . ($is_installed ? 'Yes' : 'No'));
        return $is_installed;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_add_spam_protection(...$args)
    {
        $this->get_logger()->info('Starting captcha code insertion for Elementor forms.');

        $item = $args[0];
        $item_index = $args[1];

        /** @var \ElementorPro\Modules\Forms\Widgets\Form $form */
        $form = $args[2];

        $settings = $form->get_settings();
        $number_of_fields = count($settings['form_fields']) - 1;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Elementor
        if ($item_index !== $number_of_fields || (isset($_POST) && !empty($_POST))) {
            return $item;
        }

        $captcha = $this->Controller->get_module('protection')->get_captcha();

        if (!empty($captcha)) {
            $wrapped_captcha = sprintf('<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-text elementor-col-100 elementor-field-required">%s</div>', $captcha);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
            echo $wrapped_captcha;
        }

        return $item;
    }

    /**
     * @param mixed ...$args
     * @return bool|int
     */
    public function wp_is_spam(...$args)
    {
        $this->get_logger()->info('Starting spam validation for Elementor form.');

        $record = $args[0];
        $ajax_handler = $args[1];

        if (null === $record || null === $ajax_handler) {
            return false;
        }

        $fields = $record->get('fields');

        if (null === $fields || !is_array($fields)) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Elementor
        $array_post_data = $_POST;

        $Protection = $this->Controller->get_module('protection');

        if ($Protection->is_spam($array_post_data)) {
            $message = $Protection->get_message();
            $this->get_logger()->warning('Spam detected! Message: ' . $message);

            $field_name = '';
            foreach ($fields as $key => $data) {
                if (isset($data['type']) && 'hidden' !== $data['type']) {
                    $field_name = $key;
                    break;
                }
            }

            $ajax_handler->add_error($field_name, sprintf(esc_html__('Spam detected: %s', 'captcha-for-contact-form-7'), $message));
            return true;
        }

        return false;
    }
}
