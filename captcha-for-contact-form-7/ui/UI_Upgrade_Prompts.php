<?php

namespace f12_cf7_captcha {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Contextual Upgrade Prompts — trigger-based admin notices
	 * that encourage free users to try/upgrade to the SilentShield API.
	 *
	 * Notices are:
	 * - Non-intrusive (dismissable, notice-info class)
	 * - Transient-based (each trigger shown max once per 7 days)
	 * - Only shown in WP-Admin, never on frontend
	 * - Only shown on plugin pages or dashboard
	 */
	class UI_Upgrade_Prompts {

		private const OPTION_DISMISSED = 'f12_cf7_captcha_dismissed_prompts';

		public function __construct() {
			if ( ! is_admin() ) {
				return;
			}

			add_action( 'admin_notices', [ $this, 'maybe_show_notices' ] );
			add_action( 'wp_ajax_f12_dismiss_upgrade_prompt', [ $this, 'handle_dismiss' ] );
		}

		/**
		 * Evaluate triggers and show the first matching notice.
		 */
		public function maybe_show_notices(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Only show on dashboard or plugin pages
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$allowed_screens = [ 'dashboard', 'toplevel_page_f12-cf7-captcha' ];
			$is_plugin_page  = strpos( $screen->id, 'f12-cf7-captcha' ) !== false;
			$is_dashboard    = $screen->id === 'dashboard';

			if ( ! $is_plugin_page && ! $is_dashboard ) {
				return;
			}

			// Don't show if API is already active
			$instance = CF7Captcha::get_instance();
			$api_key  = $instance->get_settings( 'beta_captcha_api_key', 'beta' );

			if ( ! empty( $api_key ) ) {
				// Check trial-specific prompts only
				$this->check_trial_prompts( $instance );
				return;
			}

			// Evaluate triggers in priority order (only show one at a time)
			$triggers = [
				'high_spam_rate'   => [ $this, 'check_high_spam_rate' ],
				'version_update'   => [ $this, 'check_version_update' ],
				'first_week'       => [ $this, 'check_first_week_usage' ],
			];

			foreach ( $triggers as $trigger_id => $callback ) {
				if ( $this->is_dismissed( $trigger_id ) ) {
					continue;
				}

				$notice = call_user_func( $callback, $instance );
				if ( $notice ) {
					$this->render_notice( $trigger_id, $notice['message'], $notice['cta_text'], $notice['cta_url'] );
					return; // Only one notice at a time
				}
			}
		}

		/**
		 * Check trial-specific prompts (expiring, expired).
		 */
		private function check_trial_prompts( CF7Captcha $instance ): void {
			$trial_meta = get_option( 'f12_cf7_captcha_trial_meta', [] );
			if ( empty( $trial_meta ) || empty( $trial_meta['expires_at'] ) ) {
				return;
			}

			$expires   = strtotime( $trial_meta['expires_at'] );
			$now       = time();
			$days_left = (int) ceil( ( $expires - $now ) / DAY_IN_SECONDS );

			// Trial expiring soon (3 days or less)
			if ( $days_left > 0 && $days_left <= 3 && ! $this->is_dismissed( 'trial_expiring' ) ) {
				$this->render_notice(
					'trial_expiring',
					sprintf(
						/* translators: %d: number of days */
						__( 'Your SilentShield API trial ends in %d days. Keep AI-powered protection for your forms.', 'captcha-for-contact-form-7' ),
						$days_left
					),
					__( 'View Plans', 'captcha-for-contact-form-7' ),
					'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=admin-notice&utm_campaign=trial-expiring'
				);
				return;
			}

			// Trial expired
			if ( $days_left <= 0 && ! $this->is_dismissed( 'trial_expired' ) ) {
				$this->render_notice(
					'trial_expired',
					__( 'Your SilentShield API trial has expired. Your forms are back in standalone mode. Upgrade to keep the enhanced protection.', 'captcha-for-contact-form-7' ),
					__( 'Upgrade Now', 'captcha-for-contact-form-7' ),
					'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=admin-notice&utm_campaign=trial-expired',
					'warning'
				);
			}
		}

		/**
		 * Trigger: High spam rate detected (10+ blocks in the last hour).
		 */
		private function check_high_spam_rate( CF7Captcha $instance ): ?array {
			$counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			$hourly   = (int) ( $counters['checks_spam_hourly'] ?? 0 );

			// Also check from block log if available
			if ( $hourly < 10 ) {
				// Quick check: blocks in the last hour from transient
				$recent_blocks = (int) get_transient( 'f12_cf7_captcha_recent_blocks' );
				if ( $recent_blocks < 10 ) {
					return null;
				}
			}

			return [
				'message'  => __( 'Your forms are receiving heavy spam traffic. SilentShield API uses AI behavior analysis to catch bots that rule-based protection misses — including headless browsers and automated scripts.', 'captcha-for-contact-form-7' ),
				'cta_text' => __( 'Try API Protection', 'captcha-for-contact-form-7' ),
				'cta_url'  => admin_url( 'admin.php?page=f12-cf7-captcha-beta' ),
			];
		}

		/**
		 * Trigger: Plugin was recently updated to a new version.
		 */
		private function check_version_update( CF7Captcha $instance ): ?array {
			$last_version = get_option( 'f12_cf7_captcha_last_seen_version', '' );
			$current      = defined( 'FORGE12_CAPTCHA_VERSION' ) ? FORGE12_CAPTCHA_VERSION : '';

			if ( empty( $current ) || $last_version === $current ) {
				return null;
			}

			// Update the stored version
			update_option( 'f12_cf7_captcha_last_seen_version', $current );

			return [
				'message'  => sprintf(
					/* translators: %s: version number */
					__( 'SilentShield updated to v%s! New features include enhanced analytics, detailed block tracking, and reason codes. For even stronger protection, try the SilentShield API with AI behavior analysis.', 'captcha-for-contact-form-7' ),
					$current
				),
				'cta_text' => __( 'Learn More', 'captcha-for-contact-form-7' ),
				'cta_url'  => admin_url( 'admin.php?page=f12-cf7-captcha-beta' ),
			];
		}

		/**
		 * Trigger: User has been using the plugin for ~7 days (first-week onboarding).
		 */
		private function check_first_week_usage( CF7Captcha $instance ): ?array {
			$installed_at = get_option( 'f12_cf7_captcha_installed_at', '' );

			if ( empty( $installed_at ) ) {
				// Set the installation timestamp on first check
				update_option( 'f12_cf7_captcha_installed_at', current_time( 'mysql' ) );
				return null;
			}

			$days_since = (int) ( ( time() - strtotime( $installed_at ) ) / DAY_IN_SECONDS );

			// Show between day 7 and day 14
			if ( $days_since < 7 || $days_since > 14 ) {
				return null;
			}

			$counters    = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			$total_blocks = (int) ( $counters['checks_spam'] ?? 0 );

			if ( $total_blocks < 5 ) {
				return null; // Not enough data to show value
			}

			return [
				'message'  => sprintf(
					/* translators: %d: number of blocks */
					__( 'SilentShield has blocked %d spam submissions so far. Want to catch even more? The SilentShield API detects advanced bots that rule-based protection misses.', 'captcha-for-contact-form-7' ),
					$total_blocks
				),
				'cta_text' => __( 'Try Free for 14 Days', 'captcha-for-contact-form-7' ),
				'cta_url'  => admin_url( 'admin.php?page=f12-cf7-captcha-beta' ),
			];
		}

		/**
		 * Render a dismissable admin notice.
		 */
		private function render_notice( string $trigger_id, string $message, string $cta_text, string $cta_url, string $type = 'info' ): void {
			$nonce = wp_create_nonce( 'f12_dismiss_upgrade_prompt' );
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible f12-upgrade-notice"
			     data-trigger="<?php echo esc_attr( $trigger_id ); ?>"
			     data-nonce="<?php echo esc_attr( $nonce ); ?>"
			     style="border-left-color:#2563eb; padding:12px 16px;">
				<div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
					<div style="display:flex; align-items:center; gap:10px; flex:1;">
						<span style="font-size:18px;">&#128737;</span>
						<p style="margin:0; font-size:13px;">
							<strong>SilentShield:</strong>
							<?php echo esc_html( $message ); ?>
						</p>
					</div>
					<a href="<?php echo esc_url( $cta_url ); ?>"
					   <?php echo ( strpos( $cta_url, 'silentshield.io' ) !== false ) ? 'target="_blank"' : ''; ?>
					   style="display:inline-block; background:#2563eb; color:#fff; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; white-space:nowrap;">
						<?php echo esc_html( $cta_text ); ?> &rarr;
					</a>
				</div>
			</div>
			<script>
			(function(){
				document.querySelectorAll('.f12-upgrade-notice').forEach(function(notice) {
					notice.addEventListener('click', function(e) {
						if (e.target.classList.contains('notice-dismiss') || e.target.closest('.notice-dismiss')) {
							var trigger = notice.getAttribute('data-trigger');
							var nonce = notice.getAttribute('data-nonce');
							var xhr = new XMLHttpRequest();
							xhr.open('POST', '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>');
							xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
							xhr.send('action=f12_dismiss_upgrade_prompt&trigger=' + encodeURIComponent(trigger) + '&_wpnonce=' + encodeURIComponent(nonce));
						}
					});
				});
			})();
			</script>
			<?php
		}

		/**
		 * AJAX handler to dismiss a notice for 7 days.
		 */
		public function handle_dismiss(): void {
			check_ajax_referer( 'f12_dismiss_upgrade_prompt' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( -1 );
			}

			$trigger = sanitize_text_field( wp_unslash( $_POST['trigger'] ?? '' ) );
			if ( empty( $trigger ) ) {
				wp_die( -1 );
			}

			$dismissed = get_option( self::OPTION_DISMISSED, [] );
			$dismissed[ $trigger ] = time() + ( 7 * DAY_IN_SECONDS );
			update_option( self::OPTION_DISMISSED, $dismissed );

			wp_die( 1 );
		}

		/**
		 * Check if a trigger has been dismissed and is still within the cooldown window.
		 */
		private function is_dismissed( string $trigger_id ): bool {
			$dismissed = get_option( self::OPTION_DISMISSED, [] );

			if ( ! isset( $dismissed[ $trigger_id ] ) ) {
				return false;
			}

			// Check if the dismiss period has expired
			if ( time() > (int) $dismissed[ $trigger_id ] ) {
				// Clean up expired dismissal
				unset( $dismissed[ $trigger_id ] );
				update_option( self::OPTION_DISMISSED, $dismissed );
				return false;
			}

			return true;
		}
	}
}
