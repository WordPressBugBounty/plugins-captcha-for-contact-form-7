<?php

namespace f12_cf7_captcha\core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Log_WordPress_Interface {
	/**
	 * @param string $type      The type of log entry.
	 * @param array  $form_data The form data to be logged.
	 * @param bool   $is_spam   Whether the submission is spam.
	 * @param string $message   Optional message.
	 *
	 * @return bool
	 */
	public function maybe_log( string $type, array $form_data, bool $is_spam = true, string $message = '' ): bool;

	/**
	 * @return bool
	 */
	public function is_logging_enabled(): bool;

	/**
	 * @return int
	 */
	public function get_count(): int;

	/**
	 * @return int
	 */
	public function reset_table(): int;

	/**
	 * @param string $create_time
	 *
	 * @return int
	 */
	public function delete_older_than( string $create_time ): int;
}
