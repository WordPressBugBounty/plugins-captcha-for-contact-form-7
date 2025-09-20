<?php
/**
 * @var string $hash_id
 * @var string $hash_field_name
 * @var string $hash_value
 * @var string $wrapper_classes
 * @var string $wrapper_attributes
 * @var string $label
 * @var string $classes
 * @var string $attributes
 * @var string $captcha_id
 * @var string $field_name
 * @var string $placeholder
 * @var string $captcha_data
 * @var string $captcha_reload
 * @var string $method
 */
?>
<div class="f12-captcha template-1">
	<?php if ( $method !== 'image' ): ?>
        <!-- Label korrekt mit `for` verknüpfen -->
        <label for="<?php echo esc_attr( $captcha_id ); ?>" class="c-label">
			<?php esc_html_e( $label ); ?>
        </label>
	<?php endif; ?>

    <div class="c-header">
        <div class="c-input">
            <!-- Dynamische CAPTCHA-Daten mit Aria LIVE aktualisieren -->
            <div class="c-data" aria-live="polite" aria-atomic="true" aria-describedby="captcha-instructions">
				<?php echo $captcha_data; ?>
            </div>

            <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo $wrapper_attributes; ?>>
				<?php if ( $method === 'image' ): ?>
                    <!-- CAPTCHA-Beschreibung für Screenreader -->
                    <div class="c-hint" id="captcha-image-hint">
						<?php esc_html_e( 'Enter the characters shown in the image:', 'captcha-for-contact-form-7' ); ?>
                    </div>
				<?php endif; ?>

                <!-- Textfeld für Benutzereingabe -->
                <input class="f12c <?php echo esc_attr( $classes ); ?>"
                       data-method="<?php echo esc_attr( $method ); ?>" <?php echo $attributes; ?>
                       type="text" id="<?php echo esc_attr( $captcha_id ); ?>"
                       name="<?php echo esc_attr( $field_name ); ?>"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>"
                       value="" aria-required="true"
                       aria-labelledby="<?php echo $method === 'image' ? 'captcha-image-hint' : ''; ?>"
                       aria-describedby="captcha-instructions"/>
            </div>
        </div>

        <!-- Schaltfläche für CAPTCHA-Neuladen -->
        <div class="c-reload" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Reload CAPTCHA', 'captcha-for-contact-form-7' ); ?>">
			<?php echo $captcha_reload; ?>
        </div>
    </div>

    <!-- Verstecktes Eingabefeld für HASH-Werte -->
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>"
           name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>

    <!-- Screenreader-Beschreibung -->
    <p id="captcha-instructions" class="screen-reader-text">
		<?php esc_html_e( 'This CAPTCHA helps ensure that you are human. Please enter the requested characters.', 'captcha-for-contact-form-7' ); ?>
    </p>
</div>