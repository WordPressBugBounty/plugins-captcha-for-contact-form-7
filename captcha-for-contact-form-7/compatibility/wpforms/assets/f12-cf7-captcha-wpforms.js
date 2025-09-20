/**
 * Handle Reloads of Captcha and Timers
 * for WPForms
 */
window.f12cf7captcha_wpforms = {
    reloadTimer: function ($form) {
        var $root = ($form && $form.jquery) ? $form : jQuery($form || document);

        $root.find('.f12t').each(function(){
            var field = jQuery(this).find('.f12_timer');

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha_avada.ajaxurl,
                data: { action: 'f12_cf7_captcha_timer_reload' },
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

    showErrors: function(response, $form) {
        var $root = ($form && $form.jquery) ? $form : jQuery($form);

        // Alte Captcha-Fehler entfernen
        $root.find('.f12-captcha-error').remove();

        if (response && response.errors) {
            jQuery.each(response.errors, function(fieldName, message) {
                // WPForms kümmert sich um "general" selbst → überspringen
                if (fieldName === 'general') {
                    return;
                }

                var $field = $root.find('[name="'+fieldName+'"]');
                if ($field.length) {
                    $field.after('<span class="f12-captcha-error wpforms-error">'+message+'</span>');
                    $field.addClass('wpforms-error');
                } else {
                    $root.prepend('<div class="f12-captcha-error wpforms-error">'+message+'</div>');
                }
            });
        }
    },

    init: function(){
        // globaler Ajax-Hook
        jQuery(document).ajaxComplete(function(event, xhr, settings){
            try {
                var response = JSON.parse(xhr.responseText);

                // nur WPForms-Responses beachten
                if (!response || (typeof response.success === 'undefined')) return;

                // Formular finden (WPForms hängt ID im Payload an)
                var $form = jQuery(settings.context || 'form.wpforms-form');

                if (response.success) {
                    // Erfolg → Fehler zurücksetzen
                    $form.find('.f12-captcha-error').remove();
                    $form.find('.wpforms-error').removeClass('wpforms-error');
                } else {
                    // Fehler → anzeigen
                    if (response.data && response.data.errors) {
                        window.f12cf7captcha_wpforms.showErrors(response.data.errors, $form);
                    }
                }

                // IMMER Captcha + Timer reloaden
                window.f12cf7captcha_wpforms.reloadTimer($form);
                if (window.f12cf7captcha_cf7) {
                    window.f12cf7captcha_cf7.reloadAllCaptchas();
                }

            } catch(e) {
                // keine JSON-Response → ignorieren
            }
        });
    }
};

window.f12cf7captcha_wpforms.init();
