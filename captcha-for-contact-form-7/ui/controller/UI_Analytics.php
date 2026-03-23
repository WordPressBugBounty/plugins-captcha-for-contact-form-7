<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\core\log\BlockLog;
	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Analytics Admin Page — Block-Log Overview with charts and log table.
	 * Data is fetched via WP REST API endpoints (registered in RestController).
	 */
	class UI_Analytics extends UI_Page {

		public function __construct( UI_Manager $UI_Manager ) {
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-analytics', 'Analytics', 3 );
		}

		public function get_settings( $settings ): array {
			return $settings;
		}

		protected function the_sidebar( $slug, $page ) {
			?>
			<div class="box">
				<div class="section">
					<h2><?php esc_html_e( 'About Analytics', 'captcha-for-contact-form-7' ); ?></h2>
					<p><?php esc_html_e( 'This page shows detailed analytics about blocked spam submissions. Enable "Detailed Tracking" in the Extended Settings to start collecting data.', 'captcha-for-contact-form-7' ); ?></p>
					<p><?php esc_html_e( 'Data is automatically deleted after the configured retention period.', 'captcha-for-contact-form-7' ); ?></p>
				</div>
			</div>
			<?php
		}

		protected function the_content( $slug, $page, $settings ) {
			$enabled = BlockLog::is_enabled();
			?>

			<?php if ( ! $enabled ) : ?>
				<div class="section-container">
					<div class="section-wrapper">
						<div class="section" style="text-align:center; padding:60px 20px;">
							<h2><?php esc_html_e( 'Detailed Tracking is disabled', 'captcha-for-contact-form-7' ); ?></h2>
							<p style="margin-top:12px; color:#64748b; max-width:500px; margin-left:auto; margin-right:auto;">
								<?php esc_html_e( 'Enable "Detailed Tracking" in the Extended Settings tab to start collecting block analytics data.', 'captcha-for-contact-form-7' ); ?>
							</p>
						</div>
					</div>
				</div>
				<?php return; endif; ?>

			<?php
			// Check if API mode is active
			$api_key    = \f12_cf7_captcha\CF7Captcha::get_instance()->get_settings( 'beta_captcha_api_key', 'beta' );
			$protection = \f12_cf7_captcha\CF7Captcha::get_instance()->get_module( 'protection' );
			$api_active = $protection->has_module( 'api-validator' )
			              && $protection->get_module( 'api-validator' )->is_enabled()
			              && ! empty( $api_key );

			if ( ! $api_active ) :
			?>
			<!-- Block Insights Banner -->
			<div style="margin-bottom:24px; padding:20px 24px; background:linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border:1px solid #bfdbfe; border-radius:10px; display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
				<div style="flex:1; min-width:280px;">
					<div style="font-size:15px; font-weight:700; color:#1e40af; margin-bottom:6px;">
						<?php esc_html_e( 'How many bots are getting through?', 'captcha-for-contact-form-7' ); ?>
					</div>
					<div style="font-size:13px; color:#475569; line-height:1.5;">
						<?php esc_html_e( 'Rule-based protection (CAPTCHA, Timer, IP) catches simple bots but misses advanced ones like headless Chrome or Puppeteer. SilentShield API uses AI behavior analysis, browser fingerprinting, and adaptive challenges to detect 99% of bots — invisibly, without CAPTCHAs.', 'captcha-for-contact-form-7' ); ?>
					</div>
				</div>
				<div style="text-align:center; min-width:180px;">
					<a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=analytics-banner&utm_campaign=block-insights' ); ?>"
					   target="_blank"
					   style="display:inline-block; background:#2563eb; color:#fff; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap;">
						<?php esc_html_e( 'View Plans', 'captcha-for-contact-form-7' ); ?> &rarr;
					</a>
					<div style="font-size:11px; color:#94a3b8; margin-top:6px;">
						<?php esc_html_e( 'Free plan available', 'captcha-for-contact-form-7' ); ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php
			// Shadow Mode section — visible when shadow mode is enabled and API is NOT active
			$shadow_mode_enabled = (int) \f12_cf7_captcha\CF7Captcha::get_instance()->get_settings( 'protection_api_shadow_mode', 'global' );
			if ( $shadow_mode_enabled && ! $api_active ) :
				$shadow_stats = \f12_cf7_captcha\core\protection\Shadow_Mode::get_stats();
				$shadow_week  = $shadow_stats['current_week'];
			?>
			<!-- Shadow Mode: API Comparison -->
			<div style="margin-bottom:24px; background:#fff; border:2px solid #8b5cf6; border-radius:12px; overflow:hidden;">
				<div style="padding:16px 24px; background:linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-bottom:1px solid #ddd6fe; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
					<div>
						<div style="display:flex; align-items:center; gap:8px;">
							<span style="display:inline-block; width:10px; height:10px; background:#8b5cf6; border-radius:50%; animation:f12-pulse 2s infinite;"></span>
							<h3 style="margin:0; font-size:16px; font-weight:700; color:#5b21b6;">
								<?php esc_html_e( 'API Comparison Mode Active', 'captcha-for-contact-form-7' ); ?>
							</h3>
						</div>
						<p style="margin:4px 0 0; font-size:13px; color:#7c3aed;">
							<?php esc_html_e( 'Tracking local protection verdicts to estimate API improvement.', 'captcha-for-contact-form-7' ); ?>
						</p>
					</div>
					<a href="<?php echo esc_url( 'https://silentshield.io/register?utm_source=wp-plugin&utm_medium=shadow-banner&utm_campaign=shadow-mode' ); ?>"
					   target="_blank"
					   style="display:inline-block; background:#7c3aed; color:#fff; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap;">
						<?php esc_html_e( 'Activate API to catch these bots', 'captcha-for-contact-form-7' ); ?> &rarr;
					</a>
				</div>
				<div style="padding:20px 24px;">
					<!-- This Week Stats -->
					<div style="margin-bottom:16px;">
						<div style="font-size:13px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">
							<?php esc_html_e( 'This Week', 'captcha-for-contact-form-7' ); ?>
						</div>
						<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px;">
							<div style="background:#f8fafc; border-radius:10px; padding:16px; text-align:center;">
								<div style="font-size:28px; font-weight:700; color:#1e40af;"><?php echo esc_html( number_format_i18n( $shadow_week['total'] ) ); ?></div>
								<div style="font-size:12px; color:#64748b; margin-top:4px;"><?php esc_html_e( 'Total Checks', 'captcha-for-contact-form-7' ); ?></div>
							</div>
							<div style="background:#fef2f2; border-radius:10px; padding:16px; text-align:center;">
								<div style="font-size:28px; font-weight:700; color:#dc2626;"><?php echo esc_html( number_format_i18n( $shadow_week['blocked'] ) ); ?></div>
								<div style="font-size:12px; color:#991b1b; margin-top:4px;"><?php esc_html_e( 'Blocked Locally', 'captcha-for-contact-form-7' ); ?></div>
							</div>
							<div style="background:#f0fdf4; border-radius:10px; padding:16px; text-align:center;">
								<div style="font-size:28px; font-weight:700; color:#16a34a;"><?php echo esc_html( number_format_i18n( $shadow_week['passed'] ) ); ?></div>
								<div style="font-size:12px; color:#065f46; margin-top:4px;"><?php esc_html_e( 'Passed Local Checks', 'captcha-for-contact-form-7' ); ?></div>
							</div>
							<div style="background:linear-gradient(135deg, #faf5ff 0%, #fdf2f8 100%); border:1px solid #e9d5ff; border-radius:10px; padding:16px; text-align:center;">
								<div style="font-size:28px; font-weight:700; color:#7c3aed;">
									~<?php echo esc_html( number_format_i18n( $shadow_week['estimated_additional'] ) ); ?>
								</div>
								<div style="font-size:12px; color:#6b21a8; margin-top:4px;"><?php esc_html_e( 'Est. Additional API Catches', 'captcha-for-contact-form-7' ); ?></div>
							</div>
						</div>
					</div>

					<!-- Explanation -->
					<div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:14px 18px; margin-bottom:16px;">
						<div style="font-size:13px; color:#5b21b6; line-height:1.6;">
							<?php printf(
								/* translators: %1$d: estimated additional catches, %2$d: percentage */
								esc_html__( 'Based on industry data, SilentShield API catches ~%2$d%% more sophisticated bots that bypass rule-based detection (headless Chrome, Puppeteer, AI-generated submissions). This week: %1$d estimated additional bots would be caught with API protection.', 'captcha-for-contact-form-7' ),
								$shadow_week['estimated_additional'],
								$shadow_stats['estimated_additional_pct']
							); ?>
						</div>
					</div>

					<!-- All-Time Stats -->
					<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding-top:12px; border-top:1px solid #f1f5f9;">
						<div style="font-size:13px; color:#64748b;">
							<?php printf(
								/* translators: %1$s: total checks, %2$s: total blocked, %3$s: estimated additional */
								esc_html__( 'All time: %1$s checks, %2$s blocked locally, ~%3$s est. additional with API', 'captcha-for-contact-form-7' ),
								'<strong>' . esc_html( number_format_i18n( $shadow_stats['total'] ) ) . '</strong>',
								'<strong>' . esc_html( number_format_i18n( $shadow_stats['blocked'] ) ) . '</strong>',
								'<strong>' . esc_html( number_format_i18n( $shadow_stats['estimated_additional'] ) ) . '</strong>'
							); ?>
						</div>
						<?php if ( $shadow_stats['last_updated'] > 0 ) : ?>
						<div style="font-size:11px; color:#94a3b8;">
							<?php printf(
								/* translators: %s: human-readable time difference */
								esc_html__( 'Last updated: %s ago', 'captcha-for-contact-form-7' ),
								human_time_diff( $shadow_stats['last_updated'], time() )
							); ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<style>
				@keyframes f12-pulse {
					0%, 100% { opacity: 1; }
					50% { opacity: 0.4; }
				}
			</style>
			<?php endif; ?>

			<?php
			// Side-by-Side Comparison (visible when API/trial is active)
			$trial_meta = get_option( 'f12_cf7_captcha_trial_meta', [] );
			$has_trial   = ! empty( $trial_meta ) && ! empty( $trial_meta['activated_at'] );

			if ( $api_active && $has_trial ) :
				$trial_expires = ! empty( $trial_meta['expires_at'] ) ? strtotime( $trial_meta['expires_at'] ) : 0;
				$trial_days_left = max( 0, (int) ceil( ( $trial_expires - time() ) / DAY_IN_SECONDS ) );
			?>
			<!-- Side-by-Side: Standalone vs API -->
			<div style="margin-bottom:24px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
				<div style="padding:20px 24px 12px; border-bottom:1px solid #f1f5f9;">
					<h3 style="margin:0; font-size:16px; font-weight:700;">
						<?php esc_html_e( 'Standalone vs. SilentShield API', 'captcha-for-contact-form-7' ); ?>
					</h3>
					<p style="margin:4px 0 0; font-size:13px; color:#64748b;">
						<?php esc_html_e( 'See the difference since activating API protection.', 'captcha-for-contact-form-7' ); ?>
					</p>
				</div>
				<div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
					<!-- Standalone Column -->
					<div style="padding:20px 24px; background:#f8fafc;">
						<div style="font-size:13px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">
							<?php esc_html_e( 'Before (Standalone)', 'captcha-for-contact-form-7' ); ?>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Protection Score', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:24px; font-weight:700; color:#d97706;">65<span style="font-size:14px; color:#94a3b8;">/100</span></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Detection', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#475569;"><?php esc_html_e( 'Rule-based (CAPTCHA, Timer, IP)', 'captcha-for-contact-form-7' ); ?></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Reason Codes', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#475569;"><?php esc_html_e( 'Generic ("JS failed", "Timer expired")', 'captcha-for-contact-form-7' ); ?></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Bot Detection Rate', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#475569;">~70%</div>
						</div>
						<div>
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Visibility', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#475569;"><?php esc_html_e( 'CAPTCHA visible to users', 'captcha-for-contact-form-7' ); ?></div>
						</div>
					</div>
					<!-- API Column -->
					<div style="padding:20px 24px; background:linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border-left:2px solid #3b82f6;">
						<div style="font-size:13px; font-weight:600; color:#1e40af; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">
							<?php esc_html_e( 'Now (SilentShield API)', 'captcha-for-contact-form-7' ); ?>
							<?php if ( $trial_days_left > 0 ) : ?>
								<span style="background:#dbeafe; color:#1d4ed8; font-size:10px; padding:2px 6px; border-radius:3px; margin-left:6px; vertical-align:middle;">
									<?php printf( esc_html__( 'Trial: %d days left', 'captcha-for-contact-form-7' ), $trial_days_left ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Protection Score', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:24px; font-weight:700; color:#16a34a;">100<span style="font-size:14px; color:#94a3b8;">/100</span></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Detection', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#065f46;"><?php esc_html_e( 'AI Behavior Analysis + Fingerprinting', 'captcha-for-contact-form-7' ); ?></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Reason Codes', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#065f46;"><?php esc_html_e( '13 specific codes with score breakdown', 'captcha-for-contact-form-7' ); ?></div>
						</div>
						<div style="margin-bottom:14px;">
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Bot Detection Rate', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#065f46;">~99%</div>
						</div>
						<div>
							<div style="font-size:12px; color:#94a3b8;"><?php esc_html_e( 'Visibility', 'captcha-for-contact-form-7' ); ?></div>
							<div style="font-size:14px; color:#065f46;"><?php esc_html_e( 'Completely invisible to users', 'captcha-for-contact-form-7' ); ?></div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Time Range Selector -->
			<div style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
				<label for="f12-analytics-days" style="font-weight:600;">
					<?php esc_html_e( 'Time Range:', 'captcha-for-contact-form-7' ); ?>
				</label>
				<select id="f12-analytics-days" style="padding:6px 12px; border:1px solid #d1d5db; border-radius:6px;">
					<option value="7"><?php esc_html_e( '7 days', 'captcha-for-contact-form-7' ); ?></option>
					<option value="30" selected><?php esc_html_e( '30 days', 'captcha-for-contact-form-7' ); ?></option>
					<option value="90"><?php esc_html_e( '90 days', 'captcha-for-contact-form-7' ); ?></option>
				</select>
			</div>

			<!-- Overview Cards -->
			<div id="f12-analytics-overview" style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
				<div style="background:#f1f5f9; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-stat-today" style="font-size:36px; font-weight:700; color:#1e40af;">—</div>
					<div style="font-size:14px; color:#475569; margin-top:4px;"><?php esc_html_e( 'Blocked Today', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#fef3c7; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-stat-week" style="font-size:36px; font-weight:700; color:#92400e;">—</div>
					<div style="font-size:14px; color:#78350f; margin-top:4px;"><?php esc_html_e( 'This Week', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#fee2e2; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-stat-month" style="font-size:36px; font-weight:700; color:#b91c1c;">—</div>
					<div style="font-size:14px; color:#991b1b; margin-top:4px;"><?php esc_html_e( 'This Month', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#dbeafe; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-stat-rate" style="font-size:36px; font-weight:700; color:#1d4ed8;">—</div>
					<div style="font-size:14px; color:#1e40af; margin-top:4px;"><?php esc_html_e( 'Block Rate', 'captcha-for-contact-form-7' ); ?></div>
				</div>
			</div>

			<!-- Charts Row -->
			<div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:24px;">
				<!-- Timeline Chart -->
				<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
					<h3 style="margin:0 0 16px 0; font-size:16px;"><?php esc_html_e( 'Blocks per Day', 'captcha-for-contact-form-7' ); ?></h3>
					<div id="f12-chart-timeline" style="height:220px; display:flex; align-items:flex-end; gap:2px;"></div>
				</div>

				<!-- Protection Breakdown -->
				<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
					<h3 style="margin:0 0 16px 0; font-size:16px;"><?php esc_html_e( 'By Protection', 'captcha-for-contact-form-7' ); ?></h3>
					<div id="f12-chart-protection"></div>
				</div>
			</div>

			<!-- Reason Codes -->
			<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px;">
				<h3 style="margin:0 0 16px 0; font-size:16px;"><?php esc_html_e( 'Block Reasons', 'captcha-for-contact-form-7' ); ?></h3>
				<div id="f12-chart-reasons"></div>
			</div>

			<!-- Block Log Table -->
			<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
				<h3 style="margin:0 0 16px 0; font-size:16px;"><?php esc_html_e( 'Block Log', 'captcha-for-contact-form-7' ); ?></h3>
				<table class="widefat striped" id="f12-block-log-table" style="border:0;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Protection', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Page', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Detail', 'captcha-for-contact-form-7' ); ?></th>
						</tr>
					</thead>
					<tbody id="f12-block-log-body">
						<tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;">
							<?php esc_html_e( 'Loading...', 'captcha-for-contact-form-7' ); ?>
						</td></tr>
					</tbody>
				</table>
				<div id="f12-block-log-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
					<span id="f12-log-page-info" style="color:#64748b; font-size:13px;"></span>
					<div style="display:flex; gap:8px;">
						<button id="f12-log-prev" class="button" disabled><?php esc_html_e( 'Previous', 'captcha-for-contact-form-7' ); ?></button>
						<button id="f12-log-next" class="button" disabled><?php esc_html_e( 'Next', 'captcha-for-contact-form-7' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Detail Overlay -->
			<div id="f12-block-detail-overlay" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,0.5);" onclick="if(event.target===this)this.style.display='none'">
				<div style="position:absolute; right:0; top:0; bottom:0; width:480px; background:#fff; box-shadow:-4px 0 24px rgba(0,0,0,0.15); overflow-y:auto; padding:24px;">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
						<h3 style="margin:0; font-size:18px;"><?php esc_html_e( 'Block Detail', 'captcha-for-contact-form-7' ); ?></h3>
						<button onclick="document.getElementById('f12-block-detail-overlay').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:#64748b;">&times;</button>
					</div>
					<div id="f12-block-detail-content"></div>
				</div>
			</div>

			<script>
			(function(){
				var API_BASE = '<?php echo esc_js( rest_url( 'f12-cf7-captcha/v1/analytics' ) ); ?>';
				var NONCE    = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
				var LOG_LIMIT = 20;
				var logOffset = 0;
				var logTotal  = 0;
				var currentDays = 30;
				var logEntries  = [];

				var PROTECTION_COLORS = {
					timer:'#6366f1', honeypot:'#22c55e', captcha:'#f59e0b', ip_ban:'#ef4444',
					ip_rate:'#f97316', blacklist:'#8b5cf6', browser:'#14b8a6', javascript:'#ec4899',
					multiple:'#64748b', api:'#3b82f6'
				};

				var PROTECTION_LABELS = {
					timer:'Timer', honeypot:'Honeypot', captcha:'Captcha', ip_ban:'IP Ban',
					ip_rate:'IP Rate Limit', blacklist:'Blacklist', browser:'Browser Check',
					javascript:'JavaScript', multiple:'Duplicate Submit', api:'SilentShield API'
				};

				function apiFetch(endpoint, params) {
					var url = API_BASE + '/' + endpoint;
					var qs = Object.keys(params||{}).map(function(k){return k+'='+encodeURIComponent(params[k])}).join('&');
					if(qs) url += '?' + qs;
					return fetch(url, {headers:{'X-WP-Nonce':NONCE}}).then(function(r){return r.json()});
				}

				function loadAll() {
					var days = currentDays;
					apiFetch('summary', {days:days}).then(renderSummary);
					apiFetch('timeline', {days:days}).then(renderTimeline);
					apiFetch('reasons', {days:days}).then(renderReasons);
					loadLog(0);
				}

				function renderSummary(d) {
					document.getElementById('f12-stat-today').textContent = (d.today||0).toLocaleString();
					document.getElementById('f12-stat-week').textContent = (d.week||0).toLocaleString();
					document.getElementById('f12-stat-month').textContent = (d.month||0).toLocaleString();
					document.getElementById('f12-stat-rate').textContent = (d.rate||0) + '%';
				}

				function renderTimeline(d) {
					var container = document.getElementById('f12-chart-timeline');
					var data = d.timeline || [];
					if(!data.length) {
						container.innerHTML = '<div style="color:#94a3b8; text-align:center; width:100%; padding:80px 0;"><?php echo esc_js( __( 'No data', 'captcha-for-contact-form-7' ) ); ?></div>';
						return;
					}
					var max = Math.max.apply(null, data.map(function(r){return r.count})) || 1;
					var html = '';
					data.forEach(function(r){
						var pct = Math.max(2, (r.count/max)*100);
						var label = r.day.substring(5); // MM-DD
						html += '<div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; min-width:0;" title="'+r.day+': '+r.count+'">';
						html += '<div style="font-size:10px; color:#64748b; margin-bottom:2px;">'+r.count+'</div>';
						html += '<div style="width:100%; max-width:24px; background:#ef4444; border-radius:3px 3px 0 0; height:'+pct+'%;"></div>';
						html += '<div style="font-size:9px; color:#94a3b8; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:32px;">'+label+'</div>';
						html += '</div>';
					});
					container.innerHTML = html;
				}

				function renderReasons(d) {
					// By protection (horizontal bars)
					var protContainer = document.getElementById('f12-chart-protection');
					var byProt = d.by_protection || [];
					if(!byProt.length) {
						protContainer.innerHTML = '<div style="color:#94a3b8; padding:20px; text-align:center;"><?php echo esc_js( __( 'No data', 'captcha-for-contact-form-7' ) ); ?></div>';
					} else {
						var maxP = Math.max.apply(null, byProt.map(function(r){return parseInt(r.count)})) || 1;
						var html = '';
						byProt.forEach(function(r) {
							var pct = (parseInt(r.count)/maxP)*100;
							var color = PROTECTION_COLORS[r.protection] || '#64748b';
							var label = PROTECTION_LABELS[r.protection] || r.protection;
							html += '<div style="margin-bottom:8px;">';
							html += '<div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:2px;"><span>'+label+'</span><span style="font-weight:600;">'+parseInt(r.count).toLocaleString()+'</span></div>';
							html += '<div style="height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden;"><div style="height:100%; width:'+pct+'%; background:'+color+'; border-radius:4px;"></div></div>';
							html += '</div>';
						});
						protContainer.innerHTML = html;
					}

					// By reason code
					var reasonContainer = document.getElementById('f12-chart-reasons');
					var byReason = d.by_reason || [];
					if(!byReason.length) {
						reasonContainer.innerHTML = '<div style="color:#94a3b8; padding:20px; text-align:center;"><?php echo esc_js( __( 'No data', 'captcha-for-contact-form-7' ) ); ?></div>';
					} else {
						var totalR = byReason.reduce(function(s,r){return s+parseInt(r.count)},0) || 1;
						var maxR = Math.max.apply(null, byReason.map(function(r){return parseInt(r.count)})) || 1;
						var colors = ['#ef4444','#f59e0b','#f97316','#8b5cf6','#3b82f6','#22c55e','#14b8a6','#ec4899','#6366f1','#64748b','#a855f7','#e11d48','#0ea5e9'];
						var html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">';
						// Bar chart side
						html += '<div>';
						byReason.forEach(function(r,i) {
							var pct = (parseInt(r.count)/maxR)*100;
							var rpct = ((parseInt(r.count)/totalR)*100).toFixed(1);
							html += '<div style="margin-bottom:6px;">';
							html += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:1px;"><span>'+r.reason_code.replace(/_/g,' ')+'</span><span>'+rpct+'%</span></div>';
							html += '<div style="height:6px; background:#f1f5f9; border-radius:3px; overflow:hidden;"><div style="height:100%; width:'+pct+'%; background:'+colors[i%colors.length]+'; border-radius:3px;"></div></div>';
							html += '</div>';
						});
						html += '</div>';
						// Table side
						html += '<div><table class="widefat" style="border:0; font-size:12px;"><thead><tr><th>Code</th><th style="text-align:right;">Count</th><th style="text-align:right;">%</th></tr></thead><tbody>';
						byReason.forEach(function(r) {
							var rpct = ((parseInt(r.count)/totalR)*100).toFixed(1);
							html += '<tr><td style="font-family:monospace; font-size:11px;">'+r.reason_code+'</td><td style="text-align:right;">'+parseInt(r.count).toLocaleString()+'</td><td style="text-align:right;">'+rpct+'%</td></tr>';
						});
						html += '</tbody></table></div></div>';
						reasonContainer.innerHTML = html;
					}
				}

				function loadLog(offset) {
					logOffset = offset;
					apiFetch('log', {days:currentDays, limit:LOG_LIMIT, offset:offset}).then(renderLog);
				}

				function renderLog(d) {
					logEntries = d.data || [];
					logTotal = d.total || 0;
					var tbody = document.getElementById('f12-block-log-body');

					if(!logEntries.length) {
						tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8;"><?php echo esc_js( __( 'No block events recorded yet.', 'captcha-for-contact-form-7' ) ); ?></td></tr>';
					} else {
						var html = '';
						logEntries.forEach(function(e, idx) {
							var color = PROTECTION_COLORS[e.protection] || '#64748b';
							var label = PROTECTION_LABELS[e.protection] || e.protection;
							var ts = new Date(e.ts + 'Z').toLocaleString();
							html += '<tr style="cursor:pointer;" onclick="window.f12ShowBlockDetail('+idx+')">';
							html += '<td style="white-space:nowrap; font-size:12px;">'+ts+'</td>';
							html += '<td><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; color:#fff; background:'+color+';">'+label+'</span></td>';
							html += '<td style="font-size:12px;">'+e.reason_code.replace(/_/g,' ')+'</td>';
							html += '<td style="font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="'+(e.page_url||'')+'">'+(e.page_url||'—')+'</td>';
							html += '<td><button class="button button-small" onclick="event.stopPropagation();window.f12ShowBlockDetail('+idx+')"><?php echo esc_js( __( 'View', 'captcha-for-contact-form-7' ) ); ?></button></td>';
							html += '</tr>';
						});
						tbody.innerHTML = html;
					}

					// Pagination
					var totalPages = Math.max(1, Math.ceil(logTotal / LOG_LIMIT));
					var currentPage = Math.floor(logOffset / LOG_LIMIT) + 1;
					document.getElementById('f12-log-page-info').textContent = '<?php echo esc_js( __( 'Page', 'captcha-for-contact-form-7' ) ); ?> ' + currentPage + ' / ' + totalPages + ' (' + logTotal + ' <?php echo esc_js( __( 'entries', 'captcha-for-contact-form-7' ) ); ?>)';
					document.getElementById('f12-log-prev').disabled = (logOffset <= 0);
					document.getElementById('f12-log-next').disabled = (logOffset + LOG_LIMIT >= logTotal);
				}

				window.f12ShowBlockDetail = function(idx) {
					var e = logEntries[idx];
					if(!e) return;
					var overlay = document.getElementById('f12-block-detail-overlay');
					var content = document.getElementById('f12-block-detail-content');

					var color = PROTECTION_COLORS[e.protection] || '#64748b';
					var label = PROTECTION_LABELS[e.protection] || e.protection;
					var ts = new Date(e.ts + 'Z').toLocaleString();

					var html = '';
					html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Protection', 'captcha-for-contact-form-7' ) ); ?></div><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; color:#fff; background:'+color+'; margin-top:4px;">'+label+'</span></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Verdict', 'captcha-for-contact-form-7' ) ); ?></div><div style="font-weight:600; margin-top:4px;">'+(e.verdict||'blocked')+'</div></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Time', 'captcha-for-contact-form-7' ) ); ?></div><div style="font-size:13px; margin-top:4px;">'+ts+'</div></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Page', 'captcha-for-contact-form-7' ) ); ?></div><div style="font-size:13px; margin-top:4px; word-break:break-all;">'+(e.page_url||'—')+'</div></div>';
					html += '</div>';

					// Reason
					html += '<div style="margin-bottom:16px;">';
					html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Reason Code', 'captcha-for-contact-form-7' ) ); ?></div>';
					html += '<div style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:6px 10px; border-radius:6px;">'+e.reason_code+'</div>';
					html += '</div>';

					if(e.reason_detail) {
						html += '<div style="margin-bottom:16px;">';
						html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Explanation', 'captcha-for-contact-form-7' ) ); ?></div>';
						html += '<div style="font-size:13px; background:#fefce8; padding:8px 12px; border-radius:6px; border:1px solid #fde68a;">'+e.reason_detail+'</div>';
						html += '</div>';
					}

					// Score (API mode)
					if(e.score !== null && e.score !== undefined && e.score !== '') {
						html += '<div style="margin-bottom:16px;">';
						html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Score', 'captcha-for-contact-form-7' ) ); ?></div>';
						html += '<div style="font-size:24px; font-weight:700; font-family:monospace;">'+parseFloat(e.score).toFixed(3)+'</div>';
						html += '</div>';
					}

					// API reason codes
					if(e.reason_codes) {
						try {
							var codes = typeof e.reason_codes === 'string' ? JSON.parse(e.reason_codes) : e.reason_codes;
							if(Array.isArray(codes) && codes.length) {
								html += '<div style="margin-bottom:16px;">';
								html += '<div style="font-size:12px; color:#64748b; margin-bottom:6px;"><?php echo esc_js( __( 'API Reason Codes', 'captcha-for-contact-form-7' ) ); ?></div>';
								html += '<div style="display:flex; flex-wrap:wrap; gap:4px;">';
								codes.forEach(function(c){
									html += '<span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; background:#f1f5f9; border:1px solid #e2e8f0;">'+c.replace(/_/g,' ')+'</span>';
								});
								html += '</div></div>';
							}
						} catch(ex){}
					}

					// Score breakdown from meta
					if(e.meta) {
						try {
							var meta = typeof e.meta === 'string' ? JSON.parse(e.meta) : e.meta;
							if(meta.score_breakdown) {
								var sb = meta.score_breakdown;
								var keys = ['keyboard','pause','mouse','speed','context','scroll','capabilities'];
								html += '<div style="margin-bottom:16px;">';
								html += '<div style="font-size:12px; color:#64748b; margin-bottom:8px;"><?php echo esc_js( __( 'Score Breakdown', 'captcha-for-contact-form-7' ) ); ?></div>';
								keys.forEach(function(k){
									if(sb[k] === undefined || sb[k] === null) return;
									var val = parseFloat(sb[k]);
									var pct = Math.min(100, Math.max(0, val * 100));
									var barColor = val >= 0.7 ? '#22c55e' : val >= 0.4 ? '#f59e0b' : '#ef4444';
									html += '<div style="margin-bottom:6px;">';
									html += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:2px;"><span style="text-transform:capitalize;">'+k+'</span><span style="font-family:monospace;">'+val.toFixed(2)+'</span></div>';
									html += '<div style="height:6px; background:#f1f5f9; border-radius:3px; overflow:hidden;"><div style="height:100%; width:'+pct+'%; background:'+barColor+'; border-radius:3px;"></div></div>';
									html += '</div>';
								});
								html += '</div>';
							}
						} catch(ex){}
					}

					// IP hash + form info
					html += '<div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:16px;">';
					if(e.ip_hash) {
						html += '<div style="font-size:11px; color:#94a3b8; margin-bottom:4px;">IP Hash: '+e.ip_hash.substring(0,16)+'...</div>';
					}
					if(e.form_plugin) {
						html += '<div style="font-size:11px; color:#94a3b8;">Plugin: '+e.form_plugin+(e.form_id ? ' / Form: '+e.form_id : '')+'</div>';
					}
					html += '</div>';

					content.innerHTML = html;
					overlay.style.display = 'block';
				};

				// Event listeners
				document.getElementById('f12-analytics-days').addEventListener('change', function(){
					currentDays = parseInt(this.value);
					loadAll();
				});
				document.getElementById('f12-log-prev').addEventListener('click', function(){
					loadLog(Math.max(0, logOffset - LOG_LIMIT));
				});
				document.getElementById('f12-log-next').addEventListener('click', function(){
					loadLog(logOffset + LOG_LIMIT);
				});

				// Initial load
				loadAll();
			})();
			</script>
			<?php
		}
	}
}
