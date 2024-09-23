<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\Validator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerAvada
 */
class ControllerAvada extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Avada';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'avada';

    /**
     * Check if Avada captcha integration is enabled
     *
     * @return bool Returns true if Avada captcha integration is enabled, false otherwise
     * @throws \Exception
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_avada', $this->is_installed() && (int)$this->Controller->get_settings('protection_avada_enable', 'global') === 1);
    }

    /**
     * Checks if Avada theme is installed.
     *
     * @return bool Returns true if Avada theme is installed, false otherwise.
     */
    public function is_installed(): bool
    {
        return function_exists('Avada');
    }

    /**
     * Initialize Avada settings and validators
     */
    protected function on_init(): void
    {
	    $this->name = __('Avada', 'captcha-for-contact-form-7');

        // Add Spam Protection to the form
        add_filter('fusion_element_form_content', [$this, 'wp_add_spam_protection'], 10, 2);

        // Check for Spam
        add_filter('fusion_form_demo_mode', [$this, 'wp_is_spam'], 10, 1);

        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'wp_add_assets'));
    }

    /**
     * Adds spam protection to the given HTML form
     *
     * @param string $html    The HTML form content
     * @param mixed  ...$args Additional arguments (not used in this method)
     *
     * @return string The modified HTML form content with spam protection added
     */
    public function wp_add_spam_protection(...$args): string
    {
        $html = $args[0];

        $position = 'before_submit';

        /*if (isset($settings['avada']) && isset($settings['avada']['protect_avada_position'])) {
            $position = $settings['avada']['protect_avada_position'];
        } else {
            $position = 'after_submit';
        }*/
        $captcha = $this->Controller->get_modul('protection')->get_captcha();

        if ($position === 'before_submit' && str_contains($html, '<div class="fusion-form-field fusion-form-submit-field')) {
            $html = str_replace('<div class="fusion-form-field fusion-form-submit-field', $captcha . '<div class="fusion-form-field fusion-form-submit-field', $html);
        } else if (str_contains($html, '</form>')) {
            $html = str_replace("</form>", $captcha . '</form>', $html);
        } else {
            $html .= $captcha;
        }

        return $html;

    }

    /**
     * Converts form data to an associative array
     *
     * @param string $data The form data to be converted
     *
     * @return array The associative array representation of the form data
     */
    protected function form_data_to_arary($data): array
    {
        $data = wp_unslash($data); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        /*
         * Convert the string to an array, e.g.: firstName=John&lastName=Doe&age=25
         */
        parse_str($data, $value);

        return $value;
    }

    /**
     * Checks if the submitted form data is considered as spam
     *
     * @param mixed ...$args The arguments passed to the function (variadic)
     *
     * @return mixed The original value if the form data is not spam, otherwise does not return anything
     */
    public function wp_is_spam(...$args)
    {
        $value = $args[0];

        if (!isset($_POST['formData'])) {
            return false;
        }

        $array_post_data = $this->form_data_to_arary($_POST['formData']);

        /**
         * @var Protection $Protection
         */
        $Protection = $this->Controller->get_modul('protection');

        if (!$this->Controller->get_modul('protection')->is_spam($array_post_data)) {
            return $value;
        }

        echo wp_json_encode(['status' => 'error', 'info' => 'spam-1']);
        wp_die(sprintf(__('Error: Spam: %s', 'captcha-for-contact-form-7'), $Protection->get_message()));
    }

    /**
     * Add assets for Avada form
     */
    public function wp_add_assets()
    {
        wp_enqueue_script('f12-cf7-captcha-avada', plugin_dir_url(__FILE__) . 'assets/f12-cf7-captcha-avada.js', array('jquery'));
        wp_localize_script('f12-cf7-captcha-avada', 'f12_cf7_captcha_avada', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
}
