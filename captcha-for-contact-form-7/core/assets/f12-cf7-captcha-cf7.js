/**
 * Reload all Captchas
 * This will regenerate the Hash and the Captcha Value
 */
window.f12cf7captcha_cf7 = {
    storedOnclicks: {}, // To store onclick handlers for submit buttons
    allowNextClick: false,

    // Logger aktivieren mit ?f12-debug=true
    logger: (function () {
        const enabled = (new URLSearchParams(window.location.search)).get("f12-debug") === "true";
        function formatArgs(args) {
            return ["[f12cf7captcha]"].concat(Array.from(args));
        }
        return {
            log: function () { if (enabled) console.log.apply(console, formatArgs(arguments)); },
            warn: function () { if (enabled) console.warn.apply(console, formatArgs(arguments)); },
            error: function () { if (enabled) console.error.apply(console, formatArgs(arguments)); }
        };
    })(),

    /**
     * Save and remove onclick handlers for all forms containing js_end_time
     */
    processFormOnclicks: function () {
        var self = this;
        jQuery('form').each(function () {
            var $form = jQuery(this);
            if ($form.find('.js_end_time').length > 0) {
                $form.find('button[type=submit], input[type=submit]').each(function () {
                    var $button = jQuery(this);
                    var buttonId = $button.attr('id') || (Math.random().toString(36).substr(2, 9));

                    var inlineOnclick = $button.attr('onclick');
                    if (inlineOnclick) {
                        self.storedOnclicks[buttonId] = inlineOnclick;
                        $button.removeAttr('onclick');
                        $button.attr('data-f12-stored-id', buttonId);
                        self.logger.log("Onclick gespeichert & entfernt", {buttonId, form: $form});
                    }
                });
            }
        });
    },

    /**
     * Handle the click event for submit buttons and execute stored onclick logic
     */
    handleFormSubmission: function () {
        var self = this;
        jQuery(document).on('click', 'button[type=submit], input[type=submit]', function (event) {
            var $button = jQuery(this);
            var $form = $button.closest('form');

            if (self.allowNextClick) {
                self.allowNextClick = false;
                self.logger.log("allowNextClick → Klick durchgelassen", $button);
                return;
            }

            if ($form.find('.js_end_time').length > 0) {
                event.preventDefault();

                var timestamp = Date.now();
                var js_microtime = timestamp / 1000;
                $form.find('.js_end_time').val(js_microtime);

                self.logger.log("js_end_time gesetzt", {form: $form, value: js_microtime});

                self.finalizeSubmit($form, $button);
            }
        });
    },

    finalizeSubmit: function ($form, $button) {
        var buttonId = $button.attr('data-f12-stored-id');
        if (buttonId && this.storedOnclicks[buttonId]) {
            try {
                eval(this.storedOnclicks[buttonId]);
                this.logger.log("Inline-Onclick ausgeführt", buttonId);
            } catch (e) {
                this.logger.error("Fehler im Inline-Onclick", e);
            }
        }

        // Wichtig: zuerst die Click-Handler feuern, aber Rekursion vermeiden
        if (!this.allowNextClick) {
            this.allowNextClick = true;
            $button[0].dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            this.logger.log("Nativer Click-Event dispatched", $button);
            this.allowNextClick = false;
        }

        // Dann Formular absenden (triggert Submit-Handler)
        if ($form[0].requestSubmit) {
            $form[0].requestSubmit($button[0]);
            this.logger.log("Formular mit requestSubmit() abgesendet", $form);
        } else {
            $form.trigger('submit'); // jQuery-Events feuern
            $form[0].submit();       // nativer Fallback
            this.logger.log("Formular mit trigger('submit') + submit() abgesendet", $form);
        }
    },

    showOverlay: function($container) {
        if ($container.css('position') === 'static') {
            $container.css('position', 'relative');
        }
        if ($container.find('.f12-captcha-overlay').length === 0) {
            $container.append('<div class="f12-captcha-overlay"></div>');
            this.logger.log("Overlay hinzugefügt", $container);
        }
    },

    hideOverlay: function($container) {
        $container.find('.f12-captcha-overlay').remove();
        this.logger.log("Overlay entfernt", $container);
    },

    reloadAllCaptchas: function () {
        var self = this;
        jQuery(document).find('.f12c').each(function () {
            self.logger.log("Reload Captcha gestartet", this);
            self.reloadCaptcha(jQuery(this));
        });
    },

    reloadCaptcha: function (e) {
        var self = this;
        var $container = e.closest('.f12-captcha');
        this.showOverlay($container);

        $container.find('.f12c').each(function () {
            var input_id = jQuery(this).attr('id');
            var hash_id = 'hash_' + input_id;

            var hash = jQuery('#' + hash_id);
            var label = $container.find('.c-data');
            var method = jQuery(this).attr('data-method');

            self.logger.log("Captcha Reload AJAX", {method, input_id});

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha.ajaxurl,
                data: {
                    action: 'f12_cf7_captcha_reload',
                    captchamethod: method
                },
                success: function (data) {
                    data = JSON.parse(data);

                    if (method == 'image') {
                        label.find('.captcha-image').html(data.label);
                    }
                    if (method == 'math') {
                        label.find('.captcha-calculation').html(data.label);
                    }
                    hash.val(data.hash);
                    self.logger.log("Captcha neu gesetzt", {method, hash: data.hash});
                },
                complete: function () {
                    self.hideOverlay($container);
                },
                error: function (xhr, textstatus, errorThrown) {
                    self.logger.error("Captcha reload Fehler", errorThrown);
                }
            });
        });
    },

    reloadTimer: function () {
        var self = this;
        jQuery(document).find('.f12t').each(function () {
            var fieldname = 'f12_timer';
            var field = jQuery(this).find('.' + fieldname);

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha.ajaxurl,
                data: { action: 'f12_cf7_captcha_timer_reload' },
                success: function (data) {
                    data = JSON.parse(data);
                    field.val(data.hash);
                    self.logger.log("Timer neu gesetzt", data.hash);
                },
                error: function (xhr, textstatus, errorThrown) {
                    self.logger.error("Timer reload Fehler", errorThrown);
                }
            });
        });
    },

    init: function () {
        this.logger.log("Init gestartet");

        this.processFormOnclicks();
        this.handleFormSubmission();

        jQuery(document).on('click', '.cf7.captcha-reload', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.f12cf7captcha_cf7.reloadCaptcha(jQuery(this));
        });

        jQuery(document).ready(function () {
            var timestamp = Date.now();
            var js_microtime = (timestamp / 1000);
            jQuery(document).find('form').each(function () {
                jQuery(this).find('.js_start_time').val(js_microtime);
            });
            window.f12cf7captcha_cf7.logger.log("js_start_time gesetzt", js_microtime);
        });

        var wpcf7Elm = document.querySelector('.wpcf7');
        if (!wpcf7Elm) return;

        wpcf7Elm.addEventListener('wpcf7mailsent', function () {
            window.f12cf7captcha_cf7.reloadAllCaptchas();
            window.f12cf7captcha_cf7.reloadTimer();
            window.f12cf7captcha_cf7.logger.log("wpcf7mailsent → Captchas neu geladen");
        }, false);

        wpcf7Elm.addEventListener('wpcf7submit', function () {
            window.f12cf7captcha_cf7.reloadAllCaptchas();
            window.f12cf7captcha_cf7.reloadTimer();
            window.f12cf7captcha_cf7.logger.log("wpcf7submit → Captchas neu geladen");
        }, false);

        wpcf7Elm.addEventListener('wpcf7spam', function (event) {
            var id = event.detail.apiResponse.into;
            if (!id) return;
            jQuery(id).find('.f12c').addClass('wpcf7-not-valid not-valid');
            window.f12cf7captcha_cf7.logger.warn("wpcf7spam → Captcha als not-valid markiert", id);
        }, false);
    }
};

window.f12cf7captcha_cf7.init();