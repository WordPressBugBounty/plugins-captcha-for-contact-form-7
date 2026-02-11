<?php

namespace f12_cf7_captcha;

use Forge12\Shared\Logger;

require_once __DIR__ . '/activation.php';
require_once __DIR__ . '/helpers/uuid.php';
require_once __DIR__ . '/telemetry.php';
require_once __DIR__ . '/cron.php';
require_once __DIR__ . '/upgrade.php';
require_once __DIR__ . '/review.php';

/**
 * Allow data: protocol in wp_kses for captcha images
 * This is needed because some themes/plugins process images and break data: URLs
 */
add_filter( 'kses_allowed_protocols', function ( $protocols ) {
	if ( ! in_array( 'data', $protocols, true ) ) {
		$protocols[] = 'data';
	}
	return $protocols;
} );

/**
 * Disable WordPress native lazy loading for captcha images
 */
add_filter( 'wp_img_tag_add_loading_attr', function ( $value, $image, $context ) {
	// If the image is a captcha image (has our class or is a data: URL), disable lazy loading
	if ( strpos( $image, 'captcha-image' ) !== false || strpos( $image, 'data:image' ) !== false ) {
		return false;
	}
	return $value;
}, 10, 3 );

/**
 * Disable Avada lazy loading for captcha images
 */
add_filter( 'avada_lazyload_exclude_images', function ( $exclude ) {
	$exclude[] = 'captcha-image';
	$exclude[] = 'no-lazy';
	$exclude[] = 'skip-lazy';
	return $exclude;
} );

/**
 * Prevent other plugins from modifying captcha image output
 */
add_filter( 'the_content', function ( $content ) {
	// Restore any broken data: URLs in captcha images
	$content = preg_replace(
		'/<img([^>]*class="[^"]*(?:captcha-image|no-lazy|skip-lazy)[^"]*"[^>]*)src="image\/png;base64,/',
		'<img$1src="data:image/png;base64,',
		$content
	);
	return $content;
}, 999 );

// On activation
register_activation_hook(FORGE12_CAPTCHA_BASENAME, function () {
	$logger = Logger::getInstance();

	try {
		on_activation();
		on_update();

		$logger->info("Plugin activated", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'version'=> FORGE12_CAPTCHA_VERSION,
		]);
	} catch (\Throwable $e) {
		$logger->error("Error during plugin activation", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $e->getMessage(),
			'trace'  => $e->getTraceAsString(),
		]);
		throw $e;
	}
});

// On deactivation
function clear_cron_jobs() {
	$logger = Logger::getInstance();

	wp_clear_scheduled_hook('f12_cf7_captcha_daily_telemetry');
	wp_clear_scheduled_hook('weeklyIPClear');
	wp_clear_scheduled_hook('dailyCaptchaClear');
	wp_clear_scheduled_hook('dailyCaptchaTimerClear');

	$logger->info("Plugin deactivated, cron jobs removed", [
		'plugin' => FORGE12_CAPTCHA_SLUG,
	]);
}
register_deactivation_hook(FORGE12_CAPTCHA_BASENAME, __NAMESPACE__ . '\\clear_cron_jobs');

// Check on every plugin load if an update is needed
add_action('plugins_loaded', function () {
	add_cron_jobs();
	$logger = Logger::getInstance();
	try {
		on_update();
		$logger->debug("Update check performed on plugin load", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
		]);
	} catch (\Throwable $e) {
		$logger->error("Error in update check", [
			'plugin' => FORGE12_CAPTCHA_SLUG,
			'error'  => $e->getMessage(),
		]);
	}
});
