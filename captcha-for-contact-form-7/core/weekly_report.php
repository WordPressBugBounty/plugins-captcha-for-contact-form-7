<?php

namespace f12_cf7_captcha;

use f12_cf7_captcha\core\log\BlockLog;
use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly Email Report — sends a summary email to the admin
 * for standalone (non-API) users. Shows blocks this week,
 * top reasons, breakdown by protection type, and an upsell
 * for the SilentShield API.
 */

/**
 * Hook: Send the weekly report email.
 */
add_action( 'f12_cf7_captcha_weekly_report', __NAMESPACE__ . '\\send_weekly_report' );

/**
 * Send the weekly protection report email.
 */
function send_weekly_report(): void {
	$logger = Logger::getInstance();

	try {
		$instance = CF7Captcha::get_instance();
		$api_key  = $instance->get_settings( 'beta_captcha_api_key', 'beta' );

		// Only send for NON-API (standalone) users
		if ( ! empty( $api_key ) ) {
			$logger->debug( 'Weekly report skipped: API key present (use monthly report instead)', [ 'plugin' => 'f12-cf7-captcha' ] );
			return;
		}

		// Only send if the admin has opted in
		$enabled = (int) $instance->get_settings( 'protection_weekly_report', 'global' );
		if ( $enabled !== 1 ) {
			$logger->debug( 'Weekly report skipped: not enabled', [ 'plugin' => 'f12-cf7-captcha' ] );
			return;
		}

		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Gather stats from block log (last 7 days)
		$block_log       = new BlockLog( $logger );
		$overview        = $block_log->get_overview();
		$by_reason       = $block_log->get_summary_by_reason( 7 );
		$by_protection   = $block_log->get_summary_by_protection( 7 );

		$total_blocked = (int) ( $overview['week'] ?? 0 );

		// Get telemetry counters for total requests
		$counters       = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
		$total_requests = (int) ( $counters['checks_total'] ?? 0 );

		// Block rate
		$block_rate = $total_requests > 0 ? round( ( $total_blocked / $total_requests ) * 100, 1 ) : 0;

		// Top 3 reason codes
		$top_reasons        = array_slice( $by_reason, 0, 3 );
		$total_reason_count = 0;
		foreach ( $by_reason as $r ) {
			$total_reason_count += (int) $r['count'];
		}

		// Protection type breakdown
		$total_protection_count = 0;
		foreach ( $by_protection as $p ) {
			$total_protection_count += (int) $p['count'];
		}

		// Build email
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[SilentShield] Weekly Protection Report — %s', 'captcha-for-contact-form-7' ),
			$site_name
		);

		$body = build_weekly_report_html(
			$site_name,
			$site_url,
			$total_requests,
			$total_blocked,
			$block_rate,
			$top_reasons,
			$total_reason_count,
			$by_protection,
			$total_protection_count
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: SilentShield <noreply@silentshield.io>',
		];

		$sent = wp_mail( $admin_email, $subject, $body, $headers );

		if ( $sent ) {
			$logger->info( 'Weekly report sent', [
				'plugin' => 'f12-cf7-captcha',
				'to'     => $admin_email,
				'blocks' => $total_blocked,
			] );
			update_option( 'f12_cf7_captcha_last_weekly_report', current_time( 'mysql' ) );
		} else {
			$logger->error( 'Weekly report failed to send', [
				'plugin' => 'f12-cf7-captcha',
				'to'     => $admin_email,
			] );

			core\log\AuditLog::log(
				core\log\AuditLog::TYPE_CRON,
				'WEEKLY_REPORT_SEND_FAILED',
				core\log\AuditLog::SEVERITY_WARNING,
				'Weekly report email failed to send',
				[ 'to' => $admin_email ]
			);
		}
	} catch ( \Throwable $e ) {
		$logger->error( 'Weekly report cron failed with exception', [
			'plugin' => 'f12-cf7-captcha',
			'error'  => $e->getMessage(),
		] );

		core\log\AuditLog::log(
			core\log\AuditLog::TYPE_CRON,
			'CRON_FAILED',
			core\log\AuditLog::SEVERITY_ERROR,
			sprintf( 'Weekly report cron failed: %s', $e->getMessage() ),
			[ 'job' => 'send_weekly_report', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ]
		);
	}
}

/**
 * Build the HTML email body for the weekly report.
 */
function build_weekly_report_html(
	string $site_name,
	string $site_url,
	int $total_requests,
	int $total_blocked,
	float $block_rate,
	array $top_reasons,
	int $total_reason_count,
	array $by_protection,
	int $total_protection_count
): string {

	// Build reason codes rows
	$reason_rows = '';
	foreach ( $top_reasons as $r ) {
		$code  = esc_html( str_replace( '_', ' ', $r['reason_code'] ?? '' ) );
		$count = (int) ( $r['count'] ?? 0 );
		$pct   = $total_reason_count > 0 ? round( ( $count / $total_reason_count ) * 100 ) : 0;
		$reason_rows .= "<tr><td style=\"padding:8px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;\">{$code}</td>";
		$reason_rows .= "<td style=\"padding:8px 12px;font-size:13px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600;\">{$count}</td>";
		$reason_rows .= "<td style=\"padding:8px 12px;font-size:13px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600;\">{$pct}%</td></tr>";
	}

	if ( empty( $reason_rows ) ) {
		$reason_rows = '<tr><td colspan="3" style="padding:12px;text-align:center;color:#94a3b8;">No blocks recorded this week</td></tr>';
	}

	// Build protection breakdown rows
	$protection_rows = '';
	foreach ( $by_protection as $p ) {
		$name  = esc_html( ucfirst( $p['protection'] ?? '' ) );
		$count = (int) ( $p['count'] ?? 0 );
		$pct   = $total_protection_count > 0 ? round( ( $count / $total_protection_count ) * 100 ) : 0;
		$protection_rows .= "<tr><td style=\"padding:8px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;\">{$name}</td>";
		$protection_rows .= "<td style=\"padding:8px 12px;font-size:13px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600;\">{$pct}%</td></tr>";
	}

	if ( empty( $protection_rows ) ) {
		$protection_rows = '<tr><td colspan="2" style="padding:12px;text-align:center;color:#94a3b8;">&mdash;</td></tr>';
	}

	// Labels
	$header_label          = esc_html__( 'Your Weekly Protection Report', 'captcha-for-contact-form-7' );
	$total_checks_label    = esc_html__( 'Total Checks', 'captcha-for-contact-form-7' );
	$blocked_label         = esc_html__( 'Blocked', 'captcha-for-contact-form-7' );
	$rate_label            = esc_html__( 'Block Rate', 'captcha-for-contact-form-7' );
	$reasons_label         = esc_html__( 'Top Block Reasons', 'captcha-for-contact-form-7' );
	$protection_label      = esc_html__( 'Breakdown by Protection Type', 'captcha-for-contact-form-7' );
	$missing_label         = esc_html__( 'What You\'re Missing', 'captcha-for-contact-form-7' );
	$missing_desc          = esc_html__( 'With SilentShield API you\'d also get:', 'captcha-for-contact-form-7' );
	$feature_behavior      = esc_html__( 'AI Behavior Analysis', 'captcha-for-contact-form-7' );
	$feature_fingerprint   = esc_html__( 'Browser Fingerprinting', 'captcha-for-contact-form-7' );
	$feature_adaptive      = esc_html__( 'Adaptive Challenges', 'captcha-for-contact-form-7' );
	$feature_score         = esc_html__( 'Score Breakdown', 'captcha-for-contact-form-7' );
	$cta_label             = esc_html__( 'Start Free', 'captcha-for-contact-form-7' );
	$footer_text           = esc_html__( 'You received this email because weekly reports are enabled on', 'captcha-for-contact-form-7' );

	$formatted_requests = number_format_i18n( $total_requests );
	$formatted_blocked  = number_format_i18n( $total_blocked );

	$cta_url = esc_url( 'https://silentshield.io/register?utm_source=plugin&utm_medium=email&utm_campaign=weekly_report&utm_content=cta_button' );

	return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">

<!-- Header -->
<tr><td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:28px 32px;text-align:center;">
<div style="font-size:22px;font-weight:700;color:#fff;">&#128737; SilentShield</div>
<div style="font-size:15px;font-weight:600;color:rgba(255,255,255,0.95);margin-top:8px;">{$header_label}</div>
<div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:4px;">{$site_name}</div>
</td></tr>

<!-- Stats Grid -->
<tr><td style="padding:24px 32px 12px;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="33%" style="text-align:center;padding:12px;">
<div style="font-size:24px;font-weight:700;color:#1e40af;">{$formatted_requests}</div>
<div style="font-size:11px;color:#64748b;">{$total_checks_label}</div>
</td>
<td width="33%" style="text-align:center;padding:12px;">
<div style="font-size:24px;font-weight:700;color:#dc2626;">{$formatted_blocked}</div>
<div style="font-size:11px;color:#64748b;">{$blocked_label}</div>
</td>
<td width="33%" style="text-align:center;padding:12px;">
<div style="font-size:24px;font-weight:700;color:#d97706;">{$block_rate}%</div>
<div style="font-size:11px;color:#64748b;">{$rate_label}</div>
</td>
</tr>
</table>
</td></tr>

<!-- Top Block Reasons -->
<tr><td style="padding:12px 32px;">
<div style="font-size:14px;font-weight:600;margin-bottom:8px;">{$reasons_label}</div>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
{$reason_rows}
</table>
</td></tr>

<!-- Protection Type Breakdown -->
<tr><td style="padding:12px 32px;">
<div style="font-size:14px;font-weight:600;margin-bottom:8px;">{$protection_label}</div>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
{$protection_rows}
</table>
</td></tr>

<!-- What You're Missing (Upsell) -->
<tr><td style="padding:16px 32px;">
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:20px;">
<div style="font-size:14px;font-weight:700;color:#1e40af;margin-bottom:8px;">{$missing_label}</div>
<div style="font-size:13px;color:#475569;margin-bottom:12px;">{$missing_desc}</div>
<table cellpadding="0" cellspacing="0" style="margin-bottom:4px;">
<tr><td style="padding:3px 0;font-size:13px;color:#334155;">&#10003; {$feature_behavior}</td></tr>
<tr><td style="padding:3px 0;font-size:13px;color:#334155;">&#10003; {$feature_fingerprint}</td></tr>
<tr><td style="padding:3px 0;font-size:13px;color:#334155;">&#10003; {$feature_adaptive}</td></tr>
<tr><td style="padding:3px 0;font-size:13px;color:#334155;">&#10003; {$feature_score}</td></tr>
</table>
</div>
</td></tr>

<!-- CTA -->
<tr><td style="padding:20px 32px 28px;text-align:center;">
<a href="{$cta_url}" style="display:inline-block;background:#2563eb;color:#fff;padding:14px 32px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">{$cta_label} &rarr;</a>
</td></tr>

<!-- Footer -->
<tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;">
<div style="font-size:11px;color:#94a3b8;">{$footer_text} {$site_name}.</div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}
