<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CaptchaGenerator
 * Generate the custom captcha as an image
 *
 * @package forge12\contactform7
 */
abstract class CaptchaGenerator extends BaseModul
{
    /**
     * @var string List of allowed characters for the captcha
     */
    private $_allowedCharacters = 'abcdefghjkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The Captcha string.
     *
     * @var string
     */
    protected $_captcha = '';

    /**
     * Latest Captcha Session
     */
    protected ?Captcha $Captcha_Session = null;

    /**
     * The Unique ID
     */
    private string $unique_id = '';

    /**
     * constructor.
     *
     * @TODO adjust after updating all captchas
     */
	public function __construct(CF7Captcha $Controller, int $length)
	{
		parent::__construct($Controller);

		$this->get_logger()->debug(
			"__construct(): Starte Generierung eines neuen Captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'length' => $length
			]
		);

		$this->generate_captcha($length);

		$this->get_logger()->info(
			"__construct(): Captcha erfolgreich generiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'length' => $length
			]
		);
	}


    /**
     * Retrieves the last unique ID for CAPTCHA.
     *
     * @return string The last unique ID for CAPTCHA.
     */
	public function get_last_unique_id_captcha(): string
	{
		$uniqueId = $this->get_unique_id();
		$captchaId = 'c_' . $uniqueId;

		// Maske für Log (erste 3 und letzte 3 Zeichen behalten)

		$this->get_logger()->debug(
			"get_last_unique_id_captcha(): Unique Captcha-ID ermittelt",
			[
				'plugin'     => 'f12-cf7-captcha',
				'captcha_id' => $captchaId
			]
		);

		return $captchaId;
	}


    /**
     * Retrieves the last unique ID hash.
     *
     * @return string The last unique ID hash.
     */
	public function get_last_unique_id_hash(): string
	{
		$uniqueId = $this->get_unique_id();
		$hashId   = 'hash_c_' . $uniqueId;

		$this->get_logger()->debug(
			"get_last_unique_id_hash(): Unique Hash-ID ermittelt",
			[
				'plugin'  => 'f12-cf7-captcha',
				'hash_id' => $hashId
			]
		);

		return $hashId;
	}


	/**
	 * Generates a unique ID and retrieves it.
	 *
	 * @return string The generated unique ID.
	 */
	public function generate_and_get_unique_id(): string
	{
		$this->unique_id = bin2hex(random_bytes(10));


		$this->get_logger()->info(
			"generate_and_get_unique_id(): Neue Unique-ID generiert",
			[
				'plugin'    => 'f12-cf7-captcha',
				'unique_id' => $this->unique_id
			]
		);

		return $this->get_unique_id();
	}


    /**
     * Retrieves the unique ID.
     *
     * @return string The unique ID.
     */
	public function get_unique_id(): string
	{
		if (empty($this->unique_id)) {
			$this->get_logger()->debug(
				"get_unique_id(): Unique-ID leer, generiere neue",
				['plugin' => 'f12-cf7-captcha']
			);

			return $this->generate_and_get_unique_id();
		}

		$this->get_logger()->debug(
			"get_unique_id(): Vorhandene Unique-ID zurückgegeben",
			[
				'plugin'    => 'f12-cf7-captcha',
				'unique_id' => $this->unique_id
			]
		);

		return $this->unique_id;
	}


    abstract protected function get_field(string $field_name): string;

    /**
     * Retrieve the AJAX response as a string
     *
     * @return string The AJAX response
     */
    abstract function get_ajax_response(): string;

    /**
     * Gets the latest captcha session.
     *
     * This method returns the latest captcha session object. If there is no latest captcha session,
     * it returns null.
     *
     * @return Captcha|null The latest captcha session object, or null if there is no latest captcha session.
     */
	public function generate_and_get_captcha(): ?Captcha
	{
		$this->get_logger()->debug(
			"generate_and_get_captcha(): Starte Erstellung einer neuen Captcha-Session",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$this->Captcha_Session = new Captcha($this->Controller->get_logger(), '');
		$result = $this->Captcha_Session->save();

		if ($result) {
			$this->get_logger()->info(
				"generate_and_get_captcha(): Neue Captcha-Session erfolgreich gespeichert",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->Captcha_Session->get_id()
				]
			);
		} else {
			$this->get_logger()->error(
				"generate_and_get_captcha(): Fehler beim Speichern der Captcha-Session",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $this->Captcha_Session;
	}


    /**
     * Generate and return the reload button for the captcha
     *
     * @return string The HTML markup for the reload button
     */
	public function get_reload_button(): string
	{
		$image_url   = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/';
		$reload_icon = 'reload-icon.png';

		$setting_icon = $this->Controller->get_settings('protection_captcha_reload_icon', 'global');

		if ($setting_icon === 'white') {
			$reload_icon = 'reload-icon-white.png';
		}

		$image_url .= $reload_icon;

		$this->get_logger()->debug(
			"get_reload_button(): Reload-Icon ermittelt",
			[
				'plugin'       => 'f12-cf7-captcha',
				'setting_icon' => $setting_icon,
				'icon_file'    => $reload_icon,
				'url'          => $image_url
			]
		);

		/**
		 * Filter erlaubt es, das Icon zu überschreiben
		 */
		$image_url = apply_filters('f12-cf7-captcha-reload-icon', $image_url);

		$this->get_logger()->info(
			"get_reload_button(): Reload-Button HTML generiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'url'    => $image_url
			]
		);

		return sprintf(
			'<a href="javascript:void(0);" class="cf7 captcha-reload" title="%s"><img style="margin-top:5px;" src="%s" alt="%s"/></a>',
			__('Reload Captcha', 'captcha-for-contact-form-7'),
			$image_url,
			__('Reload', 'captcha-for-contact-form-7')
		);
	}


    /**
     * Generate a captcha string of specified length
     *
     * @param int $length The length of the captcha string to generate
     *
     * @return void
     */
	private function generate_captcha(int $length): void
	{
		$this->get_logger()->debug(
			"generate_captcha(): Starte Generierung",
			[
				'plugin' => 'f12-cf7-captcha',
				'length' => $length
			]
		);

		$result = '';
		$max    = strlen($this->_allowedCharacters) - 1;

		for ($i = 0; $i < $length; $i++) {
			$result .= $this->_allowedCharacters[rand(0, $max)];
		}

		$this->_captcha = $result;

		$this->get_logger()->info(
			"generate_captcha(): Captcha-Code generiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'masked' => $result,
				'length' => $length
			]
		);
	}


    /**
     * Generate the captcha string and return it
     *
     * @return string
     */
	public function get(): string
	{
		if (empty($this->_captcha)) {
			$this->get_logger()->warning(
				"get(): Kein Captcha gesetzt",
				['plugin' => 'f12-cf7-captcha']
			);
			return '';
		}

		// Maskieren: nur 1. und letzte Stelle sichtbar
		$length = strlen($this->_captcha);

		$this->get_logger()->debug(
			"get(): Captcha zurückgegeben",
			[
				'plugin' => 'f12-cf7-captcha',
				'masked' => $this->_captcha,
				'length' => $length
			]
		);

		return $this->_captcha;
	}

}