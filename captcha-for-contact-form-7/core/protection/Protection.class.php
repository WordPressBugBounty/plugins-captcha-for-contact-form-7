<?php

namespace f12_cf7_captcha\core\protection;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\browser\Browser;
use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
use f12_cf7_captcha\core\protection\ip\IPValidator;
use f12_cf7_captcha\core\protection\javascript\Javascript_Validator;
use f12_cf7_captcha\core\protection\multiple_submission\Multiple_Submission_Validator;
use f12_cf7_captcha\core\protection\rules\RulesHandler;
use f12_cf7_captcha\core\protection\time\Timer_Validator;

if (!defined('ABSPATH')) {
    exit;
}

require_once('browser/Browser.php');
require_once('multiple_submission/Multiple_Submission_Validator.class.php');
require_once('time/Timer_Validator.class.php');
require_once('captcha/Captcha_Validator.class.php');
require_once('rules/RulesHandler.class.php');
require_once('ip/IPValidator.class.php');
require_once('javascript/Javascript_Validator.php');

class Protection extends BaseModul
{
    protected $_moduls = [];
    private Log_WordPress $Logger;

    public function __construct(CF7Captcha $Controller, Log_WordPress $Logger)
    {
        parent::__construct($Controller);

        $this->Logger = $Logger;

        add_action('f12_cf7_captcha_compatibilities_loaded', array($this, 'on_init'));
    }

    /**
     * Initializes the modules for the software.
     *
     * This method initializes the modules required for the software to function properly.
     *
     * @return void
     */
    private function init_moduls(): void
    {
        $moduls = [
            'browser-validator' => new Browser($this->Controller),
            'ip-validator' => new IPValidator($this->Controller),
            'javascript-validator' => new Javascript_Validator($this->Controller),
            'rule-validator' => new RulesHandler($this->Controller),
            'multiple-submission-validator' => new Multiple_Submission_Validator($this->Controller),
            'timer-validator' => new Timer_Validator($this->Controller),
            'captcha-validator' => new Captcha_Validator($this->Controller),
        ];

        foreach ($moduls as $name => $BaseModul) {
            $this->_moduls[$name] = $BaseModul;
        }
    }

    /**
     * Retrieves the specified module based on its name.
     *
     * @param string $name The name of the module to retrieve.
     *
     * @return BaseProtection The specified module.
     * @throws \Exception If the specified module does not exist.
     */
    public function get_modul(string $name): BaseProtection
    {
        if (!isset($this->_moduls[$name])) {
            throw new \Exception(sprintf('Modul %s does not exist.', $name));
        }

        return $this->_moduls[$name];
    }


    /**
     * Retrieves the name of the field.
     *
     * @return string The name of the field.
     */
    protected function get_field_name(): string
    {
        return 'f12_captcha';
    }

    /**
     * Retrieves the captcha for spam protection.
     *
     * This method retrieves the captcha for spam protection by calling the `get_spam_protection()`
     * method on each module and concatenating the results into a single string.
     *
     * @return string The captcha for spam protection.
     */
    public function get_captcha(): string
    {
        $captcha = [];

        foreach ($this->_moduls as $key => $modul) {
            $captcha[$key] = $modul->get_captcha();
        }

        return implode("", $captcha);
    }

    /**
     * Determines if the submitted data is considered spam.
     *
     * This method checks if the submitted data is considered spam by iterating through the loaded modules
     * and calling their respective "is_spam" method.
     *
     * @param mixed ...$args The arguments passed to the method. In this case, it is the data submitted.
     *
     * @param bool  $skip    Skip validation, default: false
     *
     * @return bool Returns true if the submitted data is spam, otherwise false.
     *
     * @since  1.12.2
     *
     * @filter f12-cf7-captcha-skip-validation
     */
    public function is_spam(...$args): bool
    {
        /**
         * If no data submitted we can skip the validation process
         */
        if (!isset($args[0])) {
            return false;
        }

        /**
         * Skip Validation
         *
         * This hook can be used from developers to skip the validation for specific forms.
         *
         * @param bool $skip Skip validation, default: false
         *
         * @since 1.12.2
         */
        if (apply_filters('f12-cf7-captcha-skip-validation', false)) {
            return false;
        }

        $array_post_data = $args[0];

        $is_spam = false;

        foreach ($this->_moduls as $modul) {
            if ($modul->is_spam($array_post_data)) {
                $is_spam = true;

                $this->message = $modul->get_message();
                $this->Logger->maybe_log('protection', $array_post_data, true, $this->message);
                break;
            }
        }

        if (!$is_spam) {
            foreach ($this->_moduls as $modul) {
                $modul->success();
            }
            $this->Logger->maybe_log('protection', $array_post_data, false);
        }

        return $is_spam;
    }

    public function on_init(): void
    {
        $this->init_moduls();
    }

    protected function is_enabled(): bool
    {
        return true;
    }
}