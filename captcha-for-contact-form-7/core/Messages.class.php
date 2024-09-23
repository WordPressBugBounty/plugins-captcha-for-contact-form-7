<?php

namespace f12_cf7_captcha\core;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Messages
 */
class Messages
{
    /**
     * @var Messages|null
     */
    private static $instance;

    /**
     * @var array
     */
    private $messages = [];

    /**
     * @return Messages
     */
    public static function getInstance(): Messages
    {
        if (null === self::$instance) {
            self::$instance = new Messages();
        }

        return self::$instance;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    private function __construct()
    {
    }

    /**
     * Adds a message to the list of messages.
     *
     * @param string $message The message to be added.
     * @param string $type    The type of message. Accepts 'error', 'success', 'info', 'warning', 'offer', 'critical'.
     *                        If the given type is not found in the list, it will be used as is.
     *
     * @return void
     */
    public function add($message, $type)
    {
        $types = [
            'error' => 'alert-danger',
            'success' => 'alert-success',
            'info' => 'alert-info',
            'warning' => 'alert-warning',
            'offer' => 'alert-offer',
            'critical' => 'alert-critical'
        ];

        $type = $types[$type] ?? $type;

        $this->messages[] = sprintf('<div class="box %s" role="alert"><div class="section">%s</div></div>', esc_attr($type), esc_html($message));
    }

    /**
     * Returns a string representation of all the messages stored in the class.
     *
     * @return string A string representation of all the messages.
     */
    public function get_all(): string
    {
        return implode("\n", $this->messages);
    }

    /**
     * @return string
     * @deprecated
     */
    public function getAll(): string
    {
        return $this->get_all();
    }
}