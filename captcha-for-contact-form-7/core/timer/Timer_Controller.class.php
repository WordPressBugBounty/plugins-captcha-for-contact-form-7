<?php

namespace f12_cf7_captcha\core\timer;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

require_once('CaptchaTimer.class.php');
require_once('CaptchaTimerCleaner.class.php');

/**
 * Class Timer_Controller
 * Enables the validation of forms / comments by submit time
 */
class Timer_Controller extends BaseModul
{
    private string $createtime = '';

    private ?CaptchaTimer $Latest_Timer = null;

    private ?CaptchaTimerCleaner $Captcha_Timer_Cleaner = null;

    /**
     * Constructor
     */
    public function __construct(CF7Captcha $Controller)
    {
        parent::__construct($Controller);

        $this->Captcha_Timer_Cleaner = new CaptchaTimerCleaner($Controller);


        add_action('init', array($this, '_init'));
    }

    /**
     * Retrieves the CaptchaTimerCleaner instance associated with this system.
     *
     * This method returns the instance of the CaptchaTimerCleaner class that is responsible for managing
     * and cleaning up the timers in the system.
     *
     * @return CaptchaTimerCleaner The CaptchaTimerCleaner instance associated with this system.
     */
    public function get_timer_cleaner(): CaptchaTimerCleaner
    {
        return $this->Captcha_Timer_Cleaner;
    }


    /**
     * Create and get a CaptchaTimer object.
     *
     * @return CaptchaTimer The newly created CaptchaTimer object.
     * @throws \Exception
     */
    public function factory(): CaptchaTimer
    {

        return new CaptchaTimer();
    }


    /**
     * Checks if the protection time feature is enabled.
     *
     * This method retrieves the value of the 'protection_time_enable' setting from the global settings
     * using the Controller object. The method compares the retrieved value with 1 and returns true if they are equal,
     * indicating that the protection time feature is enabled. Otherwise, it returns false.
     *
     * @return bool True if the protection time feature is enabled, false otherwise.
     */
    protected function is_enabled(): bool
    {
        return $this->Controller->get_settings('protection_time_enable', 'global') === 1;
    }

    /**
     * Retrieves the latest timer.
     *
     * This method returns the latest instance of CaptchaTimer class that was set using the set_latest_timer() method.
     * If no timer is set, it returns null.
     *
     * @return CaptchaTimer|null The latest timer object if it is set, or null if no timer is set.
     */
    public function get_latest_timer(): ?CaptchaTimer
    {
        return $this->Latest_Timer;
    }

    /**
     * @private WordPress Hook
     */
    public function _init()
    {
        do_action('f12_cf7_captcha_timer_validator_init');
    }


    /**
     * Get the create time of the object
     *
     * @return string The create time in the format 'Y-m-d H:i:s'
     */
    private function get_create_time(): string
    {
        if (empty($this->createtime)) {
            $dt = new \DateTime();
            $this->createtime = $dt->format('Y-m-d H:i:s');
        }

        return $this->createtime;

    }

    /**
     * Generate a hash for the given user's IP address
     *
     * @param string $user_ip_address The user's IP address
     *
     * @return string The generated hash
     */
    private function generate_hash(string $user_ip_address)
    {
        return \password_hash(time() . $user_ip_address, PASSWORD_DEFAULT);
    }

    /**
     * Get the current time in milliseconds.
     *
     * @return float The current time in milliseconds.
     */
    private function get_time_in_ms(): float
    {
        return round(microtime(true) * 1000);
    }

    /**
     * Adds a timer to the system.
     *
     * This method creates a new instance of CaptchaTimer class and saves it in the system.
     * The timer is associated with the user's IP address, and it includes a unique hash,
     * value (time in milliseconds), and creation time.
     *
     * @return string|null The hash of the timer if it is successfully saved, or null if the saving fails.
     * @throws \Exception
     */
    public function add_timer(): ?string
    {
        $User_Data = $this->Controller->get_modul('user-data');
        $user_ip_address = $User_Data->get_ip_address();

        $hash = $this->generate_hash($user_ip_address);

        $CaptchaTimer = new CaptchaTimer(
            [
                'hash' => $hash,
                'value' => $this->get_time_in_ms(),
                'createtime' => $this->get_create_time()
            ]
        );

        if ($CaptchaTimer->save()) {
            $this->Latest_Timer = $CaptchaTimer;

            return $hash;
        }

        return null;
    }

    /**
     * Retrieves a timer by its hash.
     *
     * @param string $hash The hash of the timer to retrieve.
     *
     * @return CaptchaTimer|null The CaptchaTimer object if found, or null if not found.
     *
     * @throws RuntimeException When WPDB is not defined.
     */
    public function get_timer(string $hash): ?CaptchaTimer
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined');
        }

        return (new CaptchaTimer())->get_by_hash($hash);
    }

    /**
     * Removes a timer with the given hash.
     *
     * @param string $hash The hash of the timer to be removed.
     *
     * @return void
     * @throws RuntimeException if the global $wpdb variable is not defined.
     *
     */
    public function remove_timer(string $hash): void
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined');
        }

        (new CaptchaTimer())->delete_by_hash($hash);
    }
}