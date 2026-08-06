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

		// Redirect old page slugs to new React pages
		add_action( 'admin_init', [ $this, 'redirect_old_pages' ] );

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

		$this->add_feedback_menu_item();
		$this->add_support_menu_item();

		// Add type="module" to the script tag so dynamic imports (code splitting) work
		add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 3 );
	}

	/**
	 * A permanent way out of the plugin for someone who has a problem.
	 *
	 * The review notice only appears after ten days and twenty blocked attempts, and it can
	 * be dismissed for good — so it cannot be the only route. This one is always there.
	 *
	 * add_submenu_page() cannot point at an external address, so the entry is registered
	 * normally and its href rewritten afterwards.
	 */
	private function add_feedback_menu_item(): void {
		$slug = 'silentshield-feedback';

		add_submenu_page(
			'f12-cf7-captcha',
			__( 'Feedback', 'captcha-for-contact-form-7' ),
			__( 'Feedback & Support', 'captcha-for-contact-form-7' ),
			'manage_options',
			$slug,
			'__return_null'
		);

		$this->point_menu_item_at( $slug, \f12_cf7_captcha\get_feedback_url( 'admin-menu' ) );
	}

	/**
	 * Send a registered submenu entry to an external address, and open it in a new tab.
	 *
	 * add_submenu_page() cannot point at an external address, so the entry is registered
	 * normally and its href rewritten here.
	 *
	 * The new-tab part used to be written with esc_js(), which HTML-encodes the ampersands in
	 * a query string — so the emitted selector asked for `…&amp;utm_medium=…` while the anchor
	 * carries `…&utm_medium=…`, matched nothing, and both entries quietly opened in the same
	 * tab. Throwing an admin out of their dashboard to file a bug report is a good way to lose
	 * the report, which is exactly what this was meant to prevent. The URL now travels as a
	 * JSON string literal and is compared against the attribute value rather than being
	 * interpolated into a selector.
	 *
	 * @param string $slug The slug the entry was registered under.
	 * @param string $url  Where it should actually go.
	 */
	private function point_menu_item_at( string $slug, string $url ): void {
		global $submenu;

		if ( ! isset( $submenu['f12-cf7-captcha'] ) ) {
			return;
		}

		foreach ( $submenu['f12-cf7-captcha'] as &$item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) {
				$item[2] = $url;
				break;
			}
		}
		unset( $item );

		add_action( 'admin_footer', static function () use ( $url ) {
			printf(
				'<script>(function(){var u=%s;document.querySelectorAll("#adminmenu a").forEach(function(a){if(a.getAttribute("href")===u){a.target="_blank";a.rel="noopener";}});})();</script>',
				wp_json_encode( $url )
			);
		} );
	}

	/**
	 * A way to the product site from inside the plugin.
	 *
	 * Admin-side on purpose. Guideline 10 governs credit links on "users' front-facing
	 * websites" — the front-end credit therefore stays opt-in and off by default, while a menu
	 * entry an administrator sees in their own dashboard is an ordinary plugin link.
	 *
	 * Same mechanism as the feedback entry above.
	 */
	private function add_support_menu_item(): void {
		$slug = 'silentshield-support-us';

		add_submenu_page(
			'f12-cf7-captcha',
			__( 'Support us', 'captcha-for-contact-form-7' ),
			__( 'Support us', 'captcha-for-contact-form-7' ),
			'manage_options',
			$slug,
			'__return_null'
		);

		$this->point_menu_item_at( $slug, \f12_cf7_captcha\get_credit_url( 'admin-menu' ) );
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

			// The entries pointing at silentshield.io had their slug replaced by the full URL
			// back at registration time, so they cannot be matched by name here — and an
			// absolute URL is never one of the old PHP pages this is meant to clean up.
			// Without this they were registered and deleted again in the same request, which
			// is why the "Feedback & Support" entry never appeared in the menu at all.
			if ( strpos( $slug, 'http://' ) === 0 || strpos( $slug, 'https://' ) === 0 ) {
				continue;
			}

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
	 * Redirect old PHP admin page slugs to their React equivalents.
	 */
	public function redirect_old_pages(): void {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$redirects = [
			'f12-cf7-captcha'                           => 'silentshield-admin',
			'f12-cf7-captcha_f12-cf7-captcha-extended'  => 'silentshield-protection',
			'f12-cf7-captcha-extended'                  => 'silentshield-protection',
			'f12-cf7-captcha-audit-log'                 => 'silentshield-audit-log',
			'f12-cf7-captcha_f12-cf7-captcha-audit-log' => 'silentshield-audit-log',
			// The old Beta screen (ui/controller/UI_Beta.php) held the API key and the
			// SilentShield toggles, which now live on the API page. It was missing from this
			// list, so a bookmark or an old link landed on "Sorry, you are not allowed to
			// access this page" instead of the screen that replaced it.
			'f12-cf7-captcha-beta'                      => 'silentshield-api',
			'f12-cf7-captcha_f12-cf7-captcha-beta'      => 'silentshield-api',
		];

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( isset( $redirects[ $page ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $redirects[ $page ] ) );
			exit;
		}
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
		// React & ReactDOM are provided by WordPress (wp-includes) so every
		// plugin on the page shares the same React instance — no duplicate-
		// context bugs when WooCommerce or other React-based plugins are active.
		if ( file_exists( $js_file ) ) {
			$deps = [ 'react', 'react-dom', 'wp-i18n' ];

			// react-jsx-runtime was registered in WP 6.6.
			if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
				$deps[] = 'react-jsx-runtime';
			}

			wp_enqueue_script(
				'silentshield-admin',
				$this->react_dist_url . 'silentshield-admin.js',
				$deps,
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
				// Built server-side so the version is attached in one place — see core/feedback.php.
				'feedbackUrl' => esc_url_raw( \f12_cf7_captcha\get_feedback_url( 'help-page' ) ),
				'supportUrl'  => esc_url_raw( \f12_cf7_captcha\get_support_url( 'help-page' ) ),
				// The sidebar's own links. Built here rather than in JS because the language
				// segment comes from the site locale, which only PHP knows.
				'docsUrl'      => esc_url_raw( \f12_cf7_captcha\get_docs_url( 'admin-sidebar' ) ),
				'feedbackUrlSidebar' => esc_url_raw( \f12_cf7_captcha\get_feedback_url( 'admin-sidebar' ) ),
				'supportUsUrl' => esc_url_raw( \f12_cf7_captcha\get_credit_url( 'admin-sidebar' ) ),
			] );
		}
	}
}
