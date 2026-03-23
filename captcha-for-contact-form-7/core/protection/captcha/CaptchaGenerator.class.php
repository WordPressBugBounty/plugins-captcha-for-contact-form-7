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
    protected $_allowedCharacters = 'abcdefghjkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Lowercase-only characters for use when audio captcha is enabled.
     * TTS cannot distinguish between upper- and lowercase letters.
     */
    private const ALLOWED_CHARACTERS_AUDIO = 'abcdefghjkmnopqrstuvwxyz23456789';

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
     * @param CF7Captcha $Controller The main controller.
     * @param int        $length     Length of captcha code. Pass 0 to skip generation (for pooled captchas).
     */
	public function __construct(CF7Captcha $Controller, int $length)
	{
		parent::__construct($Controller);

		// Skip generation if length is 0 (used for pooled captchas)
		if ($length === 0) {
			$this->get_logger()->debug(
				"__construct(): Skipping captcha generation (length=0, likely pooled)",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__,
				]
			);
			return;
		}

		$this->get_logger()->debug(
			"__construct(): Starting generation of a new captcha",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'length' => $length
			]
		);

		$this->generate_captcha($length);

		$this->get_logger()->info(
			"__construct(): Captcha successfully generated",
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

		// Mask for log (keep first 3 and last 3 characters)

		$this->get_logger()->debug(
			"get_last_unique_id_captcha(): Unique captcha ID determined",
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
			"get_last_unique_id_hash(): Unique hash ID determined",
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
			"generate_and_get_unique_id(): New unique ID generated",
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
				"get_unique_id(): Unique ID empty, generating new one",
				['plugin' => 'f12-cf7-captcha']
			);

			return $this->generate_and_get_unique_id();
		}

		$this->get_logger()->debug(
			"get_unique_id(): Existing unique ID returned",
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
			"generate_and_get_captcha(): Starting creation of a new captcha session",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$this->Captcha_Session = new Captcha($this->Controller->get_logger(), '');
		$result = $this->Captcha_Session->save();

		if ($result) {
			$this->get_logger()->info(
				"generate_and_get_captcha(): New captcha session saved successfully",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $this->Captcha_Session->get_id()
				]
			);
		} else {
			$this->get_logger()->error(
				"generate_and_get_captcha(): Error saving captcha session",
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

		$setting_icon = $this->get_protection_setting('protection_captcha_reload_icon');

		if ($setting_icon === 'white') {
			$reload_icon = 'reload-icon-white.png';
		}

		$image_url .= $reload_icon;

		$this->get_logger()->debug(
			"get_reload_button(): Reload icon determined",
			[
				'plugin'       => 'f12-cf7-captcha',
				'setting_icon' => $setting_icon,
				'icon_file'    => $reload_icon,
				'url'          => $image_url
			]
		);

		/**
		 * Filter allows overriding the icon
		 */
		$image_url = apply_filters('f12-cf7-captcha-reload-icon', $image_url);

		$this->get_logger()->info(
			"get_reload_button(): Reload button HTML generated",
			[
				'plugin' => 'f12-cf7-captcha',
				'url'    => $image_url
			]
		);

		// Reload button styling settings
		$bg_color      = $this->get_protection_setting('protection_captcha_reload_bg_color');
		$padding       = $this->get_protection_setting('protection_captcha_reload_padding');
		$border_radius = $this->get_protection_setting('protection_captcha_reload_border_radius');
		$border_color  = $this->get_protection_setting('protection_captcha_reload_border_color');
		$icon_size     = $this->get_protection_setting('protection_captcha_reload_icon_size');

		// Validate and apply defaults
		if ( empty( $bg_color ) || ! preg_match( '/^#[a-fA-F0-9]{6}$/', $bg_color ) ) {
			$bg_color = '#2196f3';
		}
		$padding       = is_numeric( $padding ) ? (int) $padding : 3;
		$border_radius = is_numeric( $border_radius ) ? (int) $border_radius : 3;
		$icon_size     = is_numeric( $icon_size ) ? (int) $icon_size : 16;

		// Build inline styles for <a> — use !important to prevent theme/plugin CSS overrides
		$a_styles = sprintf(
			'display:inline-flex !important; align-items:center !important; justify-content:center !important; background-color:%s !important; padding:%dpx !important; border-radius:%dpx !important; line-height:0 !important; box-sizing:content-box !important; text-decoration:none !important;',
			$bg_color, $padding, $border_radius
		);
		if ( ! empty( $border_color ) && preg_match( '/^#[a-fA-F0-9]{6}$/', $border_color ) ) {
			$a_styles .= sprintf( ' border:1px solid %s !important;', $border_color );
		}

		// Build inline styles for <img> — use !important to prevent theme/plugin CSS overrides
		$img_styles = sprintf( 'display:block !important; width:%dpx !important; height:%dpx !important; max-width:none !important; margin:0 !important; padding:0 !important;', $icon_size, $icon_size );

		return sprintf(
			'<a href="#" class="cf7 captcha-reload" style="%s" title="%s"><img style="%s" src="%s" alt="%s"/></a>',
			esc_attr( $a_styles ),
			esc_attr__('Reload Captcha', 'captcha-for-contact-form-7'),
			esc_attr( $img_styles ),
			esc_url( $image_url ),
			esc_attr__('Reload', 'captcha-for-contact-form-7')
		);
	}

	/**
	 * Generate the reload button for v2 templates (5-9).
	 * Uses inline SVG instead of PNG, transparent background by default,
	 * and no !important so template CSS can style the button.
	 *
	 * @since 2.1.0
	 * @return string The HTML markup for the reload button.
	 */
	public function get_reload_button_v2(): string
	{
		$icon_size = $this->get_protection_setting('protection_captcha_reload_icon_size');
		$icon_size = is_numeric( $icon_size ) ? (int) $icon_size : 16;

		$svg = sprintf(
			'<svg aria-hidden="true" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>',
			$icon_size
		);

		return sprintf(
			'<a href="#" class="cf7 captcha-reload" style="display:inline-flex;align-items:center;justify-content:center;background:transparent;padding:0;border:none;line-height:0;text-decoration:none;" title="%s">%s</a>',
			esc_attr__('Reload Captcha', 'captcha-for-contact-form-7'),
			$svg
		);
	}


	/**
	 * Generate inline styles for the audio button, consistent with the reload button.
	 *
	 * @return string Inline CSS styles.
	 */
	/**
	 * Restrict allowed characters to lowercase + digits for audio captcha compatibility.
	 * Must be called before generate_captcha().
	 */
	public function use_audio_safe_characters(): void {
		$this->_allowedCharacters = self::ALLOWED_CHARACTERS_AUDIO;
	}

	public function get_audio_button_styles(): string {
		$bg_color      = $this->get_protection_setting( 'protection_captcha_reload_bg_color' );
		$padding       = $this->get_protection_setting( 'protection_captcha_reload_padding' );
		$border_radius = $this->get_protection_setting( 'protection_captcha_reload_border_radius' );
		$border_color  = $this->get_protection_setting( 'protection_captcha_reload_border_color' );

		if ( empty( $bg_color ) || ! preg_match( '/^#[a-fA-F0-9]{6}$/', $bg_color ) ) {
			$bg_color = '#2196f3';
		}
		$padding       = is_numeric( $padding ) ? (int) $padding : 3;
		$border_radius = is_numeric( $border_radius ) ? (int) $border_radius : 3;

		$styles = sprintf(
			'display:inline-flex; align-items:center; justify-content:center; background-color:%s; padding:%dpx; border-radius:%dpx; line-height:0; box-sizing:content-box;',
			$bg_color, $padding, $border_radius
		);

		if ( ! empty( $border_color ) && preg_match( '/^#[a-fA-F0-9]{6}$/', $border_color ) ) {
			$styles .= sprintf( ' border:1px solid %s;', $border_color );
		}

		return $styles;
	}

	/**
	 * Audio button styles for v2 templates (5-9).
	 * Transparent background, styled via template CSS.
	 *
	 * @since 2.1.0
	 * @return string Inline CSS styles.
	 */
	public function get_audio_button_styles_v2(): string {
		return 'display:inline-flex; align-items:center; justify-content:center; background:transparent; padding:0; border:none; line-height:0; cursor:pointer;';
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
			"generate_captcha(): Starting generation",
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
			"generate_captcha(): Captcha code generated",
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
				"get(): No captcha set",
				['plugin' => 'f12-cf7-captcha']
			);
			return '';
		}

		// Masking: only 1st and last position visible
		$length = strlen($this->_captcha);

		$this->get_logger()->debug(
			"get(): Captcha returned",
			[
				'plugin' => 'f12-cf7-captcha',
				'masked' => $this->_captcha,
				'length' => $length
			]
		);

		return $this->_captcha;
	}

}