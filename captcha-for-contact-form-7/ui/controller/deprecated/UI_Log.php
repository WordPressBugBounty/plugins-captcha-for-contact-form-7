<?php

namespace f12_cf7_captcha\deprecated {

	use f12_cf7_captcha\core\Messages;
	use forge12\contactform7\CF7Captcha\core\log\Log_Cleaner;
	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UILog
     */
    class UI_Log extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'log', 'Log Settings', 99);

            /**
             * Add the Option to delete the Log Entries
             */
            add_action($UI_Manager->get_domain() . '_ui_db_after_content', array($this, 'the_content_log_reset'), 10, 1);
            add_filter($UI_Manager->get_domain() . '_ui_db_before_on_save', array($this, 'maybe_clean'), 10, 1);
        }

        /**
         * Clean the database
         *
         * @param $message
         * @param $parameter
         *
         * @return string
         */
        public function maybe_clean($settings)
        {
            $this->do_clean_log();
            return $settings;
        }

        private function do_clean_log()
        {
            if (isset($_POST['captcha-log-clean-all'])) {
                $LogCleaner = new Log_Cleaner();
                if ($LogCleaner->resetTable()) {
                    Messages::getInstance()->add(__('Logs removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }
        }

        /**
         * Resets the content of the IP Log section in the user interface.
         * This function displays the HTML code necessary to reset the IP Log entries manually.
         *
         * @param array $settings The settings of the IP Log section.
         *
         * @return void
         */
        public function the_content_log_reset($settings)
        {
            ?>
            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="protect_comments"><?php _e('Logs', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p style="margin-top:0;">
                            <strong><?php _e('Delete Log Entries', 'captcha-for-contact-form-7'); ?></strong>
                        </p>
                        <p>
                            <?php _e('This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Entries:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $entries); ?>
                        </p>
                        <input type="submit" class="button" name="captcha-log-clean-all"
                               value="<?php _e('Delete All', 'captcha-for-contact-form-7'); ?>"/>
                        <p>
                            <?php _e('Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }

        protected function on_save($settings)
        {
            foreach ($settings['logs'] as $key => $value) {
                if (isset($_POST[$key])) {
                    $settings['logs'][$key] = (int)$_POST[$key];
                } else {
                    $settings['logs'][$key] = 0;
                }
            }
            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['logs'];
            ?>
            <h2>
                <?php _e('Log Settings', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="enable"><?php _e('Status', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p style="margin-top:0;">
                            <strong><?php _e('Enable Logging', 'captcha-for-contact-form-7'); ?></strong></p>
                        <input
                                id="enable"
                                type="checkbox"
                                value="1"
                                name="enable"
                            <?php echo isset($settings['enable']) && $settings['enable'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <?php _e('If enabled, all submitted forms will be tracked within the log entries.', 'captcha-for-contact-form-7'); ?>
                    </span>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * @param $slug
         * @param $page
         *
         * @return void
         */
        protected function the_sidebar($slug, $page)
        {
            return;
        }

        /**
         * @param $settings
         *
         * @return mixed
         */
        public function get_settings($settings)
        {
            $settings['logs'] = array(
                'enable' => 1,
            );

            return $settings;
        }

    }
}