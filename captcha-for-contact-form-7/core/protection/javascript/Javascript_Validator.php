<?php

namespace f12_cf7_captcha\core\protection\javascript;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseProtection;
use f12_cf7_captcha\core\protection\Field_Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Javascript_Validator extends BaseProtection
{
    /**
     * Smallest accepted gap between the js start and end timestamps, in seconds.
     *
     * Deliberately tiny, because this gap is *not* how long the visitor spent on the form.
     * js_start_time is written when the captcha initialises, which measurements on the E2E
     * suite put only 0.35–0.83 s before the submit even for a scripted run — so a threshold
     * anywhere near "human filling speed" would reject real people on slow devices.
     *
     * All this rejects is the degenerate machine case of two timestamps written in the same
     * breath. Enforcing a real minimum dwell time is the Timer module's job
     * (protection_time_ms): it measures server-side from render and cannot be forged.
     */
    private const DEFAULT_MIN_DURATION = 0.05;

    /**
     * Slack allowed when a token looks like it was issued in the future.
     *
     * Only covers the server's own clock being adjusted between render and submit (NTP step,
     * load-balanced nodes drifting). Client clocks never enter this comparison.
     */
    private const CLOCK_SKEW_TOLERANCE = 300;

    /**
     * @var array<string, float>
     */
    private $start_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * @var array<string, float>
     */
    private $end_time = [
        'php' => 0.0,
        'js' => 0.0
    ];

    /**
     * Verified token from the submitted data, or null when the submission carried none.
     *
     * Null is not by itself suspicious: form HTML rendered before this plugin version, held
     * in a full-page cache, or produced by an integration that injects the timing fields on
     * its own (Fluent Forms conversational) legitimately has no token. Those fall back to the
     * legacy fixed field names and the legacy (delta-only) checks.
     */
    private ?Field_Token $Token = null;

    /**
     * Private constructor for the class.
     *
     * Initializes the PHP and JS components and sets up a filter for the f12-cf7-captcha-log-data hook.
     * This hook is used to retrieve log data.
     */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		$this->get_logger()->info('Constructor started.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$this->Token = Field_Token::from_request($this->collect_request_data());

		$this->get_logger()->debug('Field token resolved from request.', [
			'has_token' => $this->Token !== null ? 'yes' : 'no',
		]);

		$this->init_php();
		$this->init_js();

		add_filter('f12-cf7-captcha-log-data', [$this, 'get_log_data']);

		$this->get_logger()->info('Constructor completed.', [
			'class' => __CLASS__,
		]);
	}

	protected function is_enabled(): bool
	{
		$raw = $this->get_protection_setting('protection_javascript_enable');

		if ($raw === '' || $raw === null) {
			$raw = 1;
		}

		$is_enabled = (int) $raw === 1;

		$debug = f12_is_debug();

		if ($debug) {
			if ($is_enabled) {
				$this->get_logger()->info('JavaScript protection is enabled.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);
			} else {
				$this->get_logger()->warning('JavaScript protection is disabled.', [
					'class' => __CLASS__,
					'method' => __METHOD__,
				]);
			}
		}

		$result = apply_filters('f12-cf7-captcha-skip-validation-javascript', $is_enabled);

		if ($debug && $is_enabled && !$result) {
			$this->get_logger()->debug('JavaScript protection skipped by filter.', [
				'filter' => 'f12-cf7-captcha-skip-validation-javascript',
				'original_state' => $is_enabled,
			]);
		}

		return $result;
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
		$this->get_logger()->info('Adding timer data to log data.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Get the default data
		$data['Timer Data'] = $this->get_timer_as_string();
		$this->get_logger()->debug('Complete timer data added.', [
			'timer_data' => $data['Timer Data'],
		]);

		// Get the PHP data
		$data['Timer Data PHP'] = $this->get_timer_as_string('php');
		$this->get_logger()->debug('PHP timer data added.', [
			'timer_data_php' => $data['Timer Data PHP'],
		]);

		// Get the JS data
		$data['Timer Data JS'] = $this->get_timer_as_string('js');
		$this->get_logger()->debug('JS timer data added.', [
			'timer_data_js' => $data['Timer Data JS'],
		]);

		$this->get_logger()->info('Log data array completed.', [
			'final_data_keys' => array_keys($data),
		]);

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
		$this->get_logger()->info('Initializing JavaScript timer data.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$data = $this->collect_request_data();

		$start = (float) ( $this->read_field($data, Field_Token::SLOT_JS_START) ?? 0.0 );
		$end   = (float) ( $this->read_field($data, Field_Token::SLOT_JS_END) ?? 0.0 );

		$this->set_start_time('js', $start);
		$this->set_end_time('js', $end);

		$this->get_logger()->info('JavaScript timer data set successfully.', [
			'js_start' => $this->get_start_time('js'),
			'js_end' => $this->get_end_time('js'),
		]);
	}

	/**
	 * Flatten every place a form plugin might have hidden our fields into one array.
	 *
	 * Avada posts the whole form url-encoded inside `formData`, Fluent Forms inside `data`.
	 * Collecting them once here means the token lookup and every field read see the same view
	 * of the request, instead of each consumer re-parsing the same payloads.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_request_data(): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the form plugin
		$data = (array) wp_unslash($_POST);

		// Avada: the real fields live url-encoded inside `formData`.
		if (isset($data['formData']) && is_string($data['formData'])) {
			parse_str($data['formData'], $form_data);
			$data = array_merge($data, $form_data);
		}

		// Fluent Forms: same idea, but url-encoded twice and inside `data`.
		if (isset($data['data']) && is_string($data['data']) && defined('FLUENTFORM')) {
			parse_str(urldecode($data['data']), $form_data);
			$data = array_merge($data, $form_data);
		}

		return $data;
	}

	/**
	 * Read one protected field, preferring the rotated name and falling back to the legacy one.
	 *
	 * The fallback is what keeps this change from blocking real visitors on rollout: form HTML
	 * that was rendered (or cached) before the update carries the old fixed names and no token,
	 * and must keep validating exactly as it did before.
	 *
	 * @param array  $data The flattened request data.
	 * @param string $slot One of the Field_Token::SLOT_* constants.
	 *
	 * @return string|null The raw value, or null when the field is absent under either name.
	 */
	private function read_field(array $data, string $slot): ?string
	{
		if ($this->Token !== null) {
			$rotated = $this->Token->field_name($slot);

			if (isset($data[$rotated]) && is_scalar($data[$rotated])) {
				return sanitize_text_field((string) $data[$rotated]);
			}
		}

		// Legacy slot names double as the fallback field names.
		if (isset($data[$slot]) && is_scalar($data[$slot])) {
			return sanitize_text_field((string) $data[$slot]);
		}

		return null;
	}

    /**
     * @param string $type php or js
     * @param float  $microtime
     *
     * @return void
     */
	private function set_start_time(string $type, float $microtime)
	{
		$this->get_logger()->debug("Setting start time for type '{$type}'.", [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'type'      => $type,
			'microtime' => $microtime,
		]);

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
		$this->get_logger()->debug("Setting end time for type '{$type}'.", [
			'class'     => __CLASS__,
			'method'    => __METHOD__,
			'type'      => $type,
			'microtime' => $microtime,
		]);

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
		$this->get_logger()->info('Initializing PHP timer data.', [
			'class'  => __CLASS__,
			'method' => __METHOD__,
		]);

		$start = (float) ( $this->read_field($this->collect_request_data(), Field_Token::SLOT_PHP_START) ?? 0.0 );

		$this->set_start_time('php', $start);

		if ($start != 0.0) {
			$this->get_logger()->debug('PHP start time exists. Setting end time.');
			$this->set_end_time('php', microtime(true));
		} else {
			$this->get_logger()->debug('PHP start time missing. Setting current time as start time.');
			$this->set_start_time('php', microtime(true));
		}

		$this->get_logger()->info('PHP timer data set successfully.', [
			'php_start' => $this->get_start_time('php'),
			'php_end' => $this->get_end_time('php'),
		]);
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
		$this->get_logger()->info('Generating hidden form fields for timer.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Timer fields not generated because JavaScript protection is disabled.');
			return '';
		}

		$time = $this->get_start_time('php');
		$this->get_logger()->debug('PHP start time value: ' . $time);

		$Token = Field_Token::current();

		// The `js_start_time` / `js_end_time` CSS classes stay fixed while the `name` attributes
		// rotate: the browser-side code selects by class, so it keeps working untouched, and a
		// bot still cannot know under which names to POST without reading this markup.
		$additional_fields = [
			'<input type="hidden" name="' . esc_attr($Token->field_name(Field_Token::SLOT_PHP_START)) . '" value="' . esc_attr((string) $time) . '" />',
			'<input type="hidden" name="' . esc_attr($Token->field_name(Field_Token::SLOT_JS_END)) . '" class="js_end_time" value="" />',
			'<input type="hidden" name="' . esc_attr($Token->field_name(Field_Token::SLOT_JS_START)) . '" class="js_start_time" value="" />'
		];

		$output = implode("", $additional_fields);

		$this->get_logger()->debug('Generated form fields returned.', [
			'output_length' => strlen($output),
		]);

		return $output;
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
		$this->get_logger()->info('Attempting to generate captcha form field.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		if (!$this->is_enabled()) {
			$this->get_logger()->warning('Captcha output skipped because JavaScript protection is disabled.');
			return '';
		}

		$form_field_html = $this->get_form_field();

		if (empty($form_field_html)) {
			$this->get_logger()->error('Form field generation failed.', [
				'class' => __CLASS__,
			]);
			// Optional: A fallback value or error message
		} else {
			$this->get_logger()->debug('Captcha form field generated successfully.', [
				'html_length' => strlen($form_field_html),
			]);
		}

		return $form_field_html;
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
	private function get_start_time(string $type = 'php'): float
	{
		if (!isset($this->start_time[$type])) {
			$this->get_logger()->error("Error: Start time type '{$type}' not found.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'available_types' => array_keys($this->start_time),
			]);
			return 0.0;
		}

		$this->get_logger()->debug("Start time for type '{$type}' retrieved: " . $this->start_time[$type], [
			'type' => $type,
		]);

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
	private function get_end_time(string $type = 'php'): float
	{
		if (!isset($this->end_time[$type])) {
			$this->get_logger()->error("Error: End time type '{$type}' not found.", [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'available_types' => array_keys($this->end_time),
			]);
			return 0.0;
		}

		$this->get_logger()->debug("End time for type '{$type}' retrieved: " . $this->end_time[$type], [
			'type' => $type,
		]);

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
		$this->get_logger()->info("Calculating time difference for type '{$type}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'output_format' => $output,
		]);

		$start_time = $this->get_start_time($type);
		$end_time = $this->get_end_time($type);

		$difference = $end_time - $start_time;

		$this->get_logger()->debug("Raw time difference: " . $difference . " seconds.", [
			'start_time' => $start_time,
			'end_time' => $end_time,
		]);

		if ($output === 'ms') {
			$result = round($difference * 1000);
			$this->get_logger()->debug('Time converted to milliseconds.', [
				'result_ms' => $result,
			]);
			return (string)$result;
		}

		$result = round($difference);
		$this->get_logger()->debug('Time rounded to seconds.', [
			'result_s' => $result,
		]);
		return (string)$result;
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
		$this->get_logger()->info("Creating formatted time string for type '{$type}'.", [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$start_time = $this->get_start_time($type);
		$end_time = $this->get_end_time($type);

		if ($start_time === 0.0 || $end_time === 0.0) {
			$this->get_logger()->warning('Time data missing or invalid. Cannot format string.', [
				'start_time' => $start_time,
				'end_time' => $end_time,
			]);
			return 'Time data not available.';
		}

		$data = [
			'Form loaded' => date('d.m.Y H:i:s', (int)$start_time) . ' [' . $start_time . ']',
			'Form submitted' => date('d.m.Y H:i:s', (int)$end_time) . ' [' . $end_time . ']',
			'Elapsed time' => $this->get_difference($type) . ' ms, ' . $this->get_difference($type, 's') . ' s',
		];

		$response = '';
		foreach ($data as $key => $value) {
			$response .= $key . ': ' . $value . ', ';
		}

		$response = rtrim($response, ', '); // Remove trailing comma and space

		$this->get_logger()->debug("Formatted time string created.", [
			'output' => $response,
		]);

		return $response;
	}

    /**
     * Check if the user is a human.
     *
     * @return bool
     */
	public function is_human(): bool
	{
		$debug = f12_is_debug();

		if ($debug) {
			$this->get_logger()->info('Performing JavaScript-based human verification.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		$start = $this->get_start_time('js');
		$end   = $this->get_end_time('js');

		// Check if start time was captured
		if ($start <= 0.0) {
			if ($debug) {
				$this->get_logger()->warning('JS start time was not captured.');
			}
			return false;
		}

		// Check if end time was captured
		if ($end <= 0.0) {
			if ($debug) {
				$this->get_logger()->warning('JS end time was not captured.');
			}
			return false;
		}

		// Both timestamps come from the *same* client clock, so their difference is meaningful
		// even when that clock is wrong. The absolute values are not — they are never compared
		// against the server clock, because a visitor with a skewed system clock is a real
		// visitor, not a bot.
		$duration = $end - $start;

		// Previously this compared the difference rounded to whole milliseconds against "0",
		// which let a *negative* duration through — an end time before the start time was
		// treated as valid.
		if ($duration <= 0.0) {
			if ($debug) {
				$this->get_logger()->warning('JS end time is not after the start time. Classified as bot.', [
					'duration' => $duration,
				]);
			}
			return false;
		}

		$minimum = $this->get_min_duration();
		if ($duration < $minimum) {
			if ($debug) {
				$this->get_logger()->warning('Form filled in faster than humanly possible.', [
					'duration' => $duration,
					'minimum'  => $minimum,
				]);
			}
			return false;
		}

		// The signed token is the only value in the submission a bot cannot fabricate, so the
		// page-age check hangs off it rather than off the submitted php_start_time.
		if ($this->Token !== null) {
			$age = $this->Token->get_age();

			if ($age < -self::CLOCK_SKEW_TOLERANCE) {
				if ($debug) {
					$this->get_logger()->warning('Token was issued in the future. Classified as bot.', [
						'age' => $age,
					]);
				}
				return false;
			}

			$max_age = $this->get_max_token_age();
			if ($age > $max_age) {
				if ($debug) {
					$this->get_logger()->warning('Token is stale — form HTML is older than the allowed window.', [
						'age'     => $age,
						'max_age' => $max_age,
					]);
				}
				return false;
			}
		}

		if ($debug) {
			$this->get_logger()->info('JavaScript-based verification successful. Classified as human.');
		}

		return true;
	}

	/**
	 * Smallest accepted time between form render and submit, in seconds.
	 *
	 * @filter f12-cf7-captcha-js-min-duration
	 */
	private function get_min_duration(): float
	{
		return (float) apply_filters('f12-cf7-captcha-js-min-duration', self::DEFAULT_MIN_DURATION);
	}

	/**
	 * Largest accepted age of the signed token, in seconds.
	 *
	 * @filter f12-cf7-captcha-token-max-age
	 */
	private function get_max_token_age(): int
	{
		return (int) apply_filters('f12-cf7-captcha-token-max-age', Field_Token::DEFAULT_MAX_AGE);
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
		$debug = f12_is_debug();

		if ($debug) {
			$this->get_logger()->info('Performing spam check.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);
		}

		if (!$this->is_enabled()) {
			if ($debug) {
				$this->get_logger()->debug('Spam check skipped: JavaScript protection is disabled.', [
					'class' => __CLASS__,
				]);
			}
			return false;
		}

		if (!$this->is_human()) {
			if ($debug) {
				$this->get_logger()->warning('Form classified as spam: JavaScript validation failed.', [
					'class' => __CLASS__,
				]);
			}
			$this->set_message(__('javascript-protection', 'captcha-for-contact-form-7'));
			return true;
		}

		if ($debug) {
			$this->get_logger()->info('Form classified as not spam.');
		}

		return false;
	}

	public function success(): void
	{
		$this->get_logger()->info('Successful form submission detected.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Additional logic can be implemented here
		// to be executed upon successful validation.
		// For example:
		// - Delete temporary data
		// - Send a notification
		// - Update counters

		// TODO: Implement success logic here.
	}
}