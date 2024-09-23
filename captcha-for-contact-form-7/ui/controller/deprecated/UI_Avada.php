<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UIAvada
     */
    class UI_Avada extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'avada', 'Avada Forms');
        }

        /**
         * Hide if the Avada Theme is not installed
         *
         * @return false|void
         */
        public function hide_in_menu()
        {
            if (!function_exists('Avada')) {
                return true;
            }
            return parent::hide_in_menu();
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            foreach ($settings['avada'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['avada'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['avada'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['avada'][$key] = 0;
                }
            }

            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['avada'];

            ?>
            <h2>
                <?php _e('Avada Forms', 'captcha-for-contact-form-7'); ?>
            </h2>


            <div class="section">
                <h3>
                    <?php _e('Captcha Settings', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_avada"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada"
                                type="checkbox"
                                value="1"
                                name="protect_avada"
                            <?php echo isset($settings['protect_avada']) && $settings['protect_avada'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada"><?php _e('Enable Spam Protection for Avada Forms', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_avada_method"><?php _e('Protection Method', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_method"
                                type="radio"
                                value="honey"
                                name="protect_avada_method"
                            <?php echo isset($settings['protect_avada_method']) && $settings['protect_avada_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada_method"><?php _e('Honeypot', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_avada_method_math"
                                type="radio"
                                value="math"
                                name="protect_avada_method"
                            <?php echo isset($settings['protect_avada_method']) && $settings['protect_avada_method'] === 'math' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada_method_math"><?php _e('Arithmetic', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_avada_method_image"
                                type="radio"
                                value="image"
                                name="protect_avada_method"
                            <?php echo isset($settings['protect_avada_method']) && $settings['protect_avada_method'] === 'image' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada_method_image"><?php _e('Image', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_avada_position"><?php _e('Position', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_position_after_submit"
                                type="radio"
                                value="after_submit"
                                name="protect_avada_position"
                            <?php echo isset($settings['protect_avada_position']) && $settings['protect_avada_position'] === 'after_submit' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada_position_after_submit"><?php _e('After Submit Button', 'captcha-for-contact-form-7'); ?></label>
                    </span><br><br>

                        <input
                                id="protect_avada_position_before_submit"
                                type="radio"
                                value="before_submit"
                                name="protect_avada_position"
                            <?php echo isset($settings['protect_avada_position']) && $settings['protect_avada_position'] === 'before_submit' ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <label for="protect_avada_position_before_submit"><?php _e('Before Submit Button (Beta)', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="protect_avada_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>

                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_avada_fieldname'] ?? 'f12_captcha'; ?>"
                                name="protect_avada_fieldname"
                        />
                        <span>
                        <label for="protect_avada_fieldname"><?php _e('Enter a unique name for the Captcha field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label>
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
                        <label for="protect_avada_time_enable"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_time_enable"
                                type="checkbox"
                                value="1"
                                name="protect_avada_time_enable"
                            <?php echo isset($settings['protect_avada_time_enable']) && $settings['protect_avada_time_enable'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span><label
                                    for="protect_avada_time_enable"><?php _e('Enable to track the time from entering till submitting the form.', 'captcha-for-contact-form-7'); ?></label></span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="protect_avada_time_ms"><?php _e('Time in Milliseconds', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_time_ms"
                                type="text"
                                value="<?php echo $settings['protect_avada_time_ms'] ?? 500; ?>"
                                name="protect_avada_time_ms"
                        />
                        <span><label
                                    for="protect_cf7_time_ms"><?php _e('Enter the Time in Milliseconds to determine if the user is a bot (e.g. enter 1000 for 1 second).', 'captcha-for-contact-form-7'); ?></label></span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="protect_avada_timer_fieldname"><?php _e('Fieldname', 'captcha-for-contact-form-7'); ?></label>
                    </div>

                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_timer_fieldname"
                                type="text"
                                value="<?php echo $settings['protect_avada_timer_fieldname'] ?? 'f12_timer'; ?>"
                                name="protect_avada_timer_fieldname"
                        />
                        <span><label
                                    for="protect_avada_timer_fieldname"><?php _e('Enter a unique name for the Timer field. This makes it harder for bots to recognize the honeypot.', 'captcha-for-contact-form-7'); ?></label></span>
                    </div>
                </div>
            </div>
            <div class="section">
                <h3>
                    <?php _e('Multiple Submission Protection', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="protect_avada_multiple_submissions"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="protect_avada_multiple_submissions"
                                type="checkbox"
                                value="1"
                                name="protect_avada_multiple_submissions"
                            <?php echo isset($settings['protect_avada_multiple_submissions']) && $settings['protect_avada_multiple_submissions'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                            <label for="protect_avada_multiple_submissions"><?php _e('Enable to prevent forms from being submitted multiple times.', 'captcha-for-contact-form-7'); ?></label>
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

        /**
         * @param array $settings
         *
         * @return array<mixed>
         */
        public function get_settings($settings)
        {
            $settings['avada'] = array(
                'protect_avada' => 0,
                'protect_avada_time_enable' => 0,
                'protect_avada_time_ms' => 500,
                'protect_avada_fieldname' => 'f12_captcha',
                'protect_avada_timer_fieldname' => 'f12_timer',
                'protect_avada_multiple_submissions' => 0,
                'protect_avada_method' => 'honey',
                'protect_avada_position' => 'after_submit'
            );

            return $settings;
        }

    }
}