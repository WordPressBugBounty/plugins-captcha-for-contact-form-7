/**
 * Handle Reloads of Captcha and Timers
 * for Fluent Forms
 */
window.f12cf7captcha_fluent = {
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
     * Show error messages returned by Captcha backend
     */
    showErrors: function(response, $form) {
        var $root = ($form && $form.jquery) ? $form : jQuery($form);

        if (response && response.errors) {
            // Alte Fehlermeldungen entfernen
            $root.find('.f12-captcha-error').remove();

            jQuery.each(response.errors, function(fieldName, message) {
                var $field = $root.find('[name="'+fieldName+'"]');
                if ($field.length) {
                    $field.after('<span class="f12-captcha-error ff-el-is-error">'+message+'</span>');
                    $field.addClass('ff-el-is-error');
                } else {
                    $root.prepend('<div class="f12-captcha-error ff-el-is-error">'+message+'</div>');
                }
            });
        }
    },

    init: function(){
        // bei Erfolg → Timer neu laden & alte Fehlermeldungen entfernen
        jQuery(document).on('fluentform_submission_success', function(e, form, response){
            var $form = jQuery(form); // sicherstellen dass es jQuery ist
            $form.find('.f12-captcha-error').remove();
            $form.find('.ff-el-is-error').removeClass('ff-el-is-error');

            window.f12cf7captcha_fluent.reloadTimer($form);
            if(window.f12cf7captcha_cf7){
                window.f12cf7captcha_cf7.reloadAllCaptchas();
            }
        });

        // bei Fehler → Fehlermeldung anzeigen & Timer neu laden
        jQuery(document).on('fluentform_submission_failed', function(e, form, response){
            var $form = jQuery(form); // sicherstellen dass es jQuery ist

            window.f12cf7captcha_fluent.showErrors(response, $form);
            window.f12cf7captcha_fluent.reloadTimer($form);
            if(window.f12cf7captcha_cf7){
                window.f12cf7captcha_cf7.reloadAllCaptchas();
            }
        });
    }
};

window.f12cf7captcha_fluent.init();
