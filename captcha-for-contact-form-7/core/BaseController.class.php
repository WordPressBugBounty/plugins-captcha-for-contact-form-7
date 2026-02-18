<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\Protection;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseController {
	/**
	 * Represents a variable to store the name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * @var string $id  The unique identifier for the entity.
	 *                  This should be a string value.
	 */
	protected string $id = '';

	/**
	 * @var string $description Description
	 */
	protected string $description = '';

	/**
	 * @var string $settings_key The settings key used to check if this module is enabled.
	 */
	protected string $settings_key = '';

	/**
	 * @var array $hooks Declarative hook registration.
	 *
	 * Format:
	 * [
	 *     ['type' => 'action', 'hook' => 'hook_name', 'method' => 'method_name', 'priority' => 10, 'args' => 1],
	 *     ['type' => 'filter', 'hook' => 'hook_name', 'method' => 'method_name', 'priority' => 10, 'args' => 1],
	 * ]
	 */
	protected array $hooks = [];

	/**
	 * @var CF7Captcha|null The instance of the CF7 Controller
	 */
	protected ?CF7Captcha $Controller;
	/**
	 * @var Log_WordPress_Interface|null The instance of the logger used for logging messages.
	 */
	protected ?Log_WordPress_Interface $Logger;

	/**
	 * Constructor for the class.
	 *
	 * @param CF7Captcha    $Controller The CF7Captcha object that will be assigned to $this->Controller.
	 * @param Log_WordPress_Interface $Logger     The Log_WordPress object that will be assigned to $this->Logger.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller, Log_WordPress_Interface $Logger)
	{
		$this->Controller = $Controller;
		$this->Logger = $Logger;
		add_action('f12_cf7_captcha_compatibilities_loaded', array($this, 'wp_init'));
	}

	/**
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->Controller->get_logger();
	}

	/**
	 * Get the name of the object.
	 *
	 * @return string The name of the object.
	 */
	public function get_name(): string
	{
		return __($this->name, 'captcha-for-contact-form-7');
	}

	/**
	 * Returns the description of the object.
	 *
	 * @return string The description of the object.
	 */
	public function get_description(): string
	{
		return $this->description;
	}

	/**
	 * Get the ID of the instance.
	 *
	 * @return string The ID of the instance.
	 */
	public function get_id(): string
	{
		return $this->id;
	}

	/**
	 * Initializes the WordPress plugin.
	 *
	 * This method checks if the plugin is enabled and then invokes the on_init() method.
	 *
	 * @return void
	 */
	public function wp_init(): void
	{
		if ($this->is_enabled()) {
			$this->on_init();
		}
	}

	/**
	 * Check if this module is enabled.
	 *
	 * Uses $this->settings_key and $this->id for the filter name.
	 * Formula: $this->is_installed() && setting === 1, with filter f12_cf7_captcha_is_installed_{$this->id}
	 *
	 * @return bool
	 */
	public function is_enabled(): bool
	{
		$is_installed = $this->is_installed();

		$setting_value = $this->Controller->get_settings($this->settings_key, 'global');

		if ($setting_value === '' || $setting_value === null) {
			$setting_value = 1;
		}

		$is_active = $is_installed && (int) $setting_value === 1;

		return apply_filters('f12_cf7_captcha_is_installed_' . $this->id, $is_active);
	}

	/**
	 * Check if the underlying plugin/software is installed.
	 *
	 * @return bool
	 */
	abstract public function is_installed(): bool;

	/**
	 * Initialize the module by translating the name and registering hooks from $this->hooks.
	 *
	 * Controllers with extra logic should override on_init() and call parent::on_init().
	 *
	 * @return void
	 */
	protected function on_init(): void
	{
		foreach ($this->hooks as $hook) {
			$type     = $hook['type'] ?? 'action';
			$name     = $hook['hook'];
			$method   = $hook['method'];
			$priority = $hook['priority'] ?? 10;
			$args     = $hook['args'] ?? 1;

			if ($type === 'filter') {
				add_filter($name, [$this, $method], $priority, $args);
			} else {
				add_action($name, [$this, $method], $priority, $args);
			}
		}
	}

	/**
	 * Default implementation for adding spam protection.
	 *
	 * Simply echoes the captcha HTML. Controllers with custom rendering should override this.
	 *
	 * @param mixed ...$args Any number of arguments.
	 * @return void
	 */
	public function wp_add_spam_protection(...$args)
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
		echo $this->get_captcha_html();
	}

	/**
	 * Get the Protection module instance.
	 *
	 * @return Protection
	 */
	protected function get_protection(): Protection
	{
		return $this->Controller->get_module('protection');
	}

	/**
	 * Get the captcha HTML from the Protection module.
	 *
	 * @param string|null $form_id Optional form ID for per-form settings resolution.
	 *
	 * @return string
	 */
	protected function get_captcha_html( ?string $form_id = null ): string
	{
		$protection = $this->get_protection();

		$protection->set_context( $this->id, $form_id );
		$html = $protection->get_captcha();
		$protection->clear_context();

		return $html;
	}

	/**
	 * Check if the submitted POST data is spam.
	 *
	 * @param array|null  $post_data POST data to check. Defaults to $_POST.
	 * @param string|null $form_id   Optional form ID for per-form settings resolution.
	 *
	 * @return string|null Error message if spam detected, null otherwise.
	 */
	protected function check_spam( ?array $post_data = null, ?string $form_id = null ): ?string
	{
		if ( $post_data === null ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling compatibility controller
			$post_data = $_POST;
		}

		$protection = $this->get_protection();

		$protection->set_context( $this->id, $form_id );
		$is_spam = $protection->is_spam( $post_data );
		$message = $is_spam ? $protection->get_message() : null;
		$protection->clear_context();

		return $message;
	}

	/**
	 * Format a spam error message for display.
	 *
	 * @param string $message The raw spam message.
	 *
	 * @return string
	 */
	protected function format_spam_message( string $message ): string
	{
		return sprintf(
			esc_html__( 'Captcha not correct: %s', 'captcha-for-contact-form-7' ),
			esc_html( $message )
		);
	}
}
