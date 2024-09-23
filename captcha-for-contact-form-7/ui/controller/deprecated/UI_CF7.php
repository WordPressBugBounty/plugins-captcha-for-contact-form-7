<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UIDashboard
     */
    class UI_CF7 extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_manager)
        {
            parent::__construct($UI_manager, 'cf7', 'Contact Form 7');
        }

        /**
         * Hide if the CF7 Plugin is not installed
         *
         * @return false|void
         */
        public function hide_in_menu()
        {
            if (!function_exists('wpcf7')) {
                return true;
            }
            return parent::hide_in_menu();
        }

        /**
         * @param $settings
         *
         * @return array{'protect_cf7_time_enable': string, 'protect_cf7_time_ms': int, 'protect_cf7_fieldname': string}
         */
        public function get_settings($settings)
        {
            $settings['cf7'] = array(
                'protect_cf7_time_enable' => 0,
                'protect_cf7_time_ms' => 500,
                'protect_cf7_timer_fieldname' => 'f12_timer',
                'protect_cf7_fieldname' => 'f12_captcha',
                'protect_cf7_captcha_enable' => 0,
                'protect_cf7_multiple_submissions' => 0,
                'protect_cf7_method' => 'honey'
            );

            return $settings;
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            $default = $this->get_settings([]);

            foreach ($default['cf7'] as $key => $value) {
                //foreach ($settings['cf7'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['cf7'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['cf7'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['cf7'][$key] = 0;
                }
            }
            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['cf7'];

            ?>
            <h2>
                <?php _e('Contact Form 7', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('Captcha Settings', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_captcha_enable"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_captcha_enable"
                                type="checkbox"
                                value="1"
                                name="protect_cf7_captcha_enable"
                            <?php echo isset($settings['protect_cf7_captcha_enable']) && $settings['protect_cf7_captcha_enable'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <?php _e('Enable the Captcha for every Contact Form 7 available on this system.', 'captcha-for-contact-form-7'); ?>
                    </span>
                        <p>
                            (<?php _e('You can also enable the protection for each formular individually within the form settings.', 'captcha-for-contact-form-7'); ?>
                            )
                        </p>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_method"><?php _e('Protection Method', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_method"
                                type="radio"
                                value="honey"
                                name="protect_cf7_method"
                            <?php echo isset($settings['protect_cf7_method']) && $settings['protect_cf7_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_cf7_method"><?php _e('Honeypot', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_cf7_method_math"
                                type="radio"
                                value="math"
                                name="protect_cf7_method"
                            <?php echo isset($settings['protect_cf7_method']) && $settings['protect_cf7_method'] === 'math' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_cf7_method_math"><?php _e('Arithmetic', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_cf7_method_image"
                                type="radio"
                                value="image"
                                name="protect_cf7_method"
                            <?php echo isset($settings['protect_cf7_method']) && $settings['protect_cf7_method'] === 'image' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_cf7_method_image"><?php _e('Image', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_cf7_fieldname'] ?? 'f12_captcha'; ?>"
                                name="protect_cf7_fieldname"
                        />
                        <span>
                        <label for="protect_cf7_fieldname"><?php _e('Enter a unique name for the Captcha field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>
                    <?php _e('Time Based Protection', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_time_enable"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_time_enable"
                                type="checkbox"
                                value="1"
                                name="protect_cf7_time_enable"
                            <?php echo isset($settings['protect_cf7_time_enable']) && $settings['protect_cf7_time_enable'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_cf7_time_enable"><?php _e('Enable to track the time from entering till submitting the form.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_time_ms"><?php _e('Time in Milliseconds', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_time_ms"
                                type="text"
                                value="<?php echo $settings['protect_cf7_time_ms'] ?? 500; ?>"
                                name="protect_cf7_time_ms"
                        />
                        <span>
                        <label for="protect_cf7_time_ms"><?php _e('Enter the Time in Milliseconds to determine if the user is a bot (e.g. enter 1000 for 1 second).', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_timer_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_timer_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_cf7_timer_fieldname'] ?? 'f12_timer'; ?>"
                                name="protect_cf7_timer_fieldname"
                        />
                        <span>
                        <label for="protect_cf7_timer_fieldname"><?php _e('Enter a unique name for the Timer field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>
                    <?php _e('Multiple Submission Protection', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_cf7_multiple_submissions"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_cf7_multiple_submissions"
                                type="checkbox"
                                value="1"
                                name="protect_cf7_multiple_submissions"
                            <?php echo isset($settings['protect_cf7_multiple_submissions']) && $settings['protect_cf7_multiple_submissions'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_cf7_multiple_submissions"><?php _e('Enable to prevent forms from being submitted multiple times.', 'captcha-for-contact-form-7'); ?></label>
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