<?php

namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

require_once __DIR__ . '/activation.php';
require_once __DIR__ . '/helpers/uuid.php';
require_once __DIR__ . '/telemetry.php';
require_once __DIR__ . '/cron.php';
require_once __DIR__ . '/upgrade.php';
require_once __DIR__ . '/review.php';

// Bei Aktivierung
register_activation_hook(FORGE12_CAPTCHA_BASENAME, function () {
	$logger = Logger::getInstance();

	try {
		on_activation();
		on_update();

		$logger->info("Plugin aktiviert", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'version'=> FORGE12_CAPTCHA_VERSION,
		]);
	} catch (\Throwable $e) {
		$logger->error("Fehler bei Plugin-Aktivierung", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $e->getMessage(),
			'trace'  => $e->getTraceAsString(),
		]);
		throw $e;
	}
});

// Bei Deaktivierung
function clear_cron_jobs() {
	$logger = Logger::getInstance();

	wp_clear_scheduled_hook('f12_cf7_captcha_daily_telemetry');
	wp_clear_scheduled_hook('weeklyIPClear');
	wp_clear_scheduled_hook('dailyCaptchaClear');
	wp_clear_scheduled_hook('dailyCaptchaTimerClear');

	$logger->info("Plugin deaktiviert, Cronjobs entfernt", [
		'plugin' => FORGE12_CAPTCHA_SLUG,
	]);
}
register_deactivation_hook(FORGE12_CAPTCHA_BASENAME, __NAMESPACE__ . '\\clear_cron_jobs');

// Bei jedem Plugin-Load prüfen, ob Update nötig ist
add_action('plugins_loaded', function () {
	add_cron_jobs();
	$logger = Logger::getInstance();
	try {
		on_update();
		$logger->debug("Update-Check bei Plugin-Load durchgeführt", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
		]);
	} catch (\Throwable $e) {
		$logger->error("Fehler im Update-Check", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $e->getMessage(),
		]);
	}
});
