<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CaptchaGenerator
 * Generate the custom captcha as an image
 *
 * @package forge12\contactform7
 */
abstract class CaptchaGenerator extends BaseModul
{
    /**
     * @var string List of allowed characters for the captcha
     */
    private $_allowedCharacters = 'abcdefghjkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The Captcha string.
     *
     * @var string
     */
    protected $_captcha = '';

    /**
     * Latest Captcha Session
     */
    protected ?Captcha $Captcha_Session = null;

    /**
     * The Unique ID
     */
    private string $unique_id = '';

    /**
     * constructor.
     *
     * @TODO adjust after updating all captchas
     */
    public function __construct(CF7Captcha $Controller, int $length)
    {
        parent::__construct($Controller);

        $this->generate_captcha($length);
    }

    /**
     * Retrieves the last unique ID for CAPTCHA.
     *
     * @return string The last unique ID for CAPTCHA.
     */
    public function get_last_unique_id_captcha(): string
    {
        return 'c_' . $this->get_unique_id();
    }

    /**
     * Retrieves the last unique ID hash.
     *
     * @return string The last unique ID hash.
     */
    public function get_last_unique_id_hash(): string
    {
        return 'hash_c_' . $this->get_unique_id();
    }

    /**
     * Generates a unique ID and retrieves it.
     *
     * @return string The generated unique ID.
     */
    public function generate_and_get_unique_id(): string
    {
        $this->unique_id = bin2hex(random_bytes(10));

        return $this->get_unique_id();
    }

    /**
     * Retrieves the unique ID.
     *
     * @return string The unique ID.
     */
    public function get_unique_id(): string
    {
        if (empty($this->unique_id)) {
            return $this->generate_and_get_unique_id();
        }
        return $this->unique_id;
    }


    abstract protected function get_field(string $field_name): string;

    /**
     * Retrieve the AJAX response as a string
     *
     * @return string The AJAX response
     */
    abstract function get_ajax_response(): string;

    /**
     * Gets the latest captcha session.
     *
     * This method returns the latest captcha session object. If there is no latest captcha session,
     * it returns null.
     *
     * @return Captcha|null The latest captcha session object, or null if there is no latest captcha session.
     */
    public function generate_and_get_captcha(): ?Captcha
    {
        $this->Captcha_Session = new Captcha('');
        $this->Captcha_Session->save();

        return $this->Captcha_Session;
    }

    /**
     * Regenerate Captcha
     *
     * @return void
     * @deprecated
     */
    public function getReloadButton()
    {
        $this->get_reload_button();
    }

    /**
     * Generate and return the reload button for the captcha
     *
     * @return string The HTML markup for the reload button
     */
    public function get_reload_button(): string
    {
        $image_url = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/';
        $reload_icon = 'reload-icon.png';

        $setting_icon = $this->Controller->get_settings('protection_captcha_reload_icon', 'global');

        if ($setting_icon === 'white') {
            $reload_icon = 'reload-icon-white.png';
        }

        $image_url .= $reload_icon;

        /**
         * Reload Icon
         *
         * Define a custom icon for the reload button.
         *
         * @param string $image_url The URL to the icon
         *
         * @since 2.0.4
         */
        $image_url = apply_filters('f12-cf7-captcha-reload-icon', $image_url);

        return sprintf('<a href="javascript:void(0);" class="cf7 captcha-reload" title="%s"><img style="margin-top:5px;" src="%s" alt="%s"/></a>', __('Reload Captcha', 'captcha-for-contact-form-7'), $image_url, __('Reload', 'captcha-for-contact-form-7'));
    }

    /**
     * Generate a captcha string of specified length
     *
     * @param int $length The length of the captcha string to generate
     *
     * @return void
     */
    private function generate_captcha(int $length): void
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $this->_allowedCharacters[rand(0, strlen($this->_allowedCharacters) - 1)];
        }

        $this->_captcha = $result;
    }

    /**
     * Generate the captcha string and return it
     *
     * @return string
     */
    public function get()
    {
        return $this->_captcha;
    }
}