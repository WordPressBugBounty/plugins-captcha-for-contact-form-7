<?php

namespace f12_cf7_captcha\core\log;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use f12_cf7_captcha\core\Log_WordPress;

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
        add_action('weeklyIPClear', array($this, 'clean'));
    }


    /**
     * Deletes log entries that are older than 3 weeks.
     *
     * @return int The number of log entries deleted.
     */
    public function clean()
    {
        $date_time = new \DateTime('-3 Weeks');
        return $this->Logger->delete_older_than($date_time->format('Y-m-d H:i:s'));
    }

    /**
     * Resets the table in the WordPress log.
     *
     * @return void
     * @deprecated
     */
    public function resetTable()
    {
        $this->reset_table();
    }

    /**
     * Resets the table in the logger.
     *
     * @return void
     * @deprecated
     */
    public function reset_table(): void
    {
        $this->Logger->reset_table();
    }
}