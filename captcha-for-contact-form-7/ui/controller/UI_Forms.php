<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\core\settings\Form_Discovery;
	use f12_cf7_captcha\core\settings\Override_Panel_Renderer;
	use f12_cf7_captcha\core\settings\Settings_Resolver;
	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page_Form;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class UI_Forms extends UI_Page_Form {

		private ?Form_Discovery $discovery = null;
		private ?Settings_Resolver $resolver = null;

		public function __construct( UI_Manager $UI_Manager ) {
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-forms', 'Forms', 3 );
		}

		private function get_discovery(): Form_Discovery {
			if ( $this->discovery === null ) {
				$this->discovery = new Form_Discovery();
			}

			return $this->discovery;
		}

		private function get_resolver(): Settings_Resolver {
			if ( $this->resolver === null ) {
				$this->resolver = new Settings_Resolver();
			}

			return $this->resolver;
		}

		/**
		 * Provide default settings for this page (none needed).
		 */
		public function get_settings( $settings ): array {
			return $settings;
		}

		/**
		 * No POST-based saving — everything via REST API.
		 */
		protected function on_save( $settings ): array {
			return $settings;
		}

		/**
		 * Render page content: single flat view with form list + slide-in panels.
		 */
		protected function the_content( $slug, $page, $settings ): void {
			$this->hide_submit_button( true );

			$integrations    = $this->get_discovery()->get_integrations();
			$global_settings = $settings['global'] ?? [];
			$resolver        = $this->get_resolver();

			?>
			<div class="section-container">
				<h2><?php esc_html_e( 'Form Settings', 'captcha-for-contact-form-7' ); ?></h2>
				<div class="section-wrapper">
					<div class="section advanced">
						<?php
						$has_any_forms = false;

						foreach ( $integrations as $integration ) {
							if ( ! $integration['installed'] ) {
								continue;
							}

							$int_id   = $integration['id'];
							$int_name = $integration['name'];

							// Integration-level overrides
							$int_overrides      = $resolver->get_integration_overrides( $int_id );
							$int_enabled        = ! empty( $int_overrides['_enabled'] );
							$int_override_count = 0;
							if ( $int_enabled ) {
								foreach ( $int_overrides as $k => $v ) {
									if ( $k !== '_enabled' ) {
										$int_override_count ++;
									}
								}
							}

							// Discover forms
							$has_forms = $integration['has_forms'];
							$forms     = $has_forms ? $this->get_discovery()->get_forms( $int_id ) : [];

							if ( empty( $forms ) && ! $has_forms ) {
								// System-level integration: single row in its own option block
								$has_any_forms = true;
								?>
								<div class="option">
									<div class="label">
										<label><strong><?php echo esc_html( $int_name ); ?></strong></label>
									</div>
									<div class="input">
										<div class="f12-forms-integration-row">
											<div>
												<strong><?php echo esc_html( $int_name ); ?></strong>
												<?php if ( $int_enabled && $int_override_count > 0 ) : ?>
													<span class="f12-forms-badge f12-forms-badge--active">
														<?php
														echo esc_html( sprintf(
															_n( '%d Override', '%d Overrides', $int_override_count, 'captcha-for-contact-form-7' ),
															$int_override_count
														) );
														?>
													</span>
												<?php else : ?>
													<span class="f12-forms-badge f12-forms-badge--global">
														<?php esc_html_e( 'Global Settings', 'captcha-for-contact-form-7' ); ?>
													</span>
												<?php endif; ?>
											</div>
											<a href="#" class="button button-small f12-configure-btn"
											   data-panel="<?php echo esc_attr( 'f12-panel-integration-' . $int_id ); ?>">
												<?php esc_html_e( 'Configure', 'captcha-for-contact-form-7' ); ?>
											</a>
										</div>
									</div>
								</div>
								<?php
								continue;
							}

							if ( empty( $forms ) ) {
								continue;
							}

							$has_any_forms = true;
							?>
							<div class="option">
								<div class="label">
									<label><strong><?php echo esc_html( $int_name ); ?></strong></label>
									<p style="padding-right:20px;">
										<?php echo esc_html( sprintf(
											/* translators: %d: Number of forms */
											_n( '%d form found', '%d forms found', count( $forms ), 'captcha-for-contact-form-7' ),
											count( $forms )
										) ); ?>
									</p>
								</div>
								<div class="input">
									<div class="f12-forms-integration-row" style="background:#f1f5f9;">
										<div>
											<strong><?php echo esc_html( sprintf(
												/* translators: %s: Integration name */
												__( 'All %s Forms', 'captcha-for-contact-form-7' ),
												$int_name
											) ); ?></strong>
											<?php if ( $int_enabled && $int_override_count > 0 ) : ?>
												<span class="f12-forms-badge f12-forms-badge--active">
													<?php
													echo esc_html( sprintf(
														_n( '%d Override', '%d Overrides', $int_override_count, 'captcha-for-contact-form-7' ),
														$int_override_count
													) );
													?>
												</span>
											<?php else : ?>
												<span class="f12-forms-badge f12-forms-badge--global">
													<?php esc_html_e( 'Global Settings', 'captcha-for-contact-form-7' ); ?>
												</span>
											<?php endif; ?>
										</div>
										<a href="#" class="button button-small f12-configure-btn"
										   data-panel="<?php echo esc_attr( 'f12-panel-integration-' . $int_id ); ?>">
											<?php esc_html_e( 'Configure', 'captcha-for-contact-form-7' ); ?>
										</a>
									</div>

									<?php foreach ( $forms as $form ) :
										$form_id             = $form['id'];
										$form_title          = $form['title'];
										$form_overrides      = $resolver->get_form_overrides( $int_id, $form_id );
										$form_enabled        = ! empty( $form_overrides['_enabled'] );
										$form_override_count = 0;
										if ( $form_enabled ) {
											foreach ( $form_overrides as $k => $v ) {
												if ( $k !== '_enabled' ) {
													$form_override_count ++;
												}
											}
										}
										?>
										<div class="f12-forms-integration-row">
											<div>
												<strong><?php echo esc_html( $form_title ); ?></strong>
												<span style="color:#94a3b8; margin-left:5px;">(ID: <?php echo esc_html( $form_id ); ?>)</span>
												<?php if ( $form_enabled && $form_override_count > 0 ) : ?>
													<span class="f12-forms-badge f12-forms-badge--active">
														<?php
														echo esc_html( sprintf(
															_n( '%d Override', '%d Overrides', $form_override_count, 'captcha-for-contact-form-7' ),
															$form_override_count
														) );
														?>
													</span>
												<?php else : ?>
													<span class="f12-forms-badge f12-forms-badge--global">
														<?php esc_html_e( 'Inherited', 'captcha-for-contact-form-7' ); ?>
													</span>
												<?php endif; ?>
											</div>
											<a href="#" class="button button-small f12-configure-btn"
											   data-panel="<?php echo esc_attr( 'f12-panel-form-' . $int_id . '-' . $form_id ); ?>">
												<?php esc_html_e( 'Configure', 'captcha-for-contact-form-7' ); ?>
											</a>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
							<?php
						}

						if ( ! $has_any_forms ) {
							?>
							<div class="option">
								<div class="label">
									<label><strong><?php esc_html_e( 'No Forms', 'captcha-for-contact-form-7' ); ?></strong></label>
								</div>
								<div class="input">
									<p><?php esc_html_e( 'No installed integrations with forms found.', 'captcha-for-contact-form-7' ); ?></p>
								</div>
							</div>
							<?php
						}
						?>
					</div>
					<div class="section-sidebar">
						<div class="section">
							<h2><?php esc_html_e( 'Form Settings', 'captcha-for-contact-form-7' ); ?></h2>
							<p><?php esc_html_e( 'This page allows you to override protection settings for specific integrations or individual forms.', 'captcha-for-contact-form-7' ); ?></p>
							<h3><?php esc_html_e( 'Inheritance', 'captcha-for-contact-form-7' ); ?></h3>
							<p><?php esc_html_e( 'Settings are inherited from the parent level. The hierarchy is: Global > Integration > Form. You only need to set values where you want to deviate from the parent.', 'captcha-for-contact-form-7' ); ?></p>
							<h3><?php esc_html_e( 'How to use', 'captcha-for-contact-form-7' ); ?></h3>
							<p><?php esc_html_e( 'Click "Configure" next to an integration or form to open the override panel. Enable individual settings and change the values you want to override.', 'captcha-for-contact-form-7' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<?php

			// Render all hidden panels
			foreach ( $integrations as $integration ) {
				if ( ! $integration['installed'] ) {
					continue;
				}

				$int_id        = $integration['id'];
				$int_name      = $integration['name'];
				$int_overrides = $resolver->get_integration_overrides( $int_id );

				// Integration panel
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from Override_Panel_Renderer is properly escaped internally
				echo Override_Panel_Renderer::render_integration_panel( $int_id, $int_name, $global_settings, $int_overrides );

				// Form panels
				if ( $integration['has_forms'] ) {
					$forms = $this->get_discovery()->get_forms( $int_id );
					foreach ( $forms as $form ) {
						$form_id        = $form['id'];
						$form_title     = $form['title'];
						$form_overrides = $resolver->get_form_overrides( $int_id, $form_id );
						$effective      = $resolver->resolve( $global_settings, $int_id );
						$sources        = $resolver->get_sources( $global_settings, $int_id, $form_id );

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo Override_Panel_Renderer::render_form_panel( $int_id, $form_id, $form_title, $int_name, $effective, $form_overrides, $sources );
					}
				}
			}

			// Render slide-in container
			Override_Panel_Renderer::render_slide_in_container();

			// Enqueue JS and localize data
			wp_enqueue_script(
				'f12-forms-admin',
				$this->get_ui_manager()->get_plugin_dir_url() . 'ui/assets/f12-forms-admin.js',
				[],
				'1.2',
				true
			);
			wp_localize_script( 'f12-forms-admin', 'f12FormsAdmin', [
				'restUrl'     => esc_url_raw( rest_url( 'f12-cf7-captcha/v1/' ) ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'saving'      => __( 'Saving...', 'captcha-for-contact-form-7' ),
				'saveLabel'   => __( 'Save', 'captcha-for-contact-form-7' ),
				'msgSuccess'  => __( 'Settings saved.', 'captcha-for-contact-form-7' ),
				'msgError'    => __( 'Error saving settings.', 'captcha-for-contact-form-7' ),
				'badgeGlobal' => __( 'Global Settings', 'captcha-for-contact-form-7' ),
			] );
		}

		/**
		 * Render the page-level sidebar (empty — content is inline).
		 */
		protected function the_sidebar( $slug, $page ): void {
		}
	}
}
