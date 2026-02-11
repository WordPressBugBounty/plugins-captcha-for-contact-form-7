<?php

namespace f12_cf7_captcha\core\log;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Responsible to create the Post Type for the logs
 */
class Array_Formatter
{
	/**
	 * Check if an array key is likely a password field
	 */
	private static function is_password_field(string $key): bool {
		$key = strtolower($key);
		return in_array($key, ['password', 'pass', 'pwd', 'passwort', 'pw'], true);
	}

	/**
	 * Mask password (replace completely with asterisks)
	 */
	private static function mask_password(string $password): string {
		$len = strlen($password);
		if ($len === 0) {
			return '';
		}
		// Optional: keep first and last letter visible
		if ($len > 2) {
			return substr($password, 0, 1) . str_repeat('*', $len - 2) . substr($password, -1);
		}
		return str_repeat('*', $len);
	}

	public static function to_string($data, $delimiter = '', $use_key_as_label = false)
	{
		$response = '';

		foreach ($data as $key => $value) {
			if (true === $use_key_as_label) {
				$response .= $key . ': ';
			}

			if (is_array($value)) {
				$value = self::to_string($value, $delimiter, $use_key_as_label);
			}

			// Apply masking
			if (is_string($value)) {
				if (self::is_password_field($key)) {
					$value = self::mask_password($value);
				}
				elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
					$value = self::mask_email($value);
				}
				elseif (filter_var($value, FILTER_VALIDATE_IP)) {
					$value = self::mask_ip($value);
				}
			}

			$response .= $value . $delimiter;
		}

		\Forge12\Shared\Logger::getInstance()->debug("Array_Formatter used", [
			'plugin'  => 'f12-cf7-captcha',
			'preview' => mb_substr($response, 0, 120) . (strlen($response) > 120 ? '...' : '')
		]);

		return $response;
	}

	/**
	 * Mask email (as in Logger)
	 */
	private static function mask_email(string $email): string {
		[$user, $domain] = explode('@', $email, 2);
		$len = strlen($user);
		if ($len <= 2) {
			$maskedUser = substr($user, 0, 1) . '*';
		} else {
			$maskedUser = substr($user, 0, 1) . str_repeat('*', $len - 2) . substr($user, -1);
		}
		return $maskedUser . '@' . $domain;
	}

	/**
	 * Mask IP (as in Logger)
	 */
	private static function mask_ip(string $ip): string {
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

}