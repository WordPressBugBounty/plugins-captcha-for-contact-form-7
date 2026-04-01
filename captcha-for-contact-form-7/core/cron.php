<?php
namespace f12_cf7_captcha;

use Forge12\Shared\Logger;
use f12_cf7_captcha\core\log\AuditLog;

/**
 * Registers all cron jobs for the plugin.
 */
/**
 * Register custom cron schedules.
 */
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['monthly'] ) ) {
		$schedules['monthly'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once Monthly', 'captcha-for-contact-form-7' ),
		];
	}
	return $schedules;
} );

function add_cron_jobs() {
	$logger = Logger::getInstance();

	// 🔹 Daily Telemetry Job — only schedule when telemetry is enabled
	$settings = get_option( 'f12-cf7-captcha-settings', [] );
	$telemetry_enabled = ! empty( $settings['global']['telemetry'] ) && (int) $settings['global']['telemetry'] === 1;

	if ( $telemetry_enabled ) {
		if ( ! wp_next_scheduled( 'f12_cf7_captcha_daily_telemetry' ) ) {
			wp_schedule_event( time(), 'daily', 'f12_cf7_captcha_daily_telemetry' );
			$logger->info( "Cron job registered", [
				'plugin'   => 'f12-cf7-captcha',
				'job'      => 'f12_cf7_captcha_daily_telemetry',
				'interval' => 'daily'
			] );
		} else {
			$logger->debug( "Cron job already exists", [
				'plugin' => 'f12-cf7-captcha',
				'job'    => 'f12_cf7_captcha_daily_telemetry'
			] );
		}
	} else {
		// Telemetry disabled — ensure cron is removed
		if ( wp_next_scheduled( 'f12_cf7_captcha_daily_telemetry' ) ) {
			wp_clear_scheduled_hook( 'f12_cf7_captcha_daily_telemetry' );
			$logger->info( "Cron job unscheduled (telemetry disabled)", [
				'plugin' => 'f12-cf7-captcha',
				'job'    => 'f12_cf7_captcha_daily_telemetry',
			] );
		}
	}

	// Weekly IP Clear
	if ( ! wp_next_scheduled( 'weeklyIPClear' ) ) {
		wp_schedule_event( time(), 'weekly', 'weeklyIPClear' );
		$logger->info( "Cron job registered", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'weeklyIPClear',
			'interval' => 'weekly'
		] );
	} else {
		$logger->debug( "Cron job already exists", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'weeklyIPClear'
		] );
	}

	// Daily Captcha Clear
	if ( ! wp_next_scheduled( 'dailyCaptchaClear' ) ) {
		wp_schedule_event( time(), 'daily', 'dailyCaptchaClear' );
		$logger->info( "Cron job registered", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'dailyCaptchaClear',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cron job already exists", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'dailyCaptchaClear'
		] );
	}

	// Monthly API Report
	if ( ! wp_next_scheduled( 'f12_cf7_captcha_monthly_report' ) ) {
		// Schedule for the 1st of next month at 9:00 AM local time
		$next_month = strtotime( 'first day of next month 09:00:00' );
		wp_schedule_event( $next_month, 'monthly', 'f12_cf7_captcha_monthly_report' );
		$logger->info( "Cron job registered", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'f12_cf7_captcha_monthly_report',
			'interval' => 'monthly'
		] );
	}

	// Weekly Email Report
	if ( ! wp_next_scheduled( 'f12_cf7_captcha_weekly_report' ) ) {
		// Schedule for next Monday at 9:00 AM local time
		$next_monday = strtotime( 'next monday 09:00:00' );
		wp_schedule_event( $next_monday, 'weekly', 'f12_cf7_captcha_weekly_report' );
		$logger->info( "Cron job registered", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'f12_cf7_captcha_weekly_report',
			'interval' => 'weekly'
		] );
	} else {
		$logger->debug( "Cron job already exists", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'f12_cf7_captcha_weekly_report'
		] );
	}

	// Daily Captcha Timer Clear
	if ( ! wp_next_scheduled( 'dailyCaptchaTimerClear' ) ) {
		wp_schedule_event( time(), 'daily', 'dailyCaptchaTimerClear' );
		$logger->info( "Cron job registered", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'dailyCaptchaTimerClear',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cron job already exists", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'dailyCaptchaTimerClear'
		] );
	}

	// Register audit bookend hooks for all cron jobs
	register_cron_audit_hooks();
}

/**
 * Run a cron callback wrapped with audit logging (timing + error capture).
 *
 * @param string   $job_name Human-readable job name.
 * @param callable $callback The actual work to execute.
 */
function run_cron_with_audit( string $job_name, callable $callback ): void {
	$start = microtime( true );
	try {
		$callback();
		$duration = round( microtime( true ) - $start, 3 );
		AuditLog::log(
			AuditLog::TYPE_CRON,
			'CRON_SUCCESS',
			AuditLog::SEVERITY_INFO,
			sprintf( 'Cron job "%s" completed in %ss', $job_name, $duration ),
			[ 'job' => $job_name, 'duration_s' => $duration ]
		);
	} catch ( \Throwable $e ) {
		$duration = round( microtime( true ) - $start, 3 );
		AuditLog::log(
			AuditLog::TYPE_CRON,
			'CRON_FAILED',
			AuditLog::SEVERITY_ERROR,
			sprintf( 'Cron job "%s" failed: %s', $job_name, $e->getMessage() ),
			[
				'job'        => $job_name,
				'duration_s' => $duration,
				'error'      => $e->getMessage(),
				'file'       => $e->getFile(),
				'line'       => $e->getLine(),
			]
		);
	}
}

/**
 * Register audit bookend hooks for all plugin cron jobs.
 * Fires a "start" action at priority 0 and a "completed" action at priority 9999
 * to capture timing for each cron hook execution.
 */
function register_cron_audit_hooks(): void {
	$hooks = [
		'weeklyIPClear'                    => 'Weekly IP / Log Cleanup',
		'dailyCaptchaClear'                => 'Daily Captcha Cleanup',
		'dailyCaptchaTimerClear'           => 'Daily Captcha Timer Cleanup',
		'f12_cf7_captcha_daily_telemetry'  => 'Daily Telemetry',
		'f12_cf7_captcha_monthly_report'   => 'Monthly Report',
		'f12_cf7_captcha_weekly_report'    => 'Weekly Report',
	];

	foreach ( $hooks as $hook => $label ) {
		// Record start time at lowest priority
		add_action( $hook, function () use ( $hook ) {
			$GLOBALS['_f12_cron_start_' . $hook] = microtime( true );
		}, 0 );

		// Log completion at highest priority
		add_action( $hook, function () use ( $hook, $label ) {
			$start    = $GLOBALS['_f12_cron_start_' . $hook] ?? microtime( true );
			$duration = round( microtime( true ) - $start, 3 );
			AuditLog::log(
				AuditLog::TYPE_CRON,
				'CRON_COMPLETED',
				AuditLog::SEVERITY_INFO,
				sprintf( 'Cron job "%s" completed in %ss', $label, $duration ),
				[ 'hook' => $hook, 'job' => $label, 'duration_s' => $duration ]
			);
		}, 9999 );
	}
}
