<?php
namespace Forge12\Shared;

// Sicherstellen, dass Interface existiert
if (! interface_exists('Forge12\Shared\LoggerInterface')) {
	require_once __DIR__ . '/logger.interface.php';
}

if (!defined('F12_DEBUG')) {
	define('F12_DEBUG', false);
}

if (!defined('F12_DEBUG_LOG_LEVEL')) {
	define('F12_DEBUG_LOG_LEVEL', 200);
}

if (!class_exists('Forge12\Shared\Logger')) {
	class Logger implements LoggerInterface
	{
		private static $instance;
		private $log_file;
		private $log_level;
		private $log_dir;

		const DEBUG    = 100;
		const INFO     = 200;
		const NOTICE   = 250;
		const WARNING  = 300;
		const ERROR    = 400;
		const CRITICAL = 500;

		private function __construct()
		{
			// Debug aus → Logger ist komplett deaktiviert
			if (!defined('F12_DEBUG') || !F12_DEBUG) {
				return;
			}

			// Sicher WordPress Upload-DIR holen
			$upload_dir = wp_upload_dir();

			if (empty($upload_dir['basedir']) || !is_string($upload_dir['basedir'])) {
				$base = WP_CONTENT_DIR . '/uploads';
			} else {
				$base = $upload_dir['basedir'];
			}

			// Pfad normalisieren
			$base = $this->normalizePath($base);

			// Log-Ordner festlegen
			$this->log_dir = $this->normalizePath($base . '/f12-logs');

			// Falls Pfad relativ ist → absolut machen
			$this->log_dir = $this->ensureAbsolutePath($this->log_dir);

			// Sicherstellen, dass Verzeichnis existiert
			if (!is_dir($this->log_dir)) {
				wp_mkdir_p($this->log_dir);
			}

			// Logdatei definieren
			$this->log_file = $this->normalizePath(
				$this->log_dir . '/plugins-' . date('Y-m-d') . '.log'
			);

			// Logdatei 100% absolut sicher machen
			$this->log_file = $this->ensureAbsolutePath($this->log_file);

			// Log-Level
			$this->log_level = defined('F12_DEBUG_LOG_LEVEL')
				? F12_DEBUG_LOG_LEVEL
				: self::INFO;
		}

		public static function getInstance()
		{
			if (!self::$instance) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Pfade bereinigen (Windows + Linux)
		 */
		private function normalizePath(string $path): string
		{
			// Backslashes → Slashes
			$path = str_replace('\\', '/', $path);

			// Doppelte Slashes entfernen, außer nach C:
			$path = preg_replace('#(?<!:)/{2,}#', '/', $path);

			return rtrim($path, '/');
		}

		/**
		 * ABSOLUTEN Pfad erzwingen
		 */
		private function ensureAbsolutePath(string $path): string
		{
			$path = $this->normalizePath($path);

			// Linux absolute Pfade: /var/www/...
			if (substr($path, 0, 1) === '/') {
				return $path;
			}

			// Windows absolute Pfade: C:/xampp/...
			if (preg_match('#^[A-Za-z]:/#', $path)) {
				return $path;
			}

			// → Pfad ist relativ → ABSPATH davor hängen
			$absolute = $this->normalizePath(ABSPATH . '/' . $path);

			return $absolute;
		}


		private function sanitizeContext(array $context): array
		{
			foreach ($context as $key => $value) {
				if (in_array(strtolower($key), ['ip', 'user_ip'])) {
					$context[$key] = $this->mask_ip($value);
				}
				if (in_array(strtolower($key), ['email', 'user_email'])) {
					$context[$key] = $this->mask_email($value);
				}
				if (in_array(strtolower($key), ['password', 'pwd'])) {
					$context[$key] = $this->mask_password($value);
				}
			}
			return $context;
		}

		private function mask_password(string $password): string
		{
			return '********';
		}

		private function mask_email(string $email): string
		{
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return 'invalid';
			}
			[$user, $domain] = explode('@', $email, 2);
			$len = strlen($user);
			if ($len <= 2) {
				$maskedUser = substr($user, 0, 1) . '*';
			} else {
				$maskedUser = substr($user, 0, 1) . str_repeat('*', $len - 2) . substr($user, -1);
			}
			return $maskedUser . '@' . $domain;
		}

		private function mask_ip(string $ip): string
		{
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$parts = explode('.', $ip);
				$parts[3] = '0';
				return implode('.', $parts);
			}
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				return substr($ip, 0, 20) . '::';
			}
			return 'unknown';
		}

		private function rotateLogs(int $maxSize = 52428800, int $maxFiles = 5): void
		{
			if (file_exists($this->log_file) && filesize($this->log_file) > $maxSize) {

				for ($i = $maxFiles - 1; $i >= 1; $i--) {
					$old = $this->log_file . '.' . $i;
					$new = $this->log_file . '.' . ($i + 1);
					if (file_exists($old)) {
						rename($old, $new);
					}
				}

				rename($this->log_file, $this->log_file . '.1');
			}
		}

		private function cleanupOldLogs(int $days = 7): void
		{
			foreach (glob($this->log_dir . '/plugins-*.log*') as $file) {
				if (filemtime($file) < strtotime("-{$days} days")) {
					@unlink($file);
				}
			}
		}

		private function writeLog($level, $levelName, $message, array $context = [])
		{
			if (!defined('F12_DEBUG') || !F12_DEBUG) {
				return;
			}

			if ($level < $this->log_level) {
				return;
			}

			// Vor jedem Schreiben absolut sicherstellen
			$this->log_file = $this->ensureAbsolutePath($this->log_file);

			// Cleanup & Rotation
			$this->cleanupOldLogs();
			$this->rotateLogs();

			$context = $this->sanitizeContext($context);

			$time   = date('Y-m-d H:i:s');
			$plugin = $context['plugin'] ?? 'unknown';

			$msg = sprintf(
				"[%s] [%s] [%s] %s %s\n",
				$time,
				strtoupper($levelName),
				$plugin,
				$message,
				$context ? json_encode($context) : ''
			);

			// Schreiben in absolut sicheren Pfad
			error_log($msg, 3, $this->log_file);
		}

		public function debug($message, array $context = []): void
		{
			$this->writeLog(self::DEBUG, 'DEBUG', $message, $context);
		}

		public function info($message, array $context = []): void
		{
			$this->writeLog(self::INFO, 'INFO', $message, $context);
		}

		public function error($message, array $context = []): void
		{
			$this->writeLog(self::ERROR, 'ERROR', $message, $context);
		}

		public function warning($message, array $context = []): void
		{
			$this->writeLog(self::WARNING, 'WARNING', $message, $context);
		}

		public function notice($message, array $context = []): void
		{
			$this->writeLog(self::NOTICE, 'NOTICE', $message, $context);
		}

		public function critical($message, array $context = []): void
		{
			$this->writeLog(self::CRITICAL, 'CRITICAL', $message, $context);
		}
	}
}
