<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\javascript\JavascriptValidator;
use f12_cf7_captcha\core\TemplateController;
use f12_cf7_captcha\core\UserData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CaptchaMathGenerator
 * Generate the custom captcha as an image
 *
 * @package forge12\contactform7
 */
class CaptchaMathGenerator extends CaptchaGenerator {
	/**
	 * First number
	 */
	private $_number_1 = 0;

	/**
	 * Last number
	 */
	private $_number_2 = 0;

	/**
	 * Method
	 */
	private $_method = '+';

	/**
	 * Allowed math calculations
	 */
	private $_allowed_method = '+-*';

	/**
	 * constructor.
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller, 0);

		$this->get_logger()->debug(
			"__construct(): Initialisierung gestartet",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$this->init();

		$this->get_logger()->info(
			"__construct(): Initialisierung abgeschlossen",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);
	}

	/**
	 * Gets the method used for the captcha calculation.
	 *
	 * This method returns a string representing the mathematical operation
	 * used in the captcha calculation. The available methods include addition,
	 * subtraction and multiplication.
	 *
	 * @return string The method used for the captcha calculation. Defaults: +,-,*
	 */
	public function get_method(): string
	{
		if (empty($this->_method)) {
			$this->get_logger()->warning(
				"get_method(): Keine Methode gesetzt",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__
				]
			);
			return '';
		}

		$this->get_logger()->debug(
			"get_method(): Methode zurückgegeben",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'method' => $this->_method
			]
		);

		return $this->_method;
	}

	/**
	 * Generates a random number within the specified range.
	 *
	 * This method generates a random number between the given minimum and
	 * maximum values (inclusive) using the `rand()` function in PHP.
	 *
	 * @param int $min The minimum value for the generated number.
	 * @param int $max The maximum value for the generated number.
	 *
	 * @return int The randomly generated number.
	 */
	private function generate_number($min, $max): int
	{
		$number = rand($min, $max);

		$this->get_logger()->debug(
			"generate_number(): Zufallszahl generiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'min'    => $min,
				'max'    => $max,
				'result' => $number
			]
		);

		return $number;
	}


	/**
	 * Initializes the captcha by generating random numbers, selecting a method and calculating the result.
	 */
	private function init(): void
	{
		$this->_number_1 = $this->generate_number(5, 10);
		$this->_number_2 = $this->generate_number(1, 5);

		$this->_method = $this->_allowed_method[$this->generate_number(0, 2)];

		switch ($this->_method) {
			case '*':
				$this->_captcha = $this->_number_1 * $this->_number_2;
				break;
			case '-':
				$this->_captcha = $this->_number_1 - $this->_number_2;
				break;
			case '+':
			default:
				$this->_captcha = $this->_number_1 + $this->_number_2;
				break;
		}

		$this->get_logger()->debug(
			"init(): Mathe-Captcha initialisiert",
			[
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'number_1' => $this->_number_1,
				'number_2' => $this->_number_2,
				'method'   => $this->_method,
				'captcha'  => $this->_captcha
			]
		);
	}


	/**
	 * Retrieves the value of captcha.
	 *
	 * @return string The value of captcha.
	 */
	public function get(): string
	{
		if (empty($this->_captcha)) {
			$this->get_logger()->warning(
				"get(): Kein Captcha gesetzt",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__
				]
			);
			return '';
		}

		// Maskierung: z. B. nur Länge anzeigen
		$length = strlen((string) $this->_captcha);

		$this->get_logger()->debug(
			"get(): Captcha-Wert zurückgegeben",
			[
				'plugin'  => 'f12-cf7-captcha',
				'class'   => __CLASS__,
				'length'  => $length,
				'type'    => is_numeric($this->_captcha) ? 'numeric' : 'string'
			]
		);

		return (string) $this->_captcha;
	}

	/**
	 * Gets the calculation string for the captcha.
	 *
	 * This method returns a string containing the calculation for the captcha.
	 * The calculation is formatted as "<number_1> <method> <number_2> = ?",
	 * where <number_1> and <number_2> are the operands and <method> is the
	 * mathematical operation to be performed.
	 *
	 * @return string The calculation string for the captcha.
	 */
	public function get_calculation(): string
	{
		$calculation = sprintf(
			'<span class="captcha-calculation">%d %s %d = ?</span>',
			$this->_number_1,
			$this->_method,
			$this->_number_2
		);

		$this->get_logger()->debug(
			"get_calculation(): Mathe-Captcha erstellt",
			[
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'formula'  => sprintf("%d %s %d = ?", $this->_number_1, $this->_method, $this->_number_2)
			]
		);

		return $calculation;
	}

	/**
	 * Checks if the provided captcha code is valid.
	 *
	 * This method checks if the provided captcha code matches the captcha code
	 * associated with the provided captcha hash. It also ensures that the captcha
	 * has not been previously validated. If the captcha code is valid, the method
	 * marks the captcha as validated and saves it.
	 *
	 * @param string $captcha_code The captcha code to validate.
	 * @param string $captcha_hash The hash value of the captcha.
	 *
	 * @return bool Returns true if the captcha code is valid and the captcha is marked as validated, false
	 *              otherwise.
	 * @throws \Exception
	 */
	public function is_valid(string $captcha_code, string $captcha_hash): bool
	{
		/** @var UserData $User_Data */
		$User_Data  = $this->Controller->get_modul('user-data');
		$ip_address = $User_Data->get_ip_address();

		$this->get_logger()->debug(
			"is_valid(): Starte Validierung",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'ip'     => $ip_address,
				'hash'   => $captcha_hash
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), $ip_address);
		$Captcha = $Captcha->get_by_hash($captcha_hash);

		if (!$Captcha) {
			$this->get_logger()->warning(
				"is_valid(): Kein Captcha für Hash gefunden",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__,
					'ip'     => $ip_address
				]
			);
			return false;
		}

		if ($Captcha->get_validated() == 1) {
			$this->get_logger()->info(
				"is_valid(): Captcha bereits validiert → ungültig",
				[
					'plugin' => 'f12-cf7-captcha',
					'class'  => __CLASS__,
					'id'     => $Captcha->get_id()
				]
			);
			return false;
		}

		$Captcha->set_validated(1);
		$Captcha->save();

		if ((int)$captcha_code !== (int)$Captcha->get_code()) {
			$this->get_logger()->warning(
				"is_valid(): Code stimmt nicht überein → ungültig",
				[
					'plugin'    => 'f12-cf7-captcha',
					'class'     => __CLASS__,
					'id'        => $Captcha->get_id(),
					'submitted' => $captcha_code,
					'expected'  => $Captcha->get_code()
				]
			);
			return false;
		}

		$this->get_logger()->info(
			"is_valid(): Captcha erfolgreich validiert",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__,
				'id'     => $Captcha->get_id()
			]
		);

		return true;
	}

	/**
	 * Gets the form field for the captcha.
	 *
	 * This method returns the HTML string containing the form field for the captcha.
	 * The form field includes a label, calculation, reload button, and input field.
	 * The calculation is generated using the get_calculation() method.
	 * The reload button is generated using the get_reload_button() method.
	 *
	 * @param string            $field_name         The name of the field used as id and name for the input.
	 *
	 * @formatter:off
         *
         * @param array  $args{
         *      An associative array of additional arguments:
         *
         *      @type string    $classes            The CSS classes for the captcha.
         *      @type string    $wrapper_classes    The CSS classes for the captcha wrapper.
         *      @type array     $attrbiutes         An associative array of additional HTML attributes for
         *                                          the captcha input field.
         *      @type array     $wrapper_attributes An associative array of additional HTML
         *                                          attributes for the captcha wrapper. Default values are provided for all
         *                                          arguments.
         * }
         *
         * @formatter:on
	 *
	 * @return string The HTML string for the captcha form field.
	 */
	public function get_field( string $field_name, array $args = [] ): string {
		/*
		 * Parse the args
		 */
		$atts = [
			'classes'            => '',
			'wrapper_classes'    => '',
			'attributes'         => [],
			'wrapper_attributes' => [],
		];

		$atts = array_merge( $atts, $args );


		/**
		 * @var UserData $User_Data
		 */
		$User_Data  = $this->Controller->get_modul( 'user-data' );
		$ip_address = $User_Data->get_ip_address();

		/*
		 * Maybe generate the captcha session
		 */
		if ( $this->Captcha_Session != null ) {
			$Captcha_Session = $this->Captcha_Session;
			$this->get_logger()->debug(
				"get_field(math): Vorhandene Captcha-Session wiederverwendet",
				[
					'plugin' => 'f12-cf7-captcha',
					'ip'     => $ip_address,
				]
			);
		} else {
			$Captcha_Session = new Captcha( $this->Controller->get_logger(), $ip_address );
			$this->get_logger()->debug(
				"get_field(math): Neue Captcha-Session erstellt",
				[
					'plugin' => 'f12-cf7-captcha',
					'ip'     => $ip_address,
				]
			);
		}

		/*
		 * Update the captcha session values
		 */
		$Captcha_Session->set_code( $this->get() );
		$Captcha_Session->save();

		/*
		 * Store the session as latest session
		 */
		$this->Captcha_Session = $Captcha_Session;

		/*
		 * Get the label
		 */
		$label = $this->Controller->get_settings( 'protection_captcha_label', 'global' );

		/*
		 * Set the label
		 */
		#$label = sprintf( "<div class=\"c-header\"><div class=\"c-label\">%s</div><div class=\"c-data\">%s</div><div class=\"c-reload\">%s</div></div>", $label, $this->get_calculation(), $this->get_reload_button() );

		/*
		 * Parse the attributes
		 */
		$attributes = '';

		foreach ( $atts['attributes'] as $key => $value ) {
			$attributes .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		/*
		 * Parse the wrapper attributes
		 */
		$wrapper_attributes = '';
		foreach ( $atts['wrapper_attributes'] as $key => $value ) {
			$wrapper_attributes .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$hash = $Captcha_Session->get_hash();

		/*
		 * Generate a unique ID
		 */
		$hash_id    = $this->get_last_unique_id_hash();
		$captcha_id = $this->get_last_unique_id_captcha();


		/*
		 * Get the placeholder
		 */
		$placeholder = $this->Controller->get_settings( 'protection_captcha_placeholder', 'global' );

		/**
		 * Generate the captcha html output
		 *
		 * @var TemplateController $TemplateController
		 */
		$TemplateController = $this->Controller->get_modul( 'template' );


		/*
		 * Get Template
		 */
		$template = (int) $this->Controller->get_settings( 'protection_captcha_template', 'global' );

		if (!in_array($template, [0, 1, 2], true)) {
			$template = 0;
		}

		$this->get_logger()->info(
			"get_field(math): Captcha-Feld wird generiert",
			[
				'plugin'     => 'f12-cf7-captcha',
				'field_name' => $field_name,
				'template'   => $template,
				'hash_id'    => substr($hash_id, 0, 6) . '...',
				'captcha_id' => substr($captcha_id, 0, 6) . '...',
				'formula'    => sprintf("%d %s %d = ?", $this->_number_1, $this->_method, $this->_number_2)
			]
		);

		$captcha = $TemplateController->get_plugin_template( 'captcha/template-' . $template, [
			'hash_id'            => $hash_id,
			'hash_field_name'    => $field_name . '_hash',
			'hash_value'         => $hash,
			'wrapper_classes'    => $atts['wrapper_classes'],
			'wrapper_attributes' => $wrapper_attributes,
			'label'              => $label,
			'classes'            => $atts['classes'],
			'attributes'         => $attributes,
			'captcha_id'         => $captcha_id,
			'field_name'         => $field_name,
			'placeholder'        => $placeholder,
			'captcha_data'       => $this->get_calculation(),
			'captcha_reload'     => $this->get_reload_button(),
			'method'             => 'math',
		] );

		#$captcha = sprintf( '<input type="hidden" id="%s" name="%s_hash" value="%s"/>', esc_attr( $hash_id ), esc_attr( $field_name ), esc_attr( $hash ) );
		#$captcha .= sprintf( '<div class="%s" %s><label>%s</label><input class="f12c%s" data-method="math" %s type="text" id="%s" name="%s" placeholder="%s" value=""/></div>', ' ' . $atts['wrapper_classes'],
		#	$wrapper_attributes, $label, $atts['classes'], $attributes, esc_attr( $captcha_id ), esc_attr( $field_name ), esc_attr( $placeholder ) );

		#$captcha = sprintf( '<div class="f12-captcha template-1">%s</div>', $captcha );

		/**
		 * Update the Math Field before output
		 *
		 * The filter allows developers to customize the form field for the honeypot before returning.
		 *
		 * @param string  $captcha         The HTML content of the form input field used as honeypot.
		 * @param string  $field_name      The Name of the field used as id and name for the input.
		 * @param string  $label           The Label for the captcha
		 * @param Captcha $Captcha_Session The Captcha Session storing the Captcha Information
		 * @param string  $classes         The CSS classes for the captcha
		 *
		 * @since 1.0.0
		 */
		$filtered = apply_filters( 'f12-cf7-captcha-get-form-field-math', $captcha, $field_name, $label, $Captcha_Session, $atts['classes'] );


		if ($filtered !== $captcha) {
			$this->get_logger()->debug(
				"get_field(math): Captcha-Feld durch Filter modifiziert",
				[
					'plugin'     => 'f12-cf7-captcha',
					'field_name' => $field_name
				]
			);
		}

		return $filtered;
	}

	/**
	 * Retrieves the AJAX response.
	 *
	 * Retrieves the response from an AJAX request by calling the get_calculation() method.
	 *
	 * @return string The AJAX response.
	 */
	public function get_ajax_response(): string
	{
		$calculation = $this->get_calculation();

		$this->get_logger()->debug(
			"get_ajax_response(math): Captcha-Formel an Ajax zurückgegeben",
			[
				'plugin'  => 'f12-cf7-captcha',
				'class'   => __CLASS__,
				'formula' => sprintf("%d %s %d = ?", $this->_number_1, $this->_method, $this->_number_2)
			]
		);

		return $calculation;
	}

}
