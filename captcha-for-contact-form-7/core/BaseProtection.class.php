<?php

namespace f12_cf7_captcha\core;

if(!defined('ABSPATH')){
    exit;
}

abstract class BaseProtection extends BaseModul {

	/**
	 * Determines whether the functionality is enabled.
	 *
	 * @return bool True if the functionality is enabled, false otherwise.
	 */
	protected abstract function is_enabled(): bool;

	public abstract function success(): void;
}