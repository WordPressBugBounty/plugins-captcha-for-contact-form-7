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
	 * This method checks multiple server variables to determine the client's IP address.
	 * It first checks if the IP address is provided by the 'HTTP_CLIENT_IP' variable.
	 * If not, it then checks if the IP address is provided by the 'HTTP_X_FORWARDED_FOR' variable,
	 * which may indicate that the client is using a proxy server.
	 * If both of the above options are not set, the method falls back to the 'REMOTE_ADDR' variable,
	 * which contains the IP address of the client connecting to the server.
	 *
	 * @return string The IP address of the client.
	 */
	public function get_ip_address(): string {
		//whether ip is from share internet
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = addslashes( $_SERVER['HTTP_CLIENT_IP'] );
		} //whether ip is from proxy
		elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = addslashes( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		} //whether ip is from remote address
		else {
			$ip_address = addslashes( $_SERVER['REMOTE_ADDR'] );
		}

		return $ip_address;
	}
}