/**
 * Handle Reloads of Captcha and Timers
 * for Gravity Forms
 */
window.f12cf7captcha_gravity = {
    /**
     * Reload the Timer fields.
     */
    reloadTimer: function ($form) {
        var $root = ($form && $form.jquery) ? $form : jQuery($form || document);

        $root.find('.f12t').each(function(){
            var fieldname = 'f12_timer';
            var field = jQuery(this).find('.' + fieldname);

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha_avada.ajaxurl,
                data: {
                    action: 'f12_cf7_captcha_timer_reload'
                },
                success: function(data){
                    try {
                        data = JSON.parse(data);
                        field.val(data.hash);
                    } catch(e) {
                        console.error('Timer reload parse error', e, data);
                    }
                },
                error:function (xhr, textstatus, errorThrown){
                    console.log('Timer reload error', errorThrown);
                }
            });
        });
    },

    /**
     * Cleanup & reload after GF replaces HTML
     */
    handleReload: function(formId) {
        var $form = jQuery('#gform_' + formId);

        // Alte Fehler entfernen
        $form.find('.f12-captcha-error').remove();
        $form.find('.gfield_error').removeClass('gfield_error');

        // Timer & Captcha neu laden
        window.f12cf7captcha_gravity.reloadTimer($form);
        if (window.f12cf7captcha_cf7) {
            window.f12cf7captcha_cf7.reloadAllCaptchas();
        }
    },

    init: function(){
        // Wird nach jedem Ajax-Reload eines GF-Formulars ausgelöst
        jQuery(document).on('gform_post_render', function(event, formId){
            window.f12cf7captcha_gravity.handleReload(formId);
        });

        // Bei Success zusätzlich Confirmation-Event
        jQuery(document).on('gform_confirmation_loaded', function(event, formId){
            window.f12cf7captcha_gravity.handleReload(formId);
        });

        // Fallback: Seite initial geladen
        jQuery(document).ready(function(){
            jQuery('.gform_wrapper form').each(function(){
                var formId = jQuery(this).attr('id').replace('gform_', '');
                window.f12cf7captcha_gravity.handleReload(formId);
            });
        });
    }
};

window.f12cf7captcha_gravity.init();
