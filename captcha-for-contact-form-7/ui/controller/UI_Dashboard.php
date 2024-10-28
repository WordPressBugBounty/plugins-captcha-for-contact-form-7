<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\core\BaseController;
	use f12_cf7_captcha\core\Compatibility;
	use f12_cf7_captcha\core\Log_WordPress;
	use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
	use f12_cf7_captcha\core\protection\ip\IPBan;
	use f12_cf7_captcha\core\protection\ip\IPLog;
	use f12_cf7_captcha\core\protection\ip\IPValidator;
	use f12_cf7_captcha\core\protection\Protection;
	use f12_cf7_captcha\core\timer\Timer_Controller;
	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page_Form;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIDashboard
	 */
	class UI_Dashboard extends UI_Page_Form {
		public function __construct( UI_Manager $UI_Manager ) {
			parent::__construct( $UI_Manager, 'f12-cf7-captcha', __( 'Dashboard', 'captcha-for-contact-form-7' ), 0 );

			add_filter( $UI_Manager->get_domain() . '_ui_f12-cf7-captcha_before_on_save', array(
				$this,
				'maybe_clean'
			), 10, 1 );
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public function get_settings( $settings ) {
			$settings['global'] = [
				'protection_time_enable'                   => 0,
				'protection_time_field_name'               => 'f12_timer',
				'protection_time_ms'                       => 500,
				'protection_captcha_enable'                => 0,
				'protection_captcha_label'                 => __( 'Captcha', 'captcha-for-contact-form-7' ),
				'protection_captcha_placeholder'           => __( 'Captcha', 'captcha-for-contact-form-7' ),
				'protection_captcha_reload_icon'           => 'black',
				'protection_captcha_template'              => 2,
				'protection_captcha_method'                => 'honey',
				'protection_captcha_field_name'            => 'f12_captcha',
				'protection_multiple_submission_enable'    => 0,
				'protection_ip_enable'                     => 0,
				'protection_ip_max_retries'                => 3,
				'protection_ip_max_retries_period'         => 300,
				'protection_ip_period_between_submits'     => 60,
				'protection_ip_block_time'                 => 3600,
				'protection_log_enable'                    => 0,
				'protection_rules_url_enable'              => 0,
				'protection_rules_url_limit'               => 0,
				'protection_rules_blacklist_enable'        => 0,
				'protection_rules_blacklist_value'         => '',
				'protection_rules_blacklist_greedy'        => 0,
				'protection_rules_bbcode_enable'           => 0,
				'protection_rules_error_message_url'       => __( 'The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' ),
				'protection_rules_error_message_bbcode'    => __( 'BBCode is not allowed.', 'captcha-for-contact-form-7' ),
				'protection_rules_error_message_blacklist' => __( 'The word %s is blacklisted.', 'captcha-for-contact-form-7' ),
				'protection_browser_enable'                => 0,
				'protection_javascript_enable'             => 0,
				'protection_support_enable'                => 1,
				'protection_whitelist_emails'              => '',
				'protection_whitelist_ips'                 => ''
			];

			return $settings;
		}

		/**
		 * Clean the database
		 *
		 * @param array $settings
		 *
		 * @return array
		 * @throws \Exception
		 */
		public function maybe_clean( $settings ): array {

			/**
			 * Clean IP Log Entries
			 */
			if ( isset( $_POST['captcha-ip-log-clean-all'] ) ) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );

				/**
				 * @var IPValidator $IP_Validator
				 */
				$IP_Validator = $Protection->get_modul( 'ip-validator' );

				$IP_Log_Cleaner = $IP_Validator->get_log_cleaner();

				if ( $IP_Log_Cleaner->reset_table() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'IP Logs removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Clean IP Ban Entries
			 */
			if ( isset( $_POST['captcha-ip-ban-clean-all'] ) ) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );

				/**
				 * @var IPValidator $IP_Validator
				 */
				$IP_Validator = $Protection->get_modul( 'ip-validator' );

				$IP_Ban_Cleaner = $IP_Validator->get_ban_cleaner();

				if ( $IP_Ban_Cleaner->reset_table() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'IP Bans removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Clean All Captchas
			 */
			if ( isset( $_POST['captcha-clean-all'] ) ) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );

				/**
				 * @var Captcha_Validator $Captcha_Validator
				 */
				$Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

				$Captcha_Cleaner = $Captcha_Validator->get_captcha_cleaner();

				if ( $Captcha_Cleaner->reset_table() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Captchas removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Clean Validated Captchas
			 */
			if ( isset( $_POST['captcha-clean-validated'] ) ) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );

				/**
				 * @var Captcha_Validator $Captcha_Validator
				 */
				$Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

				$Captcha_Cleaner = $Captcha_Validator->get_captcha_cleaner();

				if ( $Captcha_Cleaner->clean_validated() ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Validated Captchas removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Clean Non Validated Captchas
			 */
			if ( isset( $_POST['captcha-clean-nonvalidated'] ) ) {
				/**
				 * @var Protection $Protection
				 */
				$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );

				/**
				 * @var Captcha_Validator $Captcha_Validator
				 */
				$Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

				$Captcha_Cleaner = $Captcha_Validator->get_captcha_cleaner();

				if ( $Captcha_Cleaner->clean_non_validated() ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Non Validated Captchas removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Delete Log entries
			 */
			if ( isset( $_POST['captcha-log-clean-all'] ) ) {
				/**
				 * @var Log_Cleaner $Log_Cleaner
				 */
				$Log_Cleaner = CF7Captcha::get_instance()->get_modul( 'log-cleaner' );

				if ( $Log_Cleaner->reset_table() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Logs removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Delete Log entries older than 3 weeks
			 */
			if ( isset( $_POST['captcha-log-clean-3-weeks'] ) ) {
				/**
				 * @var Log_Cleaner $Log_Cleaner
				 */
				$Log_Cleaner = CF7Captcha::get_instance()->get_modul( 'log-cleaner' );

				if ( $Log_Cleaner->clean() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Logs older than 3 Weeks have been removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Timers - Clean all
			 */
			if ( isset( $_POST['captcha-timer-clean-all'] ) ) {
				/**
				 * @var Timer_Controller $Timer_Controller
				 */
				$Timer_Controller = CF7Captcha::get_instance()->get_modul( 'timer' );

				$Timer_Cleaner = $Timer_Controller->get_timer_cleaner();

				if ( $Timer_Cleaner->reset_table() !== null ) {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Timers removed from database', 'captcha-for-contact-form-7' ), 'success' );
				} else {
					$this->get_ui_manager()->get_ui_message()->add( __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' ), 'error' );
				}
			}

			/**
			 * Suppress the saving of the settings
			 */
			if (
				isset( $_POST['captcha-ip-ban-clean-all'] ) ||
				isset( $_POST['captcha-ip-log-clean-all'] ) ||
				isset( $_POST['captcha-clean-all'] ) ||
				isset( $_POST['captcha-clean-validated'] ) ||
				isset( $_POST['captcha-log-clean-3-weeks'] ) ||
				isset( $_POST['captcha-log-clean-clean-all'] ) ||
				isset( $_POST['captcha-timer-clean-all'] ) ||
				isset( $_POST['captcha-clean-nonvalidated'] )
			) {
				add_filter( $this->get_domain() . '_ui_do_save_settings', '__return_false' );
			}

			return $settings;
		}

		protected function on_save( $settings ) {
			/**
			 * @var CF7Captcha $Controller
			 */
			$Controller = CF7Captcha::get_instance();
			/**
			 * @var Compatibility $Compatibility
			 */
			$Compatibility = $Controller->get_modul( 'compatibility' );

			/**
			 * @var array
			 */
			$Components = $Compatibility->get_components();

			/**
			 * Get the status of the components to save within the settings
			 */
			foreach ( $Components as $Component ) {
				/**
				 * @var BaseController $Base_Controller
				 */
				$Base_Controller = $Component['object'];

				/**
				 * @var string $field_name
				 */
				$field_name = sprintf( 'protection_%s_enable', $Base_Controller->get_id() );
				if ( isset( $_POST[ $field_name ] ) ) {
					$settings['global'][ $field_name ] = 1;
				} else {
					$settings['global'][ $field_name ] = 0;
				}
			}

			$options = [
				'protection_time_enable'                => 0,
				'protection_captcha_enable'             => 0,
				'protection_multiple_submission_enable' => 0,
				'protection_ip_enable'                  => 0,
				'protection_log_enable'                 => 0,
				'protection_rules_url_enable'           => 0,
				'protection_rules_url_limit'            => 0,
				'protection_rules_blacklist_enable'     => 0,
				'protection_rules_blacklist_greedy'     => 0,
				'protection_rules_bbcode_enable'        => 0,
				'protection_browser_enable'             => 0,
				'protection_javascript_enable'          => 0,
				'protection_captcha_template'           => 0,
			];

			/**
			 * Load all post values
			 */
			foreach ( $settings['global'] as $key => $value ) {
				if ( isset( $_POST[ $key ] ) ) {
					if ( $key == 'protection_rules_blacklist_value' || $key == 'protection_whitelist_emails' || $key == 'protection_whitelist_ips' ) {
						$settings['global'][ $key ] = sanitize_textarea_field( $_POST[ $key ] );
					} else {
						$settings['global'][ $key ] = sanitize_text_field( $_POST[ $key ] );
					}
				} else {
					if ( isset( $options[ $key ] ) ) {
						$settings['global'][ $key ] = 0;
					}
				}
			}

			$settings['global']['protection_support_enable'] = 1;

			$blacklist                                              = $settings['global']['protection_rules_blacklist_value'];
			$settings['global']['protection_rules_blacklist_value'] = '';

			if ( ! empty( $blacklist ) ) {
				update_option( 'disallowed_keys', $blacklist );
			}

			return $settings;
		}

		/**
		 * Render the license subpage content
		 */
		protected function the_content( $slug, $page, $settings ) {
			$settings = $settings['global'];
			?>
            <div class="section-container">
                <h2>
					<?php _e( 'Available Protection Services', 'captcha-for-contact-form-7' ); ?>
                </h2>
                <div class="section-wrapper">
                    <div class="section advanced">
                        <!-- SEPARATOR -->
                        <div class="option captcha-components">
                            <div class="label">
                                <label for="protect_ip"><strong><?php _e( 'Enable/Disable', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Select the plugins that should be protected. You can enable multiple or only single elements. It is also possible to disable the protection for single formulars using hooks. Have a look at the documentation for further information', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">

								<?php
								$Controller = CF7Captcha::getInstance();
								/**
								 * @var Compatibility $Compatibility
								 */
								$Compatibility = $Controller->get_modul( 'compatibility' );

								/**
								 * @var [] $Components
								 */
								$Components = $Compatibility->get_components();

								ksort( $Components );


								foreach ( $Components as $component ) {
									/**
									 * @var BaseController $Base_Controller
									 */
									$Base_Controller = $component['object'];

									/**
									 * Get the Name
									 */
									$name = $Base_Controller->get_name();

									/**
									 * Field Name created from the ID
									 */
									$id = $Base_Controller->get_id();

									/**
									 * Skip if the controller is not enabled / installed
									 */
									if ( ! $Base_Controller->is_installed() ) {
										continue;
									}

									$field_name = sprintf( 'protection_%s_enable', $id );

									$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';

									?>
                                    <div class="toggle-item-wrapper">
                                        <!-- SEPARATOR -->
                                        <div class="f12-checkbox-toggle">
                                            <div class="toggle-container">
												<?php
												echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
												?>
                                                <label for="<?php esc_attr_e( $field_name ); ?>"
                                                       class="toggle-label"></label>
                                            </div>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"><?php esc_attr_e( $name ); ?></label>
                                            <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"
                                                   id="component-<?php esc_attr_e( $id ); ?>"></label>
                                        </div>
                                    </div>
								<?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'Available Protection Services', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'This option allows you, to enable the captcha protection for WordPress, WooCommerce and supported plugins. You will only see plugins available on your WordPress installation.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'It is possible to enable the protection only for parts of your system.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
								<?php _e( 'Supported Plugins', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <ul>
								<?php foreach ( $Components as $component ):
									/**
									 * @var BaseController $Base_Controller
									 */
									$Base_Controller = $component['object'];

									/**
									 * Get the Name
									 */
									$name = $Base_Controller->get_name();
									?>
                                    <li><?php esc_attr_e( $name ); ?></li>
								<?php endforeach; ?>
                            </ul>
                            <h3>
								<?php _e( 'Is your Plugin missing?', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php echo wp_kses_post( sprintf( __( 'Feel free to open a feature request within the wordpress community board: <a href="%s">Click me.</a>', 'captcha-for-contact-form-7' ), 'https://wordpress.org/support/plugin/captcha-for-contact-form-7/' ) ); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-container">
                <h3>
					<?php _e( 'Captcha Protection', 'captcha-for-contact-form-7' ); ?>
                </h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for=""><strong><?php _e( 'Enable/Disable', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'If activated, a captcha will automatically added to all enabled protection serivces. You can select the type of the captcha below.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_captcha_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Captcha Protection', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                            <p><?php _e( 'Check if you want to add a captcha for the activated protection serivces.', 'captcha-for-contact-form-7' ); ?></p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_captcha_label"><strong><?php _e( 'Label for Captcha:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines the label for the captcha. You can also change the label using WPML or LocoTranslate Plugins.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>

                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <textarea
                                                    rows="5"
                                                    id="protection_captcha_label"
                                                    name="protection_captcha_label"
                                            ><?php
												echo stripslashes( esc_textarea( $settings['protection_captcha_label'] ?? __( 'Captcha', 'captcha-for-contact-form-7' ) ) );
												?></textarea>
                                        </div>
                                    </div>
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_captcha_placeholder"><strong><?php _e( 'Placeholder for Captcha:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines the placeholder for the captcha field. you can also change the label using WPML or LocoTranslate Plugins.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_captcha_placeholder"
                                                    type="text"
                                                    value="<?php echo $settings['protection_captcha_placeholder'] ?? __( 'Captcha', 'captcha-for-contact-form-7' ); ?>"
                                                    name="protection_captcha_placeholder"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'Captcha Protection', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'Captcha Protection allows you to add a specific protection to your forms. You can also use the minor protection methods without the captcha and vice versa.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'The <strong>Label</strong> will be displayed for your website visitors.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'The <strong>placeholder</strong> will be displayed within the captcha input field.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'If you use multiple languages use WPML String Translation or LocoTranslate to translate the label and placeholder', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label
                                        for="protection_method_honey"><?php _e( 'Protection Method', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <input
                                        id="protection_method_honey"
                                        type="radio"
                                        value="honey"
                                        name="protection_captcha_method"
									<?php echo isset( $settings['protection_captcha_method'] ) && $settings['protection_captcha_method'] === 'honey' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_method_honey"><?php _e( 'Honeypot', 'captcha-for-contact-form-7' ); ?></label>
                    </span><br><br>

                                <input
                                        id="protection_method_math"
                                        type="radio"
                                        value="math"
                                        name="protection_captcha_method"
									<?php echo isset( $settings['protection_captcha_method'] ) && $settings['protection_captcha_method'] === 'math' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_method_math"><?php _e( 'Arithmetic', 'captcha-for-contact-form-7' ); ?></label>
                    </span><br><br>

                                <input
                                        id="protection_method_image"
                                        type="radio"
                                        value="image"
                                        name="protection_captcha_method"
									<?php echo isset( $settings['protection_captcha_method'] ) && $settings['protection_captcha_method'] === 'image' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_method_image"><?php _e( 'Image', 'captcha-for-contact-form-7' ); ?></label>
                    </span>
                            </div>
                        </div>

                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h3>
								<?php _e( 'Honeypot', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'This is a hidden field that is not visible to humans, but visible for bots. It is used as a trap to catch spam bots.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
								<?php _e( 'Arithmetic', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'In this method, website visitors are required to solve a simple arithmetic problem before they can submit the form.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
								<?php _e( 'Image', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'In this method, the user is presented with an image containing distorted text they must identify. The User is then required to enter the characters visible in the image to submit the form.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label
                                        for="protection_captcha_reload_icon_black"><?php _e( 'Reload Icon', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <input
                                        id="protection_captcha_reload_icon_black"
                                        type="radio"
                                        value="black"
                                        name="protection_captcha_reload_icon"
									<?php echo isset( $settings['protection_captcha_reload_icon'] ) && $settings['protection_captcha_reload_icon'] === 'black' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_captcha_reload_icon_black">
                            <div style="width:16px; height:16px; background-color:#ccc; padding:3px; display:inline-block;">
                            <img src="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . "core/assets/reload-icon.png"; ?>"
                                 style="width:16px; height:16px;"/>
                            </div>
                            <?php _e( 'Black', 'captcha-for-contact-form-7' ); ?>
                        </label>
                    </span><br><br>

                                <input
                                        id="protection_captcha_reload_icon_white"
                                        type="radio"
                                        value="white"
                                        name="protection_captcha_reload_icon"
									<?php echo isset( $settings['protection_captcha_reload_icon'] ) && $settings['protection_captcha_reload_icon'] === 'white' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_captcha_reload_icon_white">
                            <div style="width:16px; height:16px; background-color:#000; padding:3px; display:inline-block;">
                                    <img src="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . "core/assets/reload-icon-white.png"; ?>"
                                         style="width:16px; height:16px;"/>
                            </div>
                            <?php _e( 'White', 'captcha-for-contact-form-7' ); ?>
                        </label>
                    </span>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label
                                        for="protection_captcha_template"><?php _e( 'Template', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <input
                                        id="protection_captcha_template_0"
                                        type="radio"
                                        value="0"
                                        name="protection_captcha_template"
									<?php echo isset( $settings['protection_captcha_template'] ) && $settings['protection_captcha_template'] == '0' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_captcha_template_0">
                            <div style="border:3px solid #edeaea; border-radius:3px; display:inline-block;">
                            <img src="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . "core/assets/template-0.jpg"; ?>"
                                 style=""/>
                            </div>
                        </label>
                    </span><br><br>

                                <input
                                        id="protection_captcha_template_1"
                                        type="radio"
                                        value="1"
                                        name="protection_captcha_template"
									<?php echo isset( $settings['protection_captcha_template'] ) && $settings['protection_captcha_template'] == '1' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_captcha_template_1">
                            <div style="border:3px solid #edeaea; border-radius:3px; display:inline-block;">
                                    <img src="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . "core/assets/template-1.jpg"; ?>"
                                         style=""/>
                            </div>
                        </label>
                    </span><br><br>

                                <input
                                        id="protection_captcha_template_2"
                                        type="radio"
                                        value="2"
                                        name="protection_captcha_template"
									<?php echo isset( $settings['protection_captcha_template'] ) && $settings['protection_captcha_template'] == '2' ? 'checked="checked"' : ''; ?>
                                />
                                <span>
                        <label for="protection_captcha_template_2">
                            <div style="border:3px solid #edeaea; border-radius:3px; display:inline-block;">
                            <img src="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . "core/assets/template-2.jpg"; ?>"
                                 style=""/>
                            </div>
                        </label>
                    </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section-wrapper">
                    <div class="section">

                        <div class="option">
							<?php

							/**
							 * @var Protection $Protection
							 */
							$Protection = CF7Captcha::get_instance()->get_modul( 'protection' );
							/**
							 * @var Captcha_Validator $Captcha_Validator
							 */
							$Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

							$Captcha = $Captcha_Validator->factory();

							$number_of_captchas               = $Captcha->get_count();
							$number_of_validated_captchas     = $Captcha->get_count( 1 );
							$number_of_non_validated_captchas = $Captcha->get_count( 0 );

							?>
                            <div class="label">
                                <label for=""><?php _e( 'Captchas', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <p style="margin-top:0;">
                                    <strong><?php _e( 'Delete Captcha Entries', 'captcha-for-contact-form-7' ); ?></strong>
                                </p>
                                <p>
									<?php _e( 'This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the buttons below.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                                <p>
                                    <strong><?php _e( 'Entries:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $number_of_captchas ); ?>
                                </p>
                                <p>
                                    <strong><?php _e( 'Validated:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $number_of_validated_captchas ); ?>
                                </p>
                                <p>
                                    <strong><?php _e( 'Non-Validated:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $number_of_non_validated_captchas ); ?>
                                </p>
                                <input type="submit" class="button" name="captcha-clean-all"
                                       value="<?php _e( 'Delete All', 'captcha-for-contact-form-7' ); ?>"/>
                                <input type="submit" class="button" name="captcha-clean-validated"
                                       value="<?php _e( 'Delete Validated', 'captcha-for-contact-form-7' ); ?>"/>
                                <input type="submit" class="button" name="captcha-clean-nonvalidated"
                                       value="<?php _e( 'Deleted Non-Validated', 'captcha-for-contact-form-7' ); ?>"/>
                                <p>
									<?php _e( 'Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                        </div>
                        <div class="option">
							<?php
							/**
							 * @var Timer_Controller $Timer_Controller
							 */
							$Timer_Controller = CF7Captcha::get_instance()->get_modul( 'timer' );

							$CaptchaTimer = $Timer_Controller->factory();

							$number_of_timers = $CaptchaTimer->get_count();

							?>

                            <div class="label">
                                <label for=""><?php _e( 'Timers', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <p style="margin-top:0;">
                                    <strong><?php _e( 'Delete Timer Entries', 'captcha-for-contact-form-7' ); ?></strong>
                                </p>
                                <p>
									<?php _e( 'This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the buttons below.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                                <p>
                                    <strong><?php _e( 'Entries:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $number_of_timers ); ?>
                                </p>
                                <input type="submit" class="button" name="captcha-timer-clean-all"
                                       value="<?php _e( 'Delete All', 'captcha-for-contact-form-7' ); ?>"/>
                                <p>
									<?php _e( 'Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-container">

                <h3>
					<?php _e( 'Minor Protection Services', 'captcha-for-contact-form-7' ); ?>
                </h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for=""><strong><?php _e( 'Enable/Disable', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'There are multiple protection mechanism available that you can use to stop incoming spam. Feel free to enable / disable them as required.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_javascript_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Javascript Protection', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                            <p><?php _e( 'Check if the user has javascript enabled. Most likely bots don\'t use or understand javascript.', 'captcha-for-contact-form-7' ); ?></p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>

                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_browser_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Browser Protection', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                            <p><?php _e( 'Check if the user has a valid user agent.', 'captcha-for-contact-form-7' ); ?></p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>

                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_multiple_submission_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Multiple Submission Protection', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                            <p><?php _e( 'Ensure that a form can not submitted multiple times within 2 seconds.', 'captcha-for-contact-form-7' ); ?></p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'Minor Protection Services', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'Bots are getting smarter these days, therefor we added a few additional protection methods, that will help to filter spam even better.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
                                <strong>
									<?php _e( 'Javascript Protection', 'captcha-for-contact-form-7' ); ?>
                                </strong>
                            </h3>
                            <p>
								<?php _e( 'Recommendation: Enable. This will check if the user supports JavaScript. As most of the bots are not able to interpret JavaScript, this will remove a bunch of spam.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
								<?php _e( 'Browser Protection', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'Recommendation: Enable. This will check if the user agent is valid. This can help to identify spam, you can use it to extend your protection.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <h3>
								<?php _e( 'Multiple Submission Protection', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'This will ensure that the user is not able to submit the form multiple times between 2 seconds.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-container">
                <h3>
					<?php _e( 'Protection Rules', 'captcha-for-contact-form-7' ); ?>
                </h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="protection_rules_url_enable"><strong><?php _e( 'URL Limiter', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable the URL Limiter to limit the number of allowed links in your forms.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_rules_url_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'URL Limiter', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="rule_url_limit"><strong><?php _e( 'Allowed Links:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines how many links are allowed per Field.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="rule_url_limit"
                                                    type="number"
                                                    value="<?php echo $settings['protection_rules_url_limit'] ?? 0; ?>"
                                                    name="protection_rules_url_limit"
                                            />
                                        </div>
                                    </div>
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_rules_error_message_url"><strong><?php _e( 'Error Message:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines the error message that should be displayed if the limit has been reached.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_rules_error_message_url"
                                                    type="text"
                                                    value="<?php echo $settings['protection_rules_error_message_url'] ?? __( 'The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' ); ?>"
                                                    name="protection_rules_error_message_url"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'URL Limiter', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'The URL Limiter is limiting the number of hyperlinks that can be included in the content of a form submission. Keep in mind, that the limit is by field not by form.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'The custom error message will be displayed for website visitors if the error appears, therefor it would be helpful to explain them how to solve this issue', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="protection_rules_bbcode_enable"><strong><?php _e( 'BBCode Limiter', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable the BBCode limiter to mark BBCode as Spam on your website.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_rules_bbcode_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'BBCode Filter', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_rules_error_message_bbcode"><strong><?php _e( 'Error Message:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines the error message that should be displayed if BBCode has been found.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_rules_error_message_bbcode"
                                                    type="text"
                                                    value="<?php echo $settings['protection_rules_error_message_bbcode'] ?? __( 'The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' ); ?>"
                                                    name="protection_rules_error_message_bbcode"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'BBCode Limiter', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'The BBCode Limiter allows you to disable BBCode in your forms. BBCode, which stands for Bulletin Board Code, is a lightweight markup language used to format posts in many message boards, online forums, and comment sections. BBCode tags are similar to HTML but are simpler and safer.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="section-wrapper">
                    <div class="section">

                        <div class="option">
                            <div class="label">
                                <label for="protection_rules_blacklist_enable"><strong><?php _e( 'Blacklist', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable the Blacklist for your forms. This allows you to define custom text combinations as spam.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_rules_blacklist_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Blacklist', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="rule_blacklist_value"><strong><?php _e( 'Blacklisted Texts', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p>
												<?php _e( 'Those are the values that will be triggering the blacklist to mark the input as spam.', 'captcha-for-contact-form-7' ); ?>
                                            </p>
                                            <p>
												<?php _e( 'Use one word / sentence per line.', 'captcha-for-contact-form-7' ); ?>
                                            </p>

                                            <input type="button" class="button" id="syncblacklist"
                                                   value="<?php _e( 'Load predefined Blacklist', 'captcha-for-contact-form-7' ); ?>"/>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <textarea
                                                    rows="20"
                                                    id="rule_blacklist_value"
                                                    name="protection_rules_blacklist_value"
                                            ><?php
												echo stripslashes( esc_textarea( $settings['protection_rules_blacklist_value'] ) );
												?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_rules_blacklist_greedy';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Make it greedy', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                            <p>
												<?php _e( 'If the greedy filter is enabled, even parts of the word will causing the filter to trigger, e.g.: the word "com" is blacklisted and the greedy filter is enabled, this will cause "forge12.com", "composite" and "compose" also to be filtered.', 'captcha-for-contact-form-7' ); ?>
                                            </p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_rules_error_message_blacklist"><strong><?php _e( 'Error Message:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p><?php _e( 'Defines the error message that should be displayed if BBCode has been found.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_rules_error_message_blacklist"
                                                    type="text"
                                                    value="<?php echo $settings['protection_rules_error_message_blacklist'] ?? __( 'The Limit %d has been reached. Remove the %s to continue.', 'captcha-for-contact-form-7' ); ?>"
                                                    name="protection_rules_error_message_blacklist"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'Blacklist', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
								<?php _e( 'The blacklist is a list of prohibited or undesirable input values. When a user submits a form, the data provided is checked against the blacklist. If any part of the users input matches an entry on the blacklist, the form submission will be rejected and the user will be asked to provide different information.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
								<?php _e( 'You can import a predefined blacklist from us. The predefined list contains roundabout 40.000 entries in multiple languages.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <div class="option">
                                <div class="input">
                                    <p>
                                        <strong><?php _e( 'Note', 'captcha-for-contact-form-7' ); ?>:</strong>
                                    </p>
                                    <p>
										<?php _e( 'If you notice long loading times when submitting the form, reduce the entries in the list.', 'captcha-for-contact-form-7' ); ?>
                                    </p>
                                </div>
                            </div>
                            <h3>
								<?php _e( 'Make it greedy', 'captcha-for-contact-form-7' ); ?>
                            </h3>
                            <p>
								<?php _e( 'Use the greed filter to find also parts of the word and mark them as blacklisted.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <div class="option">
                                <div class="input">
                                    <p>
                                        <strong><?php _e( 'Example', 'captcha-for-contact-form-7' ); ?>:</strong>
                                    </p>
                                    <p>
										<?php _e( 'If you have an entry name "com" and enable the greedy filter, this will also trigger for composite, compose and .com', 'captcha-for-contact-form-7' ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-container">
                <h3>
					<?php _e( 'IP Protection', 'captcha-for-contact-form-7' ); ?>
                </h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="protection_ip_enable"><strong><?php _e( 'IP Protection', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable the IP Protection to automatically stop bots from submitting any forms as long as they are blocked.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_ip_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'IP Protection', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_ip_max_retries"><strong><?php _e( 'Max Retries:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p style="padding-right:20px;"><?php _e( 'Defines the number of retries till the IP gets automatically blocked.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_ip_max_retries"
                                                    type="number"
                                                    value="<?php echo $settings['protection_ip_max_retries'] ?? 3; ?>"
                                                    name="protection_ip_max_retries"
                                            />
                                        </div>
                                    </div>

                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_ip_max_retries_period"><strong><?php _e( 'Time interval:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p style="padding-right:20px;"><?php _e( 'Defines the time interval for detection of subsequent attacks.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_ip_max_retries_period"
                                                    type="number"
                                                    value="<?php echo $settings['protection_ip_max_retries_period'] ?? 300; ?>"
                                                    name="protection_ip_max_retries_period"
                                            />
                                        </div>
                                    </div>

                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_ip_block_time"><strong><?php _e( 'Unblock after X seconds:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p style="padding-right:20px;"><?php _e( 'The user will not be able to submit any forms until he gets unblocked after the given amount of seconds.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_ip_block_time"
                                                    type="number"
                                                    value="<?php echo $settings['protection_ip_block_time'] ?? 3600; ?>"
                                                    name="protection_ip_block_time"
                                            />
                                        </div>
                                    </div>
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="protection_ip_period_between_submits"><strong><?php _e( 'Interval Protection:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p style="padding-right:20px;"><?php _e( 'All submissions faster than the given period seconds will automatically be marked as spam.', 'captcha-for-contact-form-7' ); ?></p>
                                        </div>
                                        <div class="input">
                                            <!-- SEPARATOR -->
                                            <input
                                                    id="protection_ip_period_between_submits"
                                                    type="number"
                                                    value="<?php echo $settings['protection_ip_period_between_submits'] ?? 60; ?>"
                                                    name="protection_ip_period_between_submits"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label for="protect_comments"><?php _e( 'IP Bans', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <p style="margin-top:0;">
                                    <strong><?php _e( 'Delete IP Bans Entries', 'captcha-for-contact-form-7' ); ?></strong>
                                </p>
                                <p>
									<?php _e( 'This entries will be deleted after the blocked time is over using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                                <p>
									<?php
									$IP_Ban  = new IPBan();
									$entries = $IP_Ban->get_count();
									?>
                                    <strong><?php _e( 'Entries:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $entries ); ?>
                                </p>
                                <input type="submit" class="button" name="captcha-ip-ban-clean-all"
                                       value="<?php _e( 'Delete All', 'captcha-for-contact-form-7' ); ?>"/>
                                <p>
									<?php _e( 'Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label for="protect_comments"><?php _e( 'IP Logs', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <p style="margin-top:0;">
                                    <strong><?php _e( 'Delete IP Log Entries', 'captcha-for-contact-form-7' ); ?></strong>
                                </p>
                                <p>
									<?php _e( 'This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                                <p>
									<?php
									$IP_Log  = new IPLog();
									$entries = $IP_Log->get_count();
									?>
                                    <strong><?php _e( 'Entries:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $entries ); ?>
                                </p>
                                <input type="submit" class="button" name="captcha-ip-log-clean-all"
                                       value="<?php _e( 'Delete All', 'captcha-for-contact-form-7' ); ?>"/>
                                <p>
									<?php _e( 'Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-container">
                <h3>
					<?php _e( 'Logs', 'captcha-for-contact-form-7' ); ?>
                </h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="protection_log_enable"><strong><?php _e( 'Submission Logging', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable the logs if you need further informations about verified and blocked submissions.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_log_enable';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Enable Logging', 'captcha-for-contact-form-7' );
											echo sprintf( '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>', esc_attr( $field_name ), esc_attr( $field_name ), $is_checked );
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php esc_attr_e( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="option">
                            <div class="label">
                                <label for="protect_comments"><?php _e( 'Logs', 'captcha-for-contact-form-7' ); ?></label>
                            </div>
                            <div class="input">
                                <!-- SEPARATOR -->
                                <p style="margin-top:0;">
                                    <strong><?php _e( 'Delete Log Entries', 'captcha-for-contact-form-7' ); ?></strong>
                                </p>
                                <p>
									<?php _e( 'This entries will be deleted using a WP Cronjob. If you want to reset it manually, use the button below.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                                <p>
									<?php
									$number_of_log_entries = Log_WordPress::get_instance()->get_count();

									?>
                                    <strong><?php _e( 'Entries:', 'captcha-for-contact-form-7' ); ?></strong>
									<?php printf( __( '%s entries in the database', 'captcha-for-contact-form-7' ), $number_of_log_entries ); ?>
                                </p>
                                <input type="submit" class="button" name="captcha-log-clean-all"
                                       value="<?php _e( 'Delete All', 'captcha-for-contact-form-7' ); ?>"/>
                                <input type="submit" class="button" name="captcha-log-clean-3-weeks"
                                       value="<?php _e( 'Delete older than 3 Weeks', 'captcha-for-contact-form-7' ); ?>"/>
                                <p>
									<?php _e( 'Make sure to backup your database before clicking one of these buttons.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-container">
                <!-- Whitelist Section -->
                <h3><?php _e( 'Whitelist Settings', 'captcha-for-contact-form-7' ); ?></h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="protection_whitelist_emails"><strong><?php _e( 'Whitelist Email Addresses', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p><?php _e( 'Add email addresses that should bypass all CAPTCHA checks, one per line.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <textarea
                                        rows="10"
                                        id="protection_whitelist_emails"
                                        name="protection_whitelist_emails"
                                ><?php echo esc_textarea( $settings['protection_whitelist_emails'] ); ?></textarea>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label for="protection_whitelist_ips"><strong><?php _e( 'Whitelist IP Addresses', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p><?php _e( 'Add IP addresses that should bypass all CAPTCHA checks, one per line.', 'captcha-for-contact-form-7' ); ?></p>
                                <label><strong><?php _e('Your Current IP Address', 'captcha-for-contact-form-7'); ?></strong></label>
                                <p><?php echo esc_html($_SERVER['REMOTE_ADDR']); ?></p>
                            </div>
                            <div class="input">
                                <textarea
                                        rows="10"
                                        id="protection_whitelist_ips"
                                        name="protection_whitelist_ips"
                                ><?php echo esc_textarea( $settings['protection_whitelist_ips'] ); ?></textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
			<?php
		}

		protected function the_sidebar( $slug, $page ) {
			?>
            <div class="box">
                <div class="section">
                    <h2>
						<?php _e( 'Need help?', 'captcha-for-contact-form-7' ); ?>
                    </h2>
                    <p>
						<?php printf( __( "Take a look at our <a href='%s' target='_blank'>Documentation</a>.", 'captcha-for-contact-form-7' ), 'https://www.forge12.com/blog/so-verwendest-du-das-wordpress-captcha-um-deine-webseite-zu-schuetzen/' ); ?>
                    </p>
                </div>
            </div>

            <div class="box">
                <div class="section">
                    <h2>
						<?php _e( 'Hooks:', 'captcha-for-contact-form-7' ); ?>
                    </h2>
                    <p>
                        <strong><?php _e( "This hook can be used to skip specific protection methods for forms:", 'captcha-for-contact-form-7' ); ?></strong>
                    </p>
                    <div class="option">
                        <div class="input">
                            <p>
                                apply_filters('f12-cf7-captcha-skip-validation', $enabled);
                                <br>
                            </p>
                        </div>
                    </div>
                    <p>
                        <strong><?php _e( "This hook can be used to disable the protection for a plugin:", 'captcha-for-contact-form-7' ); ?></strong>
                    </p>
                    <p>
						<?php _e( "Supported ids: avada, elementor, cf7, wpforms, ultimatemember, gravityforms, wordpress_comments, wordpress, woocommerce.", 'captcha-for-contact-form-7' ); ?>
                    </p>
                    <div class="option">
                        <div class="input">
                            <p>
                                apply_filters('f12_cf7_captcha_is_installed_{id}', $enabled);
                                <br>
                            </p>
                        </div>
                    </div>

                    <p>
                        <strong><?php _e( "This hook can be used to manipulate the layout of the captcha field:", 'captcha-for-contact-form-7' ); ?></strong>
                    </p>
                    <div class="option">
                        <div class="input">
                            <p>
                                apply_filters('f12-cf7-captcha-get-form-field-{type}', $captcha, $field_name, $label,
                                $Captcha_Session, $atts);
                                <br>
                            </p>
                        </div>
                    </div>
                    <p>
                        <strong><?php _e( "This hook can be used to load a custom the reload icon:", 'captcha-for-contact-form-7' ); ?></strong>
                    </p>
                    <div class="option">
                        <div class="input">
                            <p>
                                apply_filters('f12-cf7-captcha-reload-icon', $image_url);
                                <br>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

			<?php
		}


	}
}
