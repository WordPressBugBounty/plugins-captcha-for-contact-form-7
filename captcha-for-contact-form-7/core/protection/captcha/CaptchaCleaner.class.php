<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class will handle the clean up of the database
 * as defined by the user settings.
 */
class CaptchaCleaner extends BaseModul {
	/**
	 * @var CaptchaPool|null Captcha pool instance for pre-generation.
	 */
	private ?CaptchaPool $pool = null;

	/**
	 * @param CF7Captcha $Controller
	 */
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);

		add_action('dailyCaptchaClear', [$this, 'clean']);
		add_action('dailyCaptchaClear', [$this, 'maybe_fill_captcha_pool']);

		// Also add a more frequent hook for pool filling (every 15 minutes)
		add_action('f12_captcha_pool_fill', [$this, 'maybe_fill_captcha_pool']);

		// Schedule the pool fill hook if not already scheduled
		if (!wp_next_scheduled('f12_captcha_pool_fill')) {
			wp_schedule_event(time(), 'f12_fifteen_minutes', 'f12_captcha_pool_fill');
		}

		// Register custom cron interval
		add_filter('cron_schedules', [$this, 'add_cron_intervals']);

		$this->get_logger()->info(
			"__construct(): Cron jobs registered (dailyCaptchaClear, f12_captcha_pool_fill)",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);
	}

	/**
	 * Adds custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 *
	 * @return array Modified schedules.
	 */
	public function add_cron_intervals(array $schedules): array
	{
		$schedules['f12_fifteen_minutes'] = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __('Every 15 Minutes', 'captcha-for-contact-form-7'),
		];

		return $schedules;
	}

	/**
	 * Gets the CaptchaPool instance (lazy initialization).
	 *
	 * @return CaptchaPool
	 */
	private function get_pool(): CaptchaPool
	{
		if ($this->pool === null) {
			$this->pool = new CaptchaPool($this->Controller);
		}

		return $this->pool;
	}

	/**
	 * Fills the captcha pool if it needs refilling.
	 *
	 * Called by cron to maintain a pool of pre-generated captchas.
	 *
	 * @return int Number of captchas generated.
	 */
	public function maybe_fill_captcha_pool(): int
	{
		// Only fill pool if image captcha is enabled
		$captcha_method = $this->Controller->get_settings('protection_captcha_method', 'global');

		if ($captcha_method !== 'image') {
			$this->get_logger()->debug(
				"maybe_fill_captcha_pool(): Skipping - image captcha not enabled",
				[
					'plugin' => 'f12-cf7-captcha',
					'method' => $captcha_method
				]
			);
			return 0;
		}

		$pool = $this->get_pool();

		if (!$pool->needs_refill()) {
			$this->get_logger()->debug(
				"maybe_fill_captcha_pool(): Pool does not need refill",
				[
					'plugin' => 'f12-cf7-captcha',
					'size'   => $pool->get_pool_size()
				]
			);
			return 0;
		}

		$this->get_logger()->info(
			"maybe_fill_captcha_pool(): Filling captcha pool",
			['plugin' => 'f12-cf7-captcha']
		);

		return $pool->fill_pool();
	}

	/**
	 * Clean all expired Captchas
	 *
	 * This method deletes all Captchas that are older than 1 day.
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean(): int
	{
		$date_time = new \DateTime("-1 Day");

		$cutoff = $date_time->format('Y-m-d H:i:s');

		$this->get_logger()->debug(
			"clean(): Starting cleanup of old captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'cutoff' => $cutoff
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_older_than($cutoff);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean(): Old captchas deleted",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted,
					'cutoff'  => $cutoff
				]
			);
		} else {
			$this->get_logger()->warning(
				"clean(): No old captchas found",
				[
					'plugin' => 'f12-cf7-captcha',
					'cutoff' => $cutoff
				]
			);
		}

		return $deleted;
	}


	public function reset_table(): int
	{
		$this->get_logger()->warning(
			"reset_table(): Starting captcha table reset",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->get_logger(), '');
		$deleted = (int) $Captcha->reset_table();

		if ($deleted > 0) {
			$this->get_logger()->info(
				"reset_table(): Table emptied",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"reset_table(): No entries found in the table",
				[
					'plugin' => 'f12-cf7-captcha'
				]
			);
		}

		return $deleted;
	}

	/**
	 * Clean validated Captchas
	 *
	 * @return int The number of deleted Captchas
	 */
	public function clean_validated(): int
	{
		$this->get_logger()->debug(
			"clean_validated(): Starting cleanup of validated captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_by_validate_status(1);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean_validated(): Validated captchas deleted",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"clean_validated(): No validated captchas found to delete",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $deleted;
	}


	/**
	 * Cleans all non-validated captchas.
	 *
	 * @return int The number of captchas deleted.
	 */
	public function clean_non_validated(): int
	{
		$this->get_logger()->debug(
			"clean_non_validated(): Starting cleanup of non-validated captchas",
			[
				'plugin' => 'f12-cf7-captcha',
				'class'  => __CLASS__
			]
		);

		$Captcha = new Captcha($this->Controller->get_logger(), '');
		$deleted = (int) $Captcha->delete_by_validate_status(0);

		if ($deleted > 0) {
			$this->get_logger()->info(
				"clean_non_validated(): Non-validated captchas deleted",
				[
					'plugin'  => 'f12-cf7-captcha',
					'deleted' => $deleted
				]
			);
		} else {
			$this->get_logger()->debug(
				"clean_non_validated(): No non-validated captchas found to delete",
				['plugin' => 'f12-cf7-captcha']
			);
		}

		return $deleted;
	}
}