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
		protected function maybe_save() {
			if ( isset( $_POST[ $this->get_domain() . '_nonce' ] ) && wp_verify_nonce( $_POST[ $this->get_domain() . '_nonce' ], $this->get_domain() . '_action' ) ) {
				$settings = array();

				/**
				 * {domain}_get_settings
				 *
				 * This filter allows developers to overwrite the settings before they are saved into the database
				 *
				 * @param array $settings
				 *
				 * @since 1.0.0
				 */
				$settings = apply_filters( $this->get_domain() . '_get_settings', $settings );

				/**
				 * {domain}_ui_{slug}_before_on_save
				 *
				 * This filter allows developers to run different actions / tasks before the settings are written into the database
				 *
				 * @param array $settings
				 *
				 * @since 1.0.0
				 */
				$settings = apply_filters( $this->get_domain() . '_ui_' . $this->slug . '_before_on_save', $settings );

				/**
				 * {domain}_ui_do_save_settings
				 *
				 * This filter allows developers to disable the updating of the settings for example to add custom buttons
				 * for the ui that do different actions.
				 *
				 * @param bool $do_save Default: true
				 *
				 * @since 1.12.2
				 */
				if ( apply_filters( $this->get_domain() . '_ui_do_save_settings', true ) ) {
					$settings = $this->on_save( $settings );
					update_option( $this->get_domain() . '-settings', $settings );
					$this->get_ui_manager()->get_ui_message()->add( __( 'Settings updated', 'captcha-for-contact-form-7' ), 'success' );
				}

				/**
				 * {domain}_ui_{slug}_after_on_save
				 *
				 * This filter allows developers to run different actions / tasks after the settings have been written into the database
				 *
				 * @param array $settings
				 *
				 * @since 1.0.0
				 */
				$settings = apply_filters( $this->get_domain() . '_ui_' . $this->slug . '_after_on_save', $settings );
			}
		}

		/**
		 * Option to hide the submit button
		 *
		 * @param bool $hide
		 *
		 * @return void
		 */
		protected function hide_submit_button( $hide ) {
			$this->hide_submit_button = $hide;
		}

		/**
		 * Returns true if the button should be hidden.
		 *
		 * @return bool
		 */
		protected function is_submit_button_hidden() {
			return $this->hide_submit_button;
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
		public function render_content( $slug, $page ) {
			if ( $this->get_slug() != $page ) {
				return;
			}

			$this->maybe_save();

			$settings = apply_filters( $this->get_domain() . '_get_settings', array() );

			$this->get_ui_manager()->get_ui_message()->render();
			?>
            <div class="box">
                <form action="" method="post">
					<?php
					do_action( $this->get_domain() . '_ui_' . $page . '_before_content', $settings );
					$this->the_content( $slug, $page, $settings );
					do_action( $this->get_domain() . '_ui_' . $page . '_after_content', $settings );


					if ( ! $this->is_submit_button_hidden() ):
						wp_nonce_field( $this->get_domain() . '_action', $this->get_domain() . '_nonce' );
						?>
                        <input type="submit" name="<?php echo $this->get_domain(); ?>-settings-submit" class="button"
                               value=" <?php _e( 'Save', 'captcha-for-contact-form-7' ); ?>"/>
					<?php endif; ?>
                </form>
            </div>
			<?php
		}
	}
}