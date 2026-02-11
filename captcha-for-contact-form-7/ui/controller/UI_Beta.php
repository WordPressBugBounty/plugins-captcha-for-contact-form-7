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
	 * Class UI_Beta
	 */
	class UI_Beta extends UI_Page_Form {
		public function __construct( UI_Manager $UI_Manager ) {
			// Call the parent class constructor.
			// The parameters are:
			// 1. $UI_Manager: The UI Manager instance.
			// 2. 'f12-cf7-captcha': The unique domain name for this UI page.
			// 3. 'Beta': The displayed name of the page in the UI menu.
			// 4. 0: The priority or order in the menu (0 means at the top).
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-beta', 'Beta', 2 );

			$this->get_logger()->info( 'Constructor started.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$this->get_logger()->info( 'Constructor completed.' );
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public function get_settings( $settings ): array {
			$this->get_logger()->info( 'Adding global default settings.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Define an array with all default protection settings.
			$default_global_settings = [
				// Captcha protection
				'beta_captcha_enable' => 0,
				'beta_captcha_api_key'    => '',
			];

			// Add the default settings under the 'global' key to the passed array.
			if ( ! isset( $settings['beta'] ) || ! is_array( $settings['beta'] ) ) {
				$settings['beta'] = [];
			}
			$settings['beta'] = array_merge( $settings['beta'], $default_global_settings );

			$this->get_logger()->info( 'Beta default settings have been added to the settings array.' );

			return $settings;
		}

		public function on_save( $settings ): array {
			$this->get_logger()->info( 'Starting save process for global beta settings.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// A list of options whose value should be set to 0 if they are not present in the POST request.
			$options_to_zero = [
				'beta_captcha_enable',
			];

			$this->get_logger()->debug( 'Processing all POST values and sanitizing them.' );
			foreach ( $settings['beta'] as $key => $value ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in admin form submission
				if ( isset( $_POST[ $key ] ) ) {
					// Sanitize based on the field type
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in admin form submission
					$settings['beta'][ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					$this->get_logger()->debug( 'Text field sanitized.', [ 'key' => $key ] );
				} else {
					// Set the value to 0 if the field is in $options_to_zero and was not in the POST request.
					if ( in_array( $key, $options_to_zero, true ) ) {
						$settings['beta'][ $key ] = 0;
						$this->get_logger()->debug( 'Field not found in POST request, set to 0.', [ 'key' => $key ] );
					}
				}
			}

			$this->get_logger()->info( 'Save process for global settings completed.' );

			return $settings;
		}

		/**
		 * Render the license subpage content
		 */
		protected function the_content( $slug, $page, $settings ) {

			$settings = $settings['beta'];
			?>
            <div class="section-container">
                <h2>
					<?php esc_html_e( 'Captcha Protection (v2)', 'captcha-for-contact-form-7' ); ?>
                </h2>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for=""><strong><?php esc_html_e( 'Enable/Disable', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;">
						            <?php esc_html_e(
							            'Activate the new SilentShield Captcha protection. Unlike the old method, this version uses our API, behavior-based metrics and server-side verification for stronger and more reliable bot protection.',
							            'captcha-for-contact-form-7'
						            ); ?>
                                </p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
								            <?php
								            $field_name = 'beta_captcha_enable';
								            $is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
								            $name       = esc_html__( 'SilentShield Captcha (Beta)', 'captcha-for-contact-form-7' );
								            echo sprintf(
									            '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>',
									            esc_attr( $field_name ),
									            esc_attr( $field_name ),
									            esc_attr( $is_checked )
								            );
								            ?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>" class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
								            <?php echo esc_html( $name ); ?>
                                            <p>
									            <?php esc_html_e(
										            'Enable this option to switch from the old Captcha v1 to the new SilentShield Captcha with API verification and enhanced security.',
										            'captcha-for-contact-form-7'
									            ); ?>
                                            </p>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label for="beta_captcha_api_key"><strong><?php esc_html_e( 'API Key:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p>
									            <?php esc_html_e(
										            'Enter your SilentShield API Key. This key is required so the plugin can verify solved captchas with the SilentShield backend.',
										            'captcha-for-contact-form-7'
									            ); ?>
                                            </p>
                                        </div>

                                        <div class="input">
                                            <input
                                                    id="beta_captcha_api_key"
                                                    type="text"
                                                    value="<?php echo esc_attr( $settings['beta_captcha_api_key'] ?? '' ); ?>"
                                                    name="beta_captcha_api_key"
                                            />
                                            <p>
                                                <a href="https://silentshield.io/register" target="_blank">
                                                <?php esc_html_e( 'Request API Key', 'captcha-for-contact-form-7' ); ?>
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
						<?php esc_html_e( 'Need help?', 'captcha-for-contact-form-7' ); ?>
                    </h2>
                    <p>
						<?php printf( wp_kses( __( "Take a look at our <a href='%s' target='_blank'>Documentation</a>.", 'captcha-for-contact-form-7' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ), esc_url( 'https://www.forge12.com/blog/so-verwendest-du-das-wordpress-captcha-um-deine-webseite-zu-schuetzen/' ) ); ?>
                    </p>
                </div>
            </div>

            <div class="box">
                <div class="section">
                    <h2>
						<?php esc_html_e( 'Hooks:', 'captcha-for-contact-form-7' ); ?>
                    </h2>
                    <p>
                        <strong><?php esc_html_e( "This hook can be used to skip specific protection methods for forms:", 'captcha-for-contact-form-7' ); ?></strong>
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
                        <strong><?php esc_html_e( "This hook can be used to disable the protection for a plugin:", 'captcha-for-contact-form-7' ); ?></strong>
                    </p>
                    <p>
						<?php esc_html_e( "Supported ids: avada, fluentform, elementor, cf7, wpforms, ultimatemember, gravityforms, wordpress_comments, wordpress, woocommerce.", 'captcha-for-contact-form-7' ); ?>
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
                        <strong><?php esc_html_e( "This hook can be used to manipulate the layout of the captcha field:", 'captcha-for-contact-form-7' ); ?></strong>
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
                        <strong><?php esc_html_e( "This hook can be used to load a custom the reload icon:", 'captcha-for-contact-form-7' ); ?></strong>
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