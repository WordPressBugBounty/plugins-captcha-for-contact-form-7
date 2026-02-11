<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\protection\Protection;
use f12_cf7_captcha\core\timer\Timer_Controller;
use f12_cf7_captcha\core\UserData;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Captcha
 * Model
 *
 * @package forge12\contactform7
 */
class CaptchaAjax extends BaseModul {

	/**
	 * Constructor method for the class.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha controller instance.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info(
			"__construct(): Captcha-Modul registriert",
			[
				'plugin'  => 'f12-cf7-captcha',
				'class'   => __CLASS__
			]
		);
	}


	/**
	 * Create a new Captcha instance.
	 *
	 * @param string $ip_address The IP address for the captcha.
	 *
	 * @return Captcha
	 */
	protected function create_captcha(string $ip_address): Captcha {
		return new Captcha($this->Controller->get_logger(), $ip_address);
	}

	/**
	 * Handle the reloading of captcha based on the given method.
	 *
	 * @param string $method The captcha method (e.g. 'math', 'image', 'honey').
	 *
	 * @return array Returns an array with 'Captcha' and 'Generator' objects.
	 * @throws RuntimeException Thrown if method is not defined.
	 */
	public function handle_reload_captcha( string $method ): array
	{
		$this->get_logger()->debug(
			"handle_reload_captcha(): Request empfangen",
			[
				'plugin' => 'f12-cf7-captcha',
				'method' => $method,
				'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
			]
		);

		/** @var Protection $Protection */
		$Protection = $this->Controller->get_module('protection');

		/** @var Captcha_Validator $Captcha_Validator */
		$Captcha_Validator = $Protection->get_module('captcha-validator');

		/** @var CaptchaGenerator $Captcha_Generator */
		$Captcha_Generator = $Captcha_Validator->get_generator($method);

		/** @var UserData $User_Data */
		$User_Data  = $this->Controller->get_module('user-data');
		$ip_address = $User_Data->get_ip_address();

		// Captcha erzeugen & speichern
		$Captcha = $this->create_captcha($ip_address);
		$Captcha->set_code($Captcha_Generator->get());
		$result = $Captcha->save();

		if ($result) {
			$this->get_logger()->info(
				"handle_reload_captcha(): New captcha successfully generated",
				[
					'plugin'    => 'f12-cf7-captcha',
					'method'    => $method,
					'generator' => get_class($Captcha_Generator),
					'ip'        => $ip_address,
					'id'        => $Captcha->get_id()
				]
			);
		} else {
			$this->get_logger()->error(
				"handle_reload_captcha(): Error saving captcha",
				[
					'plugin'    => 'f12-cf7-captcha',
					'method'    => $method,
					'generator' => get_class($Captcha_Generator),
					'ip'        => $ip_address
				]
			);
		}

		return [
			'Captcha'   => $Captcha,
			'Generator' => $Captcha_Generator,
		];
	}

	/**
	 * Returns a new Timer hash
	 *
	 * @return string The Timer hash
	 * @throws \Exception
	 */
	public function handle_reload_timer(): string
	{
		/** @var Timer_Controller $Timer */
		$Timer = $this->Controller->get_module('timer');

		$this->get_logger()->debug(
			"handle_reload_timer(): Timer reload requested",
			['plugin' => 'f12-cf7-captcha']
		);

		$result = $Timer->add_timer();

		if (!empty($result)) {
			$this->get_logger()->info(
				"handle_reload_timer(): New timer successfully created",
				[
					'plugin' => 'f12-cf7-captcha',
					'timer'  => $result
				]
			);
		} else {
			$this->get_logger()->warning(
				"handle_reload_timer(): Timer could not be created",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $result;
	}
}
