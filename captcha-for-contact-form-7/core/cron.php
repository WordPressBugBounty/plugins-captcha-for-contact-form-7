<?php
namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

/**
 * Registers all cron jobs for the plugin.
 */
function add_cron_jobs() {
	$logger = Logger::getInstance();

	// 🔹 Daily Telemetry Job
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
}
