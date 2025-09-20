<?php

namespace f12_cf7_captcha\core\protection\ip_blacklist;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

class IP_Blacklist_Validator extends BaseProtection
{
	/**
	 * Private constructor for the class.
	 *
	 * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
	 * This hook is used to retrieve log data.
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Konstruktor gestartet.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->get_logger()->info('Konstruktor abgeschlossen.', [
			'class' => __CLASS__,
		]);
	}

	protected function is_enabled(): bool
	{
		$is_enabled = true;

		if ($is_enabled) {
			$this->get_logger()->info('IP-Blacklist ist aktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		} else {
			$this->get_logger()->warning('IP-Blacklist ist deaktiviert.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-ip-blacklist', $is_enabled);

		if ($is_enabled && !$result) {
			$this->get_logger()->debug('IP-Blacklist wird durch Filter übersprungen.', [
				'filter' => 'f12-cf7-captcha-skip-validation-ip-blacklist',
				'original_state' => $is_enabled,
			]);
		}

		return $result;
	}

	/**
	 * Determines if the submitted form is considered spam.
	 *
	 * This method checks if the submitted form is spam based on certain criteria.
	 *
	 * @return bool Returns true if the form is considered spam, false otherwise.
	 */
	public function is_spam(): bool
	{
		$this->get_logger()->info('Führe Spam-Überprüfung durch.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		// Wenn Modul deaktiviert ist → kein Spam
		if (!$this->is_enabled()) {
			$this->get_logger()->debug('Spam-Check übersprungen: IP-Blacklist-Schutz ist deaktiviert.', [
				'class' => __CLASS__,
			]);
			return false;
		}

		// Hole die User-IP
		$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';

		// Lade Blacklist-Einträge aus den Settings
		$settings          = get_option('f12-cf7-captcha-settings', []);
		$blacklist_raw     = $settings['global']['protection_blacklist_ips'] ?? '';
		$blacklisted_ips   = array_filter(array_map('trim', explode("\n", $blacklist_raw)));

		// Prüfe, ob User-IP auf der Blacklist steht
		if (!empty($user_ip) && in_array($user_ip, $blacklisted_ips, true)) {
			$this->get_logger()->warning('Formular als Spam eingestuft: IP auf Blacklist.', [
				'class'   => __CLASS__,
				'user_ip' => $user_ip,
			]);
			$this->set_message(__('Your IP is blocked from submitting this form.', 'captcha-for-contact-form-7'));
			return true;
		}

		$this->get_logger()->info('Formular als nicht-Spam eingestuft.', [
			'ip' => $user_ip,
		]);

		return false;
	}


	public function success(): void
	{
		$this->get_logger()->info('Erfolgreiche Formularübermittlung erkannt.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Hier kann zusätzliche Logik implementiert werden,
		// die bei einer erfolgreichen Validierung ausgeführt werden soll.
		// Zum Beispiel:
		// - Löschen temporärer Daten
		// - Senden einer Benachrichtigung
		// - Aktualisieren von Zählern

		// TODO: Implementieren Sie die Erfolg-Logik hier.
	}
}