<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerFluentForm
 */
class ControllerFluentform extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Fluentform';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'fluentform';

    /**
     * Check if the captcha is enabled for Fluent Forms
     *
     * @return bool True if the captcha is enabled, false otherwise
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_fluentform', $this->is_installed() && (int)$this->Controller->get_settings('protection_fluentform_enable', 'global') === 1);
    }

    /**
     * Check if the Fluent Forms plugin is installed
     *
     * @return bool Returns true if the Fluent Forms plugin is installed, false otherwise
     */
    public function is_installed(): bool
    {
        return defined('FLUENTFORM');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('Fluent Forms', 'captcha-for-contact-form-7');

        add_action('fluentform/render_item_submit_button', array($this, 'wp_add_spam_protection'), 5, 2);
        add_filter('fluentform/validation_errors', array($this, 'wp_is_spam'), 10, 4);
    }

    /**
     * Add spam protection to the given content.
     *
     * This method adds spam protection to the given content by injecting a captcha field based on the specified
     * validation method.
     *
     * @param mixed ...$args Any number of arguments.
     *
     * @return void The content with spam protection added.
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
    public function wp_add_spam_protection(...$args)
    {
        echo $this->Controller->get_modul('protection')->get_captcha();
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
	    $errors = $args[0];
	    $formData = $_POST['data']; // Get the string from the ajax call

	    $decodedFormData = urldecode($formData);
		parse_str($decodedFormData, $array_post_data);

        $Protection = $this->Controller->get_modul('protection');
        if ($Protection->is_spam($array_post_data)) {
	        wp_send_json([
		        'errors' => [
			        'captcha-response' => [
				        sprintf(__('Captcha verification failed: %s', 'captcha-for-contact-form-7'), $Protection->get_message()),
			        ],
		        ],
	        ], 422);
        }

        return $errors;
    }
}