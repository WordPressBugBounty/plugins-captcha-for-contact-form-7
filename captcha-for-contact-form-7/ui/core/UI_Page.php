<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	abstract class UI_Page {
		/**
		 * @var UI_Manager|null
		 */
		protected $UI_Manager = null;
		/**
		 * @var string
		 */
		protected $domain;
		/**
		 * @var string
		 */
		protected $slug;
		/**
		 * @var string
		 */
		protected $title;
		/**
		 * @var string
		 */
		protected $class;
		/**
		 * @var int
		 */
		protected $position = 0;

		/**
		 * Constructor
		 *
		 * @param UI     $UI
		 * @param string $domain
		 */
		public function __construct( UI_Manager $UI_Manager, $slug, $title, $position = 10, $class = '' ) {
			$this->UI_Manager = $UI_Manager;
			$this->slug       = $slug;
			$this->title      = $title;
			$this->class      = $class;
			$this->position   = $position;

			add_filter( $UI_Manager->get_domain() . '_settings', array( $this, 'get_settings' ) );
		}

		protected function get_ui_manager(): UI_Manager {
			return $this->UI_Manager;
		}

		public function hide_in_menu() {
			return false;
		}

		public function get_position() {
			return $this->position;
		}

		public function is_dashboard() {
			return $this->get_position() == 0;
		}

		public function get_domain() {
			return $this->get_ui_manager()->get_domain();
		}

		public function get_slug() {
			return $this->slug;
		}

		public function get_title() {
			return __( $this->title, 'captcha-for-contact-form-7' );
		}

		public function get_class() {
			return $this->class;
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public abstract function get_settings( $settings );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function the_sidebar( $slug, $page );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function the_content( $slug, $page, $settings );

		/**
		 * @return UI_Message
		 */
		private function get_ui_message(): UI_Message {
			return $this->get_ui_manager()->get_ui_message();
		}

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function render_content( $slug, $page ) {
			if ( $this->slug != $page ) {
				return;
			}

			$settings = apply_filters( $this->get_domain() . '_get_settings', array() );

            $this->get_ui_message()->render();

			do_action( $this->get_domain() . '_ui_' . $page . '_before_box' );
			?>
            <div class="box">
				<?php
				do_action( $this->get_domain() . '_ui_' . $page . '_before_content', $settings );
				$this->the_content( $slug, $page, $settings );
				do_action( $this->get_domain() . '_ui_' . $page . '_after_content', $settings );
				?>
            </div>
			<?php
			do_action( $this->get_domain() . '_ui_' . $page . '_after_box' );
		}

		/**
		 * @param string $slug
		 * @param string $page
		 *
		 * @return void
		 * @private WordPress Hook
		 */
		public function render_sidebar( $slug, $page ) {
			if ( $this->slug != $page ) {
				return;
			}
			$this->the_sidebar( $slug, $page );
		}
	}
}