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
        <div class="c-label"><label for="<?php echo esc_attr( $captcha_id ); ?>"><?php esc_attr_e( $label ); ?></label></div>
        <div class="c-data"><?php echo $captcha_data; ?></div>
        <div class="c-reload"><?php echo $captcha_reload; ?></div>
    </div>
    <input type="hidden" id="<?php echo esc_attr( $hash_id ); ?>" name="<?php echo esc_attr( $hash_field_name ); ?>"
           value="<?php echo esc_attr( $hash_value ); ?>"/>
    <div class="<?php echo esc_attr( $wrapper_classes ); ?>" <?php echo esc_attr( $wrapper_attributes ); ?>>
        <input class="f12c<?php echo esc_attr( $classes ); ?>" data-method="<?php echo esc_attr( $method ); ?>" <?php echo esc_attr( $attributes ); ?>
               type="text" id="<?php echo esc_attr( $captcha_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>" value=""/>
    </div>
</div>
