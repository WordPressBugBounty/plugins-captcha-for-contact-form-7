/**
 * Handle Reloads of Captcha and Timers
 * using the Avada Event: fusion-form-ajax-submitted called in fusion-form.js
 */
window.f12cf7captcha_avada = {
    /**
     * Reload the Timer fields.
     */
    reloadTimer: function () {
        jQuery(document).find('.f12t').each(function(){
            var fieldname = 'f12_timer';
            var field = jQuery(this).find('.'+fieldname);

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha_avada.ajaxurl,
                data: {
                    action: 'f12_cf7_captcha_timer_reload'
                },
                success: function(data, textStatus, XMLHttpRequest){
                    data = JSON.parse(data);
                    field.val(data.hash);
                },
                error:function (XMLHttpRequest, textstatus, errorThrown){
                    console.log(errorThrown);
                }
            });
        });
    },

    init: function(){
        jQuery(window).on('fusion-form-ajax-submitted', function () {
            window.f12cf7captcha_avada.reloadTimer();
        });

        // Ajax-Response global abfangen (auch wenn Avada kein Error-Event feuert)
        jQuery(document).ajaxComplete(function(event, xhr, settings) {
            try {
                var response = JSON.parse(xhr.responseText);

                if (!response || !response.status) return;

                var $form = jQuery(settings.context || 'form.fusion-form');

                if (response.status === 'error' && response.errors) {
                    //console.log('Captcha-Handler: Fehler erkannt via ajaxComplete', response);
                    window.f12cf7captcha_avada.showErrors(response, $form);
                    window.f12cf7captcha_avada.reloadTimer();
                    window.f12cf7captcha_cf7.reloadAllCaptchas();
                }

                if (response.status === 'success') {
                    //console.log('Captcha-Handler: Erfolg erkannt – Fehler ausblenden');
                    $form.find('.f12-captcha-error').remove();
                    $form.find('.fusion-form-error').removeClass('fusion-form-error');
                }
            } catch(e) {
                // keine JSON-Response -> ignorieren
            }
        });
    },

    /**
     * Show error messages returned by Captcha backend
     */
    showErrors: function(response, $form) {
        //console.log('Captcha-Handler: showErrors ausgelöst', response, $form);

        if (response && response.errors) {
            // Alte Fehlermeldungen entfernen
            $form.find('.f12-captcha-error').remove();

            jQuery.each(response.errors, function(fieldName, message) {
                //console.log('Captcha-Handler: Fehler an Feld', fieldName, message);
                var $field = $form.find('[name="'+fieldName+'"]');
                if ($field.length) {
                    $field.after('<span class="f12-captcha-error fusion-form-error-message">'+message+'</span>');
                    $field.addClass('fusion-form-error');
                } else {
                    $form.prepend('<div class="f12-captcha-error fusion-form-error-message">'+message+'</div>');
                }
            });
        }
    }

}

window.f12cf7captcha_avada.init();
