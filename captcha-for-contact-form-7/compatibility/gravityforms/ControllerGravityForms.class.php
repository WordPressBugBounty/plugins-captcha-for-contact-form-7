<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerGravityForms
 */
class ControllerGravityForms extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'GravityForms';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'gravityforms';

    /**
     * Check if the captcha is enabled for Gravity Forms
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_gravityforms', $this->is_installed() && (int)$this->Controller->get_settings('protection_gravityforms_enable', 'global') === 1);
    }

    /**
     * Check if the Gravity Forms plugin is installed
     *
     * @return bool Returns true if the Gravity Forms plugin is installed, false otherwise
     */
    public function is_installed(): bool
    {
        return class_exists('GFCommon');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('GravityForms', 'captcha-for-contact-form-7');

        add_filter('gform_get_form_filter', array($this, 'wp_add_spam_protection'), 10, 2);
        add_filter('gform_entry_is_spam', array($this, 'wp_is_spam'), 10, 3);
    }

    /**
     * Add spam protection to the given content.
     *
     * This method adds spam protection to the given content by injecting a captcha field based on the specified
     * validation method.
     *
     * @param mixed ...$args Any number of arguments.
     *
     * @return mixed The content with spam protection added.
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
    public function wp_add_spam_protection(...$args)
    {
        $form_string = $args[0];

        $captcha = $this->Controller->get_modul('protection')->get_captcha();

        if (str_contains($form_string, "<div class='gform_footer")) {
            $form_string = str_replace("<div class='gform_footer", $captcha . "<div class='gform_footer", $form_string);
        } else {
            $form_string .= $captcha;
        }

        return $form_string;
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
        $is_spam = $args[0];

        $array_post_data = $_POST;

        $Protection = $this->Controller->get_modul('protection');
        if ($Protection->is_spam($array_post_data)) {
            return true;
        }

        return $is_spam;
    }
}