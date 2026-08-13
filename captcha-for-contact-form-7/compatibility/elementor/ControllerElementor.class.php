<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ControllerElementor
 */
class ControllerElementor extends BaseController
{
    protected string $id = 'elementor';
    protected string $settings_key = 'protection_elementor_enable';

    /**
     * Element type of the Elementor v4 atomic form, as registered by Elementor Pro's
     * AtomicForm module (`Module::FORM_ELEMENT_TYPE`).
     */
    private const ATOMIC_FORM_TYPE = 'e-form';

    protected array $hooks = [
        // Classic form widget (Elementor Pro <= 3.x and the legacy widget in 4.x).
        ['type' => 'action', 'hook' => 'elementor_pro/forms/validation', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
        ['type' => 'filter', 'hook' => 'elementor_pro/forms/render/item', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 3],
        ['type' => 'filter', 'hook' => 'elementor/element/is_dynamic_content', 'method' => 'wp_mark_form_as_dynamic', 'priority' => 10, 'args' => 3],

        // Atomic form (Elementor v4 editor). A separate widget with its own render pipeline
        // and its own AJAX endpoint, so none of the three hooks above ever fire for it.
        ['type' => 'action', 'hook' => 'elementor/frontend/' . self::ATOMIC_FORM_TYPE . '/before_render', 'method' => 'wp_atomic_capture_start', 'priority' => 10, 'args' => 1],
        ['type' => 'action', 'hook' => 'elementor/frontend/' . self::ATOMIC_FORM_TYPE . '/after_render', 'method' => 'wp_atomic_capture_end', 'priority' => 10, 'args' => 1],
        ['type' => 'filter', 'hook' => 'elementor_pro/atomic_forms/spam_check', 'method' => 'wp_is_atomic_spam', 'priority' => 10, 'args' => 4],
    ];

    /**
     * Nesting depth of the output buffers this class opened, so an atomic form inside an
     * atomic form cannot leave one dangling.
     */
    private int $atomic_buffer_depth = 0;

    public function get_name(): string
    {
        return __( 'Elementor', 'captcha-for-contact-form-7' );
    }

    public function is_installed(): bool
    {
        $is_installed = defined('ELEMENTOR_VERSION');
        $this->get_logger()->debug('Elementor installed: ' . ($is_installed ? 'Yes' : 'No'));
        return $is_installed;
    }

    /**
     * Keep the captcha out of Elementor's page cache.
     *
     * Elementor stores a page's rendered markup in the `_elementor_element_cache` post meta
     * for 24 hours. Everything we inject into a form was being frozen into it on first
     * render: the same signed token, the same arithmetic question and the same captcha
     * session hash served to every visitor from then on. Worse, whatever the settings
     * happened to be at that moment was baked in too — a site that switched JavaScript
     * protection on afterwards kept serving markup without the timing fields, so the server
     * rejected every real visitor as a bot. That is how this was found.
     *
     * An element Elementor considers dynamic is written into the cache as an
     * `[elementor-element]` placeholder instead, and the cached content is run through
     * do_shortcode() on every request — so the widget, and our captcha with it, is rebuilt
     * each time. This filter is the documented way to declare that.
     *
     * Only the form widget is marked. Everything else on the page keeps its caching, which
     * is the point of the feature.
     *
     * Note the matching master switch, `elementor/element/should_render_shortcode`, is
     * Elementor's own: it turns it on only while building the cache and off again straight
     * after. Setting it ourselves emits the placeholder on paths where nothing expands it,
     * and the form disappears from the page entirely.
     *
     * @param bool                 $is_dynamic Decision so far.
     * @param array<string, mixed> $raw_data   The element's raw data.
     * @param mixed                $element    The element instance.
     *
     * @return bool
     */
    public function wp_mark_form_as_dynamic($is_dynamic, $raw_data = [], $element = null): bool
    {
        if ($is_dynamic) {
            return true;
        }

        if (!$this->is_enabled()) {
            return false;
        }

        // The classic widget is a widget (`widgetType`), the atomic form is an element of its
        // own type (`elType`) — both have to be caught or the cache freezes one of them.
        if ('form' === ($raw_data['widgetType'] ?? '') || self::ATOMIC_FORM_TYPE === ($raw_data['elType'] ?? '')) {
            $this->get_logger()->debug('Elementor form marked as dynamic so its captcha is not served from the page cache.');
            return true;
        }

        return false;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function wp_add_spam_protection(...$args)
    {
        $this->get_logger()->info('Starting captcha code insertion for Elementor forms.');

        $item = $args[0];
        $item_index = $args[1];

        /** @var \ElementorPro\Modules\Forms\Widgets\Form $form */
        $form = $args[2];

        $settings = $form->get_settings();
        $number_of_fields = count($settings['form_fields']) - 1;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Elementor
        if ($item_index !== $number_of_fields || !empty($_POST)) {
            return $item;
        }

        $captcha = $this->Controller->get_module('protection')->get_captcha();

        if (!empty($captcha)) {
            $wrapped_captcha = sprintf('<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-text elementor-col-100 elementor-field-required">%s</div>', $captcha);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally
            echo $wrapped_captcha;
        }

        return $item;
    }

    /**
     * Start buffering an atomic form so its markup can be reopened and the captcha added.
     *
     * Elementor v4 renders the atomic form from a Twig template that ends in a fixed
     * `<!-- elementor-children-placeholder -->` substitution, and offers no filter on the
     * result — `elementor_pro/forms/render/item`, the seam the classic widget gives us, does
     * not exist here. What it does offer is the element render actions around
     * `Element_Base::print_element()`, and those bracket the complete element including its
     * children. Opening a buffer on the first and closing it on the second therefore hands us
     * the finished `<form>…</form>` to edit.
     *
     * The pairing is safe: both actions fire unconditionally for every element that renders,
     * and `print_element()` returns early — before either — only when the element is emitted
     * as an `[elementor-element]` cache placeholder instead, in which case neither runs.
     *
     * @param mixed $element The element being rendered.
     *
     * @return void
     */
    public function wp_atomic_capture_start($element = null): void
    {
        if (!$this->is_atomic_render_context()) {
            return;
        }

        ob_start();
        $this->atomic_buffer_depth++;
    }

    /**
     * Close the buffer opened above and inject the captcha into the form.
     *
     * @param mixed $element The element that was rendered.
     *
     * @return void
     */
    public function wp_atomic_capture_end($element = null): void
    {
        if ($this->atomic_buffer_depth < 1) {
            return;
        }

        $this->atomic_buffer_depth--;
        $html = ob_get_clean();

        if (!is_string($html) || '' === $html) {
            return;
        }

        $captcha = $this->Controller->get_module('protection')->get_captcha();

        if (empty($captcha)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own markup, unchanged.
            echo $html;
            return;
        }

        $this->get_logger()->info('Inserting captcha into Elementor atomic form.');

        // `flex: 1 0 100%` because the atomic form is a wrapping flex row: without it the
        // captcha shares a line with whatever field precedes it and is squeezed to nothing.
        $wrapped_captcha = sprintf(
            '<div class="e-con e-atomic-element f12-atomic-form-captcha" style="flex:1 0 100%%;">%s</div>',
            $captcha
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captcha HTML is generated internally.
        echo $this->insert_before_submit($html, $wrapped_captcha);
    }

    /**
     * Whether the current render should get a captcha injected.
     *
     * The editor renders atomic elements in the browser from the same Twig templates, so
     * anything added here would show up as an unremovable element in the panel — and the
     * editor's own preview would submit through a path that never validates it.
     *
     * @return bool
     */
    private function is_atomic_render_context(): bool
    {
        if (!$this->is_enabled()) {
            return false;
        }

        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::$instance;

            if (isset($elementor->editor) && $elementor->editor->is_edit_mode()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Place the captcha directly above the submit button, falling back to the end of the form.
     *
     * Appending at the end would put it below the button and below the success/error message
     * elements, which are the last children an atomic form renders.
     *
     * @param string $html     The rendered form.
     * @param string $captcha  The markup to insert.
     *
     * @return string
     */
    private function insert_before_submit(string $html, string $captcha): string
    {
        if (preg_match('/<button\b[^>]*type="submit"/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];

            return substr($html, 0, $position) . $captcha . substr($html, $position);
        }

        $position = strrpos($html, '</form>');

        if (false !== $position) {
            return substr($html, 0, $position) . $captcha . substr($html, $position);
        }

        return $html . $captcha;
    }

    /**
     * Validate an atomic form submission.
     *
     * Elementor Pro runs every atomic submission through this filter before it executes any
     * action, which is the documented seam for exactly this. Note the message we produce is
     * only logged: the atomic frontend shows the form's own configured error element and
     * ignores the message the endpoint returns.
     *
     * Our fields arrive as ordinary top-level POST keys — Elementor's own collector only ships
     * inputs carrying `data-interaction-id`, so the browser module appends them to the request
     * itself. That keeps them out of `form_fields`, and therefore out of the notification mail
     * and the submissions table.
     *
     * @param bool                 $is_spam         Decision so far.
     * @param array<int, mixed>    $form_fields     The submitted fields, as collected by Elementor.
     * @param array<string, mixed> $widget_settings The form's settings.
     * @param int                  $post_id         The post the form lives on.
     *
     * @return bool
     */
    public function wp_is_atomic_spam($is_spam, $form_fields = [], $widget_settings = [], $post_id = 0): bool
    {
        if ($is_spam) {
            return true;
        }

        $this->get_logger()->info('Starting spam validation for Elementor atomic form.');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Elementor before this filter runs.
        $array_post_data = $_POST;

        $Protection = $this->Controller->get_module('protection');

        $Protection->set_context($this->id, null);
        $is_spam = $Protection->is_spam($array_post_data);

        if ($is_spam) {
            $this->get_logger()->warning('Spam detected! Message: ' . $Protection->get_message());
        }

        $Protection->clear_context();

        return $is_spam;
    }

    /**
     * @param mixed ...$args
     * @return bool|int
     */
    public function wp_is_spam(...$args)
    {
        $this->get_logger()->info('Starting spam validation for Elementor form.');

        $record = $args[0];
        $ajax_handler = $args[1];

        if (null === $record || null === $ajax_handler) {
            return false;
        }

        $fields = $record->get('fields');

        if (null === $fields || !is_array($fields)) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Elementor
        $array_post_data = $_POST;

        $Protection = $this->Controller->get_module('protection');

        $Protection->set_context( $this->id, null );
        $is_spam = $Protection->is_spam($array_post_data);

        if ($is_spam) {
            $message = $Protection->get_message();
            $Protection->clear_context();
            $this->get_logger()->warning('Spam detected! Message: ' . $message);

            $field_name = '';
            foreach ($fields as $key => $data) {
                if (isset($data['type']) && 'hidden' !== $data['type']) {
                    $field_name = $key;
                    break;
                }
            }

            $ajax_handler->add_error($field_name, sprintf(esc_html__('Spam detected: %s', 'captcha-for-contact-form-7'), $message));
            return true;
        }

        $Protection->clear_context();
        return false;
    }
}
