<?php

namespace f12_cf7_captcha\compatibility\cf7;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\protection\captcha\Captcha_Validator;
use f12_cf7_captcha\core\protection\Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Backend
 * Responsible to handle the admin settings for the captcha
 *
 * @deprecated  This calls will be removed in the future.
 * @package     forge12\contactform7\CF7Captcha
 */
class Backend {
    /**
     * Admin constructor.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'addHooks' ) );
        add_action( 'wpcf7_init', array( $this, 'addFormTag' ), 10, 0 );
        add_filter( 'wpcf7_validate_f12_captcha', array( $this, 'validateCaptcha' ), 10, 2 );
    }

    /**
     * Validate the captcha for a given form tag.
     *
     * This method checks if the captcha value entered by the user is valid for the provided form tag.
     *
     * @param WPCF7_ValidationResult $result The validation result object.
     * @param WPCF7_FormTag          $tag    The form tag object.
     *
     * @return WPCF7_ValidationResult The updated validation result object.
     */
    public function validateCaptcha( $result, $tag ) {
        /**
         * Skip Validation
         *
         * This hook can be used from developers to skip the validation for specific forms.
         *
         * @param bool $skip Skip validation, default: false
         *
         * @since 1.12.2
         */
        if ( apply_filters( 'f12-cf7-captcha-skip-validation', false ) ) {
            return $result;
        }

        if ( empty( $tag->name ) ) {
            $result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
        } else {
            $value = sanitize_text_field( $_POST[ $tag->name ] );
            $hash  = '';

            $captchamethod = $tag->get_option( 'captcha', '', true );

            if ( $captchamethod !== 'honey' ) {
                $hash = sanitize_text_field( $_POST[ $tag->name . '_hash' ] );
            }

            /**
             * @var Protection $Protection
             */
            $Protection = CF7Captcha::get_instance()->get_modul( 'protection' );
            /**
             * @var Captcha_Validator $Captcha_Validator
             */
            $Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

            $Generator = $Captcha_Validator->get_generator( $captchamethod );

            if ( ! $Generator->is_valid( $value, $hash ) ) {
                $result->invalidate( $tag, __( 'Captcha not valid', 'captcha-for-contact-form-7' ) );
            }
        }

        return $result;
    }

    /**
     * Add Captcha Tag
     */
    public function addFormTag() {
        $manager = \WPCF7_FormTagsManager::get_instance();
        $manager->add( 'f12_captcha', array( $this, 'addFormTagHandler' ), array( 'name-attr' => true ) );
    }

    /**
     * Add the captcha field to the form
     *
     * @param $tag
     *
     * @return string
     */
    public function addFormTagHandler( $tag ) {
        if ( empty( $tag->name ) ) {
            return '';
        }

		if ( ! class_exists( \WPCF7_ContactForm::class ) ) {
            return '';
        }

        if ( function_exists( 'wpcf7_get_validation_error' ) ) {
            $validation_error = wpcf7_get_validation_error( $tag->name );
        } else {
            return '';
        }

        if ( function_exists( 'wpcf7_form_controls_class' ) ) {
            $class = wpcf7_form_controls_class( $tag->type );
        } else {
            $class = '';
        }

        $class .= ' wpcf7-validates-as-captcha';

        if ( $validation_error ) {
            $class .= ' wpcf7-not-valid';
        }

        $atts = array();

        $atts['captchamethod'] = $tag->get_option( 'captcha', '', true );
        $atts['class']         = $tag->get_class_option( $class );
		$atts['classes']         = ' '.$tag->get_class_option( $class );
        //$atts['id']            = $tag->name;
		$atts['tabindex'] = $tag->get_option( 'tabindex', 'signed_int', true );
		$atts['step']     = $tag->get_option( 'step', 'int', true );

        $atts['aria-required'] = 'true';
		$atts['attributes']['aria-required'] = 'true';

        if ( $validation_error ) {
            $atts['aria-invalid']     = 'true';
            $atts['aria-describedby'] = wpcf7_get_validation_error_reference(
                $tag->name
            );
        } else {
            $atts['aria-invalid'] = 'false';
        }

        $value = (string) reset( $tag->values );

        if ( $tag->has_option( 'placeholder' )
            or $tag->has_option( 'watermark' ) ) {
            $atts['placeholder'] = $value;
            $value               = '';
        } else {
            $atts['placeholder'] = __( 'Captcha', 'captcha-for-contact-form-7' );
        }

        $value = $tag->get_default_option( $value );

        $atts['value'] = $value;

        $atts['type'] = 'text';

        $atts['name'] = $tag->name;

        /**
         * @var Protection $Protection
         */
        $Protection = CF7Captcha::get_instance()->get_modul( 'protection' );
        /**
         * @var Captcha_Validator $Captcha_Validator
         */
        $Captcha_Validator = $Protection->get_modul( 'captcha-validator' );

        $Generator = $Captcha_Validator->get_generator( esc_attr( $atts['captchamethod'] ) );

		$field = $Generator->get_field( esc_attr( $tag->name ), $atts );

		return $field;
    }

    /**
     * Add the hooks responsible to handle wordpress functions
     */
    public function addHooks() {
        $this->addCaptchaToCF7();
    }

    /**
     *
     */
    public function captchaCallback( $contact_form, $args = '' ) {
        $args = wp_parse_args( $args, array() );
        $type = 'f12_captcha';

        $description = __( "Generate a captcha to stop spam.", 'captcha-for-contact-form-7' );

        ?>
        <div class="control-box">
            <fieldset>
                <legend><?php echo esc_html( $description ); ?></legend>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'captcha-for-contact-form-7' ) ); ?></label>
                        </th>
                        <td><input type="text" name="name" class="tg-name oneline"
                                   id="<?php echo esc_attr( $args['content'] . '-name' ); ?>"/></td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e( 'Captcha Method', 'captcha-for-contact-form-7' ); ?>
                        </th>
                        <td>
                            <label><input type="radio" name="captcha" value="image"
                                          class="option"/> <?php echo esc_html( __( 'Image Captcha', 'captcha-for-contact-form-7' ) ); ?>
                            </label>
                            <label><input type="radio" name="captcha" value="math"
                                          class="option"/> <?php echo esc_html( __( 'Arithmetical Captcha.', 'captcha-for-contact-form-7' ) ); ?>
                            </label>
                            <label><input type="radio" name="captcha" value="honey"
                                          class="option"/> <?php echo esc_html( __( 'Honeypot Captcha.', 'captcha-for-contact-form-7' ) ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'Id attribute', 'captcha-for-contact-form-7' ) ); ?></label>
                        </th>
                        <td><input type="text" name="id" class="idvalue oneline option"
                                   id="<?php echo esc_attr( $args['content'] . '-id' ); ?>"/></td>
                    </tr>

                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class attribute', 'captcha-for-contact-form-7' ) ); ?></label>
                        </th>
                        <td><input type="text" name="class" class="classvalue oneline option"
                                   id="<?php echo esc_attr( $args['content'] . '-class' ); ?>"/></td>
                    </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>

        <div class="insert-box">
            <input type="text" name="<?php echo esc_attr( $type ); ?>" class="tag code" readonly="readonly"
                   onfocus="this.select()"/>

            <div class="submitbox">
                <input type="button" class="button button-primary insert-tag"
                       value="<?php echo esc_attr( __( 'Insert Tag', 'captcha-for-contact-form-7' ) ); ?>"/>
            </div>

            <br class="clear"/>
        </div>
        <?php
    }

    /**
     * Add the captcha button to the contact form 7 generator
     */
    private function addCaptchaToCF7() {
        if ( class_exists( '\WPCF7_TagGenerator' ) ) {
            $tag_generator = \WPCF7_TagGenerator::get_instance();
            $tag_generator->add( 'f12_captcha', __( 'Captcha', 'captcha-for-contact-form-7' ),
                array( $this, 'captchaCallback' ) );
        }
    }
}