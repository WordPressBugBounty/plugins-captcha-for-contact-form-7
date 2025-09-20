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
			// Rufe den Konstruktor der Elternklasse auf.
			// Die Parameter sind:
			// 1. $UI_Manager: Die Instanz des UI-Managers.
			// 2. 'f12-cf7-captcha': Der eindeutige Domain-Name für diese UI-Seite.
			// 3. 'Dashboard': Der angezeigte Name der Seite im UI-Menü.
			// 4. 0: Die Priorität oder Reihenfolge im Menü (0 bedeutet ganz oben).
			parent::__construct( $UI_Manager, 'f12-cf7-captcha', 'Dashboard', 0 );

			$this->get_logger()->info( 'Konstruktor gestartet.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Füge einen Filter-Hook hinzu, der vor dem Speichern der UI-Einstellungen ausgelöst wird.
			// Der Hook-Tag wird dynamisch aus dem UI-Manager-Domain-Namen und dem eigenen Domain-Namen erstellt.
			add_filter(
				$UI_Manager->get_domain() . '_ui_f12-cf7-captcha_before_on_save',
				array( $this, 'maybe_clean' ), // Rufe die Methode maybe_clean() dieser Klasse auf.
				10, // Priorität des Filters (10 ist Standard).
				1  // Anzahl der übergebenen Argumente (hier 1).
			);
			$this->get_logger()->debug( 'Filter "ui_f12-cf7-captcha_before_on_save" hinzugefügt.', [
				'hook' => $UI_Manager->get_domain() . '_ui_f12-cf7-captcha_before_on_save'
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
				'protection_captcha_enable'      => 1,
				'protection_captcha_label'       => __( 'Captcha', 'captcha-for-contact-form-7' ),
				'protection_captcha_placeholder' => __( 'Captcha', 'captcha-for-contact-form-7' ),
				'protection_captcha_reload_icon' => 'black',
				'protection_captcha_template'    => 2,
				'protection_captcha_method'      => 'honey',
				'protection_captcha_field_name'  => 'f12_captcha',
			];

			// Füge die Standardeinstellungen unter dem Schlüssel 'global' zum übergebenen Array hinzu.
			if ( !isset($settings['global']) || ! is_array( $settings['global'] ) ) {
				$settings['global'] = [];
			}
			$settings['global'] = array_merge($settings['global'], $default_global_settings);

			$this->get_logger()->info( 'Globale Standardeinstellungen wurden dem Einstellungsarray hinzugefügt.' );

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
		public function maybe_clean( array $settings ): array {
			$this->get_logger()->info( 'Starte die Überprüfung, ob eine Bereinigungsaktion angefordert wurde.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Definiere die möglichen Bereinigungsaktionen und ihre zugehörigen Meldungen und Methoden.
			$clean_actions = [
				'captcha-ip-log-clean-all'   => [
					'module'         => 'protection',
					'sub_module'     => 'ip-validator',
					'cleaner_method' => 'get_log_cleaner',
					'db_method'      => 'reset_table',
					'message'        => __( 'IP Logs removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-ip-ban-clean-all'   => [
					'module'         => 'protection',
					'sub_module'     => 'ip-validator',
					'cleaner_method' => 'get_ban_cleaner',
					'db_method'      => 'reset_table',
					'message'        => __( 'IP Bans removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-clean-all'          => [
					'module'         => 'protection',
					'sub_module'     => 'captcha-validator',
					'cleaner_method' => 'get_captcha_cleaner',
					'db_method'      => 'reset_table',
					'message'        => __( 'Captchas removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-clean-validated'    => [
					'module'         => 'protection',
					'sub_module'     => 'captcha-validator',
					'cleaner_method' => 'get_captcha_cleaner',
					'db_method'      => 'clean_validated',
					'message'        => __( 'Validated Captchas removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-clean-nonvalidated' => [
					'module'         => 'protection',
					'sub_module'     => 'captcha-validator',
					'cleaner_method' => 'get_captcha_cleaner',
					'db_method'      => 'clean_non_validated',
					'message'        => __( 'Non Validated Captchas removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-log-clean-all'      => [
					'module'         => 'log-cleaner',
					'cleaner_method' => null,
					'db_method'      => 'reset_table',
					'message'        => __( 'Logs removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-log-clean-3-weeks'  => [
					'module'         => 'log-cleaner',
					'cleaner_method' => null,
					'db_method'      => 'clean',
					'message'        => __( 'Logs older than 3 Weeks have been removed from database', 'captcha-for-contact-7-captcha' )
				],
				'captcha-timer-clean-all'    => [
					'module'         => 'timer',
					'sub_module'     => null,
					'cleaner_method' => 'get_timer_cleaner',
					'db_method'      => 'reset_table',
					'message'        => __( 'Timers removed from database', 'captcha-for-contact-7-captcha' )
				],
			];

			$action_triggered = false;
			$ui_manager       = $this->get_ui_manager();
			$ui_message       = $ui_manager->get_ui_message();
			$error_message    = __( 'Something went wrong, please try again later or contact the plugin author.', 'captcha-for-contact-form-7' );

			foreach ( $clean_actions as $post_key => $action_data ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$action_triggered = true;
					$this->get_logger()->info( "Bereinigungsaktion angefordert: '{$post_key}'" );

					try {
						// Zugriff auf die Haupt-Modul-Instanz.
						$main_module = CF7Captcha::get_instance()->get_modul( $action_data['module'] );

						// Optional: Zugriff auf die Unter-Modul-Instanz, falls vorhanden.
						$cleaner_instance = $main_module;
						if ( $action_data['sub_module'] !== null ) {
							$cleaner_instance = $main_module->get_modul( $action_data['sub_module'] );
						}

						// Optional: Zugriff auf die Cleaner-Instanz, falls eine spezielle Methode dafür existiert.
						if ( $action_data['cleaner_method'] !== null ) {
							$cleaner_instance = call_user_func( [ $cleaner_instance, $action_data['cleaner_method'] ] );
						}

						// Führe die Datenbankbereinigungsmethode aus.
						$result = call_user_func( [ $cleaner_instance, $action_data['db_method'] ] );

						// Überprüfe das Ergebnis und zeige die entsprechende Nachricht an.
						if ( $result !== null ) {
							$ui_message->add( $action_data['message'], 'success' );
							$this->get_logger()->info( "Aktion '{$post_key}' erfolgreich abgeschlossen." );
						} else {
							$ui_message->add( $error_message, 'error' );
							$this->get_logger()->error( "Aktion '{$post_key}' fehlgeschlagen." );
						}
					} catch ( \Exception $e ) {
						$ui_message->add( $error_message, 'error' );
						$this->get_logger()->critical( "Kritischer Fehler bei der Aktion '{$post_key}'.", [ 'exception' => $e->getMessage() ] );
					}
				}
			}

			// Wenn eine Bereinigungsaktion ausgeführt wurde, unterdrücke das Speichern der UI-Einstellungen.
			if ( $action_triggered ) {
				$this->get_logger()->info( 'Bereinigungsaktion erkannt. Das Speichern der UI-Einstellungen wird unterdrückt.' );
				add_filter( $this->get_domain() . '_ui_do_save_settings', '__return_false' );
			}

			return $settings;
		}

		public function on_save( $settings ): array {
			$this->get_logger()->info( 'Starte den Speichervorgang für die globalen Einstellungen.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Eine Liste der Optionen, deren Wert auf 0 gesetzt werden soll, wenn sie nicht im POST-Request vorhanden sind.
			$options_to_zero = [
				'protection_captcha_enable',
			];

			$this->get_logger()->debug( 'Verarbeite alle POST-Werte und saniere sie.' );
			foreach ( $settings['global'] as $key => $value ) {
				if ( isset( $_POST[ $key ] ) ) {
					// Sanitize basierend auf dem Feldtyp
					$settings['global'][ $key ] = sanitize_text_field( $_POST[ $key ] );
					$this->get_logger()->debug( 'Textfeld saniert.', [ 'key' => $key ] );
				} else {
					// Setze den Wert auf 0, wenn das Feld in $options_to_zero enthalten ist und nicht im POST-Request war.
					if ( in_array( $key, $options_to_zero, true ) ) {
						$settings['global'][ $key ] = 0;
						$this->get_logger()->debug( 'Feld nicht im POST-Request gefunden, auf 0 gesetzt.', [ 'key' => $key ] );
					}
				}
			}

			$this->get_logger()->info( 'Speichervorgang für die globalen Einstellungen abgeschlossen.' );

			return $settings;
		}

		protected function render_statistics() {
			$counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			$stats    = [
				'checks_total' => $counters['checks_total'] ?? 0,
				'checks_spam'  => $counters['checks_spam'] ?? 0,
				'checks_clean' => $counters['checks_clean'] ?? 0,
			];
			?>
            <div class="section-container">
                <div class="section-wrapper">
                    <div class="section">
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;">
                            <!-- Total Checks -->
                            <div style="background:#f1f5f9; border-radius:12px; padding:20px; text-align:center; ">
                                <div style="font-size:48px; font-weight:700; color:#1e40af; margin-bottom:20px;">
									<?php echo number_format_i18n( $stats['checks_total'] ); ?>
                                </div>
                                <div style="font-size:16px; color:#475569; margin-top:8px;">
									<?php _e( 'Total Checks', 'captcha-for-contact-form-7' ); ?>
                                </div>
                            </div>

                            <!-- Spam Blocked -->
                            <div style="background:#fee2e2; border-radius:12px; padding:20px; text-align:center;">
                                <div style="font-size:48px; font-weight:700; color:#b91c1c;margin-bottom:20px;">
									<?php echo number_format_i18n( $stats['checks_spam'] ); ?>
                                </div>
                                <div style="font-size:16px; color:#991b1b; margin-top:8px;">
									<?php _e( 'Spam Blocked', 'captcha-for-contact-form-7' ); ?>
                                </div>
                            </div>

                            <!-- Clean Submissions -->
                            <div style="background:#dcfce7; border-radius:12px; padding:20px; text-align:center;">
                                <div style="font-size:48px; font-weight:700; color:#166534;margin-bottom:20px;">
									<?php echo number_format_i18n( $stats['checks_clean'] ); ?>
                                </div>
                                <div style="font-size:16px; color:#14532d; margin-top:8px;">
									<?php _e( 'Clean Submissions', 'captcha-for-contact-form-7' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-sidebar">
                        <div class="section">
                            <h2>
								<?php _e( 'Notice', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p style="margin-top:20px;">
		                        <?php _e( 'These counters show the overall protection activity since the plugin has been activated.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
			<?php

		}

		/**
		 * Render the license subpage content
		 */
		protected function the_content( $slug, $page, $settings ) {
			$this->render_statistics();

			$settings = $settings['global'];
			?>
            <div class="section-container">
                <h2>
					<?php _e( 'Captcha Protection', 'captcha-for-contact-form-7' ); ?>
                </h2>
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
                                    <div class="option" style="padding:0px 10px;">
                                        <div class="label">
                                            <label
                                                    for="protection_method_honey"><strong><?php _e( 'Protection Method', 'captcha-for-contact-form-7' ); ?></strong></label>
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
                            <h2 style="margin-top:40px;">
								<?php _e( 'Protection Method', 'captcha-for-contact-form-7' ); ?>
                            </h2>
                            <p>
                                <strong>
									<?php _e( 'Honeypot', 'captcha-for-contact-form-7' ); ?>
                                </strong>
                            </p>
                            <p>
								<?php _e( 'This is a hidden field that is not visible to humans, but visible for bots. It is used as a trap to catch spam bots.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
                                <strong>
									<?php _e( 'Arithmetic', 'captcha-for-contact-form-7' ); ?>
                                </strong>
                            </p>
                            <p>
								<?php _e( 'In this method, website visitors are required to solve a simple arithmetic problem before they can submit the form.', 'captcha-for-contact-form-7' ); ?>
                            </p>
                            <p>
                                <strong>
									<?php _e( 'Image', 'captcha-for-contact-form-7' ); ?>
                                </strong>
                            </p>
                            <p>
								<?php _e( 'In this method, the user is presented with an image containing distorted text they must identify. The User is then required to enter the characters visible in the image to submit the form.', 'captcha-for-contact-form-7' ); ?>
                            </p>
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
