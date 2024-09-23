<?php

namespace f12_cf7_captcha\deprecated {

	use forge12\ui\UI_Manager;
	use forge12\ui\UI_Page_Form;

	if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Class UIFilterRules
     */
    class UI_Filter_Rules extends UI_Page_Form
    {
        public function __construct(UI_Manager $UI_Manager)
        {
            parent::__construct($UI_Manager, 'filterrules', 'Filter Rules');
        }


        protected function on_save($settings)
        {
            foreach ($settings['rules'] as $key => $value) {
                if (isset($_POST[$key])) {
                    if ($key == 'rule_blacklist_value') {
                        update_option('disallowed_keys', sanitize_textarea_field($_POST[$key]));
                    }elseif (is_numeric($value)) {
                        $settings['rules'][$key] = (int)$_POST[$key];
                    }else {
                        $settings['rules'][$key] = sanitize_text_field($_POST[$key]);
                    }
                } else {
                    $settings['rules'][$key] = 0;
                }
            }
            return $settings;
        }

        /**
         * Render the license subpage content
         */
        protected function the_content($slug, $page, $settings)
        {
            $settings = $settings['rules'];
            $settings['rule_blacklist_value'] = get_option('disallowed_keys');
            ?>
            <h2>
                <?php _e('Filter Rules', 'captcha-for-contact-form-7'); ?>
            </h2>
            <p>
                <?php _e('These rules, if enabled, will be applied to all supported forms (Avada, Comments, Elementor, CF7, ...).', 'captcha-for-contact-form-7'); ?>
            </p>

            <div class="section">
                <h3>
                    <?php _e('URL Filter', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="rule_url"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_url"
                                type="checkbox"
                                value="1"
                                name="rule_url"
                            <?php echo isset($settings['rule_url']) && $settings['rule_url'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <?php _e('If enabled, all fields will be checked if there are any urls exceeding the followed limit.', 'captcha-for-contact-form-7'); ?>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="rule_url_limit"><?php _e('Limiter', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_url_limit"
                                type="number"
                                value="<?php echo $settings['rule_url_limit'] ?? 0; ?>"
                                name="rule_url_limit"
                        />
                        <span><?php _e('Define how many links are allowed by one field.', 'captcha-for-contact-form-7'); ?></span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="rule_error_message_url"><?php _e('Error Message', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_error_message_url"
                                type="text"
                                value="<?php echo $settings['rule_error_message_url'] ?? __('The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7'); ?>"
                                name="rule_error_message_url"
                        />
                        <p><?php _e('Define the error message displayed to the visitor.', 'captcha-for-contact-form-7'); ?></p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>
                    <?php _e('BB Code Filter', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="rule_bbcode_url"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_bbcode_url"
                                type="checkbox"
                                value="1"
                                name="rule_bbcode_url"
                            <?php echo isset($settings['rule_bbcode_url']) && $settings['rule_bbcode_url'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <?php _e('Filter [url={url}]{text}[/url]', 'captcha-for-contact-form-7'); ?>
                    </span>
                    </div>
                </div>
                <div class="option">
                    <div class="label">
                        <label for="rule_error_message_bbcode"><?php _e('Error Message', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_error_message_bbcode"
                                type="text"
                                value="<?php echo $settings['rule_error_message_bbcode'] ?? __('The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7'); ?>"
                                name="rule_error_message_bbcode"
                        />
                        <p><?php _e('Define the error message displayed to the visitor.', 'captcha-for-contact-form-7'); ?></p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>
                    <?php _e('Blacklist', 'captcha-for-contact-form-7'); ?>
                </h3>
                <div class="option">
                    <div class="label">
                        <label for="rule_blacklist"><?php _e('Enable/Disable', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_blacklist"
                                type="checkbox"
                                value="1"
                                name="rule_blacklist"
                            <?php echo isset($settings['rule_blacklist']) && $settings['rule_blacklist'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span>
                        <?php _e('Enable the blacklist.', 'captcha-for-contact-form-7'); ?>
                    </span>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="rule_blacklist_greedy"><?php _e('Greedy/Ungreedy', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <input
                                id="rule_blacklist_greedy"
                                type="checkbox"
                                value="1"
                                name="rule_blacklist_greedy"
                            <?php echo isset($settings['rule_blacklist_greedy']) && $settings['rule_blacklist_greedy'] === 1 ? 'checked="checked"' : ''; ?>
                        />
                        <span><?php _e('Enable/Disable greedy filter.', 'captcha-for-contact-form-7'); ?></span>
                        <p>
                            <?php _e(' If the greedy filter is enabled, even parts of the word will causing the filter to trigger, e.g.: the word "com" is blacklisted and the greedy filter is enabled, this will cause "forge12.com", "composite" and "compose" to also trigger the error message.', 'captcha-for-contact-form-7'); ?>
                        </p>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="rule_blacklist_value"><?php _e('Blacklist', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <textarea
                                rows="20"
                                id="rule_blacklist_value"
                                name="rule_blacklist_value"
                        ><?php echo $settings['rule_blacklist_value'] ?? ''; ?></textarea>
                        <span><?php _e('Define words that should be blacklisted.', 'captcha-for-contact-form-7'); ?></span>
                        <br><br>
                        <input type="button" class="button" id="syncblacklist"
                               value="<?php _e('Load predefined Blacklist', 'captcha-for-contact-form-7'); ?>"/>
                    </div>
                </div>

                <div class="option">
                    <div class="label">
                        <label for="rule_blacklist_value"><?php _e('Error Message', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <!-- SEPARATOR -->
                        <p><strong><?php _e('Error Message', 'captcha-for-contact-form-7'); ?></strong></p>
                        <input
                                id="rule_error_message_blacklist"
                                type="text"
                                value="<?php echo $settings['rule_error_message_blacklist'] ?? __('The word %s is blacklisted.', 'captcha-for-contact-form-7'); ?>"
                                name="rule_error_message_blacklist"
                        />
                        <p><?php _e('Define the error message displayed to the visitor.', 'captcha-for-contact-form-7'); ?></p>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="option">
                    <div class="label">
                        <label for="support"><?php _e('Support Forge12 Captcha: ', 'captcha-for-contact-form-7'); ?></label>
                    </div>
                    <div class="input">
                        <input type="hidden" class="toggle" name="support"
                               value="<?php esc_attr_e($settings['support']); ?>"
                               data-before="<?php _e('On', 'captcha-for-contact-form-7'); ?>"
                               data-after="<?php _e('Off', 'captcha-for-contact-form-7'); ?>"/>

                        <p>
                            <?php _e('The Footer will contain a noscript referral to support Forge12 Captcha.', 'captcha-for-contact-form-7'); ?>
                        </p>
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
            $settings['rules'] = array(
                'support' => 1,
                'rule_url' => 0,
                'rule_url_limit' => 0,
                'rule_blacklist' => 0,
                'rule_blacklist_greedy' => 1,
                'rule_blacklist_value' => '',
                'rule_bbcode_url' => 0,
                'rule_error_message_url' => __('The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7'),
                'rule_error_message_bbcode' => __('BBCode is not allowed.', 'captcha-for-contact-form-7'),
                'rule_error_message_blacklist' => __('The word %s is blacklisted.', 'captcha-for-contact-form-7'),
            );

            return $settings;
        }

    }
}