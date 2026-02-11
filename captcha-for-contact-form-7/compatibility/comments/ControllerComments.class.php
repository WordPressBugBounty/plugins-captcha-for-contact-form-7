<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerComments
 */
class ControllerComments extends BaseController
{
    protected string $name = 'WordPress Comments';
    protected string $id = 'wordpress_comments';
    protected string $settings_key = 'protection_wordpress_comments_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'comment_form_after_fields', 'method' => 'wp_add_spam_protection'],
        ['type' => 'filter', 'hook' => 'preprocess_comment', 'method' => 'wp_is_spam', 'priority' => 1],
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
        $commentdata = $args[0];

        $spam_message = $this->check_spam();

        if ($spam_message !== null) {
            wp_die(
                '<p>' . $this->format_spam_message($spam_message) . '</p>',
                esc_html__('Comment Submission Failed', 'captcha-for-contact-form-7'),
                ['response' => 403, 'back_link' => true]
            );
        }

        return $commentdata;
    }
}
