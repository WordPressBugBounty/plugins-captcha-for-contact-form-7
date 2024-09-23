<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'forge12\ui\UI_Page_Manager' ) ) {
		/**
		 * Handles all Pages of the given Object
		 */
		class UI_Page_Manager {
			/**
			 * @var ?UI_Manager $UI_Manager
			 */
			private $UI_Manager = null;

			/**
			 * @var array<UI_Page> $Page_Storage ;
			 */
			private $Page_Storage = [];

			/**
			 * Constructor
			 */
			public function __construct( UI_Manager $UI_Manager ) {
				$this->UI_Manager = $UI_Manager;

				// Sort Pages after intialize
				add_action( $this->get_domain() . '_ui_after_load_pages', array( $this, 'sort_pages' ), 999999999, 1 );
			}

			/**
			 * Sort the UI Pages by the Position
			 */
			public function sort_pages( UI_Manager $UI_Manager ) {
				if ( empty( $this->Page_Storage ) ) {
					return;
				}

				usort( $this->Page_Storage, function ( $a, $b ) {
					if ( $a->get_position() < $b->get_position() ) {
						return - 1;
					} else if ( $a->get_position() > $b->get_position() ) {
						return 1;
					} else {
						return 0;
					}
				} );
			}

			/**
			 * Add a page to the UI (addPage())
			 */
			public function add_page( UI_Page $UI_Page ) {
				$this->Page_Storage[ $UI_Page->get_slug() ] = $UI_Page;

				add_action( 'forge12-plugin-content-' . $this->get_domain(), array(
					$UI_Page,
					'render_content'
				), 10, 2 );

				add_action( 'forge12-plugin-sidebar-' . $this->get_domain(), array(
					$UI_Page,
					'render_sidebar'
				), 10, 2 );
			}

			/**
			 * Get Page By Slug (get())
			 *
			 * @param string $slug
			 *
			 * @return UI_Page|null
			 */
			private function get_page_by_slug( string $slug ){
				if ( ! isset( $this->Page_Storage[ $slug ] ) ) {
					return null;
				}

				return $this->Page_Storage[ $slug ];
			}

			/**
			 * Return the Storage of the Pages (getPages())
			 *
			 * @return UI_Page[]
			 */
			public function get_page_storage(): array {
				return $this->Page_Storage;
			}

			/**
			 * Get the UI Manager
			 *
			 * @return UI_Manager
			 */
			private function get_ui_manager(): UI_Manager {
				return $this->UI_Manager;
			}

			/**
			 * Return the Domain of the UI Instance
			 *
			 * @return string
			 */
			private function get_domain(): string {
				$UI_Manager = $this->get_ui_manager();

				return $UI_Manager->get_domain();
			}
		}
	}
}