<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerCF7
 */
class ControllerCF7 extends BaseController
{
    protected string $name = 'Contact Forms 7';
    protected string $id = 'cf7';
    protected string $settings_key = 'protection_cf7_enable';

    protected array $hooks = [
        ['type' => 'filter', 'hook' => 'wpcf7_form_elements', 'method' => 'wp_add_spam_protection', 'priority' => 100],
        ['type' => 'filter', 'hook' => 'wpcf7_spam', 'method' => 'wp_is_spam', 'priority' => 100, 'args' => 2],
    ];

    public function is_installed(): bool
    {
        return function_exists('wpcf7');
    }

    /**
     * Get the current CF7 form ID if available.
     *
     * @return string|null
     */
    private function get_current_form_id(): ?string
    {
        if ( function_exists( 'wpcf7_get_current_contact_form' ) ) {
            $form = wpcf7_get_current_contact_form();
            if ( $form ) {
                return (string) $form->id();
            }
        }
        return null;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_add_spam_protection(...$args)
    {
        $content = $args[0];

        $form_id = $this->get_current_form_id();
        $captcha = sprintf('<p><span class="wpcf7-form-control-wrap">%s</span></p>', $this->get_captcha_html( $form_id ));

        if (preg_match('!<input(.*?)type="submit"!', $content, $matches)) {
            $content = str_replace($matches[0], $captcha . $matches[0], $content);
        } else {
            $content .= $captcha;
        }

        return $content;
    }

    /**
     * @param mixed ...$args
     * @return bool|int
     */
    public function wp_is_spam(...$args)
    {
        $spam = $args[0];

        $form_id = $this->get_current_form_id();
        $spam_message = $this->check_spam( null, $form_id );

        if ($spam_message !== null) {
            add_filter('wpcf7_display_message', function ($message, $status) use ($spam_message) {
                if ($status == 'spam') {
                    return $spam_message;
                }

                return $message;
            }, 10, 2);

            return true;
        }

        return $spam;
    }
}
