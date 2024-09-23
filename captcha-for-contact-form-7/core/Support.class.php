<?php

namespace f12_cf7_captcha\core;
use f12_cf7_captcha\CF7Captcha;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Class Support
 */
class Support extends BaseModul
{
    public function __construct(CF7Captcha $Controller)
    {
        parent::__construct($Controller);

        add_action('wp_footer', array($this, 'wp_add_link'), 9999);
    }

    /**
     * Returns the link to be used in the maybe_load_link method.
     *
     * @return string The link HTML markup with the title, href, and display text.
     */
    private function get_link(): string
    {
        return sprintf('<noscript><a title="%s" href="%s">%s</a></noscript>', 'Digital Agentur', 'https://www.forge12.com', 'Digitalagentur Forge12 Interactive GmbH');

    }

    /**
     * Retrieves a link to be loaded if it exists.
     * The link will only be loaded if the support setting for 'global' is set to 1.
     *
     * @return string The link to be loaded, or an empty string if the link does not exist.
     */
    public function maybe_load_link(): string
    {
        $link = '';
        //if ($this->Controller->get_settings('support', 'global') == 1) {
            $link = $this->get_link();
        //}
        return $link;
    }

    /**
     * Adds a link to the current page if the link exists.
     * The link is loaded using the maybe_load_link() method.
     *
     * @return void
     */
    public function wp_add_link()
    {
        echo $this->maybe_load_link();
    }
}