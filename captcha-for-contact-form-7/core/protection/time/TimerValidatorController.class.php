<?php

namespace f12_cf7_captcha\core\protection\time;

use f12_cf7_captcha\core\timer\CaptchaTimer;
use f12_cf7_captcha\core\timer\TimerValidator;
use f12_cf7_captcha\core\Validator;

if (!defined('ABSPATH')) {
    exit;
}

abstract class TimerValidatorController extends Validator
{
    /**
     * @deprecated
     * @var TimerValidatorController
     */
    private static $_instances = array();

    /**
     * @return TimerValidatorController
     * @deprecated
     * @deprecated
     */
    public static function getInstance()
    {
        return self::get_instance();
    }

    /**
     * Returns an instance of the called class, creating it if it does not exist.
     *
     * @return self
     * @deprecated
     */
    public static function get_instance()
    {
        $called_class = get_called_class();

        if (!isset(self::$_instances[$called_class])) {
            self::$_instances[$called_class] = new $called_class();
        }
        return self::$_instances[$called_class];
    }

    /**
     * Get the error message
     *
     * @return string The error message
     */
    protected function get_error_message(): string
    {
        return __('Time between loading the page and submitting the form has not reached the minimum elapsed time.', 'captcha-for-contact-form-7');
    }

    /**
     * Deletes a timer by its hash value.
     *
     * @return bool Returns true if the timer is successfully deleted, false otherwise.
     */
    protected function delete_timer(): bool
    {
        $field_name = $this->get_field_name();

        if (empty($field_name) || !isset($_POST[$field_name])) {
            return false;
        }

        $hash = sanitize_text_field($_POST[$field_name]);

        return (new CaptchaTimer())->delete_by_hash($hash);
    }

    /**
     * Generates a timer input field.
     *
     * The timer input field is an HTML `<input>` element of type "hidden" that contains a generated hash value.
     * This method utilizes the `getPostFieldName()` method to determine the name of the input field.
     *
     * @return string The HTML code for the timer input field.
     *
     * @throws \Exception
     * @see getPostFieldName()
     */
    protected function generate_timer_input_field()
    {
        $field_name = $this->get_field_name();

        /**
         * @var TimerValidator $Timer_Validator
         */
        $Timer_Validator = $this->Controller->get_modul('timer-validator');

        $hash = $Timer_Validator->add_timer();

        $html = sprintf('<div class="f12t"><input type="hidden" class="f12_timer" name="%s" value="%s"/></div>', esc_attr($field_name), esc_attr($hash));

        return $html;
    }

    /**
     * Return the Validation Time in MS
     *
     * @return int
     */
    public abstract function get_validation_time();

    /**
     * Validate spam for given data.
     *
     * @param array   $data    The data to validate.
     * @param string &$message The message to display if the data is considered spam.
     *
     * @return bool True if the data is spam, false otherwise.
     * @throws \Exception
     */
    public function validate_spam(array $data = array(), &$message = ''): bool
    {
        $field_name = $this->get_field_name();

        if (empty($field_name) || !isset($data[$field_name])) {
            return false;
        }

        $hash = sanitize_text_field($data[$field_name]);

        /**
         * Load the Validator
         *
         * @var TimerValidator $Timer_Validator
         */
        $Timer_Validator = $this->Controller->get_modul('timer-validator');

        /**
         * Load the Timer
         *
         * @var CaptchaTimer $Timer
         */
        $Timer = $Timer_Validator->get_timer($hash);

        if (!$Timer) {
            return true;
        }

        $time_in_ms = round(microtime(true) * 1000);

        #echo sprintf("%s - %s = %s", $time_in_ms, $Timer->get_value(), $time_in_ms - (float)$Timer->get_value()) . PHP_EOL;
        $minimum_time_in_ms = $this->get_validation_time();

        if (($time_in_ms - (float)$Timer->get_value()) < $minimum_time_in_ms) {
            return true;
        }

        $Timer->delete();

        return false;
    }
}