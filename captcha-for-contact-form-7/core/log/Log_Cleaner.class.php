<?php

namespace f12_cf7_captcha\core\log;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Log_WordPress_Interface;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * This class will handle the clean up of the database
 * as defined by the user settings.
 */
class Log_Cleaner extends BaseModul
{
    /**
     * @var Log_WordPress_Interface
     */
    private Log_WordPress_Interface $Logger;

    /**
     * Constructor for the class.
     *
     * @param Log_WordPress_Interface $Logger The WordPress logger instance to be used.
     *
     * @return void
     */
    public function __construct(CF7Captcha $Controller, Log_WordPress_Interface $Logger)
    {
        parent::__construct($Controller);

        $this->Logger = $Logger;

	    $this->get_logger()->info("Instance created", [
		    'plugin' => 'f12-cf7-captcha',
		    'class'  => static::class
	    ]);

        add_action('weeklyIPClear', array($this, 'clean'));
    }


	/**
	 * Deletes log entries that are older than 3 weeks.
	 *
	 * @return int The number of log entries deleted.
	 * @throws \Throwable
	 */
    public function clean()
    {
	    $date_time = new \DateTime('-3 Weeks');
	    $threshold = $date_time->format('Y-m-d H:i:s');

	    try {
		    $deleted = $this->Logger->delete_older_than($threshold);

		    $this->get_logger()->info("Log cleaner executed", [
			    'plugin'    => 'f12-cf7-captcha',
			    'class'     => static::class,
			    'threshold' => $threshold,
			    'deleted'   => $deleted
		    ]);

		    return $deleted;
	    } catch (\Throwable $e) {
		    $this->get_logger()->error("Error cleaning logs", [
			    'plugin'    => 'f12-cf7-captcha',
			    'class'     => static::class,
			    'threshold' => $threshold,
			    'error'     => $e->getMessage()
		    ]);
		    throw $e; // do not swallow errors
	    }
    }

    /**
     * Resets the table in the WordPress log.
     *
     * @return void
     * @deprecated
     */
    public function resetTable()
    {
	    try {
		    $this->reset_table();

		    $this->get_logger()->warning("Table has been reset", [
			    'plugin' => 'f12-cf7-captcha',
			    'class'  => static::class
		    ]);
	    } catch (\Throwable $e) {
		    $this->get_logger()->error("Error resetting table", [
			    'plugin' => 'f12-cf7-captcha',
			    'class'  => static::class,
			    'error'  => $e->getMessage()
		    ]);
		    throw $e; // important: do not swallow errors
	    }
    }

	/**
	 * Resets the table in the logger.
	 *
	 * @return void
	 * @throws \Throwable
	 * @deprecated
	 */
	public function reset_table(): void
	{
		try {
			$this->Logger->reset_table();

			$this->get_logger()->warning("Logger table reset", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => static::class
			]);
		} catch (\Throwable $e) {
			$this->get_logger()->error("Error resetting logger table", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => static::class,
				'error'  => $e->getMessage()
			]);
			throw $e; // do not swallow errors
		}
	}
}