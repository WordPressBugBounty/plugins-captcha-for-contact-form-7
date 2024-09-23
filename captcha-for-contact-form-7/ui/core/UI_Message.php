<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Messages
	 */
	class UI_Message {
		private $UI_Manager;

		/**
		 * @var array
		 */
		private $messages = [];

		public function __construct( UI_Manager $UI_Manager ) {
			$this->set_ui_manager( $UI_Manager );
		}

		private function set_ui_manager( UI_Manager $UI_Manager ) {
			$this->UI_Manager = $UI_Manager;
		}

		public function get_ui_manager(): UI_Manager {
			return $this->UI_Manager;
		}

		/**
		 * getAll function.
		 *
		 * @access public
		 * @return string
		 */
		public function render() {
			foreach ( $this->messages as $key => $value ) {
				echo wp_kses( $value, [ 'div' => [ 'class' => [], 'role' => [] ] ] ) . PHP_EOL;
			}
		}

		/**
		 * add function.
		 *
		 * @access public
		 *
		 * @param mixed $message
		 * @param mixed $type
		 *
		 * @return void
		 */
		public function add( $message, $type ) {
			if ( $type === 'error' ) {
				$type = 'alert-danger';

			} elseif ( $type === 'success' ) {
				$type = 'alert-success';

			} elseif ( $type === 'info' ) {
				$type = 'alert-info';

			} elseif ( $type === 'warning' ) {
				$type = 'alert-warning';

			} elseif ( $type === 'offer' ) {
				$type = 'alert-offer';

			} elseif ( $type === 'critical' ) {
				$type = 'alert-critical';
			}

			$this->messages[] = '<div class="box ' . \esc_attr( $type ) . '" role="alert">' . esc_html( $message ) . '</div>';
		}
	}
}