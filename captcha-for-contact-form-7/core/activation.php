<?php
namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

use f12_cf7_captcha\core\protection\captcha\Captcha;
use f12_cf7_captcha\core\protection\ip\IPBan;
use f12_cf7_captcha\core\protection\ip\IPLog;
use f12_cf7_captcha\core\protection\ip\Salt;
use f12_cf7_captcha\core\timer\CaptchaTimer;

/**
 * Create all required tables to store the captcha codes within the database
 */
function on_activation() {
	$logger = Logger::getInstance();

	try {
		// User Data
		$Captcha = new Captcha( $logger, '' );
		$Captcha->create_table();
		$logger->info( "Table created", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => 'captcha'
		] );

		$Salt = new Salt( $logger );
		$Salt->create_table();
		$logger->info( "Table created", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => 'salt'
		] );

		$Captcha_Timer = new CaptchaTimer( $logger );
		$Captcha_Timer->create_table();
		$logger->info( "Table created", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => 'captcha_timer'
		] );

		$IP_Log = new IPLog( $logger );
		$IP_Log->create_table();
		$logger->info( "Table created", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => 'ip_log'
		] );

		$IP_Ban = new IPBan( $logger );
		$IP_Ban->create_table();
		$logger->info( "Table created", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => 'ip_ban'
		] );

		if ( ! get_option( 'f12_cf7_captcha_installed_at' ) ) {
			update_option( 'f12_cf7_captcha_installed_at', time() );
		}

	} catch ( \Throwable $e ) {
		$logger->error( "Error during plugin activation", [
			'plugin' => 'f12-cf7-captcha',
			'error'  => $e->getMessage(),
			'trace'  => $e->getTraceAsString()
		] );
		throw $e; // important: do not swallow errors
	}
}