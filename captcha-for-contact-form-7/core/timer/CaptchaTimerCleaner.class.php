<?php

namespace f12_cf7_captcha\core\timer;
use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CaptchaTimerCleaner
 *
 * This class is responsible for cleaning captchas older than 1 day and resetting the table of captchas.
 * It extends the BaseModul class.
 */
class CaptchaTimerCleaner extends BaseModul
{
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);
		$this->get_logger()->info('Constructor started.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		add_action('dailyCaptchaTimerClear', array($this, 'clean'));
		$this->get_logger()->debug('Hook "dailyCaptchaTimerClear" added for the "clean" method.');

		$this->get_logger()->info('Constructor completed.');
	}
    /**
     * Clean all captchas older than 1 day
     *
     * @return int The number of captchas deleted
     */
	public function clean(): int
	{
		$this->get_logger()->info('Starting the daily cleanup job for expired timer entries.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		try {
			// Create a DateTime object that is 24 hours in the past
			$date_time = new \DateTime('-1 Day');
			$date_time_formatted = $date_time->format('Y-m-d H:i:s');
			$this->get_logger()->debug('Timestamp for cleanup: ' . $date_time_formatted);
		} catch (\Exception $e) {
			$this->get_logger()->error('Error creating the DateTime object. Cleanup aborted.', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}

		// Instantiate a new CaptchaTimer object and call the delete function
		$timer_handler = new CaptchaTimer($this->get_logger());
		$rows_deleted = $timer_handler->delete_older_than($date_time_formatted);

		$this->get_logger()->info('Cleanup completed.', [
			'rows_deleted' => $rows_deleted,
		]);

		return $rows_deleted;
	}

    /**
     * Reset the table of Captchas
     *
     * @return int The number of rows affected by the reset operation
     */
	public function reset_table(): int
	{
		$this->get_logger()->info('Starting the process to reset the entire table via the main controller.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Instantiate a new CaptchaTimer object to access the reset_table() method
		$timer_handler = new CaptchaTimer($this->get_logger());

		// Call the method and store the result
		$rows_deleted = $timer_handler->reset_table();

		$this->get_logger()->info('Table reset completed.', [
			'rows_deleted' => $rows_deleted,
		]);

		return $rows_deleted;
	}
}