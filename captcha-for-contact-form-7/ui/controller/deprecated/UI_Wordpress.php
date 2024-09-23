<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UILogin
     */
    class UI_Wordpress extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'wordpress', 'WordPress');
        }

        /**
         * Return the Default settings for the
         * Wordpress Login page.
         *
         * @param $settings
         *
         * @return mixed
         */
        public function get_settings($settings)
        {
            $settings['wp_login_page'] = array(
                'protect_login' => 0,
                'protect_login_fieldname' => 'f12_captcha',
                'protect_login_method' => 'honey',
                'protect_registration' => 0,
                'protect_registration_fieldname' => 'f12_captcha',
                'protect_registration_method' => 'honey'
            );

            return $settings;
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {

            foreach ($settings['wp_login_page'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['wp_login_page'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['wp_login_page'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['wp_login_page'][$key] = 0;
                }
            }

            return $settings;
        }

        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['wp_login_page'];
            ?>
            <h2>
                <?php _e('WordPress Login', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('Captcha Settings', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_login"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_login"
                                type="checkbox"
                                value="1"
                                name="protect_login"
                            <?php echo isset($settings['protect_login']) && $settings['protect_login'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_login"><?php _e('Enable Spam Protection for WordPress Login', 'captcha-for-contact-form-7'); ?></label>
                    </span>

                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_login_method"><?php _e('Protection Method', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_login_method"
                                type="radio"
                                value="honey"
                                name="protect_login_method"
                            <?php echo isset($settings['protect_login_method']) && $settings['protect_login_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_login_method"><?php _e('Honeypot', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_login_method_math"
                                type="radio"
                                value="math"
                                name="protect_login_method"
                            <?php echo isset($settings['protect_login_method']) && $settings['protect_login_method'] === 'math' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_login_method_math"><?php _e('Arithmetic', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_login_method_image"
                                type="radio"
                                value="image"
                                name="protect_login_method"
                            <?php echo isset($settings['protect_login_method']) && $settings['protect_login_method'] === 'image' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_login_method_image"><?php _e('Image', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_login_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_login_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_login_fieldname'] ?? 'f12_captcha'; ?>"
                                name="protect_login_fieldname"
                        />
                        <span>
                        <label for="protect_login_fieldname"><?php _e('Enter a unique name for the Captcha field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
                    </span>

                    </div>
                </div>
            </div>


            <h2>
                <?php _e('WordPress Registration', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('Captcha Settings', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_registration"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_registration"
                                type="checkbox"
                                value="1"
                                name="protect_registration"
                            <?php echo isset($settings['protect_registration']) && $settings['protect_registration'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_registration"><?php _e('Enable Spam Protection for WordPress Registration', 'captcha-for-contact-form-7'); ?></label>
                    </span>

                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_registration_method"><?php _e('Protection Method', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_registration_method"
                                type="radio"
                                value="honey"
                                name="protect_registration_method"
                            <?php echo isset($settings['protect_registration_method']) && $settings['protect_registration_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_login_method"><?php _e('Honeypot', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_registration_method_math"
                                type="radio"
                                value="math"
                                name="protect_registration_method"
                            <?php echo isset($settings['protect_registration_method']) && $settings['protect_registration_method'] === 'math' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_registration_method_math"><?php _e('Arithmetic', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_registration_method_image"
                                type="radio"
                                value="image"
                                name="protect_registration_method"
                            <?php echo isset($settings['protect_registration_method']) && $settings['protect_registration_method'] === 'image' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_registration_method_image"><?php _e('Image', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_registration_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_registration_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_registration_fieldname'] ?? 'f12_captcha'; ?>"
                                name="protect_registration_fieldname"
                        />
                        <span>
                        <label for="protect_registration_fieldname"><?php _e('Enter a unique name for the Captcha field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
                    </span>

                    </div>
                </div>
            </div>
            <?php
        }

        protected function the_sidebar($slug, $page)
        {
            if ($page != 'settings') {
                return;
            }
        }
    }
}