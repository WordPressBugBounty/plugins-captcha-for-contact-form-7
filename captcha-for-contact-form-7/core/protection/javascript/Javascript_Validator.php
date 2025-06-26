<?php

namespace f12_cf7_captcha\core\protection\javascript;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;

class Javascript_Validator extends BaseProtection
{

    /**
     * @var array<string => float>
     */
    private $start_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * @var array <string => float>
     */
    private $end_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * Private constructor for the class.
     *
     * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
     * This hook is used to retrieve log data.
     */
    public function __construct(CF7Captcha $Controller)
    {
        parent::__construct($Controller);

        $this->init_php();
        $this->init_js();

        add_filter('f12-cf7-captcha-log-data', [$this, 'get_log_data']);
    }

    protected function is_enabled(): bool
    {
        $is_enabled =  (int)$this->Controller->get_settings('protection_javascript_enable', 'global') === 1;

	    return apply_filters('f12-cf7-captcha-skip-validation-javascript', $is_enabled);
    }

    /**
     * Add the Timer Data to the Data
     *
     * @param $data
     *
     * @return mixed
     */
    public function get_log_data($data)
    {
        /*
         * Get the default data
         */
        $data['Timer Data'] = $this->get_timer_as_string();
        /*
         * Get the PHP Data
         */
        $data['Timer Data PHP'] = $this->get_timer_as_string('php');
        /*
         * Get the JS Data
         */
        $data['Timer Data JS'] = $this->get_timer_as_string('js');

        return $data;
    }

    /**
     * Initializes JavaScript variables for tracking form submission times.
     *
     * This method initializes JavaScript variables by extracting start and end times from the $_POST array
     * and sets the start and end times for tracking form submission times.
     */
    public function init_js()
    {
        $start = 0.0;
        $end = 0.0;

        if (isset($_POST['js_start_time']) && isset($_POST['js_end_time'])) {
            $start = (float)$_POST['js_start_time'];
            $end = (float)$_POST['js_end_time'];
        }

        // Avada
        if (isset($_POST['formData']) && !is_array($_POST['formData'])) {
            parse_str(wp_unslash($_POST['formData']), $form_data);

            if (isset($form_data['js_start_time']) && isset($form_data['js_end_time'])) {
                $start = (float)$form_data['js_start_time'];
                $end = (float)$form_data['js_end_time'];
            }
        }

	    if(isset($_POST['data']) && defined('FLUENTFORM') && is_string($_POST['data'])) {
		    $decodedFormData = urldecode($_POST['data']);
		    parse_str($decodedFormData, $form_data);

		    if(isset($form_data['js_start_time'])) {
			    $start = (float)$form_data['js_start_time'];
		    }

		    if(isset($form_data['js_end_time'])) {
			    $end = (float)$form_data['js_end_time'];
		    }
	    }

        $this->set_start_time('js', $start);
        $this->set_end_time('js', $end);
    }

    /**
     * @param string $type php or js
     * @param float  $microtime
     *
     * @return void
     */
    private function set_start_time(string $type, float $microtime)
    {

        $this->start_time[$type] = $microtime;
    }

    /**
     * @param string $type php or js
     * @param float  $microtime
     *
     * @return void
     */
    private function set_end_time(string $type, float $microtime)
    {
        $this->end_time[$type] = $microtime;
    }

    /**
     * Initializes the PHP start time for form processing.
     *
     * This method retrieves the PHP start time from the request data and sets it for form processing.
     * It first checks if the 'php_start_time' parameter is set in the $_POST superglobal array.
     * If found, it assigns the float value of the parameter to the local variable $start.
     *
     * If the 'php_start_time' parameter is not found in the $_POST array,
     * it checks if the 'formData' parameter is set in the $_POST superglobal array.
     * If found, it extracts and assigns the 'php_start_time' parameter value from the 'formData' using parse_str
     * and wp_unslash.
     *
     * Finally, it calls the 'set_start_time' method of the current object to set the PHP start time.
     * If the $start value is not equal to 0.0, it also calls the 'set_end_time' method to set the PHP end time.
     * If the $start value is equal to 0.0, it calls the 'set_start_time' method to set the PHP start time using
     * microtime.
     *
     * @return void
     */
    private function init_php()
    {
        $start = 0.0;

        // CF7, Discussions ...
        if (isset($_POST['php_start_time'])) {
            $start = (float)$_POST['php_start_time'];
        }

        // Avada
        if (isset($_POST['formData'])) {
            parse_str(wp_unslash($_POST['formData']), $form_data);

            if (isset($form_data['php_start_time'])) {
                $start = (float)$form_data['php_start_time'];
            }
        }

		// Fluentform
	    if(isset($_POST['data']) && defined('FLUENTFORM') && is_string($_POST['data'])) {
		    $decodedFormData = urldecode($_POST['data']);
		    parse_str($decodedFormData, $form_data);

			if(isset($form_data['php_start_time'])) {
				$start = (float)$form_data['php_start_time'];
			}
	    }

        $this->set_start_time('php', $start);

        if ($start != 0.0) {
            $this->set_end_time('php', microtime(true));
        } else {
            $this->set_start_time('php', microtime(true));
        }

    }

    /**
     * Retrieves additional form fields for the current form.
     *
     * This method generates HTML code for additional form fields that should be included in the form.
     *
     * @return string The additional form fields HTML code.
     */
    public function get_form_field(): string
    {
        if (!$this->is_enabled()) {
            return '';
        }

        $time = $this->get_start_time('php');

        $additional_fields = [
            '<input type="hidden" name="php_start_time" value="' . $time . '" />',
            '<input type="hidden" name="js_end_time" class="js_end_time" value="" />',
            '<input type="hidden" name="js_start_time" class="js_start_time" value="" />'
        ];

        return implode("", $additional_fields);
    }

    /**
     * Retrieves the CAPTCHA field for the current form.
     *
     * This method generates the CAPTCHA field HTML code that should be included in the form.
     *
     * @param mixed ...$args Optional arguments.
     *
     * @return string The CAPTCHA field HTML code.
     */
    public function get_captcha(...$args): string
    {
        if (!$this->is_enabled()) {
            return '';
        }

        return $this->get_form_field();
    }

    /**
     * Retrieves the start time for a given type.
     *
     * This method returns the start time for a specified type. The default type is 'php'.
     *
     * @param string $type The type of start time to retrieve. Default is 'php'.
     *
     * @return float The start time for the specified type.
     */
    private function get_start_time($type = 'php'): float
    {
        return $this->start_time[$type];
    }

    /**
     * Retrieves the end time for a given type.
     *
     * This method returns the end time for the specified type. The default type is 'php'.
     *
     * @param string $type (optional) The type of end time to retrieve. Defaults to 'php'.
     *
     * @return float The end time for the specified type.
     */
    private function get_end_time($type = 'php'): float
    {
        return $this->end_time[$type];
    }

    /**
     * @param string $type   php or js
     * @param string $output ms for milliseconds, s for seconds
     *
     * @return string
     */
    private function get_difference(string $type = 'php', string $output = 'ms'): string
    {
        $difference = $this->get_end_time($type) - $this->get_start_time($type);
        if ($output == 'ms') {
            return round($difference * 1000);
        }

        return round($difference);
    }

    /**
     * Retrieves the timer information as a formatted string.
     *
     * This method retrieves the start time, end time, and time passed and formats them into a string.
     *
     * @param string $type (optional) The type of timer to retrieve. Default is 'php'.
     *
     * @return string The timer information as a formatted string.
     */
    private function get_timer_as_string(string $type = 'php'): string
    {
        $data = [
            'Form loaded' => date('d.m.Y H:i:s', (int)$this->get_start_time($type)) . ' [' . $this->get_start_time($type) . ']',
            'Form submitted' => date('d.m.Y H:i:s', (int)$this->get_end_time($type)) . ' [' . $this->get_end_time($type) . ']',
            'Time passed' => $this->get_difference($type) . ' ms, ' . $this->get_difference($type, 's') . ' sec',
        ];

        $response = '';
        foreach ($data as $key => $value) {
            $response .= $key . ': ' . $value . ',';
        }

        return $response;
    }

    /**
     * Check if the user is a human.
     *
     * @return bool
     */
    public function is_human(): bool
    {
        if ($this->get_difference('js') == 0 || $this->get_difference('js') == '0.0') {
            return false;
        }

        if ($this->get_start_time('js') == 0.0) {
            return false;
        }

        if ($this->get_end_time('js') == 0.0) {
            return false;
        }

        return true;
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
        if (!$this->is_enabled()) {
            return false;
        }

        if (!$this->is_human()) {
            $this->set_message(__('javascript-protection', 'captcha-for-contact-form-7' ));

            return true;
        }

        return false;
    }

    public function success(): void
    {
        // TODO: Implement success() method.
    }
}