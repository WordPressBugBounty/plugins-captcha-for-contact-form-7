<?php

namespace f12_cf7_captcha;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * React Admin App – renders the SilentShield React SPA
 * as the primary admin interface.
 *
 * The old PHP-based UI pages remain accessible via their direct URLs
 * as a fallback, but are hidden from the admin menu.
 */
class UI_ReactApp {
	private string $react_dist_path;
	private string $react_dist_url;

	/**
	 * Subpages exposed in the WordPress admin menu.
	 * Each entry maps a slug suffix to [ menu-title, hash-route ].
	 */
	private const SUBPAGES = [
		'protection' => [ 'Protection Settings',      '/protection' ],
		'advanced'   => [ 'Advanced Settings',         '/advanced' ],
		'forms'      => [ 'Forms',                     '/forms' ],
		'analytics'  => [ 'Analytics',                 '/analytics' ],
		'mail-log'   => [ 'Mail Log',                  '/mail-log' ],
		'audit-log'  => [ 'Audit Log',                 '/audit-log' ],
		'cleanup'    => [ 'Data Cleanup',              '/data-cleanup' ],
		'api'        => [ 'API / SilentShield',        '/api' ],
	];

	public function __construct() {
		$this->react_dist_path = plugin_dir_path( __FILE__ ) . 'react-app/dist/';
		$this->react_dist_url  = plugin_dir_url( __FILE__ ) . 'react-app/dist/';

		// Run after the old UI registration (priority 10) so we can modify the menu
		add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );

		// Hide old PHP UI submenu pages
		add_action( 'admin_menu', [ $this, 'hide_old_submenu_pages' ], 99 );

		// Highlight the correct submenu item for subpages
		add_filter( 'submenu_file', [ $this, 'highlight_submenu' ] );
	}

	/**
	 * Register the React SPA as the primary admin page,
	 * plus one submenu entry per SPA subpage.
	 */
	public function register_menu(): void {
		// Main "Dashboard" entry
		$hook = add_submenu_page(
			'f12-cf7-captcha',
			__( 'SilentShield', 'captcha-for-contact-form-7' ),
			__( 'Dashboard', 'captcha-for-contact-form-7' ),
			'manage_options',
			'silentshield-admin',
			[ $this, 'render_page' ],
			0
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, [ $this, 'on_page_load' ] );
		}

		// Register subpages — all render the same SPA, JS picks up the hash route
		foreach ( self::SUBPAGES as $slug => [ $label, $route ] ) {
			$translated_label = __( $label, 'captcha-for-contact-form-7' );
			$sub_slug         = 'silentshield-' . $slug;
			$sub_hook         = add_submenu_page(
				'f12-cf7-captcha',
				'SilentShield – ' . $translated_label,
				$translated_label,
				'manage_options',
				$sub_slug,
				[ $this, 'render_page' ]
			);

			if ( $sub_hook ) {
				add_action( 'load-' . $sub_hook, [ $this, 'on_page_load' ] );
			}
		}

		// Add type="module" to the script tag so dynamic imports (code splitting) work
		add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 3 );
	}

	/**
	 * Highlight the correct submenu item when a subpage is active.
	 */
	public function highlight_submenu( ?string $submenu_file ): ?string {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $submenu_file;
		}

		// Check if current page is one of our subpage slugs
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( str_starts_with( $page, 'silentshield-' ) && $page !== 'silentshield-admin' ) {
			return $page;
		}

		return $submenu_file;
	}

	/**
	 * Hide old PHP UI submenu pages from the admin menu.
	 * They remain accessible via their direct URLs as a fallback.
	 */
	public function hide_old_submenu_pages(): void {
		global $submenu;

		if ( ! isset( $submenu['f12-cf7-captcha'] ) ) {
			return;
		}

		// Build set of our own slugs to keep
		$keep = [ 'silentshield-admin' ];
		foreach ( array_keys( self::SUBPAGES ) as $key ) {
			$keep[] = 'silentshield-' . $key;
		}

		// Collect slugs of old PHP pages to hide
		$old_slugs = [];
		foreach ( $submenu['f12-cf7-captcha'] as $item ) {
			$slug = $item[2] ?? '';
			if ( ! in_array( $slug, $keep, true ) && $slug !== 'f12-cf7-captcha' ) {
				$old_slugs[] = $slug;
			}
		}

		foreach ( $old_slugs as $slug ) {
			remove_submenu_page( 'f12-cf7-captcha', $slug );
		}

		// Also hide the default "dashboard" submenu that duplicates the main menu
		remove_submenu_page( 'f12-cf7-captcha', 'f12-cf7-captcha' );
	}

	/**
	 * Called when our admin page is being loaded.
	 * Enqueue assets and remove unnecessary admin notices.
	 */
	public function on_page_load(): void {
		// Enqueue React bundle
		$this->enqueue_assets();

		// Dequeue old PHP UI assets that may conflict
		add_action( 'admin_enqueue_scripts', [ $this, 'dequeue_old_assets' ], 999 );

		// Remove other plugin notices on our page for a clean UI
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Dequeue old PHP UI scripts/styles that are not needed on the React page
	 * and can cause JS errors (e.g. wp-pointer dependency issues).
	 */
	public function dequeue_old_assets(): void {
		wp_dequeue_script( 'wp-pointer' );
		wp_dequeue_style( 'wp-pointer' );
		wp_dequeue_script( 'f12-cf7-captcha-admin' );
		wp_dequeue_style( 'f12-cf7-captcha-admin' );
	}

	/**
	 * Render the HTML shell for the React SPA.
	 * Injects a small script to set the hash route matching the WP submenu slug.
	 */
	public function render_page(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Determine the hash route from the subpage slug
		$route = '/';
		if ( $page !== 'silentshield-admin' && isset( self::SUBPAGES[ str_replace( 'silentshield-', '', $page ) ] ) ) {
			$route = self::SUBPAGES[ str_replace( 'silentshield-', '', $page ) ][1];
		}
		?>
		<div class="wrap" style="margin: 0; padding: 0; max-width: none;">
			<div id="silentshield-root" style="min-height: 100vh;"></div>
		</div>
		<?php if ( $route !== '/' ) : ?>
		<script>
			// Set hash route before React mounts so HashRouter picks up the correct page
			if ( ! window.location.hash || window.location.hash === '#/' ) {
				window.location.hash = <?php echo wp_json_encode( '#' . $route ); ?>;
			}
		</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Add type="module" attribute to our script tag for ES module support.
	 */
	public function add_module_type( string $tag, string $handle, string $src ): string {
		if ( 'silentshield-admin' === $handle ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Redirect the script translation file lookup so WordPress finds our
	 * handle-based JSON filenames instead of the default MD5-based ones.
	 *
	 * WordPress expects {domain}-{locale}-{md5}.json but we ship
	 * {domain}-{locale}-silentshield-admin.json.
	 */
	public function fix_script_translation_file( string $file, string $handle, string $domain ): string {
		if ( 'silentshield-admin' !== $handle || 'captcha-for-contact-form-7' !== $domain ) {
			return $file;
		}

		// Replace the MD5 hash in the filename with the script handle
		$file = preg_replace(
			'/captcha-for-contact-form-7-([a-zA-Z_]+)-[a-f0-9]{32}\.json$/',
			'captcha-for-contact-form-7-$1-silentshield-admin.json',
			$file
		);

		return $file;
	}

	/**
	 * Enqueue the React bundle JS + CSS and pass config via wp_localize_script.
	 */
	private function enqueue_assets(): void {
		$js_file  = $this->react_dist_path . 'silentshield-admin.js';
		$css_file = $this->react_dist_path . 'silentshield-admin.css';

		$js_ver  = file_exists( $js_file ) ? (string) filemtime( $js_file ) : FORGE12_CAPTCHA_VERSION;
		$css_ver = file_exists( $css_file ) ? (string) filemtime( $css_file ) : FORGE12_CAPTCHA_VERSION;

		// CSS
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'silentshield-admin',
				$this->react_dist_url . 'silentshield-admin.css',
				[],
				$css_ver
			);
		}

		// JS (type=module for Vite output)
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'silentshield-admin',
				$this->react_dist_url . 'silentshield-admin.js',
				[ 'wp-i18n' ],
				$js_ver,
				true
			);

			// Fix JSON filename lookup before loading translations
			add_filter( 'load_script_translation_file', [ $this, 'fix_script_translation_file' ], 10, 3 );

			// Load translations for the React app JS bundle
			wp_set_script_translations(
				'silentshield-admin',
				'captcha-for-contact-form-7',
				plugin_dir_path( __DIR__ ) . 'languages'
			);

			// Pass configuration to the React app (includes locale for debugging)
			wp_localize_script( 'silentshield-admin', 'silentShieldConfig', [
				'locale'    => determine_locale(),
				'userLocale' => get_user_locale(),
				'apiUrl'    => esc_url_raw( rest_url( 'f12-cf7-captcha/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'version'   => FORGE12_CAPTCHA_VERSION,
				'pluginUrl' => esc_url_raw( plugin_dir_url( __FILE__ ) ),
				'iconUrl'   => esc_url_raw( plugin_dir_url( __FILE__ ) . 'assets/icon-captcha-20x20.png' ),
				'siteUrl'   => home_url(),
			] );
		}
	}
}
