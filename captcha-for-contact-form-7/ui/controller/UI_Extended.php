<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\core\BaseController;
	use f12_cf7_captcha\core\Compatibility;
	use f12_cf7_captcha\core\Log_WordPress;
	use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
	use f12_cf7_captcha\core\protection\ip\IPBan;
	use f12_cf7_captcha\core\protection\ip\IPLog;
	use f12_cf7_captcha\core\protection\Protection;
	use f12_cf7_captcha\core\timer\Timer_Controller;
	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page_Form;
	use forge12\contactform7\CF7Captcha\CaptchaCleaner;
	use forge12\contactform7\CF7Captcha\Messages;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UI_Extended
	 */
	class UI_Extended extends UI_Page_Form {
		public function __construct( UI_Manager $UI_Manager ) {
			// Rufe den Konstruktor der Elternklasse auf.
			// Die Parameter sind:
			// 1. $UI_Manager: Die Instanz des UI-Managers.
			// 2. 'f12-cf7-captcha': Der eindeutige Domain-Name für diese UI-Seite.
			// 3. 'Dashboard': Der angezeigte Name der Seite im UI-Menü.
			// 4. 0: Die Priorität oder Reihenfolge im Menü (0 bedeutet ganz oben).
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-extended', 'Extended', 1 );

			$this->get_logger()->info( 'Konstruktor gestartet.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Füge einen Filter-Hook hinzu, der vor dem Speichern der UI-Einstellungen ausgelöst wird.
			// Der Hook-Tag wird dynamisch aus dem UI-Manager-Domain-Namen und dem eigenen Domain-Namen erstellt.
			add_filter(
				$UI_Manager->get_domain() . '_ui_f12-cf7-captcha-extended_before_on_save',
				array( $this, 'maybe_clean' ), // Rufe die Methode maybe_clean() dieser Klasse auf.
				10, // Priorität des Filters (10 ist Standard).
				1  // Anzahl der übergebenen Argumente (hier 1).
			);
			$this->get_logger()->debug( 'Filter "ui_f12-cf7-captcha-extended_before_on_save" hinzugefügt.', [
				'hook' => $UI_Manager->get_domain() . '_ui_f12-cf7-captcha_before-extended_on_save'
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
				// Timer-Schutz
				'protection_time_enable'                   => 1,
				'protection_time_field_name'               => 'f12_timer',
				'protection_time_ms'                       => 500,

				// Mehrfach-Submission-Schutz
				'protection_multiple_submission_enable'    => 1,

				// IP-basiertes Rate-Limiting
				'protection_ip_enable'                     => 0,
				'protection_ip_max_retries'                => 3,
				'protection_ip_max_retries_period'         => 300,
				'protection_ip_period_between_submits'     => 60,
				'protection_ip_block_time'                 => 3600,

				// Sonstige Regeln und Whitelists
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

				// Browser- und JavaScript-Erkennung
				'protection_browser_enable'                => 1,
				'protection_javascript_enable'             => 1,

				// Whitelists
				'protection_whitelist_emails'              => '',
				'protection_whitelist_ips'                 => '',
				'protection_whitelist_role_admin'          => 1,
				'protection_whitelist_role_logged_in'      => 1,
				'protection_blacklist_ips'                 => '',

				// Telemetry
				'telemetry'                                => 1,
			];

			// Füge die Standardeinstellungen unter dem Schlüssel 'global' zum übergebenen Array hinzu.
			if ( !isset($settings['global']) || ! is_array( $settings['global'] ) ) {
				$settings['global'] = [];
			}
			$settings['global'] = array_merge( $settings['global'], $default_global_settings );

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

			$Controller    = CF7Captcha::get_instance();
			$Compatibility = $Controller->get_modul( 'compatibility' );
			$Components    = $Compatibility->get_components();

			$this->get_logger()->debug( 'Überprüfe und speichere den Status der einzelnen Komponenten.' );
			foreach ( $Components as $Component ) {
				if ( ! isset( $Component['object'] ) ) {
					$this->get_logger()->warning( 'Komponente wurde nicht initialisiert und wird übersprungen.', [ 'component' => $Component ] );
					continue;
				}

				$Base_Controller = $Component['object'];
				$field_name      = sprintf( 'protection_%s_enable', $Base_Controller->get_id() );

				// Setze den Aktivierungsstatus basierend auf dem POST-Wert.
				$status                            = isset( $_POST[ $field_name ] ) ? 1 : 0;
				$settings['global'][ $field_name ] = $status;

				$this->get_logger()->debug( 'Status für Komponente gespeichert.', [
					'component_id' => $Base_Controller->get_id(),
					'status'       => $status
				] );
			}

			// Eine Liste der Optionen, deren Wert auf 0 gesetzt werden soll, wenn sie nicht im POST-Request vorhanden sind.
			$options_to_zero = [
				'protection_time_enable',
				'protection_multiple_submission_enable',
				'protection_ip_enable',
				'protection_log_enable',
				'protection_rules_url_enable',
				'protection_rules_url_limit',
				// Dieser Wert sollte als Integer behandelt werden, was sanitize_text_field tut
				'protection_rules_blacklist_enable',
				'protection_rules_blacklist_greedy',
				'protection_rules_bbcode_enable',
				'protection_browser_enable',
				'protection_javascript_enable',
				'protection_captcha_template',
				// Dieser Wert sollte als Integer behandelt werden
                'telemetry',
				'protection_whitelist_role_admin',
				'protection_whitelist_role_logged_in',
			];

			$this->get_logger()->debug( 'Verarbeite alle POST-Werte und saniere sie.' );
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'protection_' ) === 0 || in_array( $key, [ 'telemetry' ], true ) ) {
					if ( is_array( $value ) ) {
						$settings['global'][ $key ] = array_map( 'sanitize_text_field', $value );
					} else {
						// Textareas speziell behandeln
						if ( in_array( $key, [
							'protection_rules_blacklist_value',
							'protection_whitelist_emails',
							'protection_whitelist_ips',
							'protection_blacklist_ips'
						], true ) ) {
							$settings['global'][ $key ] = sanitize_textarea_field( $value );
						} else {
							$settings['global'][ $key ] = sanitize_text_field( $value );
						}
					}
					$this->get_logger()->debug( 'Neues Feld übernommen oder bestehendes aktualisiert.', [ 'key' => $key ] );
				}
			}

			$settings['global']['telemetry'] = ( isset( $_POST['telemetry'] ) && (int) $_POST['telemetry'] === 1 ) ? 1 : 0;
			$this->get_logger()->debug( 'Telemetry-Einstellung aktualisiert.', [ 'telemetry' => $settings['global']['telemetry'] ] );

			// Bearbeite die Blacklist-Werte
			$blacklist = $settings['global']['protection_rules_blacklist_value'] ?? '';
			// Setze den Wert im Einstellungsarray auf einen leeren String, da er separat gespeichert wird
			$settings['global']['protection_rules_blacklist_value'] = '';

			if ( ! empty( $blacklist ) ) {
				// Speichere die Blacklist-Werte in der WordPress-Option 'disallowed_keys'.
				update_option( 'disallowed_keys', $blacklist );
				$this->get_logger()->info( 'Blacklist-Werte erfolgreich in der Datenbank-Option "disallowed_keys" gespeichert.' );
			} else {
				// Lösche die Option, wenn die Blacklist leer ist.
				delete_option( 'disallowed_keys' );
				$this->get_logger()->info( 'Blacklist-Werte waren leer, Option "disallowed_keys" wurde gelöscht.' );
			}

			// Nachträglich fehlende Checkbox-Werte auf 0 setzen
			foreach ( $options_to_zero as $opt ) {
				if ( ! isset( $_POST[ $opt ] ) ) {
					$settings['global'][ $opt ] = 0;
					$this->get_logger()->debug( 'Nicht gesetztes Feld auf 0 zurückgesetzt.', [ 'key' => $opt ] );
				}
			}

			$this->get_logger()->info( 'Speichervorgang für die globalen Einstellungen abgeschlossen.' );

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

									$is_checked = (
										! isset( $settings[ $field_name ] ) || $settings[ $field_name ] == 1
									) ? 'checked="checked"' : '';


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
									$IP_Ban  = new IPBan( $this->UI_Manager->get_logger() );
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
									$IP_Log  = new IPLog( $this->UI_Manager->get_logger() );
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
                                <label><strong><?php _e( 'Your Current IP Address', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p><?php echo esc_html( $_SERVER['REMOTE_ADDR'] ); ?></p>
                            </div>
                            <div class="input">
                                <textarea
                                        rows="10"
                                        id="protection_whitelist_ips"
                                        name="protection_whitelist_ips"
                                ><?php echo esc_textarea( $settings['protection_whitelist_ips'] ); ?></textarea>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label for="protection_blacklist_ips"><strong><?php _e( 'Backlist IP Addresses', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p><?php _e( 'Add IP addresses that should be blocked automatically, one per line.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <textarea
                                        rows="10"
                                        id="protection_blacklist_ips"
                                        name="protection_blacklist_ips"
                                ><?php echo esc_textarea( $settings['protection_blacklist_ips'] ); ?></textarea>
                            </div>
                        </div>

                        <div class="option">
                            <div class="label">
                                <label for="protection_whitelist_role_admin"><strong><?php _e( 'Whitelist for Administrator Role', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable this option to automatically whitelist all administrators while they are logged into the website.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_whitelist_role_admin';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Activate Whitelist for Administrator Role', 'captcha-for-contact-form-7' );
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
                                <label for="protection_whitelist_role_logged_in"><strong><?php _e( 'Whitelist for Logged-In Users', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;"><?php _e( 'Enable this option to automatically whitelist all Logged-in Users.', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- SEPARATOR -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'protection_whitelist_role_logged_in';
											$is_checked = $settings[ $field_name ] == 1 ? 'checked="checked"' : '';
											$name       = __( 'Activate Whitelist for Logged-In Users', 'captcha-for-contact-form-7' );
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
                    </div>
                </div>
            </div>

            <div class="section-container">
                <!-- Telemetry Section -->
                <h3><?php _e( 'Telemetry', 'captcha-for-contact-form-7' ); ?></h3>
                <div class="section-wrapper">
                    <div class="section">
                        <div class="option">
                            <div class="label">
                                <label for="telemetry"><strong><?php _e( 'Telemetry', 'captcha-for-contact-form-7' ); ?></strong></label>
                                <p style="padding-right:20px;">
									<?php _e( 'Enable this option to allow anonymous telemetry data to be sent. This helps us improve and develop the plugin.', 'captcha-for-contact-form-7' ); ?>
                                </p>
                            </div>
                            <div class="input">
                                <div class="toggle-item-wrapper">
                                    <!-- TOGGLE -->
                                    <div class="f12-checkbox-toggle">
                                        <div class="toggle-container">
											<?php
											$field_name = 'telemetry';
											// Default = aktiv (1), nur wenn explizit 0 → deaktiviert
											$is_checked = ( ( $settings[ $field_name ] ?? 1 ) == 1 ) ? 'checked="checked"' : '';
											$name       = __( 'Enable Telemetry', 'captcha-for-contact-form-7' );

											echo sprintf(
												'<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>',
												esc_attr( $field_name ),
												esc_attr( $field_name ),
												$is_checked
											);
											?>
                                            <label for="<?php esc_attr_e( $field_name ); ?>"
                                                   class="toggle-label"></label>
                                        </div>
                                        <label for="<?php esc_attr_e( $field_name ); ?>">
											<?php echo esc_html( $name ); ?>
                                        </label>
                                        <label class="overlay" for="<?php esc_attr_e( $field_name ); ?>"></label>
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
