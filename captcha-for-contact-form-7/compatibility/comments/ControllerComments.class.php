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
    /**
     * @var string
     */
    protected string $name = 'WordPress Comments';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'wordpress_comments';

    /**
     * Determines if the CF7 Captcha plugin is enabled.
     *
     * This method checks if the CF7 Captcha plugin is enabled by filtering the 'f12_cf7_captcha_is_installed_comments'
     * hook.
     *
     * @return bool Whether the CF7 Captcha plugin is enabled or not.
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_wordpress_comments', (int)$this->Controller->get_settings('protection_wordpress_comments_enable', 'global') === 1);
    }

    /**
     * Checks if the software is installed.
     *
     * @return bool Returns true if the software is installed, false otherwise.
     */
    public function is_installed(): bool
    {
        return true;
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('WordPress Comments', 'captcha-for-contact-form-7');
        add_action('comment_form_after_fields', [$this, 'wp_add_spam_protection']);
        add_filter('preprocess_comment', [$this, 'wp_is_spam'], 1);
    }

    /**
     * Adds spam protection to comment submission.
     *
     * This method is responsible for generating and displaying the captcha
     * field for spam protection when submitting a comment. The type of captcha
     * is determined by the settings from the controller.
     *
     * @param mixed ...$args Optional arguments to be passed to the method.
     *
     * @return void
     * @throws \Exception
     */
    public function wp_add_spam_protection(...$args)
    {
        echo $this->Controller->get_modul('protection')->get_captcha();
    }

    /**
     * Determines if a comment is spam.
     *
     * This method checks if the given comment data is considered spam by
     * performing spam detection logic. If the comment is classified as spam,
     * appropriate action is taken, such as logging and displaying an error
     * message.
     *
     * @param mixed ...$args The comment data arguments.
     *
     * @return mixed The comment data.
     * @throws \Exception
     */
    public function wp_is_spam(...$args)
    {
        $commentdata = $args[0];

		$formData = $_POST;

        if ($this->Controller->get_modul('protection')->is_spam($formData)) {
            wp_die(__('Error: Spam', 'captcha-for-contact-form-7'));
        }

        return $commentdata;
    }
}