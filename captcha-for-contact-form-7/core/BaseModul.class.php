<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseModul {
	protected string $message = '';

	/**
	 * Resolver for the message, kept so get_message() can produce it even when the init
	 * callback set up by set_message_on_init() never reached this instance.
	 *
	 * @var callable|null
	 */
	private $message_resolver = null;
	/**
	 * @var CF7Captcha
	 */
	protected CF7Captcha $Controller;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param CF7Captcha $Controller The CF7Captcha controller object.
	 *
	 * @return void
	 */
	public function __construct(CF7Captcha $Controller)
	{
		$this->Controller = $Controller;

		if (f12_is_debug()) {
			$this->get_logger()->info('Constructor completed.');
		}
	}

	public function get_logger() : LoggerInterface {
		return $this->Controller->get_logger();
	}

	/**
	 * Retrieves the message stored in the object.
	 *
	 * @return string The message stored in the object.
	 */
	public function get_message(): string
	{
		// Resolve here if the init callback never reached this instance. It happens: a module
		// constructed before `init` registers a callback, and on some request paths that
		// callback does not run for the object that ends up doing the work — measured against
		// GiveWP, where the live validator carried an empty message while a freshly built one
		// carried the right text. The visible result was a rejection reading "Captcha not
		// correct:" with nothing after the colon, because the empty string travelled all the way
		// to the visitor.
		//
		// Guarded on `init` for the same reason set_message_on_init() defers at all: translating
		// earlier trips WordPress 6.7's just-in-time textdomain notice.
		if ($this->message === '' && $this->message_resolver !== null && did_action('init')) {
			$this->message = (string) call_user_func($this->message_resolver);
		}

		if (f12_is_debug()) {
			$this->get_logger()->debug('Retrieving message property.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'message' => $this->message,
			]);
		}

		return $this->message;
	}

	/**
	 * Set the message.
	 *
	 * @param string $message The message to be set.
	 *
	 * @return void
	 */
	protected function set_message(string $message): void
	{
		if (f12_is_debug()) {
			$this->get_logger()->info('Setting message property.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'old_message' => $this->message,
				'new_message' => $message,
			]);
		}

		$this->message = $message;

		if (f12_is_debug()) {
			$this->get_logger()->debug('Message set successfully.');
		}
	}

	/**
	 * Assign a translatable message, deferring the actual translation until the
	 * `init` hook has fired.
	 *
	 * Protection validators are instantiated from Protection::init_modules(),
	 * which runs on `f12_cf7_captcha_compatibilities_loaded` (fired during
	 * `after_setup_theme`) — i.e. before `init`. Calling __() that early makes
	 * WordPress 6.7+ emit a `_load_textdomain_just_in_time` "called incorrectly"
	 * notice, because the text domain would be loaded before translations are
	 * available. get_message() is only consumed during form validation (well
	 * after `init`), so resolving the string on `init` is safe.
	 *
	 * The $resolver closure must contain the literal __() call so the i18n
	 * string extractor can still pick up the msgid.
	 *
	 * @param callable $resolver Returns the translated message string.
	 *
	 * @return void
	 */
	protected function set_message_on_init(callable $resolver): void
	{
		// Kept as the fallback for get_message(): the init callback below does not reach every
		// instance on every request path, and a module that cannot name itself rejects people
		// without telling them why.
		$this->message_resolver = $resolver;

		if (did_action('init')) {
			$this->set_message((string) $resolver());
			return;
		}

		add_action('init', function () use ($resolver) {
			$this->set_message((string) $resolver());
		});
	}

	/**
	 * Get a protection setting resolved through the current context hierarchy.
	 *
	 * Falls back to reading from global settings if no context is set on Protection.
	 *
	 * @param string $key The setting key.
	 *
	 * @return mixed
	 */
	protected function get_protection_setting( string $key ) {
		try {
			/** @var \f12_cf7_captcha\core\protection\Protection $protection */
			$protection = $this->Controller->get_module( 'protection' );
			return $protection->get_setting( $key );
		} catch ( \Throwable $e ) {
			// Fallback to direct global read if Protection module not yet available
			return $this->Controller->get_settings( $key, 'global' );
		}
	}
}