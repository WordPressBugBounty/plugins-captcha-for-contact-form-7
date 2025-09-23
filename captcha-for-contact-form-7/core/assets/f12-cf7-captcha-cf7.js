/**
 * Reload all Captchas
 * This will regenerate the Hash and the Captcha Value
 */
window.f12cf7captcha_cf7 = {
    storedOnclicks: {}, // To store onclick handlers for submit buttons

    /**
     * Save and remove onclick handlers for all forms containing js_end_time
     */
    processFormOnclicks: function () {
        jQuery('form').each(function () {
            var $form = jQuery(this);
            // Check if the form has the input with class 'js_end_time'
            if ($form.find('.js_end_time').length > 0) {
                // Find all submit buttons in the form
                $form.find('button[type=submit], input[type=submit]').each(function () {
                    var $button = jQuery(this);
                    var buttonId = $button.attr('id') || (Math.random().toString(36).substr(2, 9)); // Unique ID for the button

                    var inlineOnclick = $button.attr('onclick');
                    if (inlineOnclick) {
                        // Store the inline onclick handler and remove it temporarily
                        window.f12cf7captcha_cf7.storedOnclicks[buttonId] = inlineOnclick;
                        $button.removeAttr('onclick'); // Disable onclick temporarily
                        $button.attr('data-f12-stored-id', buttonId); // Mark the button with a unique identifier
                    }
                });
            }
        });
    },

    /**
     * Handle the click event for submit buttons and execute stored onclick logic
     */
    handleFormSubmission: function () {
        jQuery(document).on('click', 'button[type=submit], input[type=submit]', function (event) {
            var $button = jQuery(this);
            var $form = $button.closest('form');

            // Check if the form contains 'js_end_time'
            if ($form.find('.js_end_time').length > 0) {
                event.preventDefault(); // Stop default submission for now

                // Execute custom logic for setting js_end_time
                var timestamp = Date.now();
                var js_microtime = timestamp / 1000; // Convert milliseconds to seconds
                $form.find('.js_end_time').val(js_microtime);

                // Execute dynamically bound click handlers (if any)
                var events = jQuery._data(this, 'events');
                if (events && events.click) {
                    events.click.forEach(function (handler) {
                        handler.handler.call(this);
                    }.bind(this));
                }

                // Fetch and execute stored onclick handler if it exists
                var buttonId = $button.attr('data-f12-stored-id');
                if (buttonId && window.f12cf7captcha_cf7.storedOnclicks[buttonId]) {
                    var storedOnclick = window.f12cf7captcha_cf7.storedOnclicks[buttonId];
                    eval(storedOnclick); // Execute the stored onclick logic
                }

                // Finally, trigger the form to submit
                $form.submit(); // Submit the form programmatically
            }
        });
    },

    showOverlay: function($container) {
        if ($container.css('position') === 'static') {
            $container.css('position', 'relative');
        }
        if ($container.find('.f12-captcha-overlay').length === 0) {
            $container.append('<div class="f12-captcha-overlay"></div>');
        }
    },

    hideOverlay: function($container) {
        $container.find('.f12-captcha-overlay').remove();
    },


    /**
     * Reload Captchas
     */
    reloadAllCaptchas: function () {
       jQuery(document).find('.f12c').each(function () {
           window.f12cf7captcha_cf7.reloadCaptcha(jQuery(this));
        });
    },

    /**
     * Reload Captchas
     */
    reloadCaptcha: function (e) {
        var $container = e.closest('.f12-captcha');
        this.showOverlay($container);

        $container.find('.f12c').each(function () {
            var input_id = jQuery(this).attr('id');
            var hash_id = 'hash_' + input_id;

            var hash = jQuery('#' + hash_id);
            var label = $container.find('.c-data');
            var method = jQuery(this).attr('data-method');

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
                },
                complete: function () {
                    window.f12cf7captcha_cf7.hideOverlay($container);
                },
                error: function (xhr, textstatus, errorThrown) {
                    console.log(errorThrown);
                }
            });
        });
    },


    /**
     * Reload Timer
     */
    reloadTimer: function () {
        jQuery(document).find('.f12t').each(function () {
            var fieldname = 'f12_timer';
            var field = jQuery(this).find('.' + fieldname);

            jQuery.ajax({
                type: 'POST',
                url: f12_cf7_captcha.ajaxurl,
                data: {
                    action: 'f12_cf7_captcha_timer_reload'
                },
                success: function (data, textStatus, XMLHttpRequest) {
                    data = JSON.parse(data);
                    field.val(data.hash);
                },
                error: function (XMLHttpRequest, textstatus, errorThrown) {
                    console.log(errorThrown);
                }
            });
        });
    },
    /**
     * Init
     */
    init: function () {
        // Identify and process onclicks in all relevant forms
        this.processFormOnclicks();

        // Handle form submission and reintegrate onclicks
        this.handleFormSubmission();

        /**
         * Reload the Captcha by User
         * @param document
         */
        jQuery(document).on('click', '.cf7.captcha-reload', function (e) {
            e.preventDefault();
            e.stopPropagation();

            window.f12cf7captcha_cf7.reloadCaptcha(jQuery(this));
            //window.f12cf7captcha_cf7.reloadTimer();
        });

        /**
         * Add timer information when the form has been loaded
         */
        jQuery(document).ready(function () {
            // Get the current timestamp in milliseconds using Date.now()
            var timestamp = Date.now();

            // Combine the timestamp and date milliseconds to create a JavaScript microtime value
            var js_microtime = (timestamp / 1000);

            jQuery(document).find('form').each(function () {
                jQuery(this).find('.js_start_time').val(js_microtime);
            });
        });

        /**
         * Add Event Listener from Contact Form 7
         */
        var wpcf7Elm = document.querySelector('.wpcf7');

        if (typeof (wpcf7Elm) === 'undefined' || wpcf7Elm === null) {
            return;
        }

        wpcf7Elm.addEventListener('wpcf7mailsent', function (event) {
            window.f12cf7captcha_cf7.reloadAllCaptchas();
            window.f12cf7captcha_cf7.reloadTimer();
        }, false);

        wpcf7Elm.addEventListener('wpcf7submit', function (event) {
            window.f12cf7captcha_cf7.reloadAllCaptchas();
            window.f12cf7captcha_cf7.reloadTimer();
        }, false);

        wpcf7Elm.addEventListener('wpcf7spam', function (event) {
            var id = event.detail.apiResponse.into;

            if (typeof (id) === 'undefined') {
                return;
            }

            jQuery(id).find('.f12c').addClass('wpcf7-not-valid not-valid');
        }, false);
    }
}

window.f12cf7captcha_cf7.init();