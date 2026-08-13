<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerComments
 */
class ControllerComments extends BaseController
{
    protected string $id = 'wordpress_comments';
    protected string $settings_key = 'protection_wordpress_comments_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'comment_form_after_fields', 'method' => 'wp_add_spam_protection'],
        ['type' => 'filter', 'hook' => 'preprocess_comment', 'method' => 'wp_is_spam', 'priority' => 1],
    ];

    public function get_name(): string
    {
        return __( 'WordPress Comments', 'captcha-for-contact-form-7' );
    }

    public function is_installed(): bool
    {
        return true;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_is_spam(...$args)
    {
        $commentdata = $args[0];

        // `preprocess_comment` fires for EVERY comment WordPress creates, not
        // just the ones a visitor typed into the comment form — an importer, a
        // REST client, WP-CLI, a cron job or another plugin's AJAX handler all
        // land here too. Those carry none of our fields, so the protection
        // chain would judge them spam and kill the request with a 403, taking
        // an unrelated feature down with it.
        if (!$this->is_comment_form_submission()) {
            return $commentdata;
        }

        $spam_message = $this->check_spam();

        if ($spam_message !== null) {
            wp_die(
                '<p>' . $this->format_spam_message($spam_message) . '</p>',
                esc_html__('Comment Submission Failed', 'captcha-for-contact-form-7'),
                ['response' => 403, 'back_link' => true]
            );
        }

        return $commentdata;
    }

    /**
     * Whether the comment being processed was actually submitted through a
     * comment form, and may therefore be held to the captcha.
     *
     * The signature we look for is the comment form's own fields. Every real
     * submission carries them — `wp-comments-post.php` rejects the comment
     * outright without `comment_post_ID`, and an AJAX comment form posts the
     * same field names — so requiring them costs no protection: a spammer
     * cannot drop them and still have a comment created. Programmatic
     * insertion, which is what we want to leave alone, never has them.
     *
     * Contexts that are never a visitor submitting a form are ruled out up
     * front, so the field check is not the only thing standing between an
     * import and a fatal 403.
     */
    protected function is_comment_form_submission(): bool
    {
        if (wp_doing_cron()
            || (defined('WP_CLI') && WP_CLI)
            || (defined('REST_REQUEST') && REST_REQUEST)
        ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only; the value is never read
        return isset($_POST['comment_post_ID'], $_POST['comment']);
    }
}
