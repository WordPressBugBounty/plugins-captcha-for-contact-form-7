<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\compatibility\cf7\Backend;
use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\Protection;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerCF7
 */
class ControllerCF7 extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Contact Forms 7';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'cf7';

    /**
     * Check if CF7 Captcha is enabled
     *
     * This method checks if the CF7 Captcha plugin is installed and enabled by using the
     * 'f12_cf7_captcha_is_installed_cf7' filter hook. It returns a boolean value indicating whether the plugin is
     * enabled or not.
     *
     * @return bool True if CF7 Captcha is enabled, false otherwise
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_cf7', $this->is_installed() && (int)$this->Controller->get_settings('protection_cf7_enable', 'global') === 1);
    }

    /**
     * Checks if the "wpcf7" function is available, indicating whether the WPCF7 plugin is installed.
     *
     * @return bool True if the WPCF7 plugin is installed; otherwise, false.
     */
    public function is_installed(): bool
    {
        return function_exists('wpcf7');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('Contact Forms 7', 'captcha-for-contact-form-7');
        /*
         * Extend the CF7 Interface
         */
        require_once('Backend.class.php');
        $Backend = new Backend();

        add_filter('wpcf7_form_elements', array($this, 'wp_add_spam_protection'), 100, 1);
        add_filter('wpcf7_spam', array($this, 'wp_is_spam'), 100, 2);
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
        $content = $args[0];

        $captcha = $this->Controller->get_modul('protection')->get_captcha();

        $captcha = sprintf('<p><span class="wpcf7-form-control-wrap">%s</span></p>', $captcha);

        if (preg_match('!<input(.*)type="submit"!', $content, $matches)) {
            $content = str_replace($matches[0], $captcha . $matches[0], $content);
        } else {
            $content .= $captcha;
        }

        return $content;
    }

    /**
     * Determines if the given submission is spam.
     *
     * This method checks if the submission is marked as spam and logs it if necessary.
     *
     * @param mixed ...$args Any number of arguments. The first argument must be the spam indicator and the second
     *                       argument must be the submission.
     *
     * @return bool|int If the submission is identified as spam, it returns true. If not, it returns the spam indicator
     *                  value provided.
     *
     * @since 1.0.0
     */
    public function wp_is_spam(...$args)
    {
        $spam = $args[0];
        $submission = $args[1];

        $array_post_data = $_POST;

        /**
         * @var Protection $Protection
         */
        $Protection = $this->Controller->get_modul('protection');

        if ($Protection->is_spam($array_post_data)) {
            add_filter('wpcf7_display_message', function ($message, $status) {
                /**
                 * @var Protection $Protection
                 */
                $Protection = $this->Controller->get_modul('protection');

                if ($status == 'spam') {
                    $message = $Protection->get_message();
                }

                return $message;
            }, 10, 2);

            return true;
        }

        return $spam;
    }
}