<?php

namespace f12_cf7_captcha\core\protection\rules;
use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Async Task for Rules
 */
class RulesAjax extends BaseModul
{
    /**
     * Constructs an instance of the class.
     *
     * This method registers an action hook that loads the required assets for the admin section.
     *
     * @return void
     */
    public function __construct(CF7Captcha $Controller)
    {
        parent::__construct($Controller);

        add_action('admin_enqueue_scripts', array($this, 'load_assets'));

        add_action('wp_ajax_f12_cf7_blacklist_sync', [$this, 'wp_handle_blacklist_sync']);
        add_action('wp_ajax_nopriv_f12_cf7_blacklist_sync', [$this, 'wp_handle_blacklist_sync']);
    }

    /**
     * Loads the required assets for the plugin.
     *
     * This method enqueues the script 'f12-cf7-rules-ajax' with the URL to the 'f12-cf7-rules-ajax.js' file
     * located in the 'assets' directory of the plugin. It specifies that the script depends on jQuery,
     * does not specify a version, and should be loaded in the footer of the page.
     *
     * It also localizes the script 'f12-cf7-rules-ajax' by creating the JavaScript object 'f12_cf7_captcha_rules'
     * and setting its 'ajaxurl' property to the admin-ajax.php URL.
     *
     * @return void
     */
    public function load_assets()
    {
        wp_enqueue_script('f12-cf7-rules-ajax', plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/f12-cf7-rules-ajax.js', array('jquery'), null, true);
        wp_localize_script('f12-cf7-rules-ajax', 'f12_cf7_captcha_rules', array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    /**
     * Retrieves the content of the blacklist from an API.
     *
     * This method fetches the content of the blacklist from the specified API endpoint and returns it as a string.
     *
     * @return string The content of the blacklist as a string.
     */
    public function get_blacklist_content(): string
    {
        return file_get_contents('https://api.forge12.com/v1/tools/blacklist.txt');
    }

    /**
     * Handles the synchronization of the blacklist.
     *
     * This method retrieves the blacklist content using the method get_blacklist_content(),
     * encodes it as JSON using wp_json_encode(), and then echoes the JSON encoded content
     * with the 'value' key. Finally, it terminates the script execution using wp_die().
     *
     * @return void
     */
    public function wp_handle_blacklist_sync(): void
    {
        $content = $this->get_blacklist_content();
        echo wp_json_encode(array('value' => $content));
        wp_die();
    }
}