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
<div class="f12-captcha template-2">
    <div class="c-header">
        <!-- Label korrekt mit `for` verknüpfen -->
        <div class="c-label">
            <label for="<?php echo esc_attr( $captcha_id ); ?>">
				<?php esc_html_e( $label ); ?>
            </label>
        </div>

        <!-- CAPTCHA-Daten mit Aria LIVE markieren -->
        <div class="c-data" aria-live="polite" aria-atomic="true" aria-describedby="captcha-instructions">
			<?php echo $captcha_data; ?>
        </div>

        <!-- CAPTCHA Reload mit Tastenzugänglichkeit -->
        <div class="c-reload" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Refresh CAPTCHA', 'captcha-for-contact-form-7' ); ?>">
			<?php echo $captcha_reload; ?>
        </div>
    </div>

    <!-- Versteckte Eingabewerte -->
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>"
           name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>

    <!-- Eingabefeld mit Barrierefreiheitsattributen -->
    <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo $wrapper_attributes; ?>>
        <input class="f12c <?php echo esc_attr( $classes ); ?>"
               data-method="<?php echo esc_attr( $method ); ?>" <?php echo esc_attr( $attributes ); ?>
               type="text"
               id="<?php echo esc_attr( $captcha_id ); ?>"
               name="<?php echo esc_attr( $field_name ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               value=""
               aria-required="true"
               aria-describedby="captcha-instructions"/>
    </div>

    <!-- Screenreader-Hinweise -->
    <p id="captcha-instructions" class="screen-reader-text">
		<?php esc_html_e( 'Please enter the characters shown in the CAPTCHA to verify that you are human.', 'captcha-for-contact-form-7' ); ?>
    </p>
</div>