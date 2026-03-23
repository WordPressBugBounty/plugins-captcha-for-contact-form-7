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
 * @var bool   $captcha_audio_enabled
 * @var string $audio_btn_styles
 * @var int    $icon_size
 */

$allowed_captcha_html = [
	'span' => [ 'class' => true, 'role' => true ],
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
	'a'      => [ 'href' => true, 'class' => true, 'title' => true, 'style' => true ],
	'button' => [ 'type' => true, 'class' => true, 'style' => true, 'aria-label' => true ],
	'svg'    => [ 'aria-hidden' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true ],
	'path'   => [ 'd' => true ],
	'div'    => [ 'class' => true ],
];
?>
<div class="f12-captcha template-6">
	<?php if ( $method !== 'image' ): ?>
        <!-- Label correctly linked with `for` -->
        <label for="<?php echo esc_attr( $captcha_id ); ?>" class="c-label">
			<?php esc_html_e( $label ); ?>
        </label>
	<?php endif; ?>

    <div class="c-header">
        <div class="c-input">
            <!-- Dynamische CAPTCHA-Daten mit Aria LIVE aktualisieren -->
            <div class="c-data" aria-live="polite" aria-atomic="true" aria-describedby="captcha-instructions">
				<?php echo wp_kses( $captcha_data, $allowed_captcha_html ); ?>
            </div>

            <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped at construction. ?>>
				<?php if ( $method === 'image' ): ?>
                    <!-- CAPTCHA description for screen readers -->
                    <div class="c-hint" id="captcha-image-hint">
						<?php esc_html_e( 'Enter the characters shown in the image:', 'captcha-for-contact-form-7' ); ?>
                    </div>
				<?php endif; ?>

                <!-- Text field for user input -->
                <input class="f12c <?php echo esc_attr( $classes ); ?>"
                       data-method="<?php echo esc_attr( $method ); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped at construction. ?>
                       type="text" id="<?php echo esc_attr( $captcha_id ); ?>"
                       name="<?php echo esc_attr( $field_name ); ?>"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>"
                       value="" aria-required="true"
                       aria-labelledby="<?php echo $method === 'image' ? 'captcha-image-hint' : ''; ?>"
                       aria-describedby="captcha-instructions"/>
            </div>
        </div>

        <!-- Button for CAPTCHA reload -->
        <div class="c-reload" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Reload CAPTCHA', 'captcha-for-contact-form-7' ); ?>">
			<?php echo $captcha_reload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated internally by get_reload_button(), all values escaped at construction. ?>
        </div>
		<?php if ( ! empty( $captcha_audio_enabled ) ): ?>
            <div class="c-audio">
                <button type="button" class="captcha-audio-btn"
                        style="<?php echo esc_attr( $audio_btn_styles ); ?>"
                        aria-label="<?php esc_attr_e( 'Listen to CAPTCHA', 'captcha-for-contact-form-7' ); ?>">
                    <svg aria-hidden="true" width="<?php echo esc_attr( $icon_size ); ?>" height="<?php echo esc_attr( $icon_size ); ?>" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                    </svg>
                </button>
                <span class="captcha-audio-tooltip" role="tooltip">
                    <?php esc_html_e( 'Click to have the CAPTCHA read aloud', 'captcha-for-contact-form-7' ); ?>
                </span>
            </div>
		<?php endif; ?>
    </div>

    <!-- Hidden input field for HASH values -->
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>"
           name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>

    <!-- Screenreader-Beschreibung -->
    <p id="captcha-instructions" class="screen-reader-text">
		<?php esc_html_e( 'This CAPTCHA helps ensure that you are human. Please enter the requested characters.', 'captcha-for-contact-form-7' ); ?>
    </p>
</div>