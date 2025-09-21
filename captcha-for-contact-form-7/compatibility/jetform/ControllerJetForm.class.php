<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class ControllerJetForm
 */
class ControllerJetForm extends BaseController
{
	protected string $name = 'JetFormBuilder';
	protected string $id   = 'jetform';

	/**
	 * Check if the captcha is enabled for JetFormBuilder
	 */
	public function is_enabled(): bool
	{
		$this->get_logger()->info('Starte Überprüfung, ob das JetForm-Modul aktiviert ist.');

		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Installationsstatus des Moduls: ' . ($is_installed ? 'Installiert' : 'Nicht installiert'));

		$setting_value = $this->Controller->get_settings('protection_jetform_enable', 'global');
		$this->get_logger()->debug( 'Wert der Einstellung "protection_jetform_enable": ' . $setting_value );

		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_jetform_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		$is_active = $is_installed && ($setting_value === 1);

		$this->get_logger()->debug('Status vor Filter: ' . ($is_active ? 'Aktiv' : 'Inaktiv'));

		$result = apply_filters('f12_cf7_captcha_is_installed_jetform', $is_active);

		$this->get_logger()->info('Endgültiger Status: ' . ($result ? 'Aktiv' : 'Inaktiv'));

		return $result;
	}

	/**
	 * Check if JetFormBuilder is installed
	 */
	public function is_installed(): bool
	{
		$this->get_logger()->info('Starte Überprüfung, ob JetFormBuilder installiert ist.');

		$is_installed = class_exists('\Jet_Form_Builder\Plugin');

		if ($is_installed) {
			$this->get_logger()->info('JetFormBuilder gefunden.');
		} else {
			$this->get_logger()->critical('JetFormBuilder nicht gefunden.');
		}

		return $is_installed;
	}

	/**
	 * Initialize JetForm integration
	 */
	public function on_init(): void
	{
		$this->name = __('JetFormBuilder', 'captcha-for-contact-form-7');

		// Captcha ins Formular einfügen
		add_action('jet-form-builder/before-start-form-row', [$this, 'wp_add_spam_protection'], 10, 1);

		// Validierung
		add_action('jet-form-builder/form-handler/before-send', [$this, 'wp_validation'], 10, 1);

		$this->get_logger()->info('JetForm-Integration initialisiert.');
	}

	/**
	 * Add captcha to JetForm (im Formular-HTML)
	 */
	public function wp_add_spam_protection( $formElement ) {
		// Debug: prüfen, welche Klasse übergeben wird
		$this->get_logger()->debug( 'Form-Element erkannt.', [
			'class' => is_object( $formElement ) ? get_class( $formElement ) : gettype( $formElement ),
		]);

		// Nur auf Submit-Button reagieren
		if ( $formElement instanceof \Jet_Form_Builder\Blocks\Types\Action_Button ) {
			$block_attrs = $formElement->block_attrs ?? [];

			// Nur wenn action_type = submit
			if ( isset( $block_attrs['action_type'] ) && $block_attrs['action_type'] === 'submit' ) {
				$this->get_logger()->info( 'Füge Captcha in JetForm vor Submit-Button ein.', [
					'label'       => $block_attrs['label'] ?? '',
					'action_type' => $block_attrs['action_type'],
				] );

				$captcha = $this->Controller->get_modul( 'protection' )->get_captcha();

				if ( empty( $captcha ) ) {
					$this->get_logger()->warning( 'Captcha leer, kein Einfügen.' );
					return;
				}

				// Captcha direkt vor dem Button ausgeben
				echo $captcha;
			}
		}
	}


	/**
	 * Validate form submission (bevor JetForm die Daten sendet)
	 */
	public function wp_validation($handler)
	{
		$this->get_logger()->info('Starte JetForm-Validierung.', [
			'form_id' => $handler->form_id ?? 'unknown',
		]);

		$Protection = $this->Controller->get_modul('protection');

		if ($Protection->is_spam($_POST)) {
			$message = $Protection->get_message() ?: __('Invalid input detected.', 'captcha-for-contact-form-7');

			$this->get_logger()->warning('Spam erkannt, Validierung fehlgeschlagen.', [
				'form_id' => $handler->form_id ?? 'unknown'
			]);

			throw new \JFB_Modules\Security\Exceptions\Spam_Exception( $message );
		}
	}
}
