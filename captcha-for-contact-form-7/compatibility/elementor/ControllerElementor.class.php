<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerElementor
 */
class ControllerElementor extends BaseController
{
    /**
     * @var string
     */
    protected string $name = 'Elementor';

    /**
     * @var string $id  The unique identifier for the entity.
     *                  This should be a string value.
     */
    protected string $id = 'elementor';

    /**
     * Checks if the CF7 Captcha module is enabled in Elementor.
     *
     * This method is used to determine whether the CF7 Captcha module is enabled or not.
     * It checks the 'f12_cf7_captcha_is_installed_elementor' filter value to determine the result.
     * The CF7 Captcha module is considered enabled if the 'ELEMENTOR_VERSION' constant is defined.
     *
     * @return bool Returns true if the CF7 Captcha module is enabled, false otherwise.
     */
	public function is_enabled(): bool
	{
		// Log the start of the check.
		$this->get_logger()->info('Starting check if the Elementor module is enabled.');

		// Check if the module is installed.
		$is_installed = $this->is_installed();
		$this->get_logger()->debug('Module installation status: ' . ($is_installed ? 'Installed' : 'Not installed'));

		// Get the global setting for Elementor protection.
		$setting_value = $this->Controller->get_settings('protection_elementor_enable', 'global');
		$this->get_logger()->debug('Setting "protection_elementor_enable" value: ' . $setting_value);

		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
			$this->get_logger()->debug( 'Wert der Einstellung "protection_elementor_enable" wurde nicht gesetzt. Verwende Standardwert: ' . $setting_value );
		}

		$is_active = $is_installed && $setting_value === 1;

		// Log the status before applying any filters.
		$this->get_logger()->debug('Module status before filter: ' . ($is_active ? 'Active' : 'Inactive'));

		// Apply a filter to allow other plugins to modify the status.
		$result = apply_filters('f12_cf7_captcha_is_installed_elementor', $is_active);

		// Log the final result after the filter.
		$this->get_logger()->info('Final status after filter: ' . ($result ? 'Active' : 'Inactive'));

		return $result;
	}

    /**
     * Check if Elementor plugin is installed
     *
     * @return bool True if Elementor is installed, false otherwise
     */
	public function is_installed(): bool
	{
		// Logge den Beginn der Überprüfung
		$this->get_logger()->info('Starte Überprüfung, ob Elementor installiert ist.');

		// Prüfe, ob die Konstante 'ELEMENTOR_VERSION' definiert ist.
		$is_installed = defined('ELEMENTOR_VERSION');

		// Logge das Ergebnis der Prüfung
		if ($is_installed) {
			$this->get_logger()->info('Elementor wurde gefunden. Die Version ist ' . ELEMENTOR_VERSION . '.');
		} else {
			$this->get_logger()->critical('Elementor wurde nicht gefunden. Das Modul kann nicht korrekt funktionieren.');
		}

		// Gib das Ergebnis zurück
		return $is_installed;
	}

    /**
     * @private WordPress Hook
     */
	public function on_init(): void
	{
		// Log the start of the initialization process for the Elementor module.
		$this->get_logger()->info('Starte die Initialisierung des Elementor-Moduls.');

		// Set the module name.
		$this->name = __('Elementor', 'captcha-for-contact-form-7');
		$this->get_logger()->debug('Modulname wurde gesetzt.', ['name' => $this->name]);

		// Add the validation action for Elementor Pro forms.
		$this->get_logger()->debug('Füge die Aktion "elementor_pro/forms/validation" zur Spam-Prüfung hinzu.');
		add_action('elementor_pro/forms/validation', array($this, 'wp_is_spam'), 10, 2);

		// Add the filter to render the spam protection field.
		$this->get_logger()->debug('Füge den Filter "elementor_pro/forms/render/item" hinzu, um den Spamschutz anzuzeigen.');
		add_filter('elementor_pro/forms/render/item', array($this, 'wp_add_spam_protection'), 10, 3);

		// Add the action to enqueue scripts.
		$this->get_logger()->debug('Füge die Aktion "wp_enqueue_scripts" hinzu, um die Skripte zu laden.');
		add_action('wp_enqueue_scripts', array($this, 'wp_add_assets'));

		// Log the successful completion of the initialization.
		$this->get_logger()->info('Initialisierung abgeschlossen.');
	}

    /**
     * Add assets for elementor
     */
	public function wp_add_assets()
	{
		// Logge den Beginn des Einreihens von Skripten.
		$this->get_logger()->info('Starte das Einreihen von Skripten für Elementor.');

		// Definiere den Handle und die URL des Skripts.
		$handle = 'f12-cf7-captcha-elementor';
		$script_url = plugin_dir_url(__FILE__) . 'assets/f12-cf7-captcha-elementor.js';

		// Logge die Details des Skripts, das geladen wird.
		$this->get_logger()->debug('Skript wird geladen.', [
			'handle' => $handle,
			'url' => $script_url,
			'dependencies' => ['jquery'],
		]);

		// Lade das Skript in die Warteschlange.
		wp_enqueue_script($handle, $script_url, array('jquery'));

		// Definiere die Daten für die Lokalisierung.
		$localization_data = [
			'ajaxurl' => admin_url('admin-ajax.php')
		];

		// Logge die Lokalisierungsdaten, die dem Skript hinzugefügt werden.
		$this->get_logger()->debug('Skript wird lokalisiert.', [
			'handle' => $handle,
			'data' => $localization_data,
		]);

		// Lokalisiere das Skript mit den definierten Daten.
		wp_localize_script($handle, 'f12_cf7_captcha_elementor', $localization_data);

		// Logge den erfolgreichen Abschluss des Vorgangs.
		$this->get_logger()->info('Skripte erfolgreich eingereiht und lokalisiert.');
	}

    /**
     * Add spam protection to the given content.
     *
     * This method adds spam protection to the given content by injecting a captcha field based on the specified
     * validation method.
     *
     * @param mixed ...$args Any number of arguments.
     *
     * @return mixed The content with spam protection added.
     *
     * @throws \Exception
     * @since 1.12.2
     *
     */
	public function wp_add_spam_protection(...$args)
	{
		// Log the beginning of the process.
		$this->get_logger()->info('Starte die Einfügung des Captcha-Codes für Elementor-Formulare.');

		$item = $args[0];
		$item_index = $args[1];

		/**
		 * @var ElementorPro\Modules\Forms\Widgets\Form $form
		 */
		$form = $args[2];

		// Log the current item index and the total number of fields for debugging.
		$settings = $form->get_settings();
		$number_of_fields = count($settings['form_fields']) - 1;
		$this->get_logger()->debug('Überprüfe die Position des Feldes.', [
			'item_index' => $item_index,
			'number_of_fields' => $number_of_fields,
		]);

		// Check if the current item is the last one and if the form has not been submitted yet.
		if ($item_index !== $number_of_fields || (isset($_POST) && !empty($_POST))) {
			// Log that the condition for adding the captcha was not met.
			$this->get_logger()->debug('Captcha wird nicht eingefügt, da die Bedingungen nicht erfüllt sind.');
			return $item;
		}

		// Get the captcha HTML from the protection module.
		$captcha = $this->Controller->get_modul('protection')->get_captcha();
		$this->get_logger()->debug('Captcha-Code wurde abgerufen.', ['captcha_length' => strlen($captcha)]);

		// Check if the captcha is empty.
		if (empty($captcha)) {
			$this->get_logger()->warning('Der Captcha-Code ist leer. Es wird kein HTML ausgegeben.');
		} else {
			// Wrap the captcha in Elementor's field markup and output it.
			$wrapped_captcha = sprintf('<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-text elementor-col-100 elementor-field-required">%s</div>', $captcha);
			echo $wrapped_captcha;

			// Log the successful output of the captcha.
			$this->get_logger()->info('Captcha-Code wurde erfolgreich in das Formular eingefügt.');
		}

		// Return the original form item to continue the rendering process.
		return $item;
	}

    /**
     * Determines if the given submission is spam.
     *
     * This method checks if the submission is marked as spam and logs it if necessary.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     *
     * @return bool|int If the submission is identified as spam, it returns true. If not, it returns the spam indicator
     *                  value provided.
     *
     * @since 1.0.0
     */
	public function wp_is_spam(...$args)
	{
		// Log the start of the spam validation process for Elementor forms.
		$this->get_logger()->info('Starte Spam-Validierung für Elementor-Formular.');

		// Extract the record and handler objects from arguments.
		$record = $args[0];
		$ajax_handler = $args[1];

		// Log the received arguments.
		$this->get_logger()->debug('Argumente erhalten.', [
			'record_exists' => (null !== $record),
			'ajax_handler_exists' => (null !== $ajax_handler),
		]);

		// Check for null values to prevent errors.
		if (null === $record || null === $ajax_handler) {
			$this->get_logger()->notice('Record- oder Ajax-Handler-Objekt ist null. Beende die Prüfung.');
			return false;
		}

		// Get the fields from the record.
		$fields = $record->get('fields');

		// Check if fields are null or not an array.
		if (null === $fields || !is_array($fields)) {
			$this->get_logger()->notice('Formularfelder sind null oder kein Array. Keine Daten zur Überprüfung vorhanden.');
			return false;
		}

		// Prepare the data to be checked.
		$array_post_data = $_POST;
		$this->get_logger()->debug('Überprüfe die folgenden POST-Daten auf Spam.', [
			'post_data_keys' => array_keys($array_post_data),
		]);

		// Get the protection module.
		$Protection = $this->Controller->get_modul('protection');

		// Perform the spam check.
		if ($Protection->is_spam($array_post_data)) {
			$message = $Protection->get_message();
			$this->get_logger()->warning('Spam erkannt! Meldung: ' . $message);

			// Find a visible field to attach the error message.
			$field_name = '';
			foreach ($fields as $key => $data) {
				if (isset($data['type']) && 'hidden' !== $data['type']) {
					$field_name = $key;
					$this->get_logger()->debug('Fehlermeldung wird an Feld angehängt.', ['field_name' => $field_name]);
					break; // Exit the loop after finding the first visible field.
				}
			}

			// Add the error message to the AJAX handler.
			$ajax_handler->add_error($field_name, sprintf(esc_html__('Spam detected: %s', 'captcha-for-contact-form-7'), $message));

			// Log the final action and return value.
			$this->get_logger()->critical('Spam erkannt. Formularübermittlung wird abgebrochen.');
			return true;
		}

		// Log if no spam was detected.
		$this->get_logger()->info('Kein Spam erkannt. Die Übermittlung wird fortgesetzt.');

		return false;
	}
}