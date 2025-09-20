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

		add_action('wp_ajax_f12_cf7_captcha_reload', [$this, 'wp_handle_reload_captcha']);
		add_action('wp_ajax_nopriv_f12_cf7_captcha_reload', [$this, 'wp_handle_reload_captcha']);

		add_action('wp_ajax_f12_cf7_captcha_timer_reload', [$this, 'wp_handle_reload_timer']);
		add_action('wp_ajax_nopriv_f12_cf7_captcha_timer_reload', [$this, 'wp_handle_reload_timer']);

		$this->get_logger()->info(
			"__construct(): Ajax-Handler für Captcha registriert",
			[
				'plugin'  => 'f12-cf7-captcha',
				'actions' => [
					'f12_cf7_captcha_reload',
					'f12_cf7_captcha_timer_reload'
				],
				'class'   => __CLASS__
			]
		);
	}


	/**
	 * Handle the reloading of captcha based on the method specified in the POST request.
	 *
	 * @return array Returns an array with 'Captcha' and 'Generator' objects.
	 * @throws RuntimeException Thrown if method is not defined.
	 */
	public function handle_reload_captcha(): array
	{
		if (!isset($_POST['captchamethod'])) {
			$this->get_logger()->error(
				"handle_reload_captcha(): Keine Methode in Request übergeben",
				['plugin' => 'f12-cf7-captcha']
			);
			throw new \RuntimeException('Method not defined.');
		}

		$method = sanitize_text_field($_POST['captchamethod']);

		$this->get_logger()->debug(
			"handle_reload_captcha(): Request empfangen",
			[
				'plugin' => 'f12-cf7-captcha',
				'method' => $method,
				'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			]
		);

		/** @var Protection $Protection */
		$Protection = $this->Controller->get_modul('protection');

		/** @var Captcha_Validator $Captcha_Validator */
		$Captcha_Validator = $Protection->get_modul('captcha-validator');

		/** @var CaptchaGenerator $Captcha_Generator */
		$Captcha_Generator = $Captcha_Validator->get_generator($method);

		/** @var UserData $User_Data */
		$User_Data  = $this->Controller->get_modul('user-data');
		$ip_address = $User_Data->get_ip_address();

		// Captcha erzeugen & speichern
		$Captcha = new Captcha($this->Controller->get_logger(), $ip_address);
		$Captcha->set_code($Captcha_Generator->get());
		$result = $Captcha->save();

		if ($result) {
			$this->get_logger()->info(
				"handle_reload_captcha(): Neues Captcha erfolgreich generiert",
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
				"handle_reload_captcha(): Fehler beim Speichern des Captchas",
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
	 * Handle the reload of the captcha
	 *
	 * @return void
	 *
	 * @throws RuntimeException if captcha is not initialized or captcha generator is not initialized
	 */
	public function wp_handle_reload_captcha(): void
	{
		$data = $this->handle_reload_captcha();

		if (!isset($data['Captcha'])) {
			$this->get_logger()->error(
				"wp_handle_reload_captcha(): Captcha nicht initialisiert",
				['plugin' => 'f12-cf7-captcha']
			);
			throw new \RuntimeException('Captcha not initialized');
		}

		/** @var Captcha $Captcha */
		$Captcha = $data['Captcha'];

		if (!isset($data['Generator'])) {
			$this->get_logger()->error(
				"wp_handle_reload_captcha(): Generator nicht initialisiert",
				[
					'plugin' => 'f12-cf7-captcha',
					'id'     => $Captcha->get_id()
				]
			);
			throw new \RuntimeException('Captcha Generator not initialized');
		}

		/** @var CaptchaGenerator $Generator */
		$Generator = $data['Generator'];

		$response = [
			'hash'  => $Captcha->get_hash(),
			'label' => $Generator->get_ajax_response(),
		];

		$this->get_logger()->info(
			"wp_handle_reload_captcha(): Ajax-Response ausgegeben",
			[
				'plugin'    => 'f12-cf7-captcha',
				'id'        => $Captcha->get_id(),
				'generator' => get_class($Generator),
				'hash'      => substr($response['hash'], 0, 6) . '...'
			]
		);

		echo wp_json_encode($response);
		wp_die();
	}


	/**
	 * Returns a new Timer hash for Ajax
	 *
	 * @return string The Timer hash
	 * @throws \Exception
	 */
	public function handle_reload_timer(): string
	{
		/** @var Timer_Controller $Timer */
		$Timer = $this->Controller->get_modul('timer');

		$this->get_logger()->debug(
			"handle_reload_timer(): Timer-Reload angefordert",
			['plugin' => 'f12-cf7-captcha']
		);

		$result = $Timer->add_timer();

		if (!empty($result)) {
			$this->get_logger()->info(
				"handle_reload_timer(): Neuer Timer erfolgreich erstellt",
				[
					'plugin' => 'f12-cf7-captcha',
					'timer'  => $result
				]
			);
		} else {
			$this->get_logger()->warning(
				"handle_reload_timer(): Timer konnte nicht erstellt werden",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $result;
	}

	/**
	 * Handle the reload timer for Ajax and output the timer hash.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function wp_handle_reload_timer(): void
	{
		$hash = $this->handle_reload_timer();

		if (empty($hash)) {
			$this->get_logger()->warning(
				"wp_handle_reload_timer(): Timer-Hash leer zurückgegeben",
				['plugin' => 'f12-cf7-captcha']
			);
		} else {
			$this->get_logger()->info(
				"wp_handle_reload_timer(): Timer erfolgreich zurückgegeben",
				[
					'plugin' => 'f12-cf7-captcha',
					'hash'   => substr($hash, 0, 6) . '...' // ⚠️ Maskieren!
				]
			);
		}

		echo wp_json_encode(['hash' => $hash]);
		wp_die();
	}
}