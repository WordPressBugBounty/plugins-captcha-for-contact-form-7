<?php
/**
 * @var string $hash_id
 * @var string $hash_field_name
 * @var string $hash_value
 * @var string $wrapper_classes
 * @var string $wrapper_attributes  Pre-escaped attribute string (each key/value escaped via esc_attr at construction)
 * @var string $label
 * @var string $classes
 * @var string $attributes          Pre-escaped attribute string (each key/value escaped via esc_attr at construction)
 * @var string $captcha_id
 * @var string $field_name
 * @var string $placeholder
 * @var string $captcha_data
 * @var string $captcha_reload
 * @var string $method
 */

$allowed_captcha_html = [
	'span' => [ 'class' => true ],
	'img'  => [
		'id'             => true,
		'alt'            => true,
		'src'            => true,
		'class'          => true,
		'style'          => true,
		'loading'        => true,
		'decoding'       => true,
		'data-skip-lazy' => true,
		'data-no-lazy'   => true,
	],
	'a'    => [ 'href' => true, 'class' => true, 'title' => true, 'style' => true ],
];
?>
<div class="f12-captcha template-2">
    <div class="c-header">
        <!-- Label correctly linked with `for` -->
        <div class="c-label">
            <label for="<?php echo esc_attr( $captcha_id ); ?>">
				<?php esc_html_e( $label ); ?>
            </label>
        </div>

        <!-- CAPTCHA data with ARIA LIVE region -->
        <div class="c-data" aria-live="polite" aria-atomic="true" aria-describedby="captcha-instructions">
			<?php echo wp_kses( $captcha_data, $allowed_captcha_html ); ?>
        </div>

        <!-- CAPTCHA reload with keyboard accessibility -->
        <div class="c-reload" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Refresh CAPTCHA', 'captcha-for-contact-form-7' ); ?>">
			<?php echo $captcha_reload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated internally by get_reload_button(), all values escaped at construction. ?>
        </div>
    </div>

    <!-- Hidden input values -->
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>"
           name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>

    <!-- Input field with accessibility attributes -->
    <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped at construction. ?>>
        <input class="f12c <?php echo esc_attr( $classes ); ?>"
               data-method="<?php echo esc_attr( $method ); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped at construction. ?>
               type="text"
               id="<?php echo esc_attr( $captcha_id ); ?>"
               name="<?php echo esc_attr( $field_name ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               value=""
               aria-required="true"
               aria-describedby="captcha-instructions"/>
    </div>

    <!-- Screen reader instructions -->
    <p id="captcha-instructions" class="screen-reader-text">
		<?php esc_html_e( 'Please enter the characters shown in the CAPTCHA to verify that you are human.', 'captcha-for-contact-form-7' ); ?>
    </p>
</div>