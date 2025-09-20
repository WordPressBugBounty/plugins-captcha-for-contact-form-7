<?php

namespace f12_cf7_captcha\core\log;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Log_WordPress;
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
     * @var Log_WordPress
     */
    private Log_WordPress $Logger;

    /**
     * Constructor for the class.
     *
     * @param Log_WordPress $Logger The WordPress logger instance to be used.
     *
     * @return void
     */
    public function __construct(CF7Captcha $Controller, Log_WordPress $Logger)
    {
        parent::__construct($Controller);

        $this->Logger = $Logger;

	    $this->get_logger()->info("Instanz erstellt", [
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

		    $this->get_logger()->info("Log-Cleaner ausgeführt", [
			    'plugin'    => 'f12-cf7-captcha',
			    'class'     => static::class,
			    'threshold' => $threshold,
			    'deleted'   => $deleted
		    ]);

		    return $deleted;
	    } catch (\Throwable $e) {
		    $this->get_logger()->error("Fehler beim Bereinigen der Logs", [
			    'plugin'    => 'f12-cf7-captcha',
			    'class'     => static::class,
			    'threshold' => $threshold,
			    'error'     => $e->getMessage()
		    ]);
		    throw $e; // Fehler nicht verschlucken
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

		    $this->get_logger()->warning("Tabelle wurde zurückgesetzt", [
			    'plugin' => 'f12-cf7-captcha',
			    'class'  => static::class
		    ]);
	    } catch (\Throwable $e) {
		    $this->get_logger()->error("Fehler beim Zurücksetzen der Tabelle", [
			    'plugin' => 'f12-cf7-captcha',
			    'class'  => static::class,
			    'error'  => $e->getMessage()
		    ]);
		    throw $e; // wichtig: Fehler nicht verschlucken
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

			$this->get_logger()->warning("Logger-Tabelle zurückgesetzt", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => static::class
			]);
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler beim Zurücksetzen der Logger-Tabelle", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => static::class,
				'error'  => $e->getMessage()
			]);
			throw $e; // Fehler nicht verschlucken
		}
	}
}