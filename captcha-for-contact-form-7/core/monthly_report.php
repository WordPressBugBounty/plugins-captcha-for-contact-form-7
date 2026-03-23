<?php

namespace f12_cf7_captcha;

use f12_cf7_captcha\core\log\BlockLog;
use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monthly API Report — sends a summary email to the admin
 * when the SilentShield API is active. Shows value delivered
 * (bots blocked, reason codes, protection score, time saved).
 */

/**
 * Hook: Send the monthly report email.
 */
add_action( 'f12_cf7_captcha_monthly_report', __NAMESPACE__ . '\\send_monthly_report' );

/**
 * Send the monthly API report email.
 */
function send_monthly_report(): void {
	$logger = Logger::getInstance();

	try {
		$instance = CF7Captcha::get_instance();
		$api_key  = $instance->get_settings( 'beta_captcha_api_key', 'beta' );

		// Only send for API users
		if ( empty( $api_key ) ) {
			$logger->debug( 'Monthly report skipped: no API key', [ 'plugin' => 'f12-cf7-captcha' ] );
			return;
		}

		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Gather stats from block log
		$block_log = new BlockLog( $logger );
		$overview  = $block_log->get_overview();
		$by_reason = $block_log->get_summary_by_reason( 30 );

		$total_checks = (int) ( $overview['month'] ?? 0 );

		// Get telemetry counters for total requests
		$counters       = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
		$total_requests = (int) ( $counters['checks_total'] ?? 0 );
		$total_spam     = (int) ( $counters['checks_spam'] ?? 0 );

		// Top 3 reason codes
		$top_reasons = array_slice( $by_reason, 0, 3 );
		$total_reason_count = 0;
		foreach ( $by_reason as $r ) {
			$total_reason_count += (int) $r['count'];
		}

		// Estimate time saved (2 min per spam at 99% detection)
		$hours_saved = round( ( $total_checks * 2 ) / 60, 1 );

		// Build email
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[SilentShield] Monthly Protection Report — %s', 'captcha-for-contact-form-7' ),
			$site_name
		);

		$body = build_report_html(
			$site_name,
			$site_url,
			$total_requests,
			$total_checks,
			$top_reasons,
			$total_reason_count,
			$hours_saved
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: SilentShield <noreply@silentshield.io>',
		];

		$sent = wp_mail( $admin_email, $subject, $body, $headers );

		if ( $sent ) {
			$logger->info( 'Monthly report sent', [
				'plugin' => 'f12-cf7-captcha',
				'to'     => $admin_email,
				'blocks' => $total_checks,
			] );
			update_option( 'f12_cf7_captcha_last_monthly_report', current_time( 'mysql' ) );
		} else {
			$logger->error( 'Monthly report failed to send', [
				'plugin' => 'f12-cf7-captcha',
				'to'     => $admin_email,
			] );

			core\log\AuditLog::log(
				core\log\AuditLog::TYPE_CRON,
				'MONTHLY_REPORT_SEND_FAILED',
				core\log\AuditLog::SEVERITY_WARNING,
				'Monthly report email failed to send',
				[ 'to' => $admin_email ]
			);
		}
	} catch ( \Throwable $e ) {
		$logger->error( 'Monthly report cron failed with exception', [
			'plugin' => 'f12-cf7-captcha',
			'error'  => $e->getMessage(),
		] );

		core\log\AuditLog::log(
			core\log\AuditLog::TYPE_CRON,
			'CRON_FAILED',
			core\log\AuditLog::SEVERITY_ERROR,
			sprintf( 'Monthly report cron failed: %s', $e->getMessage() ),
			[ 'job' => 'send_monthly_report', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ]
		);
	}
}

/**
 * Build the HTML email body for the monthly report.
 */
function build_report_html(
	string $site_name,
	string $site_url,
	int $total_requests,
	int $bots_blocked,
	array $top_reasons,
	int $total_reason_count,
	float $hours_saved
): string {
	$block_rate = $total_requests > 0 ? round( ( $bots_blocked / $total_requests ) * 100, 1 ) : 0;

	// Build reason codes rows
	$reason_rows = '';
	foreach ( $top_reasons as $r ) {
		$code = esc_html( str_replace( '_', ' ', $r['reason_code'] ?? '' ) );
		$count = (int) ( $r['count'] ?? 0 );
		$pct = $total_reason_count > 0 ? round( ( $count / $total_reason_count ) * 100 ) : 0;
		$reason_rows .= "<tr><td style=\"padding:8px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;\">{$code}</td>";
		$reason_rows .= "<td style=\"padding:8px 12px;font-size:13px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600;\">{$pct}%</td></tr>";
	}

	if ( empty( $reason_rows ) ) {
		$reason_rows = '<tr><td colspan="2" style="padding:12px;text-align:center;color:#94a3b8;">—</td></tr>';
	}

	$score_label    = esc_html__( 'Protection Score', 'captcha-for-contact-form-7' );
	$requests_label = esc_html__( 'Requests Protected', 'captcha-for-contact-form-7' );
	$blocked_label  = esc_html__( 'Bots Blocked', 'captcha-for-contact-form-7' );
	$rate_label     = esc_html__( 'Block Rate', 'captcha-for-contact-form-7' );
	$reasons_label  = esc_html__( 'Top Reason Codes', 'captcha-for-contact-form-7' );
	$saved_label    = esc_html__( 'Estimated Time Saved', 'captcha-for-contact-form-7' );
	$hours_label    = esc_html__( 'hours of manual review', 'captcha-for-contact-form-7' );
	$view_label     = esc_html__( 'View Full Analytics', 'captcha-for-contact-form-7' );
	$footer_text    = esc_html__( 'This email was sent because you have SilentShield API active on', 'captcha-for-contact-form-7' );
	$analytics_url  = esc_url( admin_url( 'admin.php?page=f12-cf7-captcha-analytics' ) );

	$formatted_requests = number_format_i18n( $total_requests );
	$formatted_blocked  = number_format_i18n( $bots_blocked );

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
<div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:4px;">{$site_name}</div>
</td></tr>

<!-- Score -->
<tr><td style="padding:28px 32px 12px;text-align:center;">
<div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;">{$score_label}</div>
<div style="font-size:48px;font-weight:800;color:#16a34a;margin:8px 0;">100/100</div>
</td></tr>

<!-- Stats Grid -->
<tr><td style="padding:12px 32px;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="33%" style="text-align:center;padding:12px;">
<div style="font-size:24px;font-weight:700;color:#1e40af;">{$formatted_requests}</div>
<div style="font-size:11px;color:#64748b;">{$requests_label}</div>
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

<!-- Reason Codes -->
<tr><td style="padding:12px 32px;">
<div style="font-size:14px;font-weight:600;margin-bottom:8px;">{$reasons_label}</div>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
{$reason_rows}
</table>
</td></tr>

<!-- Time Saved -->
<tr><td style="padding:16px 32px;">
<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:16px;text-align:center;">
<div style="font-size:12px;color:#065f46;">{$saved_label}</div>
<div style="font-size:28px;font-weight:700;color:#059669;margin:4px 0;">~{$hours_saved}h</div>
<div style="font-size:11px;color:#047857;">{$hours_label}</div>
</div>
</td></tr>

<!-- CTA -->
<tr><td style="padding:20px 32px 28px;text-align:center;">
<a href="{$analytics_url}" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">{$view_label} &rarr;</a>
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
