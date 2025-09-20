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
<div class="f12-captcha template-0">
    <div class="c-header">
        <!-- Label korrekt mit `for` verknüpfen -->
        <label for="<?php echo esc_attr( $captcha_id ); ?>" class="c-label"><?php echo esc_html( $label ); ?></label>

        <!-- CAPTCHA-Daten mit Aria-Attributen für Screenreader kennzeichnen -->
        <div class="c-data" aria-live="polite" aria-describedby="captcha-instructions">
			<?php echo $captcha_data; ?>
        </div>
        <div class="c-reload" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Reload CAPTCHA' ); ?>">
			<?php echo $captcha_reload; ?>
        </div>
        <p id="captcha-instructions" class="screen-reader-text">
			<?php esc_html_e( 'Please enter the characters shown in the CAPTCHA to ensure that you are human.' ); ?>
        </p>
    </div>

    <!-- Versteckte Werte -->
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>" name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>

    <!-- Textfeld mit Aria-Hinweisen -->
    <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo $wrapper_attributes; ?>>
        <label for="<?php echo esc_attr( $captcha_id ); ?>"><?php echo esc_html( $label ); ?></label>
        <input class="f12c <?php echo esc_attr( $classes ); ?>"
               data-method="<?php echo esc_attr( $method ); ?>"
			<?php echo $attributes; ?>
               type="text"
               id="<?php echo esc_attr( $captcha_id ); ?>"
               name="<?php echo esc_attr( $field_name ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               value=""
               aria-required="true"
               aria-labelledby="captcha-instructions"/>
    </div>
</div>