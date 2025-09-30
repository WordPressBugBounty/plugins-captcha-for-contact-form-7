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
			// Rufe den Konstruktor der Elternklasse auf.
			// Die Parameter sind:
			// 1. $UI_Manager: Die Instanz des UI-Managers.
			// 2. 'f12-cf7-captcha': Der eindeutige Domain-Name für diese UI-Seite.
			// 3. 'Beta': Der angezeigte Name der Seite im UI-Menü.
			// 4. 0: Die Priorität oder Reihenfolge im Menü (0 bedeutet ganz oben).
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-beta', 'Beta', 2 );

			$this->get_logger()->info( 'Konstruktor gestartet.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$this->get_logger()->info( 'Konstruktor abgeschlossen.' );
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public function get_settings( $settings ): array {
			$this->get_logger()->info( 'Füge die globalen Standardeinstellungen hinzu.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Definiere ein Array mit allen Standard-Schutzeinstellungen.
			$default_global_settings = [
				// Captcha-Schutz
				'beta_captcha_enable' => 0,
				'beta_captcha_api_key'    => '',
			];

			// Füge die Standardeinstellungen unter dem Schlüssel 'global' zum übergebenen Array hinzu.
			if ( ! isset( $settings['beta'] ) || ! is_array( $settings['beta'] ) ) {
				$settings['beta'] = [];
			}
			$settings['beta'] = array_merge( $settings['beta'], $default_global_settings );

			$this->get_logger()->info( 'Beta Standardeinstellungen wurden dem Einstellungsarray hinzugefügt.' );

			return $settings;
		}

		public function on_save( $settings ): array {
			$this->get_logger()->info( 'Starte den Speichervorgang für die globalen Beta-Einstellungen.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Eine Liste der Optionen, deren Wert auf 0 gesetzt werden soll, wenn sie nicht im POST-Request vorhanden sind.
			$options_to_zero = [
				'beta_captcha_enable',
			];

			$this->get_logger()->debug( 'Verarbeite alle POST-Werte und saniere sie.' );
			foreach ( $settings['beta'] as $key => $value ) {
				if ( isset( $_POST[ $key ] ) ) {
					// Sanitize basierend auf dem Feldtyp
					$settings['beta'][ $key ] = sanitize_text_field( $_POST[ $key ] );
					$this->get_logger()->debug( 'Textfeld saniert.', [ 'key' => $key ] );
				} else {
					// Setze den Wert auf 0, wenn das Feld in $options_to_zero enthalten ist und nicht im POST-Request war.
					if ( in_array( $key, $options_to_zero, true ) ) {
						$settings['beta'][ $key ] = 0;
						$this->get_logger()->debug( 'Feld nicht im POST-Request gefunden, auf 0 gesetzt.', [ 'key' => $key ] );
					}
				}
			}

			$this->get_logger()->info( 'Speichervorgang für die globalen Einstellungen abgeschlossen.' );

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
					<?php _e( 'Captcha Protection (v2)', 'captcha-for-contact-form-7' ); ?>
                </h2>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for=""><strong><?php _e( 'Enable/Disable', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;">
						            <?php _e(
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
								            $name       = __( 'SilentShield Captcha (Beta)', 'captcha-for-contact-form-7' );
								            echo sprintf(
									            '<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>',
									            esc_attr( $field_name ),
									            esc_attr( $field_name ),
									            $is_checked
								            );
								            ?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>" class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
								            <?php esc_attr_e( $name ); ?>
                                            <p>
									            <?php _e(
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
                                            <label for="beta_captcha_api_key"><strong><?php _e( 'API Key:', 'captcha-for-contact-form-7' ); ?></strong></label>
                                            <p>
									            <?php _e(
										            'Enter your SilentShield API Key. This key is required so the plugin can verify solved captchas with the SilentShield backend.',
										            'captcha-for-contact-form-7'
									            ); ?>
                                            </p>
                                        </div>

                                        <div class="input">
                                            <input
                                                    id="beta_captcha_api_key"
                                                    type="text"
                                                    value="<?php echo $settings['beta_captcha_api_key'] ?? ''; ?>"
                                                    name="beta_captcha_api_key"
                                            />
                                            <p>
                                                <a href="https://silentshield.io/register" target="_blank">
                                                <?php _e( 'Request API Key', 'captcha-for-contact-form-7')?>
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
						<?php _e( "Supported ids: avada, fluentform, elementor, cf7, wpforms, ultimatemember, gravityforms, wordpress_comments, wordpress, woocommerce.", 'captcha-for-contact-form-7' ); ?>
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