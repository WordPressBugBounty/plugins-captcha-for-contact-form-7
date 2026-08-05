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
    protected string $name = 'Elementor';
    protected string $id = 'elementor';
    protected string $settings_key = 'protection_elementor_enable';

    protected array $hooks = [
        ['type' => 'action', 'hook' => 'elementor_pro/forms/validation', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2],
        ['type' => 'filter', 'hook' => 'elementor_pro/forms/render/item', 'method' => 'wp_add_spam_protection', 'priority' => 10, 'args' => 3],
        ['type' => 'filter', 'hook' => 'elementor/element/is_dynamic_content', 'method' => 'wp_mark_form_as_dynamic', 'priority' => 10, 'args' => 3],
    ];

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

        if ('form' === ($raw_data['widgetType'] ?? '')) {
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
