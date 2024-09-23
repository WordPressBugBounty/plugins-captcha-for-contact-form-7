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
    }
}

window.f12cf7captcha_avada.init();
