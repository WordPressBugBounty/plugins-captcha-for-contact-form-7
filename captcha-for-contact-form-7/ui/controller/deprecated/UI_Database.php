<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UIDatabase
     */
    class UI_Database extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'db', 'Database', 99);
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            return $settings;
        }



        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            ?>
            <h2>
                <?php _e('Database', 'captcha-for-contact-form-7'); ?>
            </h2>

            <?php
        }

        protected function the_sidebar($slug, $page)
        {
            return;
        }

	    public function get_settings( $settings ) {
            return $settings;
	    }
    }
}