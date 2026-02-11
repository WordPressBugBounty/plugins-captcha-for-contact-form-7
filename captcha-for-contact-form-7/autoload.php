<?php
/**
 * PSR-4 style autoloader for the SilentShield plugin.
 *
 * Handles two namespace prefixes:
 *   - f12_cf7_captcha\  → plugin root directory
 *   - Forge12\Shared\   → logger/ directory
 *
 * Special cases:
 *   - f12_cf7_captcha\ui\UI_* (except UI_Manager) → ui/core/
 *   - f12_cf7_captcha\core\Log_WordPress           → core/log/
 */

spl_autoload_register( function ( string $class ) {

	// ── Forge12\Shared\ namespace ────────────────────────────────
	$forge12_prefix = 'Forge12\\Shared\\';

	if ( strpos( $class, $forge12_prefix ) === 0 ) {
		$relative = substr( $class, strlen( $forge12_prefix ) ); // e.g. "Logger" or "LoggerInterface"

		$map = [
			'Logger'          => __DIR__ . '/logger/logger.php',
			'LoggerInterface' => __DIR__ . '/logger/logger.interface.php',
		];

		if ( isset( $map[ $relative ] ) && file_exists( $map[ $relative ] ) ) {
			require_once $map[ $relative ];
		}

		return;
	}

	// ── f12_cf7_captcha\ namespace ───────────────────────────────
	$plugin_prefix = 'f12_cf7_captcha\\';

	if ( strpos( $class, $plugin_prefix ) !== 0 ) {
		return; // Not our namespace.
	}

	$relative_class = substr( $class, strlen( $plugin_prefix ) ); // e.g. "core\BaseModul"
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );                        // e.g. "BaseModul"
	$namespace_path = implode( '/', $parts );                     // e.g. "core"

	// Special case: f12_cf7_captcha\core\Log_WordPress* lives in core/log/
	if ( $namespace_path === 'core' && strpos( $class_name, 'Log_WordPress' ) === 0 ) {
		$namespace_path = 'core/log';
	}

	// Special case: f12_cf7_captcha\ui\UI_* (except UI_Manager) lives in ui/core/
	if ( $namespace_path === 'ui' && strpos( $class_name, 'UI_' ) === 0 && $class_name !== 'UI_Manager' ) {
		$namespace_path = 'ui/core';
	}

	$base_dir = __DIR__ . '/';
	$dir      = $base_dir . ( $namespace_path !== '' ? $namespace_path . '/' : '' );

	// Try both .class.php and .php extensions.
	$candidates = [
		$dir . $class_name . '.class.php',
		$dir . $class_name . '.php',
	];

	foreach ( $candidates as $file ) {
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
} );
