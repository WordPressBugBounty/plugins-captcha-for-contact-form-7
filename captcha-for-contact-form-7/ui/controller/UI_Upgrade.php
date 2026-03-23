<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Upgrade Page — Feature comparison, ROI calculator, and pricing overview.
	 * Shown as a menu item in the plugin admin to drive free→paid conversion.
	 */
	class UI_Upgrade extends UI_Page {

		public function __construct( UI_Manager $UI_Manager ) {
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-upgrade', __( 'Upgrade', 'captcha-for-contact-form-7' ), 99, 'f12-upgrade-page' );
		}

		public function get_settings( $settings ): array {
			return $settings;
		}

		protected function the_sidebar( $slug, $page ) {
			?>
			<div class="box">
				<div class="section">
					<h2><?php esc_html_e( 'Why Upgrade?', 'captcha-for-contact-form-7' ); ?></h2>
					<p><?php esc_html_e( 'The SilentShield API adds AI-powered behavior analysis that catches bots your current protection misses — completely invisible to your users.', 'captcha-for-contact-form-7' ); ?></p>
					<p><strong><?php esc_html_e( 'Free plan available.', 'captcha-for-contact-form-7' ); ?></strong></p>
				</div>
			</div>
			<div class="box">
				<div class="section">
					<h2><?php esc_html_e( 'Questions?', 'captcha-for-contact-form-7' ); ?></h2>
					<p>
						<?php printf(
							wp_kses(
								__( 'Visit our <a href="%s" target="_blank">documentation</a> or contact us at <a href="mailto:%s">%s</a>.', 'captcha-for-contact-form-7' ),
								[ 'a' => [ 'href' => [], 'target' => [] ] ]
							),
							'https://silentshield.io/docs',
							'support@silentshield.io',
							'support@silentshield.io'
						); ?>
					</p>
				</div>
			</div>
			<?php
		}

		protected function the_content( $slug, $page, $settings ) {
			$instance = CF7Captcha::get_instance();
			$api_key  = $instance->get_settings( 'beta_captcha_api_key', 'beta' );
			$site_url = home_url();

			// Get spam stats for ROI calculator
			$counters     = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			$total_checks = (int) ( $counters['checks_total'] ?? 0 );
			$total_spam   = (int) ( $counters['checks_spam'] ?? 0 );
			$spam_pct     = $total_checks > 0 ? round( ( $total_spam / $total_checks ) * 100, 1 ) : 0;

			// Estimate monthly values (rough: divide total by weeks active, multiply by 4)
			$installed_at = get_option( 'f12_cf7_captcha_installed_at', '' );
			$weeks_active = 1;
			if ( ! empty( $installed_at ) ) {
				$weeks_active = max( 1, (int) ceil( ( time() - strtotime( $installed_at ) ) / WEEK_IN_SECONDS ) );
			}
			$monthly_submissions = (int) round( ( $total_checks / $weeks_active ) * 4 );
			$monthly_spam        = (int) round( ( $total_spam / $weeks_active ) * 4 );
			?>

			<?php if ( ! empty( $api_key ) ) : ?>
				<div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:16px 20px; margin-bottom:24px;">
					<p style="margin:0; color:#065f46; font-weight:600;">
						&#10003; <?php esc_html_e( 'SilentShield API is active. You are already using enhanced protection!', 'captcha-for-contact-form-7' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Feature Comparison Table -->
			<div class="section-container" style="margin-bottom:24px;">
				<h2 style="margin:0 0 20px;"><?php esc_html_e( 'Feature Comparison', 'captcha-for-contact-form-7' ); ?></h2>
				<table class="widefat" style="border-collapse:collapse;">
					<thead>
						<tr style="background:#f8fafc;">
							<th style="padding:12px 16px; text-align:left; width:40%;"><?php esc_html_e( 'Feature', 'captcha-for-contact-form-7' ); ?></th>
							<th style="padding:12px 16px; text-align:center; width:20%;"><?php esc_html_e( 'Standalone', 'captcha-for-contact-form-7' ); ?></th>
							<th style="padding:12px 16px; text-align:center; width:20%; background:#eff6ff;">
								<?php esc_html_e( 'API Free', 'captcha-for-contact-form-7' ); ?>
							</th>
							<th style="padding:12px 16px; text-align:center; width:20%; background:#dbeafe;">
								<?php esc_html_e( 'API Pro', 'captcha-for-contact-form-7' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$features = [
							[ __( 'CAPTCHA Protection', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'Honeypot Fields', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'Timer Protection', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'JavaScript Validation', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'IP Rate Limiting', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'Content Blacklist', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'Browser Detection', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'Block Analytics', 'captcha-for-contact-form-7' ), true, true, true ],
							[ __( 'AI Behavior Analysis', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Browser Fingerprinting', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Adaptive Challenges (PoW)', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( '13 Specific Reason Codes', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Score Breakdown (7 categories)', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Invisible to Users', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Cloud Dashboard', 'captcha-for-contact-form-7' ), false, true, true ],
							[ __( 'Multi-Domain Support', 'captcha-for-contact-form-7' ), false, false, true ],
							[ __( 'Remove Branding', 'captcha-for-contact-form-7' ), false, false, true ],
							[ __( 'Priority Support', 'captcha-for-contact-form-7' ), false, false, true ],
							[ __( 'WCAG 2.1 AA Certified', 'captcha-for-contact-form-7' ), 'partial', true, true ],
						];

						foreach ( $features as $i => $f ) :
							$bg = $i % 2 === 0 ? '' : 'background:#fafbfc;';
						?>
						<tr style="<?php echo $bg; ?>">
							<td style="padding:10px 16px; font-size:13px;"><?php echo esc_html( $f[0] ); ?></td>
							<?php for ( $col = 1; $col <= 3; $col++ ) :
								$val = $f[ $col ];
								$col_bg = $col === 2 ? 'background:rgba(239,246,255,0.3);' : ( $col === 3 ? 'background:rgba(219,234,254,0.3);' : '' );
							?>
							<td style="padding:10px 16px; text-align:center; <?php echo $col_bg; ?>">
								<?php if ( $val === true ) : ?>
									<span style="color:#16a34a; font-weight:700;">&#10003;</span>
								<?php elseif ( $val === false ) : ?>
									<span style="color:#d1d5db;">&#10007;</span>
								<?php else : ?>
									<span style="color:#d97706;">~</span>
								<?php endif; ?>
							</td>
							<?php endfor; ?>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- ROI Calculator -->
			<div class="section-container" style="margin-bottom:24px;">
				<h2 style="margin:0 0 4px;"><?php esc_html_e( 'Spam Cost Calculator', 'captcha-for-contact-form-7' ); ?></h2>
				<p style="color:#64748b; font-size:13px; margin:0 0 20px;">
					<?php esc_html_e( 'Based on your actual block data from this site.', 'captcha-for-contact-form-7' ); ?>
				</p>

				<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
					<!-- Input Side -->
					<div style="background:#f8fafc; border-radius:10px; padding:20px;">
						<div style="margin-bottom:16px;">
							<label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Monthly Form Submissions', 'captcha-for-contact-form-7' ); ?>
							</label>
							<input type="number" id="f12-roi-submissions" value="<?php echo esc_attr( max( 100, $monthly_submissions ) ); ?>"
							       style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px;"
							       min="0" step="10" />
						</div>
						<div style="margin-bottom:16px;">
							<label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Spam Rate (%)', 'captcha-for-contact-form-7' ); ?>
							</label>
							<input type="number" id="f12-roi-spam-rate" value="<?php echo esc_attr( max( 5, $spam_pct ) ); ?>"
							       style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px;"
							       min="0" max="100" step="1" />
						</div>
						<div>
							<label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Minutes to Review Each Spam (avg)', 'captcha-for-contact-form-7' ); ?>
							</label>
							<input type="number" id="f12-roi-minutes" value="2"
							       style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px;"
							       min="0" step="0.5" />
						</div>
					</div>

					<!-- Result Side -->
					<div style="background:linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); border-radius:10px; padding:20px; color:#fff;">
						<div style="font-size:13px; color:rgba(255,255,255,0.7); margin-bottom:16px;">
							<?php esc_html_e( 'Your estimated monthly cost of spam:', 'captcha-for-contact-form-7' ); ?>
						</div>
						<div id="f12-roi-result-hours" style="font-size:36px; font-weight:700; margin-bottom:4px;">—</div>
						<div style="font-size:13px; color:rgba(255,255,255,0.7); margin-bottom:20px;">
							<?php esc_html_e( 'hours/month reviewing spam', 'captcha-for-contact-form-7' ); ?>
						</div>
						<div style="border-top:1px solid rgba(255,255,255,0.15); padding-top:16px;">
							<div style="font-size:13px; color:rgba(255,255,255,0.85); margin-bottom:12px;">
								<?php esc_html_e( 'With SilentShield API (~99% detection):', 'captcha-for-contact-form-7' ); ?>
							</div>
							<div id="f12-roi-result-saved" style="font-size:20px; font-weight:600; color:#34d399; margin-bottom:16px;">—</div>
							<a href="<?php echo esc_url( 'https://silentshield.io/register?utm_source=wp-plugin&utm_medium=upgrade-page&utm_campaign=roi-calculator&domain=' . rawurlencode( $site_url ) ); ?>"
							   target="_blank"
							   style="display:inline-block; background:#fff; color:#1e3a5f; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:700; text-decoration:none;">
								<?php esc_html_e( 'Start Free', 'captcha-for-contact-form-7' ); ?> &rarr;
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- CTA -->
			<div class="section-container" style="text-align:center;">
				<h2 style="margin:0 0 8px;"><?php esc_html_e( 'Ready to Upgrade?', 'captcha-for-contact-form-7' ); ?></h2>
				<p style="color:#64748b; font-size:13px; margin:0 0 20px;">
					<?php esc_html_e( 'A free plan is available. Visit our website for current pricing and plan details.', 'captcha-for-contact-form-7' ); ?>
				</p>
				<div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
					<a href="<?php echo esc_url( 'https://silentshield.io/pricing?utm_source=wp-plugin&utm_medium=upgrade-page&utm_campaign=view-plans&domain=' . rawurlencode( $site_url ) ); ?>"
					   target="_blank"
					   style="display:inline-block; background:#2563eb; color:#fff; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none;">
						<?php esc_html_e( 'View Plans & Pricing', 'captcha-for-contact-form-7' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( 'https://silentshield.io/register?utm_source=wp-plugin&utm_medium=upgrade-page&utm_campaign=start-free&domain=' . rawurlencode( $site_url ) ); ?>"
					   target="_blank"
					   style="display:inline-block; background:#f1f5f9; color:#1e293b; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none;">
						<?php esc_html_e( 'Start Free', 'captcha-for-contact-form-7' ); ?>
					</a>
				</div>
			</div>

			<!-- Referral Program -->
			<?php if ( ! empty( $api_key ) ) :
				$referral_code = get_option( 'f12_cf7_captcha_referral_code', '' );
				if ( empty( $referral_code ) ) {
					$referral_code = 'SS-' . strtoupper( substr( md5( $site_url . wp_salt() ), 0, 8 ) );
					update_option( 'f12_cf7_captcha_referral_code', $referral_code );
				}
				$referral_url = 'https://silentshield.io/register?ref=' . rawurlencode( $referral_code ) . '&utm_source=wp-plugin&utm_medium=referral&utm_campaign=refer-a-friend';
			?>
			<div class="section-container" style="margin-top:24px;">
				<div style="background:linear-gradient(135deg, #faf5ff 0%, #eff6ff 100%); border:1px solid #c4b5fd; border-radius:12px; padding:28px 32px;">
					<div style="display:flex; align-items:flex-start; gap:20px; flex-wrap:wrap;">
						<div style="flex:1; min-width:280px;">
							<div style="font-size:24px; margin-bottom:8px;">&#127873;</div>
							<h2 style="margin:0 0 8px; font-size:18px; color:#5b21b6;">
								<?php esc_html_e( 'Refer a Friend — Get 1 Month Free', 'captcha-for-contact-form-7' ); ?>
							</h2>
							<p style="margin:0 0 16px; font-size:13px; color:#6b7280; line-height:1.6;">
								<?php esc_html_e( 'Share SilentShield with other WordPress users. When they sign up using your referral link, you both get 1 month free on any paid plan.', 'captcha-for-contact-form-7' ); ?>
							</p>
							<div style="margin-bottom:12px;">
								<label style="font-size:12px; font-weight:600; color:#5b21b6; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Your Referral Link', 'captcha-for-contact-form-7' ); ?>
								</label>
								<div style="display:flex; gap:8px;">
									<input type="text" id="f12-referral-url" value="<?php echo esc_attr( $referral_url ); ?>"
									       readonly
									       style="flex:1; padding:8px 12px; border:1px solid #c4b5fd; border-radius:6px; font-size:12px; background:#fff; color:#475569;"
									       onclick="this.select();" />
									<button type="button" id="f12-referral-copy"
									        style="background:#7c3aed; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">
										<?php esc_html_e( 'Copy', 'captcha-for-contact-form-7' ); ?>
									</button>
								</div>
							</div>
							<div style="font-size:12px; color:#8b5cf6;">
								<?php esc_html_e( 'Your Referral Code:', 'captcha-for-contact-form-7' ); ?>
								<code style="background:#ede9fe; padding:2px 6px; border-radius:3px; font-weight:600;"><?php echo esc_html( $referral_code ); ?></code>
							</div>
						</div>
						<div style="background:#fff; border:1px solid #e9d5ff; border-radius:10px; padding:20px; min-width:180px; text-align:center;">
							<div style="font-size:13px; color:#6b7280; margin-bottom:8px;">
								<?php esc_html_e( 'How it works', 'captcha-for-contact-form-7' ); ?>
							</div>
							<div style="margin-bottom:10px;">
								<div style="font-size:18px;">1&#65039;&#8419;</div>
								<div style="font-size:11px; color:#475569;"><?php esc_html_e( 'Share your link', 'captcha-for-contact-form-7' ); ?></div>
							</div>
							<div style="margin-bottom:10px;">
								<div style="font-size:18px;">2&#65039;&#8419;</div>
								<div style="font-size:11px; color:#475569;"><?php esc_html_e( 'Friend signs up', 'captcha-for-contact-form-7' ); ?></div>
							</div>
							<div>
								<div style="font-size:18px;">3&#65039;&#8419;</div>
								<div style="font-size:11px; color:#475569;"><?php esc_html_e( 'Both get 1 month free', 'captcha-for-contact-form-7' ); ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<script>
			(function(){
				// Referral copy button
				var copyBtn = document.getElementById('f12-referral-copy');
				var refInput = document.getElementById('f12-referral-url');
				if (copyBtn && refInput) {
					copyBtn.addEventListener('click', function() {
						refInput.select();
						if (navigator.clipboard) {
							navigator.clipboard.writeText(refInput.value).then(function() {
								copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'captcha-for-contact-form-7' ) ); ?>';
								copyBtn.style.background = '#16a34a';
								setTimeout(function() {
									copyBtn.textContent = '<?php echo esc_js( __( 'Copy', 'captcha-for-contact-form-7' ) ); ?>';
									copyBtn.style.background = '#7c3aed';
								}, 2000);
							});
						} else {
							document.execCommand('copy');
							copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'captcha-for-contact-form-7' ) ); ?>';
							setTimeout(function() {
								copyBtn.textContent = '<?php echo esc_js( __( 'Copy', 'captcha-for-contact-form-7' ) ); ?>';
							}, 2000);
						}
					});
				}

				var subs = document.getElementById('f12-roi-submissions');
				var rate = document.getElementById('f12-roi-spam-rate');
				var mins = document.getElementById('f12-roi-minutes');
				var resultHours = document.getElementById('f12-roi-result-hours');
				var resultSaved = document.getElementById('f12-roi-result-saved');

				function calc() {
					var s = parseInt(subs.value) || 0;
					var r = parseFloat(rate.value) || 0;
					var m = parseFloat(mins.value) || 0;
					var spamCount = Math.round(s * (r / 100));
					var hours = (spamCount * m) / 60;
					resultHours.textContent = hours.toFixed(1);
					// With API: 99% caught automatically, only 1% needs review
					var savedPct = Math.round(hours * 0.99 * 10) / 10;
					resultSaved.textContent = savedPct.toFixed(1) + ' <?php echo esc_js( __( 'hours saved', 'captcha-for-contact-form-7' ) ); ?>';
				}

				subs.addEventListener('input', calc);
				rate.addEventListener('input', calc);
				mins.addEventListener('input', calc);
				calc();
			})();
			</script>
			<?php
		}
	}
}
