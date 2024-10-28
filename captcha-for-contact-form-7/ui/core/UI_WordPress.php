<?php

namespace f12_cf7_captcha\ui {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_WordPress' ) ) {
		/**
		 * Add the Pages to WordPress
		 */
		class UI_WordPress {
			private $UI_Manager = null;

			/**
			 * @param $UI_Manager
			 */
			public function __construct( $UI_Manager ) {
				$this->set_ui_manager( $UI_Manager );

				// Create the Submenus
				add_action( 'admin_menu', array( $this, 'add_submenu_pages' ) );

				// Hotfix to hide the submenus that should be hidden but still callable in wordpress backend
				add_action( 'admin_head', array( $this, 'hide_submenu_pages' ) );
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
			 * @return UI_Manager
			 */
			private function get_ui_manager(): UI_Manager {
				return $this->UI_Manager;
			}

			/**
			 * @return string
			 */
			private function get_title(): string {
				return $this->get_ui_manager()->get_name();
			}

			/**
			 * @return string
			 */
			private function get_capability(): string {
				return $this->get_ui_manager()->get_capability();
			}

			/**
			 * @return string
			 */
			private function get_slug(): string {
				return $this->get_ui_manager()->get_domain();
			}

			/**
			 * TODO ADD ICON
			 *
			 * @return string
			 */
			private function get_icon(): string {
				return $this->get_ui_manager()->get_icon();
			}

			/**
			 * Return the array containing all pages
			 *
			 * @return UI_Page[]
			 */
			private function get_page_storage(): array {
				return $this->get_ui_manager()->get_page_manager()->get_page_storage();
			}

			/**
			 * Add the WordPress Page for the Settings to the WordPress CMS
			 *
			 * @private WordPress Hook
			 */
			public function add_submenu_pages() {
				add_menu_page( $this->get_title(), $this->get_title(), $this->get_capability(), $this->get_slug(), '', $this->get_icon() );

				foreach ( $this->get_page_storage() as /** @var UI_Page $Page */ $Page ) {
					if ( $Page->is_dashboard() ) {
						$slug = $this->get_slug();
					} else {
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					add_submenu_page( $this->get_slug(), $Page->get_title(), $Page->get_title(), $this->get_capability(), $slug, function () {
						$this->render_page();
					}, $Page->get_position() );
				}
			}

			/**
			 * This will ensure that the menu page will be removed before the rendering.
			 * This needs to be called from add_action('admin_head', ''); to be working.
			 *
			 * @return void
			 */
			public function hide_submenu_pages() {
				foreach ( $this->get_page_storage() as /** @var UI_Page $Page */ $Page ) {
					if ( ! $Page->hide_in_menu() ) {
						continue;
					}

					if ( $Page->is_dashboard() ) {
						$slug = $this->get_slug();
					} else {
						$slug = $this->get_slug() . '_' . $Page->get_slug();
					}

					remove_submenu_page( $this->get_slug(), $slug );
				}
			}

			/**
			 * Render the UI Page
			 *
			 * @return void
			 */
			public function render_page() {
				$page = '';

				if ( isset( $_GET['page'] ) ) {
					$page = sanitize_text_field( $_GET['page'] );
					$page = substr( explode( $this->get_slug(), $page )[1], 1 );
				}

				if ( empty( $page ) ) {
					$page = $this->get_slug();
				}

				$Page_Storage = $this->get_page_storage();

				$Menu_Page_Storage = array();

				foreach ( $Page_Storage as $UI_Page ) {
					if ( $UI_Page->hide_in_menu() ) {
						continue;
					}

					$Menu_Page_Storage[] = $UI_Page;
				}
				?>
                <div class="forge12-plugin <?php echo esc_attr( 'captcha-for-contact-form-7' ); ?>">
                    <div class="forge12-plugin-header">
                        <div class="forge12-plugin-header-inner">
                            <img src="<?php echo $this->get_ui_manager()->get_plugin_dir_url(); ?>ui/assets/icon-captcha-128x128.png"
                                 alt="Forge12 Interactvie GmbH" title="Forge12 Interactive GmbH"/>
                            <div class="title">
                                <h1>
									<?php _e( 'Captcha', 'captcha-for-contact-form-7' ); ?>
                                </h1>
                                <p><?php _e( ' by Forge12 Interactive GmbH', 'captcha-for-contact-form-7' ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="forge12-plugin-menu">
						<?php do_action( $this->get_slug() . '_admin_menu', $Menu_Page_Storage, $page, $this->get_slug() ); ?>
                    </div>
                    <div class="forge12-plugin-content">
                        <div class="forge12-plugin-content-main">
							<?php do_action( 'forge12-plugin-content-' . $this->get_slug(), $this->get_slug(), $page ) ?>
                        </div>
                    </div>
                    <div class="forge12-plugin-footer">
                        <div class="forge12-plugin-footer-inner">
                            <img src="<?php echo $this->get_ui_manager()->get_plugin_dir_url(); ?>ui/assets/logo-forge12-dark.png"
                                 alt="Forge12 Interactvie GmbH" title="Forge12 Interactive GmbH"/>
                        </div>
                    </div>
                </div>
				<?php
			}
		}
	}
}