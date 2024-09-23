<?php

namespace f12_cf7_captcha\deprecated {

	use f12_cf7_captcha\core\Messages;
	use f12_cf7_captcha\core\protection\captcha\Captcha;
	use f12_cf7_captcha\core\protection\captcha\CaptchaCleaner;
	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UICaptcha
     */
    class UI_Captcha extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'captcha', 'Captcha');
            add_action($UI_Manager->get_domain().'_ui_db_after_content', array($this, 'the_content_captcha_reset'), 10, 1);
            add_filter($UI_Manager->get_domain().'ui_db_before_on_save', array($this, 'clean'), 10, 1);
        }

        public function the_content_captcha_reset($settings)
        {
            $entries = Captcha::getCount();
            $validated = Captcha::getCount(1);
            $nonValidated = Captcha::getCount(0);

            ?>
            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="protect_comments"><?php _e('Captchas', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p style="margin-top:0;">
                            <strong><?php _e('Delete Database Captcha Entries', 'captcha-for-contact-form-7'); ?></strong>
                        </p>
                        <p>
                            <?php _e('This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the buttons below.', 'captcha-for-contact-form-7'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Entries:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $entries); ?>
                        </p>
                        <p>
                            <strong><?php _e('Validated:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $validated); ?>
                        </p>
                        <p>
                            <strong><?php _e('Non-Validated:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $nonValidated); ?>
                        </p>
                        <input type="submit" class="button" name="captcha-clean-all"
                               value="<?php _e('Delete All', 'captcha-for-contact-form-7'); ?>"/>
                        <input type="submit" class="button" name="captcha-clean-validated"
                               value="<?php _e('Delete Validated', 'captcha-for-contact-form-7'); ?>"/>
                        <input type="submit" class="button" name="captcha-clean-nonvalidated"
                               value="<?php _e('Deleted Non-Validated', 'captcha-for-contact-form-7'); ?>"/>
                        <p>
                            <?php _e('Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }

        public function hide_in_menu()
        {
            return true;
        }

        /**
         * @private WP HOOK
         */
        public function get_settings($settings)
        {
            return $settings;
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            return $settings;
        }

        public function clean($settings)
        {
            $Cleaner = new CaptchaCleaner();
            if (isset($_POST['captcha-clean-all'])) {
                if ($Cleaner->resetTable()) {
                    Messages::getInstance()->add(__('Captchas removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }
            if (isset($_POST['captcha-clean-validated'])) {
                if ($Cleaner->cleanValidated()) {
                    Messages::getInstance()->add(__('Validated Captchas removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }
            if (isset($_POST['captcha-clean-nonvalidated'])) {
                if ($Cleaner->cleanNonValidated()) {
                    Messages::getInstance()->add(__('Non Validated Captchas removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }

            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
        }

        protected function the_sidebar($slug, $page)
        {
        }
    }
}