<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	abstract class UI_Page_Form extends UI_Page {
		/**
		 * define if the button for the submit should be displayed or not.
		 * if hidden, the wp_nonce will also be removed. Ensure you handle
		 * the save process on your own. The onSave function will still be called
		 *
		 * @var bool
		 */
		private $hide_submit_button = false;

		/**
		 * @return mixed
		 */
		protected function maybe_save(): void {
			$this->get_logger()->info( 'Starting the "Maybe Save" process to save settings if the nonce is valid.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Verify the nonce (a WordPress security measure) to ensure the authenticity of the request.
			$nonce_name   = $this->get_domain() . '_nonce';
			$nonce_action = $this->get_domain() . '_action';

			if ( isset( $_POST[ $nonce_name ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
				$this->get_logger()->info( 'Nonce validation successful. Starting the save process.' );

				$settings = [];

				// Load the default settings through a filter.
				// Other components can insert their default settings into the array here.
				$settings = apply_filters( $this->get_domain() . '_get_settings', $settings );
				$this->get_logger()->debug( 'Settings retrieved via the "_get_settings" filter.' );

				// Allow developers to perform actions before saving the settings.
				$settings = apply_filters( $this->get_domain() . '_ui_' . $this->slug . '_before_on_save', $settings );
				$this->get_logger()->debug( 'Filter "_before_on_save" applied.', [
					'domain' => $this->get_domain(),
					'slug'   => $this->get_slug(),
					'filter' => $this->get_domain() . '_ui_' . $this->slug . '_before_on_save',
					'plugin' => 'f12-cf7-captcha'
				] );

				// Check if the settings should actually be saved.
				// A developer can use this filter to disable saving,
				// e.g., if a custom button should trigger a different action.
				$do_save = apply_filters( $this->get_domain() . '_ui_do_save_settings', true );

				if ( $do_save ) {
					$this->get_logger()->info( 'Saving settings is allowed. Executing on_save().' );

					// Execute the specific on_save logic of the respective UI page.
					$settings = $this->on_save( $settings );

					// Save the final settings to the WordPress database.
					update_option( $this->get_domain() . '-settings', $settings );

					// Add a success message for the user.
					$this->get_ui_manager()->get_ui_message()->add( __( 'Settings updated', 'captcha-for-contact-form-7' ), 'success' );

					$this->get_logger()->info( 'Settings successfully updated in the database.' );
				} else {
					$this->get_logger()->info( 'Saving settings was suppressed by the "_ui_do_save_settings" filter.' );
				}

				// Allow developers to perform actions after saving the settings.
				$settings = apply_filters( $this->get_domain() . '_ui_' . $this->slug . '_after_on_save', $settings );
				$this->get_logger()->debug( 'Filter "_after_on_save" applied.' );

			} else {
				$this->get_logger()->warning( 'Nonce validation failed or nonce not present. Save process aborted.' );
			}
		}

		/**
		 * Option to hide the submit button
		 *
		 * @param bool $hide
		 *
		 * @return void
		 */
		protected function hide_submit_button( bool $hide ): void {
			$this->get_logger()->info( 'Setting whether the submit button should be hidden.', [
				'class'       => __CLASS__,
				'method'      => __METHOD__,
				'hide_button' => $hide,
			] );

			// Set the private/protected property that stores the status.
			$this->hide_submit_button = $hide;

			$this->get_logger()->debug( 'Submit button status successfully set to ' . ( $hide ? 'hidden' : 'visible' ) . '.' );
		}

		/**
		 * Returns true if the button should be hidden.
		 *
		 * @return bool
		 */
		protected function is_submit_button_hidden(): bool {
			$this->get_logger()->info( 'Checking if the submit button is hidden.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			$is_hidden = $this->hide_submit_button;

			$this->get_logger()->debug( 'Submit button status: ' . ( $is_hidden ? 'hidden' : 'visible' ) );

			return $is_hidden;
		}

		/**
		 * Update the settings and return them
		 *
		 * @param $settings
		 *
		 * @return array
		 */
		protected abstract function on_save( $settings );

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function render_content( string $slug, string $page ): void {
			$this->get_logger()->info( 'Starting the rendering of the page content.', [
				'class'          => __CLASS__,
				'method'         => __METHOD__,
				'requested_slug' => $slug,
				'page_slug'      => $page,
				'expected_slug'  => $this->get_slug(),
			] );

			// Check if the requested page slug matches the current page.
			if ( $this->get_slug() !== $page ) {
				$this->get_logger()->debug( 'The requested page does not match the current one. Rendering will be skipped.' );

				return;
			}

			$this->get_logger()->info( 'Rendering process started. Executing maybe_save().' );

			// Attempt to save the settings. This method independently checks if a POST request is present and if the nonce is valid.
			$this->maybe_save();

			// Retrieve the global settings via a filter.
			$settings = apply_filters( $this->get_domain() . '_get_settings', [] );
			$this->get_logger()->debug( 'Settings retrieved via filter.' );

			// Render the UI messages (e.g., success or error messages).
			$this->get_ui_manager()->get_ui_message()->render();

			?>
            <div class="box">
                <form action="" method="post">
					<?php
					// Trigger a hook that is placed before the main content.
					do_action( $this->get_domain() . '_ui_' . $page . '_before_content', $settings );
					$this->get_logger()->debug( 'Hook "before_content" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_before_content' ] );

					// Render the actual page content.
					$this->the_content( $slug, $page, $settings );

					// Trigger a hook that is placed after the main content.
					do_action( $this->get_domain() . '_ui_' . $page . '_after_content', $settings );
					$this->get_logger()->debug( 'Hook "after_content" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_after_content' ] );
					?>

					<?php
					// Show the "Save" button and nonce field only if it has not been explicitly hidden.
					if ( ! $this->is_submit_button_hidden() ):
						// Add the nonce field to protect the request from Cross-Site Request Forgery (CSRF).
						wp_nonce_field( $this->get_domain() . '_action', $this->get_domain() . '_nonce' );
						?>
                        <input type="submit" name="<?php echo esc_attr( $this->get_domain() ); ?>-settings-submit"
                               class="button"
                               value=" <?php echo esc_attr( __( 'Save', 'captcha-for-contact-form-7' ) ); ?>"/>
					<?php endif; ?>
                </form>
            </div>
			<?php
			$this->get_logger()->info( 'Page content rendering completed.' );
		}
	}
}