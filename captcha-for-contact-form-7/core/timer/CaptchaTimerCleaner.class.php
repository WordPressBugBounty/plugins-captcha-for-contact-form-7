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
        add_action('dailyCaptchaTimerClear', array($this, 'clean'));
    }

    /**
     * Clean all captchas older than 1 day
     *
     * @return int The number of captchas deleted
     */
    public function clean(): int
    {
        $date_time = new \DateTime('-1 Day');
        $date_time_formatted = $date_time->format('Y-m-d H:i:s');

        return (new CaptchaTimer())->delete_older_than($date_time_formatted);
    }

    /**
     * Reset the table of Captchas
     *
     * @return int The number of rows affected by the reset operation
     */
    public function reset_table(): int
    {
        return (new CaptchaTimer())->reset_table();
    }

    /**
     * Clean all Captchas
     *
     * @return bool|int
     * @deprecated
     */
    public function resetTable()
    {
        return $this->reset_table();
    }

}