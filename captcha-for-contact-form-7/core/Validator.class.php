<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;

/**
 * Hotfix for compatibility issues if the same Class is loaded by another plugin.
 */
require_once('BaseController.class.php');
abstract class Validator extends \f12_cf7_captcha\core\BaseController
{
    public function __construct(CF7Captcha $Controller = null, Log_WordPress $Logger = null)
    {
        if (null === $Controller) {
            $Controller = CF7Captcha::get_instance();
        }

        if (null === $Logger) {
            $Logger = Log_WordPress::get_instance();
        }

        parent::__construct($Controller, $Logger);

    }

    public abstract function is_spam(): bool;

    public abstract function wp_is_spam(...$args);

    public abstract function wp_add_spam_protection(...$args);

    public abstract function wp_submitted(...$args);

    protected abstract function get_field_name(): string;

}