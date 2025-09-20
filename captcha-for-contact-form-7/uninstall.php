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

require_once('logger/logger.php');
require_once('core/BaseModul.class.php');

$logger = Logger::getInstance();
require_once('core/protection/captcha/Captcha.class.php');
$Captcha = new Captcha( $logger,'' );
$Captcha->delete_table();

require_once('core/timer/CaptchaTimer.class.php');
$Captcha_Timer = new CaptchaTimer($logger);
$Captcha_Timer->delete_table();

require_once('core/protection/ip/Salt.class.php');
$Salt = new Salt($logger);
$Salt->delete_table();

require_once('core/protection/ip/IPLog.class.php');
$IP_Log = new IPLog($logger);
$IP_Log->delete_table();

require_once('core/protection/ip/IPBan.class.php');
$IP_Ban = new IPBan($logger);
$IP_Ban->delete_table();

delete_option('f12-cf7-captcha-settings');
delete_option('f12_captcha_settings');
delete_option('f12-cf7-captcha-settings-backup');
delete_option('f12-cf7-captcha_version');

/*
 * Clear logs
 */
require_once('core/log/Log_WordPress.class.php');
$Logger = Log_WordPress::get_instance();
$Logger->reset_table();
