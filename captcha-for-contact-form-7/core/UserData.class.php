<?php

namespace f12_cf7_captcha\core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IPAddress
 *
 * @package forge12\contactform7
 */
class UserData extends BaseModul {
	/**
	 * Retrieve the IP address of the client.
	 *
	 * By default, only REMOTE_ADDR is used because forwarded headers (HTTP_CLIENT_IP,
	 * HTTP_X_FORWARDED_FOR) are trivially spoofable by clients and would allow attackers
	 * to bypass IP-based protections.
	 *
	 * If the site runs behind a trusted reverse proxy or load balancer, define one of
	 * the following constants in wp-config.php to enable header-based IP detection:
	 *
	 *   define( 'F12_TRUSTED_PROXY_HEADER', 'HTTP_X_FORWARDED_FOR' );
	 *   define( 'F12_TRUSTED_PROXY_HEADER', 'HTTP_CLIENT_IP' );
	 *   define( 'F12_TRUSTED_PROXY_HEADER', 'HTTP_X_REAL_IP' );
	 *
	 * @return string The IP address of the client.
	 */
	public function get_ip_address(): string
	{
		$this->get_logger()->info('Attempting to determine the user IP address.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$ip_address = '0.0.0.0';

		$allowed_headers = [
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'HTTP_X_REAL_IP',
		];

		// Only trust forwarded headers when explicitly configured via constant.
		if ( defined( 'F12_TRUSTED_PROXY_HEADER' ) && in_array( F12_TRUSTED_PROXY_HEADER, $allowed_headers, true ) ) {
			$header = F12_TRUSTED_PROXY_HEADER;

			if ( ! empty( $_SERVER[ $header ] ) ) {
				// X-Forwarded-For can contain a comma-separated list; the first entry is the client IP.
				$ip_addresses = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip_address   = trim( reset( $ip_addresses ) );

				$this->get_logger()->debug( 'IP address found via trusted proxy header.', [
					'header' => $header,
				] );
			}
		}

		// Fallback to REMOTE_ADDR (always trustworthy, set by the web server).
		if ( $ip_address === '0.0.0.0' && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$this->get_logger()->debug( 'IP address found via REMOTE_ADDR.' );
		}

		// Validate that the result is actually an IP address.
		$sanitized_ip = filter_var( $ip_address, FILTER_VALIDATE_IP );

		if ( $sanitized_ip === false ) {
			$this->get_logger()->warning( 'Determined IP address is invalid, falling back to 0.0.0.0.', [
				'raw_ip' => sanitize_text_field( $ip_address ),
			] );
			$sanitized_ip = '0.0.0.0';
		}

		$this->get_logger()->info( 'IP address successfully determined.', [
			'ip' => $sanitized_ip,
		] );

		return $sanitized_ip;
	}
}
