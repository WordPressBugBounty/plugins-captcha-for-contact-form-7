<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Show the UI Menu
	 */
	class UI_Menu {
		/**
		 * @var UI_Manager|null
		 */
		private $UI_Manager = null;

		/**
		 * UI constructor.
		 *
		 * @param UI_Manager $UI_Manager
		 */
		public function __construct( UI_Manager $UI_Manager ) {
			$this->set_ui_manager( $UI_Manager );

			add_action( $UI_Manager->get_domain() . '_admin_menu', array( $this, 'render' ), 10, 3 );
		}

		/**
		 * @param UI_Manager $UI_Manager
		 *
		 * @return void
		 */
		private function set_ui_manager( UI_Manager $UI_Manager ) {
			$this->UI_Manager = $UI_Manager;
		}

		/**
		 *
		 * @param array<UI_Page> $Pages
		 * @param string         $active_slug
		 *
		 * @return void
		 */
		public function render( $Page_Storage, string $active_slug, string $plugin_slug ) {
			if ( ! is_array( $Page_Storage ) ) {
				$Page_Storage = array( $Page_Storage );
			}
			?>
            <nav class="navbar">
                <ul class="navbar-nav">
					<?php do_action( 'before-forge12-plugin-menu-' . $plugin_slug ); ?>
					<?php foreach ( $Page_Storage as /** @var UI_Page $Page */ $Page ): ?>
                        <li class="forge12-plugin-menu-item">
							<?php
							$class = '';

							$slug = $plugin_slug . '_' . $Page->get_slug();

							if ( $Page->is_dashboard() ) {
								$slug = $plugin_slug;
							}

							if ( $Page->get_slug() == $active_slug || ( $Page->is_dashboard() && empty( $active_slug ) ) ) {
								$class = 'active';
							}

							?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>?page=<?php echo esc_attr( $slug ); ?>"
                               title="<?php echo esc_attr( $Page->get_title() ); ?>"
                               class="<?php echo esc_attr( $class ) . ' ' . esc_attr( $Page->get_class() ); ?>">
								<?php echo esc_html( $Page->get_title() ); ?>
                            </a>
                        </li>
					<?php endforeach; ?>
					<?php do_action( 'after-forge12-plugin-menu-' . $plugin_slug ); ?>
                </ul>
            </nav>
			<?php
		}
	}
}