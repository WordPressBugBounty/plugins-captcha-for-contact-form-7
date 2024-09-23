<?php

namespace f12_cf7_captcha\core\protection\rules;
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
abstract class Rule
{
    /**
     * @var string
     */
    protected $error_message = '';

    /**
     * @var array<string>
     */
    private $messages = [];

    /**
     * @param $value
     *
     * @return bool
     */
    public abstract function is_spam($value);


    /**
     * @param string $message
     *
     * @return void
     * @deprecated
     */
    public function addMessage($message)
    {
        $this->add_message($message);
    }

    /**
     * Adds a message to the list of messages.
     *
     * @param string $message The message to be added.
     *
     * @return void
     */
    public function add_message(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return string
     * @deprecated
     */
    public function getMessages()
    {
        return $this->get_messages();
    }

    /**
     * Retrieves all the messages as a string.
     *
     * @return string The messages joined by "<br/>".
     */
    public function get_messages(): string
    {
        return implode("<br/>", $this->messages);
    }

    /**
     * @return string
     * @deprecated
     */
    public function getErrorMessage()
    {
        return $this->get_error_message();
    }

    /**
     * Retrieves the error message.
     *
     * This method returns the error message as a string.
     *
     * @return string The error message.
     */
    public function get_error_message(): string
    {
        return $this->error_message;
    }
}