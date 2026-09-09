<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ultimate Member's login and registration forms.
 *
 * One controller, two forms — and they are told apart, which every other integration gets for
 * free by having a form id. Both used to resolve their context as `ultimatemember` with no
 * form id, so credential stuffing against the login and fake accounts through the registration
 * arrived at SilentShield under one form key, and whoever read the number could not say which
 * of the two they were looking at. The hooks already know: there is one per form on each side.
 *
 * Both sides move together, and that is the point. set_context() decides which per-form
 * settings apply, so a renderer resolving `ultimatemember` while its validator resolves
 * `ultimatemember:login` renders one set of fields and demands another — the shape of both
 * 2.15.4 bugs, described at length in BaseController::get_captcha_html().
 *
 * Nothing changes for existing installations: Ultimate Member is registered in Form_Discovery
 * with has_forms => false, so the admin UI never offered a per-form override for it and none
 * can exist to be orphaned by the new ids. What the ids do is name the form in a block report.
 */
class ControllerUltimateMember extends BaseController
{
    protected string $id = 'ultimatemember';
    protected string $settings_key = 'protection_ultimatemember_enable';

    /**
     * The two forms, as they appear in a form key — `ultimatemember:login` and
     * `ultimatemember:register`.
     */
    private const FORM_LOGIN    = 'login';
    private const FORM_REGISTER = 'register';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'um_after_login_fields', 'method' => 'wp_add_spam_protection_login'],
        ['type' => 'action', 'hook' => 'um_after_register_fields', 'method' => 'wp_add_spam_protection_register'],
        ['type' => 'action', 'hook' => 'um_submit_form_errors_hook_login', 'method' => 'wp_is_spam_login', 'priority' => 5],
        ['type' => 'action', 'hook' => 'um_submit_form_errors_hook__registration', 'method' => 'wp_is_spam_register', 'priority' => 5],
    ];

    public function get_name(): string
    {
        return __( 'Ultimate Member', 'captcha-for-contact-form-7' );
    }

    public function is_installed(): bool
    {
        $is_installed = class_exists('UM_Functions');
        $this->get_logger()->debug('Ultimate Member installed: ' . ($is_installed ? 'Yes' : 'No'));
        return $is_installed;
    }

    /**
     * Render into the login form.
     *
     * @param mixed ...$args Unused. Part of the Ultimate Member hook signature.
     * @return void
     */
    public function wp_add_spam_protection_login(...$args)
    {
        $this->echo_captcha( $this->get_captcha_html( self::FORM_LOGIN ) );
    }

    /**
     * Render into the registration form.
     *
     * @param mixed ...$args Unused. Part of the Ultimate Member hook signature.
     * @return void
     */
    public function wp_add_spam_protection_register(...$args)
    {
        $this->echo_captcha( $this->get_captcha_html( self::FORM_REGISTER ) );
    }

    /**
     * Ship the rendered captcha, followed by the error of a submission that was just refused.
     *
     * Ultimate Member re-renders the form it rejected, so the message from the validation run
     * is still on Protection when the renderer comes back around.
     *
     * @param string $captcha Whatever Protection rendered for this form.
     * @return void
     */
    private function echo_captcha( string $captcha )
    {
        $this->get_logger()->info('Starting captcha code output for Ultimate Member forms.');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
        echo $captcha;

        $Protection = $this->Controller->get_module('protection');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Ultimate Member
        if (!empty($Protection->get_message()) && !empty($_POST)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
            echo '<div class="um-field-error">' . sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $Protection->get_message()) . '</div>';
        }
    }

    /**
     * Judge a login attempt.
     *
     * @param mixed ...$args Unused. Part of the Ultimate Member hook signature.
     * @return bool
     */
    public function wp_is_spam_login(...$args)
    {
        return $this->is_spam_submission( self::FORM_LOGIN );
    }

    /**
     * Judge a registration.
     *
     * @param mixed ...$args Unused. Part of the Ultimate Member hook signature.
     * @return bool
     */
    public function wp_is_spam_register(...$args)
    {
        return $this->is_spam_submission( self::FORM_REGISTER );
    }

    /**
     * @param string $form_id Which of the two forms was submitted.
     * @return bool
     */
    private function is_spam_submission( string $form_id ): bool
    {
        $this->get_logger()->info('Starting spam check for Ultimate Member.');

        $message = $this->check_spam( null, $form_id );

        // This submission has now been judged, and saying so has to happen whichever way it
        // went. Ultimate Member validates a login by running the credentials through
        // wp_authenticate() itself — twice, from inside the very hook this method is on — and
        // each of those fires `wp_authenticate_user`, where ControllerWordpressLogin is
        // waiting. Left unset on the blocking path, as it was, a single refused Ultimate
        // Member login was judged three times and reported to SilentShield three times: once
        // as `ultimatemember:login` and twice more as `wordpress_login`. That number is what
        // the customer is shown and billed on. ControllerWoocommerceLogin has always set the
        // flag unconditionally; this is the same rule, applied to the same situation.
        //
        // Nothing goes unprotected by it: the flag only ever says "this request has already
        // been through the captcha", and a submission this method refuses stays refused.
        add_filter('f12_cf7_captcha_wc_login_validated', '__return_true');
        add_filter('f12_cf7_captcha_wc_registration_validated', '__return_true');

        if ($message !== null) {
            $this->get_logger()->warning('Spam detected!');

            if (function_exists('UM')) {
                UM()->form()->add_error('f12_captcha', sprintf(__('Captcha not valid: %s', 'captcha-for-contact-form-7'), $message));
            }

            return true;
        }

        return false;
    }
}
