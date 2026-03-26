<?php

namespace f12_cf7_captcha;

use f12_cf7_captcha\ui\UI_Manager;
use f12_cf7_captcha\ui\UI_Page_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UI_Documentation
 *
 * Renders the user-facing documentation / help page.
 */
class UI_Documentation extends UI_Page_Form {

	public function __construct( UI_Manager $UI_Manager ) {
		parent::__construct( $UI_Manager, 'f12-cf7-captcha-documentation', __( 'Help', 'captcha-for-contact-form-7' ), 99 );
	}

	/**
	 * Render an inline tooltip icon with hover text.
	 *
	 * @param string $text The tooltip text to display on hover.
	 */
	public static function tooltip( string $text ): void {
		static $css_printed = false;
		if ( ! $css_printed ) {
			echo '<style>
				.ss-tt{position:relative;display:inline-block;cursor:help;font-size:14px;vertical-align:middle;opacity:0.55;margin-left:4px;}
				.ss-tt:hover{opacity:1;}
				.ss-tt .ss-tt-text{visibility:hidden;opacity:0;width:260px;background:#1d2327;color:#fff;text-align:left;
					border-radius:4px;padding:8px 10px;position:absolute;z-index:9999;bottom:125%;left:50%;
					transform:translateX(-50%);font-size:12px;font-weight:400;line-height:1.5;white-space:normal;
					transition:opacity .15s;pointer-events:none;box-shadow:0 2px 8px rgba(0,0,0,.25);}
				.ss-tt .ss-tt-text::after{content:"";position:absolute;top:100%;left:50%;margin-left:-5px;
					border-width:5px;border-style:solid;border-color:#1d2327 transparent transparent transparent;}
				.ss-tt:hover .ss-tt-text{visibility:visible;opacity:1;}
			</style>';
			$css_printed = true;
		}
		printf(
			'<span class="ss-tt">&#63;<span class="ss-tt-text">%s</span></span>',
			esc_html( $text )
		);
	}

	/**
	 * Render a small help icon linking to a documentation section.
	 *
	 * @param string $anchor The anchor on the documentation page (e.g. '#ss-modules').
	 */
	public static function help_link( string $anchor ): void {
		$url = admin_url( 'admin.php?page=f12-cf7-captcha_f12-cf7-captcha-documentation' ) . $anchor;
		printf(
			' <a href="%s" title="%s" style="text-decoration:none;font-size:14px;vertical-align:middle;opacity:0.6;">&#9432;</a>',
			esc_url( $url ),
			esc_attr__( 'Open help for this section', 'captcha-for-contact-form-7' )
		);
	}

	public function get_settings( $settings ): array {
		return $settings;
	}

	public function on_save( $settings ): array {
		return $settings;
	}

	protected function the_sidebar( $slug, $page ): void {
	}

	protected function the_content( $slug, $page, $settings ): void {
		$this->hide_submit_button( true );
		?>
		<style>
			.ss-docs { max-width: 860px; font-size: 14px; line-height: 1.7; color: #1d2327; }
			.ss-docs h2 { font-size: 22px; margin: 32px 0 12px; padding-bottom: 8px; border-bottom: 1px solid #c3c4c7; }
			.ss-docs h3 { font-size: 16px; margin: 24px 0 8px; }
			.ss-docs table { border-collapse: collapse; width: 100%; margin: 12px 0 20px; }
			.ss-docs th, .ss-docs td { text-align: left; padding: 8px 12px; border: 1px solid #c3c4c7; }
			.ss-docs th { background: #f0f0f1; font-weight: 600; }
			.ss-docs code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
			.ss-docs .ss-tip { background: #fcf9e8; border-left: 4px solid #dba617; padding: 10px 14px; margin: 12px 0; }
			.ss-docs .ss-warn { background: #fcf0f1; border-left: 4px solid #d63638; padding: 10px 14px; margin: 12px 0; }
			.ss-docs .ss-info { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px 14px; margin: 12px 0; }
			.ss-docs .ss-hier { background: #f6f7f7; padding: 16px; border-radius: 4px; font-family: monospace; line-height: 2; margin: 12px 0; }
			.ss-docs details { margin: 12px 0; }
			.ss-docs summary { cursor: pointer; font-weight: 600; padding: 8px 0; }
			.ss-docs details[open] summary { margin-bottom: 8px; }
			.ss-docs .ss-toc { background: #f6f7f7; padding: 16px 24px; border-radius: 4px; margin-bottom: 24px; }
			.ss-docs .ss-toc ol { margin: 0; padding-left: 20px; }
			.ss-docs .ss-toc li { margin: 4px 0; }
			.ss-docs .ss-toc a { text-decoration: none; }
		</style>

		<div class="ss-docs">

			<h1><?php esc_html_e( 'SilentShield - User Guide', 'captcha-for-contact-form-7' ); ?></h1>

			<nav class="ss-toc">
				<strong><?php esc_html_e( 'Table of Contents', 'captcha-for-contact-form-7' ); ?></strong>
				<ol>
					<li><a href="#ss-what"><?php esc_html_e( 'What is SilentShield?', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-start"><?php esc_html_e( 'Getting Started', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-modules"><?php esc_html_e( 'Protection Modules', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-integrations"><?php esc_html_e( 'Supported Forms & Integrations', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-whitelist"><?php esc_html_e( 'Whitelist', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-blacklist"><?php esc_html_e( 'Blacklist', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-overrides"><?php esc_html_e( 'Per-Form Settings', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-api"><?php esc_html_e( 'SilentShield API', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-logging"><?php esc_html_e( 'Logging & Analytics', 'captcha-for-contact-form-7' ); ?></a></li>
					<li><a href="#ss-faq"><?php esc_html_e( 'FAQ', 'captcha-for-contact-form-7' ); ?></a></li>
				</ol>
			</nav>

			<!-- 1. What is SilentShield? -->
			<h2 id="ss-what"><?php esc_html_e( '1. What is SilentShield?', 'captcha-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'SilentShield automatically protects your WordPress forms from spam and bots. The plugin works invisibly in the background and combines multiple protection mechanisms:', 'captcha-for-contact-form-7' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Captcha', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Math challenge, image or honeypot', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'JavaScript Detection', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Detects bots without a browser', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Timer', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Blocks submissions that are too fast', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'IP Protection', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Rate limiting and blacklist', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Content Filters', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Blocks spam words, links and BBCode', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Whitelist', 'captcha-for-contact-form-7' ); ?></strong> &ndash; <?php esc_html_e( 'Trusted users skip all checks', 'captcha-for-contact-form-7' ); ?></li>
			</ul>
			<div class="ss-info"><?php esc_html_e( 'SilentShield is GDPR compliant: no cookies, no tracking, no external services (except the optional SilentShield API).', 'captcha-for-contact-form-7' ); ?></div>

			<!-- 2. Getting Started -->
			<h2 id="ss-start"><?php esc_html_e( '2. Getting Started', 'captcha-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'After activation, SilentShield immediately protects all detected forms. No configuration required.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Active by default', 'captcha-for-contact-form-7' ); ?></h3>
			<table>
				<thead>
				<tr>
					<th><?php esc_html_e( 'Feature', 'captcha-for-contact-form-7' ); ?></th>
					<th><?php esc_html_e( 'Status', 'captcha-for-contact-form-7' ); ?></th>
					<th><?php esc_html_e( 'Description', 'captcha-for-contact-form-7' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr><td><?php esc_html_e( 'Captcha', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Math challenge in the form', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'JavaScript Protection', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Detects bots without JavaScript', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Timer', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Blocks submissions under 0.5 seconds', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Browser Detection', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Blocks requests without a browser', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Duplicate Submit Protection', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Prevents double submissions', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Admin Whitelist', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Administrators skip all checks', 'captcha-for-contact-form-7' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Logged-in User Whitelist', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Active', 'captcha-for-contact-form-7' ); ?></td><td><?php esc_html_e( 'Logged-in users skip all checks', 'captcha-for-contact-form-7' ); ?></td></tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Where to find settings', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'All settings are available in the WordPress admin sidebar under', 'captcha-for-contact-form-7' ); ?> <strong>SilentShield</strong>:</p>
			<ul>
				<li><strong>Dashboard</strong> &ndash; <?php esc_html_e( 'Overview of blocked and allowed submissions', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong>Settings</strong> &ndash; <?php esc_html_e( 'Configure all protection features', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong>Forms</strong> &ndash; <?php esc_html_e( 'Per-form settings overrides', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong>Analytics</strong> &ndash; <?php esc_html_e( 'Detailed statistics', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong>Audit Log</strong> &ndash; <?php esc_html_e( 'Log of all changes', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong>API</strong> &ndash; <?php esc_html_e( 'SilentShield API configuration (optional)', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<!-- 3. Protection Modules -->
			<h2 id="ss-modules"><?php esc_html_e( '3. Protection Modules', 'captcha-for-contact-form-7' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'Captcha', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Displays a challenge in the form that only humans can solve.', 'captcha-for-contact-form-7' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Method', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Math (default), Image or Honeypot', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'Template', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( '10+ visual designs', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'Audio', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Accessible read-aloud option', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
				<div class="ss-tip"><?php esc_html_e( 'Tip: Honeypot mode is completely invisible to visitors - ideal if you do not want a visible captcha.', 'captcha-for-contact-form-7' ); ?></div>
			</details>

			<details>
				<summary><?php esc_html_e( 'JavaScript Protection', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Automatically checks whether the browser can execute JavaScript. Real visitors do not notice anything. Bots that submit forms without a browser are blocked.', 'captcha-for-contact-form-7' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Timer Protection', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Measures the time between page load and form submission. If a form is submitted in under 0.5 seconds (impossible for humans), it is blocked.', 'captcha-for-contact-form-7' ); ?></p>
				<p><strong><?php esc_html_e( 'Minimum time', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Adjustable in milliseconds (default: 500ms)', 'captcha-for-contact-form-7' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Browser Detection', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Blocks requests without a valid browser (User-Agent). Normal visitors are never affected.', 'captcha-for-contact-form-7' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Duplicate Submit Protection', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Prevents the same form from being submitted multiple times in a row (e.g. by double-clicking).', 'captcha-for-contact-form-7' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'IP Rate Limiting', 'captcha-for-contact-form-7' ); ?></summary>
				<p><strong><?php esc_html_e( 'Disabled by default.', 'captcha-for-contact-form-7' ); ?></strong> <?php esc_html_e( 'When enabled, limits how often an IP address can submit forms.', 'captcha-for-contact-form-7' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Max. attempts', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Allowed submissions per time window (default: 3)', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'Time window', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'In seconds (default: 300 = 5 minutes)', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'Block duration', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'How long an IP is blocked (default: 3600 = 1 hour)', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

			<details>
				<summary><?php esc_html_e( 'Content Filters', 'captcha-for-contact-form-7' ); ?></summary>
				<p><strong><?php esc_html_e( 'All disabled by default.', 'captcha-for-contact-form-7' ); ?></strong></p>
				<ul>
					<li><strong><?php esc_html_e( 'URL Limit', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Blocks submissions with too many links', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'Word Blacklist', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Blocks submissions containing specific words. Greedy mode also catches partial matches.', 'captcha-for-contact-form-7' ); ?></li>
					<li><strong><?php esc_html_e( 'BBCode Filter', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Blocks submissions containing BBCode', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

			<!-- 4. Supported Integrations -->
			<h2 id="ss-integrations"><?php esc_html_e( '4. Supported Forms & Integrations', 'captcha-for-contact-form-7' ); ?></h2>

			<h3><?php esc_html_e( 'Form Plugins', 'captcha-for-contact-form-7' ); ?></h3>
			<ul>
				<li>Contact Form 7 (CF7)</li>
				<li>WPForms / WPForms Lite</li>
				<li>Elementor Pro Forms</li>
				<li>Gravity Forms</li>
				<li>Fluent Forms</li>
				<li>JetFormBuilder</li>
				<li>Avada / Fusion Builder Forms</li>
			</ul>

			<h3>WooCommerce</h3>
			<ul>
				<li><?php esc_html_e( 'Login form', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Registration form', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Checkout form (incl. PayPal, Stripe, Klarna)', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<h3>WordPress Core</h3>
			<ul>
				<li><?php esc_html_e( 'Login (wp-login.php)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Registration', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Comment forms', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Other', 'captcha-for-contact-form-7' ); ?></h3>
			<ul>
				<li>Ultimate Member (<?php esc_html_e( 'Login & Registration', 'captcha-for-contact-form-7' ); ?>)</li>
				<li>WP Job Manager (<?php esc_html_e( 'Job Applications', 'captcha-for-contact-form-7' ); ?>)</li>
			</ul>

			<div class="ss-info"><?php esc_html_e( 'Each integration can be enabled or disabled individually under Settings.', 'captcha-for-contact-form-7' ); ?></div>

			<!-- 5. Whitelist -->
			<h2 id="ss-whitelist"><?php esc_html_e( '5. Whitelist - Trusted Users', 'captcha-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Whitelisted users, IP addresses or email addresses skip all protection checks. They will never see a captcha and will never be blocked.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Role Whitelist (enabled by default)', 'captcha-for-contact-form-7' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Administrators skip', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Admins are never checked', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Logged-in users skip', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'All logged-in users are never checked', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'IP Whitelist', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'Enter IP addresses that should skip all checks. One IP per line.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Email Whitelist', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'Enter email addresses that should skip all checks. One email per line. If any form field contains a whitelisted email, the entire submission is allowed.', 'captcha-for-contact-form-7' ); ?></p>

			<div class="ss-warn"><strong><?php esc_html_e( 'Important', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'The whitelist always takes priority over the blacklist. If an IP is on both lists, it will be allowed through.', 'captcha-for-contact-form-7' ); ?></div>

			<!-- 6. Blacklist -->
			<h2 id="ss-blacklist"><?php esc_html_e( '6. Blacklist - Blocked Senders', 'captcha-for-contact-form-7' ); ?></h2>

			<h3><?php esc_html_e( 'IP Blacklist', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'Completely blocks specific IP addresses. These IPs cannot submit any forms. Enter one IP per line.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Word Blacklist', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'Blocks submissions containing specific words or phrases. Must be enabled first under Settings > Content Rules.', 'captcha-for-contact-form-7' ); ?></p>

			<!-- 7. Per-Form Settings -->
			<h2 id="ss-overrides"><?php esc_html_e( '7. Per-Form Settings', 'captcha-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'You can customize protection settings for individual integrations or even individual forms, without changing the global settings.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Examples', 'captcha-for-contact-form-7' ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Disable captcha for comments only', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Go to Forms, select "WordPress Comments" and disable Captcha. All other protections remain active.', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Longer timer for WooCommerce Checkout', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Go to Forms, select "WooCommerce Checkout" and increase the timer (checkout takes longer than a contact form).', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Different captcha method per form', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Override the captcha method for a specific form while keeping math globally.', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Hierarchy', 'captcha-for-contact-form-7' ); ?></h3>
			<div class="ss-hier">
				<?php esc_html_e( 'Global Settings (apply everywhere)', 'captcha-for-contact-form-7' ); ?><br>
				&nbsp;&nbsp;&darr; <?php esc_html_e( 'can be overridden by', 'captcha-for-contact-form-7' ); ?><br>
				<?php esc_html_e( 'Integration Settings (e.g. "all WooCommerce forms")', 'captcha-for-contact-form-7' ); ?><br>
				&nbsp;&nbsp;&darr; <?php esc_html_e( 'can be overridden by', 'captcha-for-contact-form-7' ); ?><br>
				<?php esc_html_e( 'Form Settings (e.g. "only Contact Form #42")', 'captcha-for-contact-form-7' ); ?>
			</div>

			<h3><?php esc_html_e( 'What can be customized per form?', 'captcha-for-contact-form-7' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Captcha (method, template, label, audio, design)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Timer (on/off, minimum time)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'JavaScript Protection (on/off)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Browser Detection (on/off)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Duplicate Submit Protection (on/off)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'IP Rate Limiting (on/off, all parameters)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Content Filters (URL limit, BBCode, blacklist)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Whitelist Roles (admin, logged-in users)', 'captcha-for-contact-form-7' ); ?></li>
			</ul>

			<!-- 8. SilentShield API -->
			<h2 id="ss-api"><?php esc_html_e( '8. SilentShield API', 'captcha-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'The SilentShield API is an optional cloud service for advanced bot detection. It analyzes visitor behavior (mouse movements, typing patterns etc.) and detects bots more reliably.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Activation', 'captcha-for-contact-form-7' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to SilentShield > API', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Enter your API key (or start a free trial)', 'captcha-for-contact-form-7' ); ?></li>
				<li><?php esc_html_e( 'Enable the API', 'captcha-for-contact-form-7' ); ?></li>
			</ol>

			<div class="ss-info"><?php esc_html_e( 'The API runs alongside all local protection features. Captcha, timer, JS protection etc. remain active. The API adds an additional layer of protection.', 'captcha-for-contact-form-7' ); ?></div>

			<h3><?php esc_html_e( 'What happens during an API outage?', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'If the API is unreachable, all local protection modules continue to work normally. There is no loss of protection. A notice appears in the admin area.', 'captcha-for-contact-form-7' ); ?></p>

			<h3><?php esc_html_e( 'Shadow Mode', 'captcha-for-contact-form-7' ); ?></h3>
			<p><?php esc_html_e( 'Before using the API for decisions, you can enable Shadow Mode. API results are only logged but not used for blocking. This lets you compare how the API performs versus local modules.', 'captcha-for-contact-form-7' ); ?></p>

			<!-- 9. Logging -->
			<h2 id="ss-logging"><?php esc_html_e( '9. Logging & Analytics', 'captcha-for-contact-form-7' ); ?></h2>

			<h3>Dashboard</h3>
			<p><?php esc_html_e( 'Shows at a glance: number of checked, allowed and blocked submissions, active protection modules, and recent blocks.', 'captcha-for-contact-form-7' ); ?></p>

			<h3>Analytics</h3>
			<p><?php esc_html_e( 'Detailed statistics under SilentShield > Analytics:', 'captcha-for-contact-form-7' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Timeline', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Blocks over the last 7/30/90 days', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Block Reasons', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Which module blocked how often', 'captcha-for-contact-form-7' ); ?></li>
				<li><strong><?php esc_html_e( 'Block Log', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'Individual blocked submissions with details', 'captcha-for-contact-form-7' ); ?></li>
			</ul>
			<div class="ss-tip"><?php esc_html_e( 'Prerequisite: Detailed tracking must be enabled under Settings.', 'captcha-for-contact-form-7' ); ?></div>

			<h3>Audit Log</h3>
			<p><?php esc_html_e( 'Logs all administrative changes: settings modified, plugin activated/deactivated, API errors, rate limit hits.', 'captcha-for-contact-form-7' ); ?></p>

			<h3>Mail Log</h3>
			<p><?php esc_html_e( 'When enabled, logs all form submissions (sent and blocked) with sender, recipient and content.', 'captcha-for-contact-form-7' ); ?></p>
			<div class="ss-warn"><strong><?php esc_html_e( 'Privacy notice', 'captcha-for-contact-form-7' ); ?>:</strong> <?php esc_html_e( 'The Mail Log stores personal data. Make sure this is compatible with your privacy policy. All logs are automatically deleted after the configured retention period (default: 30 days).', 'captcha-for-contact-form-7' ); ?></div>

			<!-- 10. FAQ -->
			<h2 id="ss-faq"><?php esc_html_e( '10. FAQ', 'captcha-for-contact-form-7' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'I cannot log in - SilentShield blocks my login', 'captcha-for-contact-form-7' ); ?></summary>
				<p><strong><?php esc_html_e( 'Possible causes:', 'captcha-for-contact-form-7' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'JavaScript is disabled in your browser or a browser plugin blocks the script', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'A caching plugin serves an outdated login page', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
				<p><strong><?php esc_html_e( 'Solution:', 'captcha-for-contact-form-7' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Make sure JavaScript is enabled', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Clear browser and server cache', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Add your IP address to the whitelist', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Emergency: Rename the plugin folder via FTP/SFTP to deactivate', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

			<details>
				<summary><?php esc_html_e( 'Legitimate customers are blocked at WooCommerce Checkout', 'captcha-for-contact-form-7' ); ?></summary>
				<ul>
					<li><?php esc_html_e( 'Check under Analytics which module blocks', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Disable the captcha for checkout under Forms > WooCommerce Checkout', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Increase the timer time (checkout takes longer than a contact form)', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'PayPal/Stripe payments are automatically whitelisted', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

			<details>
				<summary><?php esc_html_e( 'My form does not show a captcha', 'captcha-for-contact-form-7' ); ?></summary>
				<ul>
					<li><?php esc_html_e( 'Check under Settings if the integration for your form plugin is enabled', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Enable "Global Asset Loading" under Settings', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Or add the page URL under "Custom Asset Loading URLs"', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

			<details>
				<summary><?php esc_html_e( 'Spam still gets through', 'captcha-for-contact-form-7' ); ?></summary>
				<ol>
					<li><?php esc_html_e( 'Enable additional protection modules (IP Rate Limiting, Content Filters)', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Switch the captcha method from Honeypot to Math or Image', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Enable the word blacklist with typical spam terms', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Increase the timer minimum time', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Use the SilentShield API for behavior-based detection', 'captcha-for-contact-form-7' ); ?></li>
				</ol>
			</details>

			<details>
				<summary><?php esc_html_e( 'Is SilentShield GDPR compliant?', 'captcha-for-contact-form-7' ); ?></summary>
				<p><?php esc_html_e( 'Yes. SilentShield:', 'captcha-for-contact-form-7' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Sets no cookies', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Does no tracking', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'Stores IP addresses only as hashes (not in plain text) in logs', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'All logs have configurable retention periods with automatic deletion', 'captcha-for-contact-form-7' ); ?></li>
					<li><?php esc_html_e( 'The SilentShield API is optional and must be explicitly enabled', 'captcha-for-contact-form-7' ); ?></li>
				</ul>
			</details>

		</div>
		<?php
	}
}
