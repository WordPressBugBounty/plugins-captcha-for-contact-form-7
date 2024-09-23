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
    /**
     * @var string
     */
    protected string $name = 'Elementor';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'elementor';

    /**
     * Checks if the CF7 Captcha module is enabled in Elementor.
     *
     * This method is used to determine whether the CF7 Captcha module is enabled or not.
     * It checks the 'f12_cf7_captcha_is_installed_elementor' filter value to determine the result.
     * The CF7 Captcha module is considered enabled if the 'ELEMENTOR_VERSION' constant is defined.
     *
     * @return bool Returns true if the CF7 Captcha module is enabled, false otherwise.
     */
    public function is_enabled(): bool
    {
        return apply_filters('f12_cf7_captcha_is_installed_elementor', $this->is_installed() && (int)$this->Controller->get_settings('protection_elementor_enable', 'global') === 1);
    }

    /**
     * Check if Elementor plugin is installed
     *
     * @return bool True if Elementor is installed, false otherwise
     */
    public function is_installed(): bool
    {
        return defined('ELEMENTOR_VERSION');
    }

    /**
     * @private WordPress Hook
     */
    public function on_init(): void
    {
	    $this->name = __('Elementor', 'captcha-for-contact-form-7');

        add_action('elementor_pro/forms/validation', array($this, 'wp_is_spam'), 10, 2);
        add_filter('elementor_pro/forms/render/item', array($this, 'wp_add_spam_protection'), 10, 3);

        add_action('wp_enqueue_scripts', array($this, 'wp_add_assets'));
    }

    /**
     * Add assets for elementor
     */
    public function wp_add_assets()
    {
        wp_enqueue_script('f12-cf7-captcha-elementor', plugin_dir_url(__FILE__) . 'assets/f12-cf7-captcha-elementor.js', array('jquery'));
        wp_localize_script('f12-cf7-captcha-elementor', 'f12_cf7_captcha_elementor', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
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
        $item = $args[0];
        $item_index = $args[1];
        /**
         * @var ElementorPro\Modules\Forms\Widgets\Form $form
         */
        $form = $args[2];

        # Get the number of fields to add the captcha behind the last one.
        $settings = $form->get_settings();
        $number_of_fields = count($settings['form_fields']) - 1;

        if ($item_index !== $number_of_fields || (isset($_POST) && !empty($_POST))) {
            return $item;
        }

        $captcha = $this->Controller->get_modul('protection')->get_captcha();

        echo sprintf('<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-text elementor-col-100 elementor-field-required">%s</div>', $captcha);

        return $item;
    }

    /**
     * Determines if the given submission is spam.
     *
     * This method checks if the submission is marked as spam and logs it if necessary.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     *
     * @return bool|int If the submission is identified as spam, it returns true. If not, it returns the spam indicator
     *                  value provided.
     *
     * @since 1.0.0
     */
    public function wp_is_spam(...$args)
    {
        $record = $args[0];
        $ajax_handler = $args[1];

        if (null == $record || null == $ajax_handler) {
            return false;
        }

        $fields = $record->get('fields');

        if (null == $fields || !is_array($fields)) {
            return false;
        }

        $array_post_data = $_POST;

        $Protection = $this->Controller->get_modul('protection');
        if ($Protection->is_spam($array_post_data)) {
            /*
             * Get the first field that is not hidden to show the error message to the visitor.
             */
            $field_name = '';
            foreach ($fields as $key => $data) {
                if (isset($data['type']) && $data['type'] != 'hidden') {
                    $field_name = $key;
                }
            }

            /*
             * Add the error message
             */
            $ajax_handler->add_error($field_name, sprintf(esc_html__('Spam detected: %s', 'captcha-for-contact-form-7'), $Protection->get_message()));
            return true;
        }

        return false;
    }
}