<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\protection\Protection;
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
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		add_action( 'wp_ajax_f12_cf7_captcha_reload', [ $this, 'wp_handle_reload_captcha' ] );
		add_action( 'wp_ajax_nopriv_f12_cf7_captcha_reload', [ $this, 'wp_handle_reload_captcha' ] );

		add_action( 'wp_ajax_f12_cf7_captcha_timer_reload', [ $this, 'wp_handle_reload_timer' ] );
		add_action( 'wp_ajax_nopriv_f12_cf7_captcha_timer_reload', [ $this, 'wp_handle_reload_timer' ] );
	}

	/**
	 * Handle the reloading of captcha based on the method specified in the POST request.
	 *
	 * @return array Returns an array with 'Captcha' and 'Generator' objects.
	 * @throws RuntimeException Thrown if method is not defined.
	 */
	public function handle_reload_captcha(): array {
		if ( ! isset( $_POST['captchamethod'] ) ) {
			throw new RuntimeException( 'Method not defined.' );
		}

		/**
		 * @var Protection $Protection
		 */
		$Protection = $this->Controller->get_modul( 'protection' );
		/**
		 * @var Captcha_Validator $Captcha_Validator
		 */
		$Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

		$method = sanitize_text_field( $_POST['captchamethod'] );

		$Captcha_Generator = $Captcha_Validator->get_generator( $method );

		/**
		 * @var UserData $User_Data
		 */
		$User_Data = $this->Controller->get_modul('user-data');
		$ip_address = $User_Data->get_ip_address();

		/**
		 * Store the Captcha
		 */
		$Captcha = new Captcha($ip_address);
		/**
		 * @var CaptchaGenerator $Captcha_Generator
		 */
		$Captcha->set_code( $Captcha_Generator->get() );
		$Captcha->save();

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
	public function wp_handle_reload_captcha(): void {
		$data = $this->handle_reload_captcha();

		if ( ! isset( $data['Captcha'] ) ) {
			throw new RuntimeException( 'Captcha not initialized' );
		}

		/**
		 * @var Captcha $Captcha
		 */
		$Captcha = $data['Captcha'];

		if ( ! isset( $data['Generator'] ) ) {
			throw new RuntimeException( 'Captcha Generator not initalized' );
		}

		/**
		 * @var CaptchaGenerator $Generator
		 */
		$Generator = $data['Generator'];

		echo wp_json_encode( [ 'hash'  => $Captcha->get_hash(),
		                       'label' => $Generator->get_ajax_response()
		] );
		wp_die();
	}

	/**
	 * Returns a new Timer hash for Ajax
	 *
	 * @return string The Timer hash
	 * @throws \Exception
	 */
	public function handle_reload_timer(): string {
		return $this->Controller->get_modul( 'timer' )->add_timer();
	}


	/**
	 * Handle the reload timer for Ajax and output the timer hash.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function wp_handle_reload_timer(): void {
		$hash = $this->handle_reload_timer();

		echo wp_json_encode( [ 'hash' => $hash ] );
		wp_die();
	}
}