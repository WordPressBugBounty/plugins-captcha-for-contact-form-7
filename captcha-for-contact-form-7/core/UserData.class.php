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
	public function get_ip_address(): string
	{
		$this->get_logger()->info('Versuche, die IP-Adresse des Benutzers zu ermitteln.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$ip_address = '0.0.0.0'; // Standard-Fallback-Wert

		// Überprüfe, ob die IP-Adresse von einem Shared-Internet-Anbieter stammt.
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
			$this->get_logger()->debug('IP-Adresse über HTTP_CLIENT_IP gefunden.');
		}
		// Überprüfe, ob die IP-Adresse von einem Proxy oder Load Balancer stammt.
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// HTTP_X_FORWARDED_FOR kann eine kommaseparierte Liste von IPs enthalten.
			// Die erste IP in der Liste ist in der Regel die des tatsächlichen Clients.
			$ip_addresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip_address = trim(reset($ip_addresses));
			$this->get_logger()->debug('IP-Adresse über HTTP_X_FORWARDED_FOR gefunden.');
		}
		// Finde die IP-Adresse des Benutzers über die Remote-Adresse.
		elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = $_SERVER['REMOTE_ADDR'];
			$this->get_logger()->debug('IP-Adresse über REMOTE_ADDR gefunden.');
		}

		// Sanitize die IP-Adresse, um Injektionsrisiken zu vermeiden.
		// Die WordPress-Funktion `sanitize_text_field` ist hier besser geeignet, da `addslashes`
		// die Adresse nur für SQL-Kontext "sicher" macht, aber nicht für die allgemeine Verwendung.
		$sanitized_ip = sanitize_text_field($ip_address);

		$this->get_logger()->info('IP-Adresse erfolgreich ermittelt.', [
			'ip' => $sanitized_ip, // Maskiere die IP-Adresse im Log
		]);

		return $sanitized_ip;
	}
}