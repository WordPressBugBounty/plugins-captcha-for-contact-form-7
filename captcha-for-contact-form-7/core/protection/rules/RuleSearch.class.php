<?php

namespace f12_cf7_captcha\core\protection\rules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Filters that will be used to validate input fields.
 */
class RuleSearch extends Rule
{
    /**
     * @var array
     */
    private $words = [];

    /**
     * Greedy or Non Greedy search
     */
    private $greedy = 1;

    /**
     * Constructor method for the class.
     *
     * @param mixed  $words         The input words to be processed.
     * @param string $error_message (Optional) The error message to be displayed in case of any errors. Default is
     *                              empty string.
     * @param int    $greedy        (Optional) Flag to indicate whether to match all words or only the first one.
     *                              Default 1 (greedy).
     *
     * @return void
     */
    public function __construct(array $words, string $error_message = '', int $greedy = 1)
    {
        $this->error_message = $error_message;
        $this->words = $words;
        $this->greedy = $greedy;
    }

    /**
     * Determines if a given value is considered spam based on a list of words.
     *
     * @param string $value The input value to be checked for spam.
     *
     * @return bool Returns true if the value is considered spam, otherwise false.
     */
    public function is_spam($value): bool
    {
		if(is_array($value)){
			foreach($value as $item){
				if($this->is_spam($item)){
					return true;
				}
			}
			return false;
		}

        $error_message = $this->get_error_message();

        if ($this->greedy == 1) {
            foreach ($this->words as $word) {
                $regex = "!([^a-zA-Z0-9]+|^)" . preg_quote($word) . "([a-zA-Z0-9]+|$)!";
                if (preg_match($regex, $value)) {
                    $this->add_message(sprintf($error_message, $word));
                    return true;
                }
            }
        } else {
            foreach ($this->words as $word) {
                $regex = "!(\ |^)" . preg_quote($word) . "(\ |$)!";
                if (preg_match($regex, $value)) {
                    $this->add_message(sprintf($error_message, $word));
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $value
     *
     * @return bool
     * @deprecated
     */
    public function isSpam($value)
    {
        return $this->is_spam($value);
    }
}