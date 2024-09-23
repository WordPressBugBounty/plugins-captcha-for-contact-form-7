<?php

namespace f12_cf7_captcha\deprecated {

	use f12_cf7_captcha\core\Messages;
	use f12_cf7_captcha\core\protection\ip\IPBan;
	use f12_cf7_captcha\core\protection\ip\IPBanCleaner;
	use f12_cf7_captcha\core\protection\ip\IPLog;
	use f12_cf7_captcha\core\protection\ip\IPLogCleaner;
	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UI IP Protection
     */
    class UI_IP_Protection extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'ip', 'IP Protection');

            add_action($UI_Manager->get_domain().'_ui_db_after_content', array($this, 'the_content_ip_log_reset'), 10, 1);
            add_action($UI_Manager->get_domain().'_ui_db_after_content', array($this, 'the_content_ip_ban_reset'), 10, 1);
            add_filter($UI_Manager->get_domain().'_ui_db_before_on_save', array($this, 'maybe_clean'), 10, 1);
        }

        private function do_clean_ip_log()
        {
            if (isset($_POST['captcha-ip-log-clean-all'])) {
                $IPLogCleaner = new IPLogCleaner();
                if ($IPLogCleaner->resetTable()) {
                    Messages::getInstance()->add(__('IP Logs removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }
        }

        private function do_clean_ip_ban()
        {
            if (isset($_POST['captcha-ip-ban-clean-all'])) {
                $IPBanCleaner = new IPBanCleaner();
                if ($IPBanCleaner->reset_table()) {
                    Messages::getInstance()->add(__('IP Bans removed from database', 'captcha-for-contact-form-7'), 'success');
                } else {
                    Messages::getInstance()->add(__('Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7'), 'error');
                }
            }
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
            $this->do_clean_ip_log();
            $this->do_clean_ip_ban();
            return $settings;
        }

        /**
         * @private WP HOOK
         */
        public function get_settings($settings)
        {
            $settings['ip'] = array(
                'protect_ip' => 0, // enabled or not
                'max_retry' => 3, // max retries
                'max_retry_period' => 300, // time in seconds,
                'blockedtime' => 3600, // time in seconds - how long will the user be blocked if he fails to often
                'period_between_submits' => 60, // time between forms submits
            );
            return $settings;
        }

        /**
         * Save on form submit
         */
        protected function on_save($settings)
        {
            foreach ($settings['ip'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if (is_numeric($value)) {
                        $settings['ip'][$key] = (int)$_POST[$key];
                    } else {
                        $settings['ip'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['ip'][$key] = 0;
                }
            }

            return $settings;
        }

        public function the_content_ip_ban_reset($settings)
        {
            $entries = IPBan::getCount();

            ?>
            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="protect_comments"><?php _e('IP Bans', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p style="margin-top:0;">
                            <strong><?php _e('Delete IP Bans Entries', 'captcha-for-contact-form-7'); ?></strong>
                        </p>
                        <p>
                            <?php _e('This entries will be deleted after the blocked time is over using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Entries:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $entries); ?>
                        </p>
                        <input type="submit" class="button" name="captcha-ip-ban-clean-all"
                               value="<?php _e('Delete All', 'captcha-for-contact-form-7'); ?>"/>
                        <p>
                            <?php _e('Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }

        public function the_content_ip_log_reset($settings)
        {
            $entries = IPLog::getCount();

            ?>
            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="protect_comments"><?php _e('IP Logs', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p style="margin-top:0;">
                            <strong><?php _e('Delete IP Log Entries', 'captcha-for-contact-form-7'); ?></strong>
                        </p>
                        <p>
                            <?php _e('This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7'); ?>
                        </p>
                        <p>
                            <strong><?php _e('Entries:', 'captcha-for-contact-form-7'); ?></strong>
                            <?php printf(__('%s entries in the database', 'captcha-for-contact-form-7'), $entries); ?>
                        </p>
                        <input type="submit" class="button" name="captcha-ip-log-clean-all"
                               value="<?php _e('Delete All', 'captcha-for-contact-form-7'); ?>"/>
                        <p>
                            <?php _e('Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }

        protected function add_content_general_settings($settings)
        {
            ?>
            <div class="option">
                <div class="label">
                    <label for="protect_ip"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                </div>
                <div class="input">
                    <!-- SEPARATOR -->
                    <input
                            id="protect_ip"
                            type="checkbox"
                            value="1"
                            name="protect_ip"
                        <?php echo isset($settings['protect_ip']) && $settings['protect_ip'] === 1 ? 'checked="checked"' : ''; ?>
                    />
                    <span>
                        <label for="protect_ip"><?php _e('Enable IP Protection. This will store the IP address SHA512 encrypted within the database and catch all submits.', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="max_retry"><?php _e('Max Retries', 'captcha-for-contact-form-7'); ?></label>
                </div>
                <div class="input">
                    <!-- SEPARATOR -->
                    <input
                            id="max_retry"
                            type="text"
                            value="<?php echo (int)$settings['max_retry']; ?>"
                            name="max_retry"
                    />
                    <span>
                        <label for="max_retry"><?php _e('Number of failed attempts before the IP address is blocked., (recommend: 3 tries)', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="blockedtime"><?php _e('Period for IP address block', 'captcha-for-contact-form-7'); ?></label>
                </div>
                <div class="input">
                    <!-- SEPARATOR -->
                    <input
                            id="blockedtime"
                            type="text"
                            value="<?php echo (int)$settings['blockedtime'] ?? 3600; ?>"
                            name="blockedtime"
                    />
                    <span>
                        <label for="blockedtime"><?php _e('Define how long the IP-Address will be blocked before submitting any data again. (recommend: 3600 = 1 hour)', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                </div>
            </div>

            <div class="option">
                <div class="label">
                    <label for="max_retry_period"><?php _e('Time interval for detection of subsequent attacks', 'captcha-for-contact-form-7'); ?></label>
                </div>
                <div class="input">
                    <!-- SEPARATOR -->
                    <input
                            id="max_retry_period"
                            type="text"
                            value="<?php echo (int)$settings['max_retry_period'] ?? 500; ?>"
                            name="max_retry_period"
                    />
                    <span>
                        <label for="max_retry_period"><?php _e('Enter the period of time that will be used to recognize spam (e.g. enter 1000 for 1 second) (recommend: 3600 = 1 hour).', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                </div>
            </div>
            <?php
        }

        protected function add_content_spam_protection_settings($settings)
        {
            ?>
            <div class="option">
                <div class="label">
                    <label for="period_between_submits"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                </div>
                <div class="input">
                    <!-- SEPARATOR -->
                    <input
                            id="period_between_submits"
                            type="text"
                            value="<?php echo (int)$settings['period_between_submits']; ?>"
                            name="period_between_submits"
                    />
                    <span>
                        <label for="period_between_submits"><?php _e('Number of seconds between form submits. If they are smaller then the entered value, the submit will be recognized as Spam. (recommend: 60 seconds)', 'captcha-for-contact-form-7'); ?></label>
                    </span>
                </div>
            </div>
            <?php
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['ip'];

            ?>
            <h2>
                <?php _e('IP Protection', 'captcha-for-contact-form-7'); ?>
            </h2>

            <div class="section">
                <h3>
                    <?php _e('IP Protection', 'captcha-for-contact-form-7'); ?>
                </h3>
                <?php $this->add_content_general_settings($settings); ?>
            </div>
            <div class="section">
                <h3>
                    <?php _e('Interval Protection', 'captcha-for-contact-form-7'); ?>
                </h3>
                <?php $this->add_content_spam_protection_settings($settings); ?>
            </div>

            <?php
        }

        protected function the_sidebar($slug, $page)
        {
            return;
        }
    }
}