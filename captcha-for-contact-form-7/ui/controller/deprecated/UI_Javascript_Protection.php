<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UI Javascript Protection
     */
    class UI_Javascript_Protection extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'javascript_protection', 'JavaScript Protection', 50);
        }

        /**
         * @private WP HOOK
         */
        public function get_settings($settings)
        {
            $settings['javascript'] = array(
                'protect' => 0, // enabled or not
            );

            return $settings;
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            foreach ($settings['javascript'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['javascript'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['javascript'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['javascript'][$key] = 0;
                }
            }

            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['javascript'];

            ?>
            <h2>
                <?php _e('JavaScript Protection', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('JavaScript Protection', 'captcha-for-contact-form-7'); ?>
                </h3>

                <div class="option">
                    <div class="label">
                        <label for="protect"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect"
                                type="checkbox"
                                value="1"
                                name="protect"
				            <?php echo isset($settings['protect']) && $settings['protect'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect"><?php _e('Enable JavaScript Protection. This will help to identify bots / crawlers by JavaScript.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
            </div>
            <?php
        }

        protected function the_sidebar($slug, $page)
        {
            return;
        }
    }
}