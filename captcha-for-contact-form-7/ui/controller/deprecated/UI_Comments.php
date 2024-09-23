<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UIComments
     */
    class UI_Comments extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'comments', 'Comments');
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {

            foreach ($settings['comments'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['comments'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['comments'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['comments'][$key] = 0;
                }
            }

            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['comments'];
            ?>
            <h2>
                <?php _e('Comments', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('Captcha Settings', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_comments"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments"
                                type="checkbox"
                                value="1"
                                name="protect_comments"
                            <?php echo isset($settings['protect_comments']) && $settings['protect_comments'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_comments"><?php _e('Enable captcha protection.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_comments_method"><?php _e('Protection Method', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments_method"
                                type="radio"
                                value="honey"
                                name="protect_comments_method"
                            <?php echo isset($settings['protect_comments_method']) && $settings['protect_comments_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_comments_method"><?php _e('Honeypot', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_comments_method_math"
                                type="radio"
                                value="math"
                                name="protect_comments_method"
                            <?php echo isset($settings['protect_comments_method']) && $settings['protect_comments_method'] === 'math' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_comments_method_math"><?php _e('Arithmetic', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_comments_method_image"
                                type="radio"
                                value="image"
                                name="protect_comments_method"
                            <?php echo isset($settings['protect_comments_method']) && $settings['protect_comments_method'] === 'image' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_comments_method_image"><?php _e('Image', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_comments_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_comments_fieldname'] ?? 'f12_captcha'; ?>"
                                name="protect_comments_fieldname"
                        />
                        <span>
                        <label for="protect_comments_fieldname"><?php _e('Enter a unique name for the Captcha field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
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
                        <label for="protect_comments_time_enable"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments_time_enable"
                                type="checkbox"
                                value="1"
                                name="protect_comments_time_enable"
                            <?php echo isset($settings['protect_comments_time_enable']) && $settings['protect_comments_time_enable'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_comments_time_enable"><?php _e('Enable to track the time from entering till submitting the form.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_comments_time_ms"><?php _e('Time in Milliseconds', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments_time_ms"
                                type="text"
                                value="<?php echo $settings['protect_comments_time_ms'] ?? 500; ?>"
                                name="protect_comments_time_ms"
                        />
                        <span>
                        <label for="protect_comments_time_ms"><?php _e('Enter the Time in Milliseconds to determine if the user is a bot (e.g. enter 1000 for 1 second).', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="protect_comments_timer_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">

                        <!-- SEPARATOR -->
                        <input
                                id="protect_comments_timer_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_comments_timer_fieldname'] ?? 'f12_timer'; ?>"
                                name="protect_comments_timer_fieldname"
                        />
                        <span>
                        <label for="protect_comments_timer_fieldname"><?php _e('Enter a unique name for the Timer field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
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

        public function get_settings($settings)
        {
            $settings['comments'] = array(
                'protect_comments' => 0,
                'protect_comments_time_enable' => 0,
                'protect_comments_time_ms' => 500,
                'protect_comments_fieldname' => 'f12_captcha',
                'protect_comments_timer_fieldname' => 'f12_timer',
                'protect_comments_method' => 'honey'
            );

            return $settings;
        }
    }
}