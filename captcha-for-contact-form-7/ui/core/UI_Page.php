<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

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
			$this->get_logger()->info( 'UI page constructor started.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'slug'     => $slug,
				'title'    => $title,
				'position' => $position,
			] );

			// Set the class properties with the passed values.
			$this->slug     = $slug;
			$this->title    = $title;
			$this->class    = $class;
			$this->position = $position;
			$this->get_logger()->debug( 'UI page properties have been set.' );

			// Add a filter to load the page settings.
			// The hook tag is dynamic and based on the UI Manager's domain.
			add_filter(
				$UI_Manager->get_domain() . '_settings', // Filter name
				array( $this, 'get_settings' ), // Callback method of this class
				10, // Filter priority
				1  // Number of expected arguments (here the $settings array)
			);
			$this->get_logger()->debug( 'Filter "ui_settings" added.', [ 'hook' => $UI_Manager->get_domain() . '_settings' ] );

			$this->get_logger()->info( 'Constructor completed.' );
		}

		public function get_logger(): LoggerInterface {
			return $this->UI_Manager->get_logger();
		}

		protected function get_ui_manager(): UI_Manager {
			return $this->UI_Manager;
		}

		public function hide_in_menu(): bool {
			$this->get_logger()->info( 'Checking if the UI page should be hidden in the menu.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// This method returns 'false' by default, meaning that
			// the page should be visible in the WordPress admin menu.
			$should_hide = false;
			$this->get_logger()->debug( 'The page will be displayed in the menu.', [ 'result' => $should_hide ] );

			return $should_hide;
		}

		public function get_position() {
			$this->get_logger()->info( 'Retrieving the menu position of the UI page.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'position' => $this->position,
			] );

			// Return the stored position.
			return $this->position;
		}

		public function is_dashboard(): bool {
			$this->get_logger()->info( 'Checking if the page is the dashboard.', [
				'class'    => __CLASS__,
				'method'   => __METHOD__,
				'position' => $this->get_position(),
			] );

			// The get_position() method returns the position in the menu.
			// By default, the dashboard is registered at position 0.
			$is_dashboard = $this->get_position() === 0;

			$this->get_logger()->debug( 'Dashboard check result.', [
				'is_dashboard' => $is_dashboard,
			] );

			// Return a boolean value indicating whether the page is the dashboard.
			return $is_dashboard;
		}

		public function get_domain(): string {
			$this->get_logger()->info( 'Retrieving the domain from the UI manager.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			// Call the get_ui_manager method to get the UI Manager instance.
			$ui_manager = $this->get_ui_manager();

			// Then call the get_domain method on this instance.
			$domain = $ui_manager->get_domain();

			$this->get_logger()->debug( 'Domain successfully retrieved from UI manager.', [ 'domain' => $domain ] );

			return $domain;
		}

		public function get_slug(): string {
			$this->get_logger()->info( 'Retrieving the UI page slug.', [
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'slug'   => $this->slug,
			] );

			// Return the stored slug.
			return $this->slug;
		}

		public function get_title(): string {
			return $this->title;
		}

		public function get_class(): string {
			$this->get_logger()->info( 'Retrieving the CSS class of the UI page.', [
				'plugin' => 'f12-cf7-captcha',
				'class'      => __CLASS__,
				'method'     => __METHOD__,
				'class_name' => $this->class,
			] );

			// Return the stored class name.
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
		public function render_content( string $slug, string $page ): void {
			$this->get_logger()->info( 'Starting the rendering of the page content.', [
				'plugin' => 'f12-cf7-captcha',
				'class'          => __CLASS__,
				'method'         => __METHOD__,
				'requested_slug' => $slug,
				'page_slug'      => $page,
				'expected_slug'  => $this->slug,
			] );

			// Check if the passed page slug matches the current page.
			// If not, the rendering will be aborted.
			if ( $this->slug !== $page ) {
				$this->get_logger()->debug( 'The requested page does not match the current one. Rendering will be skipped.' );

				return;
			}

			$this->get_logger()->info( 'Rendering process started. Retrieving settings and rendering messages.' );

			// Retrieve the global settings via a filter.
			// This allows other modules to add their default settings.
			$settings = apply_filters( $this->get_domain() . '_get_settings', [] );
			$this->get_logger()->debug( 'Settings retrieved via filter.' );

			// Render the UI messages (e.g., success or error messages).
			$this->get_ui_message()->render();

			// Trigger a hook that is placed before the box container.
			do_action( $this->get_domain() . '_ui_' . $page . '_before_box' );
			$this->get_logger()->debug( 'Hook "before_box" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_before_box' ] );

			?>
            <div class="box">
				<?php
				// Trigger a hook that is placed before the main page content.
				do_action( $this->get_domain() . '_ui_' . $page . '_before_content', $settings );
				$this->get_logger()->debug( 'Hook "before_content" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_before_content' ] );

				// Render the actual page content.
				$this->the_content( $slug, $page, $settings );

				// Trigger a hook that is placed after the main content.
				do_action( $this->get_domain() . '_ui_' . $page . '_after_content', $settings );
				$this->get_logger()->debug( 'Hook "after_content" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_after_content' ] );
				?>
            </div>
			<?php
			// Trigger a hook that is placed after the box container.
			do_action( $this->get_domain() . '_ui_' . $page . '_after_box' );
			$this->get_logger()->debug( 'Hook "after_box" triggered.', [ 'hook' => $this->get_domain() . '_ui_' . $page . '_after_box' ] );

			$this->get_logger()->info( 'Page content rendering completed.' );
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