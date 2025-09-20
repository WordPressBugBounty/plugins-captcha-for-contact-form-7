<?php
namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

/**
 * Registriert alle Cronjobs für das Plugin.
 */
function add_cron_jobs() {
	$logger = Logger::getInstance();

	// 🔹 Daily Telemetry Job
	if ( ! wp_next_scheduled( 'f12_cf7_captcha_daily_telemetry' ) ) {
		wp_schedule_event( time(), 'daily', 'f12_cf7_captcha_daily_telemetry' );
		$logger->info( "Cronjob registriert", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'f12_cf7_captcha_daily_telemetry',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cronjob bereits vorhanden", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'f12_cf7_captcha_daily_telemetry'
		] );
	}

	// 🔹 Weekly IP Clear
	if ( ! wp_next_scheduled( 'weeklyIPClear' ) ) {
		wp_schedule_event( time(), 'weekly', 'weeklyIPClear' );
		$logger->info( "Cronjob registriert", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'weeklyIPClear',
			'interval' => 'weekly'
		] );
	} else {
		$logger->debug( "Cronjob bereits vorhanden", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'weeklyIPClear'
		] );
	}

	// 🔹 Daily Captcha Clear
	if ( ! wp_next_scheduled( 'dailyCaptchaClear' ) ) {
		wp_schedule_event( time(), 'daily', 'dailyCaptchaClear' );
		$logger->info( "Cronjob registriert", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'dailyCaptchaClear',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cronjob bereits vorhanden", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'dailyCaptchaClear'
		] );
	}

	// 🔹 Daily Captcha Timer Clear
	if ( ! wp_next_scheduled( 'dailyCaptchaTimerClear' ) ) {
		wp_schedule_event( time(), 'daily', 'dailyCaptchaTimerClear' );
		$logger->info( "Cronjob registriert", [
			'plugin'   => 'f12-cf7-captcha',
			'job'      => 'dailyCaptchaTimerClear',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cronjob bereits vorhanden", [
			'plugin' => 'f12-cf7-captcha',
			'job'    => 'dailyCaptchaTimerClear'
		] );
	}
}
