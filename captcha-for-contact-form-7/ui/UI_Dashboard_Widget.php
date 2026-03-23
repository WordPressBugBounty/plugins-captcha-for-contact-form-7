<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\core\protection\Protection;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Protection Score Dashboard Widget for the WordPress admin dashboard.
	 * Shows a visual score (0-100) based on active protection modules,
	 * with guidance on how to improve protection.
	 */
	class UI_Dashboard_Widget {

		public function __construct() {
			add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
		}

		/**
		 * Register the dashboard widget.
		 */
		public function register_widget(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			wp_add_dashboard_widget(
				'f12_silentshield_protection_score',
				'SilentShield — ' . esc_html__( 'Protection Score', 'captcha-for-contact-form-7' ),
				[ $this, 'render_widget' ]
			);
		}

		/**
		 * Calculate the protection score based on active modules and settings.
		 *
		 * @return array{score: int, max: int, items: array, api_active: bool}
		 */
		private function calculate_score(): array {
			$instance = CF7Captcha::get_instance();

			/** @var Protection $protection */
			$protection = $instance->get_module( 'protection' );

			$api_key    = $instance->get_settings( 'beta_captcha_api_key', 'beta' );
			$api_active = $protection->has_module( 'api-validator' )
			              && $protection->get_module( 'api-validator' )->is_enabled()
			              && ! empty( $api_key );

			$items = [];
			$score = 0;

			// --- Standalone modules (max 65 points) ---

			// Captcha (15 pts)
			$captcha_enabled = (int) $instance->get_settings( 'protection_captcha_enable', 'global' ) === 1;
			$items[]         = [
				'label'  => __( 'CAPTCHA Protection', 'captcha-for-contact-form-7' ),
				'points' => $captcha_enabled ? 15 : 0,
				'max'    => 15,
				'active' => $captcha_enabled,
			];
			$score           += $captcha_enabled ? 15 : 0;

			// Timer (10 pts)
			$timer_enabled = (int) $instance->get_settings( 'protection_time_enable', 'global' ) === 1;
			$items[]       = [
				'label'  => __( 'Timer Protection', 'captcha-for-contact-form-7' ),
				'points' => $timer_enabled ? 10 : 0,
				'max'    => 10,
				'active' => $timer_enabled,
			];
			$score         += $timer_enabled ? 10 : 0;

			// JavaScript Validation (10 pts)
			$js_enabled = (int) $instance->get_settings( 'protection_javascript_enable', 'global' ) === 1;
			$items[]    = [
				'label'  => __( 'JavaScript Validation', 'captcha-for-contact-form-7' ),
				'points' => $js_enabled ? 10 : 0,
				'max'    => 10,
				'active' => $js_enabled,
			];
			$score      += $js_enabled ? 10 : 0;

			// Browser Detection (5 pts)
			$browser_enabled = (int) $instance->get_settings( 'protection_browser_enable', 'global' ) === 1;
			$items[]         = [
				'label'  => __( 'Browser Detection', 'captcha-for-contact-form-7' ),
				'points' => $browser_enabled ? 5 : 0,
				'max'    => 5,
				'active' => $browser_enabled,
			];
			$score           += $browser_enabled ? 5 : 0;

			// IP Rate Limiting (10 pts)
			$ip_enabled = (int) $instance->get_settings( 'protection_ip_enable', 'global' ) === 1;
			$items[]    = [
				'label'  => __( 'IP Rate Limiting', 'captcha-for-contact-form-7' ),
				'points' => $ip_enabled ? 10 : 0,
				'max'    => 10,
				'active' => $ip_enabled,
			];
			$score      += $ip_enabled ? 10 : 0;

			// Multiple Submission Protection (5 pts)
			$multi_enabled = (int) $instance->get_settings( 'protection_multiple_submission_enable', 'global' ) === 1;
			$items[]       = [
				'label'  => __( 'Multiple Submission Protection', 'captcha-for-contact-form-7' ),
				'points' => $multi_enabled ? 5 : 0,
				'max'    => 5,
				'active' => $multi_enabled,
			];
			$score         += $multi_enabled ? 5 : 0;

			// Logging (5 pts)
			$log_enabled = (int) $instance->get_settings( 'protection_log_enable', 'global' ) === 1;
			$items[]     = [
				'label'  => __( 'Submission Logging', 'captcha-for-contact-form-7' ),
				'points' => $log_enabled ? 5 : 0,
				'max'    => 5,
				'active' => $log_enabled,
			];
			$score       += $log_enabled ? 5 : 0;

			// Detailed Tracking (5 pts)
			$tracking_enabled = (int) $instance->get_settings( 'protection_detailed_tracking', 'global' ) === 1;
			$items[]          = [
				'label'  => __( 'Detailed Block Tracking', 'captcha-for-contact-form-7' ),
				'points' => $tracking_enabled ? 5 : 0,
				'max'    => 5,
				'active' => $tracking_enabled,
			];
			$score            += $tracking_enabled ? 5 : 0;

			// --- API-only modules (35 pts, only reachable with API) ---

			$items[] = [
				'label'  => __( 'AI Behavior Analysis', 'captcha-for-contact-form-7' ),
				'points' => $api_active ? 15 : 0,
				'max'    => 15,
				'active' => $api_active,
				'api'    => true,
			];
			$score   += $api_active ? 15 : 0;

			$items[] = [
				'label'  => __( 'Browser Fingerprinting', 'captcha-for-contact-form-7' ),
				'points' => $api_active ? 10 : 0,
				'max'    => 10,
				'active' => $api_active,
				'api'    => true,
			];
			$score   += $api_active ? 10 : 0;

			$items[] = [
				'label'  => __( 'Adaptive Challenges (PoW)', 'captcha-for-contact-form-7' ),
				'points' => $api_active ? 10 : 0,
				'max'    => 10,
				'active' => $api_active,
				'api'    => true,
			];
			$score   += $api_active ? 10 : 0;

			return [
				'score'      => $score,
				'max'        => 100,
				'items'      => $items,
				'api_active' => $api_active,
			];
		}

		/**
		 * Render the dashboard widget.
		 */
		public function render_widget(): void {
			$data  = $this->calculate_score();
			$score = $data['score'];
			$max   = $data['max'];
			$pct   = round( ( $score / $max ) * 100 );

			// Color based on score
			if ( $score >= 80 ) {
				$color = '#16a34a'; // green
			} elseif ( $score >= 50 ) {
				$color = '#d97706'; // amber
			} else {
				$color = '#dc2626'; // red
			}

			// Stats
			$counters    = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			$total       = (int) ( $counters['checks_total'] ?? 0 );
			$spam        = (int) ( $counters['checks_spam'] ?? 0 );
			$spam_pct    = $total > 0 ? round( ( $spam / $total ) * 100, 1 ) : 0;
			?>
			<div style="text-align:center; padding:10px 0 20px;">
				<!-- Score circle -->
				<div style="position:relative; width:120px; height:120px; margin:0 auto;">
					<svg viewBox="0 0 36 36" style="transform:rotate(-90deg);">
						<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
						      fill="none" stroke="#e5e7eb" stroke-width="3" />
						<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
						      fill="none" stroke="<?php echo esc_attr( $color ); ?>" stroke-width="3"
						      stroke-dasharray="<?php echo esc_attr( $pct ); ?>, 100"
						      stroke-linecap="round" />
					</svg>
					<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center;">
						<div style="font-size:28px; font-weight:700; color:<?php echo esc_attr( $color ); ?>;">
							<?php echo esc_html( $score ); ?>
						</div>
						<div style="font-size:11px; color:#64748b;">/<?php echo esc_html( $max ); ?></div>
					</div>
				</div>

				<!-- Mini stats row -->
				<div style="display:flex; justify-content:center; gap:24px; margin-top:16px; font-size:13px; color:#475569;">
					<div>
						<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
						<div style="font-size:11px; color:#94a3b8;"><?php esc_html_e( 'Checks', 'captcha-for-contact-form-7' ); ?></div>
					</div>
					<div>
						<strong style="color:#dc2626;"><?php echo esc_html( number_format_i18n( $spam ) ); ?></strong>
						<div style="font-size:11px; color:#94a3b8;"><?php esc_html_e( 'Blocked', 'captcha-for-contact-form-7' ); ?></div>
					</div>
					<div>
						<strong><?php echo esc_html( $spam_pct ); ?>%</strong>
						<div style="font-size:11px; color:#94a3b8;"><?php esc_html_e( 'Spam Rate', 'captcha-for-contact-form-7' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Module list -->
			<div style="border-top:1px solid #e5e7eb; padding-top:12px;">
				<?php foreach ( $data['items'] as $item ) :
					$is_api = ! empty( $item['api'] );
					?>
					<div style="display:flex; align-items:center; justify-content:space-between; padding:5px 0; font-size:13px;">
						<div style="display:flex; align-items:center; gap:6px;">
							<?php if ( $item['active'] ) : ?>
								<span style="color:#16a34a;">&#10003;</span>
							<?php else : ?>
								<span style="color:#d1d5db;">&#10007;</span>
							<?php endif; ?>
							<span style="<?php echo ! $item['active'] ? 'color:#94a3b8;' : ''; ?>">
								<?php echo esc_html( $item['label'] ); ?>
								<?php if ( $is_api && ! $data['api_active'] ) : ?>
									<span style="background:#dbeafe; color:#1d4ed8; font-size:10px; padding:1px 5px; border-radius:3px; margin-left:4px;">API</span>
								<?php endif; ?>
							</span>
						</div>
						<span style="font-size:12px; color:<?php echo $item['active'] ? '#16a34a' : '#94a3b8'; ?>; font-weight:600;">
							+<?php echo esc_html( $item['active'] ? $item['points'] : $item['max'] ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			// Recent audit issues
			$audit_log     = new \f12_cf7_captcha\core\log\AuditLog( \Forge12\Shared\Logger::getInstance() );
			$recent_issues = $audit_log->get_recent_issues( 5 );
			if ( ! empty( $recent_issues ) ) :
				$severity_styles = [
					'warning'  => 'background:#fef9c3; color:#92400e; border-color:#fde68a;',
					'error'    => 'background:#fee2e2; color:#b91c1c; border-color:#fca5a5;',
					'critical' => 'background:#fce7f3; color:#9d174d; border-color:#f9a8d4;',
				];
			?>
			<div style="border-top:1px solid #e5e7eb; padding-top:12px; margin-top:12px;">
				<div style="font-size:13px; font-weight:600; margin-bottom:8px;"><?php esc_html_e( 'Recent Issues', 'captcha-for-contact-form-7' ); ?></div>
				<?php foreach ( $recent_issues as $issue ) :
					$style = $severity_styles[ $issue['severity'] ] ?? $severity_styles['warning'];
					$ts    = strtotime( $issue['ts'] );
					$ago   = human_time_diff( $ts, time() );
				?>
				<div style="padding:6px 8px; margin-bottom:4px; border-radius:6px; border:1px solid; <?php echo esc_attr( $style ); ?> font-size:12px;">
					<div style="display:flex; justify-content:space-between; align-items:center;">
						<span style="font-weight:600;"><?php echo esc_html( $issue['event_code'] ); ?></span>
						<span style="font-size:10px; opacity:0.7;"><?php echo esc_html( $ago ); ?></span>
					</div>
					<div style="margin-top:2px; opacity:0.85; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo esc_attr( $issue['description'] ); ?>">
						<?php echo esc_html( $issue['description'] ); ?>
					</div>
				</div>
				<?php endforeach; ?>
				<div style="text-align:right; margin-top:6px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-captcha-audit-log' ) ); ?>" style="font-size:11px; color:#3b82f6;">
						<?php esc_html_e( 'View Audit Log', 'captcha-for-contact-form-7' ); ?> &rarr;
					</a>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( ! $data['api_active'] ) : ?>
				<!-- Upsell banner with pricing anchor -->
				<div style="margin-top:16px; padding:14px 16px; background:linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%); border:1px solid #bfdbfe; border-radius:8px;">
					<div style="font-size:13px; font-weight:600; color:#1e40af; margin-bottom:6px;">
						<?php esc_html_e( 'Reach 100/100 with SilentShield API', 'captcha-for-contact-form-7' ); ?>
					</div>
					<div style="font-size:12px; color:#3b82f6; margin-bottom:10px;">
						<?php esc_html_e( 'Add AI behavior analysis, browser fingerprinting, and adaptive challenges.', 'captcha-for-contact-form-7' ); ?>
					</div>

					<div style="display:flex; gap:8px;">
						<a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=dashboard-widget&utm_campaign=protection-score' ); ?>"
						   target="_blank"
						   style="display:inline-block; background:#2563eb; color:#fff; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">
							<?php esc_html_e( 'View Plans', 'captcha-for-contact-form-7' ); ?> &rarr;
						</a>
						<a href="<?php echo esc_url( 'https://silentshield.io/register?utm_source=wp-plugin&utm_medium=dashboard-widget&utm_campaign=protection-score' ); ?>"
						   target="_blank"
						   style="display:inline-block; background:#f1f5f9; color:#1e293b; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">
							<?php esc_html_e( 'Start Free', 'captcha-for-contact-form-7' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<!-- Referral teaser for API users -->
				<div style="margin-top:16px; padding:12px 14px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; text-align:center;">
					<div style="font-size:12px; color:#6b21a8; font-weight:600; margin-bottom:4px;">
						&#127873; <?php esc_html_e( 'Refer a Friend — Get 1 Month Free', 'captcha-for-contact-form-7' ); ?>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-captcha-upgrade' ) ); ?>"
					   style="font-size:11px; color:#7c3aed; text-decoration:underline;">
						<?php esc_html_e( 'Get your referral link', 'captcha-for-contact-form-7' ); ?> &rarr;
					</a>
				</div>
			<?php endif; ?>
			<?php
		}
	}
}
