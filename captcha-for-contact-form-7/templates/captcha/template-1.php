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
    <label for="<?php esc_attr_e( $captcha_id ); ?>">
		<?php if ( $method != 'image' ): ?>
			<div class="c-label"><?php esc_attr_e( $label ); ?></div>
		<?php endif; ?>

        <div class="c-header">
            <div class="c-input">
                <div class="c-data"><?php echo $captcha_data; ?></div>

                <div class="<?php esc_attr_e( $wrapper_classes ); ?>" <?php esc_attr_e( $wrapper_attributes ); ?>>
					<?php if ( $method === 'image' ): ?>
                        <div class="c-hint">
							<?php _e( 'Type the characters:', 'captcha-for-contact-form-7' ); ?>
                        </div>
					<?php endif; ?>
                    <input class="f12c<?php esc_attr_e( $classes ); ?>"
                           data-method="<?php esc_attr_e( $method ); ?>" <?php esc_attr_e( $attributes ); ?>
                           type="text" id="<?php esc_attr_e( $captcha_id ); ?>"
                           name="<?php esc_attr_e( $field_name ); ?>"
                           placeholder="?" value=""/>
                </div>
            </div>

            <div class="c-reload"><?php echo $captcha_reload; ?></div>
        </div>

        <input type="hidden" id="<?php esc_attr_e( $hash_id ); ?>" name="<?php esc_attr_e( $hash_field_name ); ?>"
               value="<?php esc_attr_e( $hash_value ); ?>"/>
    </label>
</div>
