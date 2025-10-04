<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ControllerWoocommerceCheckout
 */
class ControllerWoocommerceCheckout extends BaseController
{
	protected string $name = 'WooCommerce Checkout';
	protected string $id   = 'woocommerce_checkout';

	/**
	 * Check if WooCommerce Checkout Captcha is enabled.
	 */
	public function is_enabled(): bool
	{
		$this->get_logger()->info('Starte Überprüfung, ob das WooCommerce-Checkout-Modul aktiviert ist.');

		$is_installed = $this->is_installed();
		$this->get_logger()->debug('WooCommerce Installationsstatus: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		$setting_value = $this->Controller->get_settings('protection_woocommerce_checkout_enable', 'global');
		$this->get_logger()->debug('Wert der Einstellung "protection_woocommerce_checkout_enable": ' . $setting_value);

		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
			$this->get_logger()->debug('Verwende Standardwert für "protection_woocommerce_checkout_enable": ' . $setting_value);
		}

		$is_active = $is_installed && (int)$setting_value === 1;

		$result = apply_filters('f12_cf7_captcha_is_installed_woocommerce_checkout', $is_active);
		$this->get_logger()->info('Endgültiger Status nach dem Filter: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

	/**
	 * Check if WooCommerce is installed.
	 */
	public function is_installed(): bool
	{
		$this->get_logger()->info('Prüfe, ob WooCommerce installiert ist.');
		$is_installed = class_exists('WooCommerce');

		if ($is_installed) {
			$this->get_logger()->info('WooCommerce gefunden.');
		} else {
			$this->get_logger()->critical('WooCommerce nicht gefunden.');
		}

		return $is_installed;
	}

	/**
	 * Init Hook Registration
	 */
	public function on_init(): void
	{
		$this->get_logger()->info('Initialisiere WooCommerce Checkout Modul.');

		$this->name = __('WooCommerce Checkout', 'captcha-for-contact-form-7');

		// Feld ins Checkout-Formular
		add_action('woocommerce_after_order_notes', [$this, 'wp_add_spam_protection']);

		// Validierung beim Checkout
		add_action('woocommerce_after_checkout_validation', [$this, 'wp_is_spam'], 10, 2);

		$this->get_logger()->info('WooCommerce Checkout Modul initialisiert.');
	}

	/**
	 * Feld ins Checkout-Formular einfügen
	 */
	public function wp_add_spam_protection($checkout)
	{
		$this->get_logger()->info('Füge Captcha ins WooCommerce Checkout Formular ein.');

		$Protection = $this->Controller->get_modul('protection');
		$captcha    = $Protection->get_captcha();

		if (empty($captcha)) {
			$this->get_logger()->warning('Captcha-Code leer – nichts eingefügt.');
		} else {
			echo '<div class="f12-cf7-captcha-checkout">' . $captcha . '</div>';
			$this->get_logger()->info('Captcha-Code ins Checkout eingefügt.');
		}
	}

	/**
	 * Spam-Check beim Checkout
	 *
	 * @param array          $data   Alle Checkout-Daten
	 * @param \WP_Error      $errors Fehlerobjekt, hier hinzufügen wenn Spam erkannt
	 */
	public function wp_is_spam($data, $errors)
	{
		$this->get_logger()->info('Starte Spam-Überprüfung für WooCommerce Checkout.');

		$Protection = $this->Controller->get_modul('protection');

		if ($Protection->is_spam($_POST)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt beim Checkout: ' . $message);

			$errors->add('spam', sprintf(__('Captcha not correct: %s', 'captcha-for-contact-form-7'), $message));

			$this->get_logger()->critical('Checkout gestoppt wegen Spam.');
		} else {
			$this->get_logger()->info('Kein Spam im WooCommerce Checkout erkannt.');
		}

		return $errors;
	}
}