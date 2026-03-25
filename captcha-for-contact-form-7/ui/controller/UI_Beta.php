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
	use f12_cf7_captcha\core\log\AuditLog;
	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page_Form;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UI_Beta
	 */
	class UI_Beta extends UI_Page_Form {

		/**
		 * Validate the API key against the SilentShield API.
		 * Caches the result as a transient for 12 hours.
		 *
		 * @param string $api_key
		 * @param bool $force_refresh Skip transient cache.
		 *
		 * @return string 'valid', 'invalid', 'empty', or 'error'
		 */
		private function validate_api_key( string $api_key, bool $force_refresh = false ): string {
			if ( $api_key === '' ) {
				return 'empty';
			}

			$transient_key = 'f12_beta_api_key_status_' . md5( $api_key );

			if ( ! $force_refresh ) {
				$cached = get_transient( $transient_key );
				if ( $cached !== false ) {
					return $cached;
				}
			}

			$base_url     = defined( 'F12_CAPTCHA_API_URL' ) ? F12_CAPTCHA_API_URL : 'https://api.silentshield.io/api/v1';
			$api_endpoint = rtrim( $base_url, '/' ) . '/keys/validate';

			$request_body = wp_json_encode( [ 'key' => $api_key ] );

			// Debug: log outgoing request details
			error_log( sprintf(
				'[SilentShield] validate_api_key: endpoint=%s key_prefix=%s key_len=%d body=%s',
				$api_endpoint,
				substr( $api_key, 0, 8 ) . '...',
				strlen( $api_key ),
				$request_body
			) );

			$response = wp_remote_post( $api_endpoint, [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => $request_body,
				'timeout' => 10,
			] );

			if ( is_wp_error( $response ) ) {
				$status = 'error';

				error_log( sprintf(
					'[SilentShield] validate_api_key: WP_Error=%s',
					$response->get_error_message()
				) );

				AuditLog::log(
					AuditLog::TYPE_API,
					'API_KEY_VALIDATION_UNREACHABLE',
					AuditLog::SEVERITY_WARNING,
					sprintf( 'API key validation failed: endpoint unreachable (%s)', $response->get_error_message() ),
					[ 'endpoint' => $api_endpoint, 'error' => $response->get_error_message() ]
				);
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				error_log( sprintf(
					'[SilentShield] validate_api_key: http_code=%s body=%s',
					$code,
					$body
				) );

				if ( $code === 200 && ! empty( $data['valid'] ) ) {
					$status = 'valid';
				} else {
					$status = 'invalid';

					AuditLog::log(
						AuditLog::TYPE_API,
						'API_KEY_INVALID',
						AuditLog::SEVERITY_WARNING,
						'API key validation returned invalid',
						[
							'http_code' => $code,
							'reason'    => $data['reason'] ?? 'unknown',
							'body'      => $body,
						]
					);
				}
			}

			set_transient( $transient_key, $status, 12 * HOUR_IN_SECONDS );

			return $status;
		}

		/**
		 * Get the current trial status.
		 *
		 * @return array{active: bool, expired: bool, days_left: int, expires_at: string}
		 */
		private function get_trial_status(): array {
			$meta = get_option( 'f12_cf7_captcha_trial_meta', [] );

			if ( empty( $meta ) || empty( $meta['expires_at'] ) ) {
				return [
					'active'     => false,
					'expired'    => false,
					'days_left'  => 0,
					'expires_at' => '',
				];
			}

			$expires   = strtotime( $meta['expires_at'] );
			$now       = time();
			$days_left = max( 0, (int) ceil( ( $expires - $now ) / DAY_IN_SECONDS ) );

			return [
				'active'     => $days_left > 0,
				'expired'    => $days_left === 0 && ! empty( $meta['activated_at'] ),
				'days_left'  => $days_left,
				'expires_at' => $meta['expires_at'],
			];
		}

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

			// Capture old state before POST processing for audit comparison.
			$old_api_key    = $settings['beta']['beta_captcha_api_key'] ?? '';
			$old_api_enable = (int) ( $settings['beta']['beta_captcha_enable'] ?? 0 );

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

			// Invalidate API key validation cache when key changes.
			if ( ! empty( $settings['beta']['beta_captcha_api_key'] ) ) {
				$this->validate_api_key( $settings['beta']['beta_captcha_api_key'], true );
			}

			// Audit: API key changes
			$new_api_key = $settings['beta']['beta_captcha_api_key'] ?? '';
			if ( $old_api_key !== $new_api_key ) {
				if ( empty( $old_api_key ) && ! empty( $new_api_key ) ) {
					AuditLog::log(
						AuditLog::TYPE_SETTINGS,
						'API_KEY_SET',
						AuditLog::SEVERITY_INFO,
						'SilentShield API key was set',
						[ 'key_prefix' => substr( $new_api_key, 0, 4 ) . '***' ]
					);
				} elseif ( ! empty( $old_api_key ) && empty( $new_api_key ) ) {
					AuditLog::log(
						AuditLog::TYPE_SETTINGS,
						'API_KEY_REMOVED',
						AuditLog::SEVERITY_WARNING,
						'SilentShield API key was removed',
						[ 'old_key_prefix' => substr( $old_api_key, 0, 4 ) . '***' ]
					);
				} else {
					AuditLog::log(
						AuditLog::TYPE_SETTINGS,
						'API_KEY_CHANGED',
						AuditLog::SEVERITY_INFO,
						'SilentShield API key was changed',
						[
							'old_key_prefix' => substr( $old_api_key, 0, 4 ) . '***',
							'new_key_prefix' => substr( $new_api_key, 0, 4 ) . '***',
						]
					);
				}
			}

			// Audit: API mode toggle
			$new_api_enable = (int) ( $settings['beta']['beta_captcha_enable'] ?? 0 );
			if ( $old_api_enable !== $new_api_enable ) {
				AuditLog::log(
					AuditLog::TYPE_SETTINGS,
					$new_api_enable ? 'API_MODE_ENABLED' : 'API_MODE_DISABLED',
					$new_api_enable ? AuditLog::SEVERITY_INFO : AuditLog::SEVERITY_WARNING,
					sprintf( 'SilentShield API mode %s', $new_api_enable ? 'enabled' : 'disabled' ),
					[ 'old' => $old_api_enable, 'new' => $new_api_enable ]
				);
			}

			// Handle shadow mode toggle (stored in global settings group).
			$old_shadow = (int) CF7Captcha::get_instance()->get_settings( 'protection_api_shadow_mode', 'global' );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in admin form submission
			$settings['global']['protection_api_shadow_mode'] = isset( $_POST['protection_api_shadow_mode'] ) ? 1 : 0;
			$new_shadow = (int) $settings['global']['protection_api_shadow_mode'];

			// Audit: Shadow mode toggle
			if ( $old_shadow !== $new_shadow ) {
				AuditLog::log(
					AuditLog::TYPE_SETTINGS,
					$new_shadow ? 'SHADOW_MODE_ENABLED' : 'SHADOW_MODE_DISABLED',
					AuditLog::SEVERITY_INFO,
					sprintf( 'Shadow Mode %s', $new_shadow ? 'enabled' : 'disabled' ),
					[ 'old' => $old_shadow, 'new' => $new_shadow ]
				);
			}

			$this->get_logger()->info( 'Save process for global settings completed.' );

			return $settings;
		}

		/**
		 * Render the license subpage content
		 */
		protected function the_content( $slug, $page, $settings ) {

			$settings     = $settings['beta'];
			$api_key      = $settings['beta_captcha_api_key'] ?? '';
			$api_status   = $this->validate_api_key( $api_key );
			$trial_status = $this->get_trial_status();
			$show_trial   = empty( $api_key ) && ! $trial_status['expired'];

			// Audit: log trial expiration once
			if ( $trial_status['expired'] && ! get_option( 'f12_cf7_captcha_trial_expired_logged', false ) ) {
				AuditLog::log(
					AuditLog::TYPE_TRIAL,
					'TRIAL_EXPIRED',
					AuditLog::SEVERITY_WARNING,
					'SilentShield API trial has expired — protection reverted to standalone mode',
					[ 'expires_at' => $trial_status['expires_at'] ]
				);
				update_option( 'f12_cf7_captcha_trial_expired_logged', true, false );
			}
			?>

			<?php if ( $show_trial ) : ?>
				<!-- One-Click Trial Activation Banner -->
				<div id="f12-trial-banner" class="section-container" style="margin-bottom:20px;">
					<div style="background:linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); border-radius:12px; padding:32px; color:#fff; position:relative; overflow:hidden;">
						<div style="position:absolute; top:-20px; right:-20px; width:160px; height:160px; background:rgba(255,255,255,0.05); border-radius:50%;"></div>
						<div style="position:absolute; bottom:-30px; right:60px; width:100px; height:100px; background:rgba(255,255,255,0.03); border-radius:50%;"></div>

						<div style="position:relative; z-index:1;">
							<div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
								<span style="font-size:24px;">&#128737;</span>
								<h2 style="margin:0; font-size:20px; font-weight:700; color:#fff;">
									<?php esc_html_e( 'Try SilentShield API — Free for 14 Days', 'captcha-for-contact-form-7' ); ?>
								</h2>
							</div>
							<p style="margin:0 0 8px; font-size:14px; color:rgba(255,255,255,0.85); max-width:600px; line-height:1.6;">
								<?php esc_html_e( 'Upgrade your spam protection with AI behavior analysis, browser fingerprinting, and adaptive challenges. No credit card required.', 'captcha-for-contact-form-7' ); ?>
							</p>

							<div style="display:flex; gap:16px; flex-wrap:wrap; margin:20px 0 24px;">
								<div style="display:flex; align-items:center; gap:6px; font-size:13px; color:rgba(255,255,255,0.9);">
									<span style="color:#34d399;">&#10003;</span>
									<?php esc_html_e( '10,000 requests included', 'captcha-for-contact-form-7' ); ?>
								</div>
								<div style="display:flex; align-items:center; gap:6px; font-size:13px; color:rgba(255,255,255,0.9);">
									<span style="color:#34d399;">&#10003;</span>
									<?php esc_html_e( 'One-click activation', 'captcha-for-contact-form-7' ); ?>
								</div>
								<div style="display:flex; align-items:center; gap:6px; font-size:13px; color:rgba(255,255,255,0.9);">
									<span style="color:#34d399;">&#10003;</span>
									<?php esc_html_e( 'Auto-fallback to standalone after trial', 'captcha-for-contact-form-7' ); ?>
								</div>
							</div>

							<div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
								<button type="button" id="f12-trial-activate-btn"
									style="background:#fff; color:#1e3a5f; border:none; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.15);"
									onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';"
									onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';">
									<?php esc_html_e( 'Activate Free Trial', 'captcha-for-contact-form-7' ); ?> &rarr;
								</button>
								<span id="f12-trial-status" style="font-size:13px; color:rgba(255,255,255,0.7);"></span>
							</div>
						</div>
					</div>
				</div>

				<script>
				(function() {
					var btn = document.getElementById('f12-trial-activate-btn');
					var statusEl = document.getElementById('f12-trial-status');
					if (!btn) return;

					btn.addEventListener('click', function() {
						btn.disabled = true;
						btn.style.opacity = '0.7';
						btn.textContent = '<?php echo esc_js( __( 'Activating...', 'captcha-for-contact-form-7' ) ); ?>';
						statusEl.textContent = '';

						fetch('<?php echo esc_url( rest_url( 'f12-cf7-captcha/v1/trial/activate' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
							},
							credentials: 'same-origin'
						})
						.then(function(resp) { return resp.json(); })
						.then(function(data) {
							if (data.status === 'success') {
								statusEl.style.color = '#34d399';
								statusEl.textContent = data.message || '<?php echo esc_js( __( 'Trial activated!', 'captcha-for-contact-form-7' ) ); ?>';
								btn.textContent = '<?php echo esc_js( __( 'Activated!', 'captcha-for-contact-form-7' ) ); ?>';
								btn.style.background = '#34d399';
								btn.style.color = '#fff';
								setTimeout(function() { window.location.reload(); }, 2000);
							} else {
								var msg = data.message || '<?php echo esc_js( __( 'Activation failed. Please try again.', 'captcha-for-contact-form-7' ) ); ?>';
								statusEl.style.color = '#fca5a5';
								statusEl.textContent = msg;
								btn.disabled = false;
								btn.style.opacity = '1';
								btn.innerHTML = '<?php echo esc_js( __( 'Activate Free Trial', 'captcha-for-contact-form-7' ) ); ?> &rarr;';
							}
						})
						.catch(function() {
							statusEl.style.color = '#fca5a5';
							statusEl.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'captcha-for-contact-form-7' ) ); ?>';
							btn.disabled = false;
							btn.style.opacity = '1';
							btn.innerHTML = '<?php echo esc_js( __( 'Activate Free Trial', 'captcha-for-contact-form-7' ) ); ?> &rarr;';
						});
					});
				})();
				</script>
			<?php endif; ?>

			<?php if ( $trial_status['active'] ) : ?>
				<!-- Trial Active Banner -->
				<div class="section-container" style="margin-bottom:20px;">
					<div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
						<div>
							<div style="font-size:14px; font-weight:600; color:#065f46;">
								&#128737; <?php esc_html_e( 'SilentShield API Trial Active', 'captcha-for-contact-form-7' ); ?>
							</div>
							<div style="font-size:13px; color:#047857; margin-top:4px;">
								<?php printf(
									/* translators: %d: number of days remaining */
									esc_html__( '%d days remaining — Your forms are protected by AI behavior analysis.', 'captcha-for-contact-form-7' ),
									$trial_status['days_left']
								); ?>
							</div>
						</div>
						<a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=trial-banner&utm_campaign=trial-active' ); ?>"
						   target="_blank"
						   style="display:inline-block; background:#059669; color:#fff; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none;">
							<?php esc_html_e( 'Keep Protection — View Plans', 'captcha-for-contact-form-7' ); ?> &rarr;
						</a>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $trial_status['expired'] ) : ?>
				<!-- Trial Expired Banner -->
				<div class="section-container" style="margin-bottom:20px;">
					<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
						<div>
							<div style="font-size:14px; font-weight:600; color:#991b1b;">
								<?php esc_html_e( 'Your SilentShield API trial has expired', 'captcha-for-contact-form-7' ); ?>
							</div>
							<div style="font-size:13px; color:#b91c1c; margin-top:4px;">
								<?php esc_html_e( 'Your forms are back in standalone mode. Upgrade to keep AI-powered protection.', 'captcha-for-contact-form-7' ); ?>
							</div>
						</div>
						<a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=trial-banner&utm_campaign=trial-expired' ); ?>"
						   target="_blank"
						   style="display:inline-block; background:#dc2626; color:#fff; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none;">
							<?php esc_html_e( 'Upgrade Now', 'captcha-for-contact-form-7' ); ?> &rarr;
						</a>
					</div>
				</div>
			<?php endif; ?>

            <div class="section-container">
                <h2>
					<?php esc_html_e( 'Captcha Protection (v2)', 'captcha-for-contact-form-7' ); ?>
                    <?php
                    $is_production = ! defined( 'F12_CAPTCHA_API_URL' ) || F12_CAPTCHA_API_URL === 'https://api.silentshield.io/api/v1';
                    if ( $is_production ) : ?>
                        <span style="display:inline-block;margin-left:10px;padding:2px 10px;font-size:12px;font-weight:600;border-radius:4px;background:#46b450;color:#fff;vertical-align:middle;">LIVE</span>
                    <?php else : ?>
                        <span style="display:inline-block;margin-left:10px;padding:2px 10px;font-size:12px;font-weight:600;border-radius:4px;background:#f0b849;color:#fff;vertical-align:middle;">STAGING</span>
                    <?php endif; ?>
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
                                            <?php if ( $api_status === 'valid' ) : ?>
                                                <p style="color: #46b450; font-weight: bold;">
                                                    &#10003; <?php esc_html_e( 'API key is valid.', 'captcha-for-contact-form-7' ); ?>
                                                </p>
                                            <?php elseif ( $api_status === 'invalid' ) : ?>
                                                <p style="color: #dc3232; font-weight: bold;">
                                                    &#10007; <?php esc_html_e( 'API key is invalid. Please check your key.', 'captcha-for-contact-form-7' ); ?>
                                                </p>
                                            <?php elseif ( $api_status === 'error' ) : ?>
                                                <p style="color: #f0b849; font-weight: bold;">
                                                    &#9888; <?php esc_html_e( 'Could not verify the API key. The API is currently unreachable.', 'captcha-for-contact-form-7' ); ?>
                                                </p>
                                            <?php elseif ( $api_status === 'empty' ) : ?>
                                                <p style="color: #999;">
                                                    <?php esc_html_e( 'No API key entered.', 'captcha-for-contact-form-7' ); ?>
                                                </p>
                                            <?php endif; ?>
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
			// Shadow Mode toggle — only show when API is NOT active
			$shadow_api_key    = $settings['beta_captcha_api_key'] ?? '';
			$shadow_api_enable = (int) ( $settings['beta_captcha_enable'] ?? 0 );
			$shadow_api_active = $shadow_api_enable && ! empty( $shadow_api_key );

			if ( ! $shadow_api_active ) :
				$shadow_mode_value = (int) CF7Captcha::get_instance()->get_settings( 'protection_api_shadow_mode', 'global' );
			?>
			<div class="section-container" style="margin-top:20px;">
				<h2>
					<?php esc_html_e( 'API Comparison Mode (Shadow Mode)', 'captcha-for-contact-form-7' ); ?>
					<span style="display:inline-block;margin-left:10px;padding:2px 10px;font-size:12px;font-weight:600;border-radius:4px;background:#8b5cf6;color:#fff;vertical-align:middle;"><?php esc_html_e( 'Beta', 'captcha-for-contact-form-7' ); ?></span>
				</h2>
				<div class="section-wrapper">
					<div class="section">
						<div class="option">
							<div class="label">
								<label for="protection_api_shadow_mode"><strong><?php esc_html_e( 'Enable Shadow Mode', 'captcha-for-contact-form-7' ); ?></strong></label>
								<p style="padding-right:20px;">
									<?php esc_html_e(
										'When enabled, the plugin tracks all local protection checks and estimates how many additional bots the SilentShield API would have caught. No data is sent externally — all analysis is local. View results on the Analytics page.',
										'captcha-for-contact-form-7'
									); ?>
								</p>
							</div>
							<div class="input">
								<div class="toggle-item-wrapper">
									<div class="f12-checkbox-toggle">
										<div class="toggle-container">
											<?php
											$shadow_field_name = 'protection_api_shadow_mode';
											$shadow_is_checked = $shadow_mode_value == 1 ? 'checked="checked"' : '';
											echo sprintf(
												'<input name="%s" type="checkbox" value="1" id="%s" class="toggle-button" %s>',
												esc_attr( $shadow_field_name ),
												esc_attr( $shadow_field_name ),
												esc_attr( $shadow_is_checked )
											);
											?>
											<label for="<?php echo esc_attr( $shadow_field_name ); ?>" class="toggle-label"></label>
										</div>
										<label for="<?php echo esc_attr( $shadow_field_name ); ?>">
											<?php esc_html_e( 'API Comparison Mode', 'captcha-for-contact-form-7' ); ?>
											<p>
												<?php esc_html_e(
													'Track local verdicts and show estimated API improvement on the Analytics page. This helps you see how many additional bots the SilentShield API would catch beyond your current rule-based protection.',
													'captcha-for-contact-form-7'
												); ?>
											</p>
										</label>
										<label class="overlay" for="<?php echo esc_attr( $shadow_field_name ); ?>"></label>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>


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

			<?php
			$api_key_sidebar = CF7Captcha::get_instance()->get_settings( 'beta_captcha_api_key', 'beta' );
			if ( empty( $api_key_sidebar ) ) :
			?>
            <div class="box" style="border:1px solid #bfdbfe; background:linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);">
                <div class="section">
                    <h2 style="color:#1e40af;">
						<?php esc_html_e( 'SilentShield API', 'captcha-for-contact-form-7' ); ?>
                    </h2>
                    <p style="font-size:12px; color:#475569; margin:0 0 12px;">
                        <?php esc_html_e( 'AI behavior analysis, browser fingerprinting, and adaptive challenges. Free plan available.', 'captcha-for-contact-form-7' ); ?>
                    </p>
                    <a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=beta-sidebar&utm_campaign=view-plans' ); ?>"
                       target="_blank"
                       style="display:inline-block; background:#2563eb; color:#fff; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">
                        <?php esc_html_e( 'View Plans', 'captcha-for-contact-form-7' ); ?> &rarr;
                    </a>
                </div>
            </div>
			<?php endif; ?>

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
