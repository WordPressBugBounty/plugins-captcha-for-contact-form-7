<?php
namespace f12_cf7_captcha;

use f12_cf7_captcha\core\Log_WordPress;
use f12_cf7_captcha\core\protection\captcha\Captcha;
use f12_cf7_captcha\core\protection\ip\IPBan;
use f12_cf7_captcha\core\protection\ip\IPLog;
use f12_cf7_captcha\core\protection\ip\Salt;
use f12_cf7_captcha\core\timer\CaptchaTimer;
use Forge12\Shared\Logger;

if(!defined('WP_UNINSTALL_PLUGIN')){
	die;
}

require_once('autoload.php');
require_once('logger/logger.php');

$logger = Logger::getInstance();

$Captcha = new Captcha( $logger,'' );
$Captcha->delete_table();

$Captcha_Timer = new CaptchaTimer($logger);
$Captcha_Timer->delete_table();

$Salt = new Salt($logger);
$Salt->delete_table();

$IP_Log = new IPLog($logger);
$IP_Log->delete_table();

$IP_Ban = new IPBan($logger);
$IP_Ban->delete_table();

delete_option('f12-cf7-captcha-settings');
delete_option('f12_captcha_settings');
delete_option('f12-cf7-captcha-settings-backup');
delete_option('f12-cf7-captcha_version');

/*
 * Clear logs
 */
$Logger = Log_WordPress::get_instance();
$Logger->reset_table();
