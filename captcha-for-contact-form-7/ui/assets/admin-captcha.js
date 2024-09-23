(function ($) {
    $(document).ready(function () {
        /**
         * Disable the WordPress Login & Registration
         * @type {*|jQuery|HTMLElement}
         */
        var $protectionWordPress = $('#protection_wordpress_enable')
        var $protectionWordPressLoginEnable = $('#protection_wordpress_login_enable');
        var $protectionWordPressRegisterEnable = $('#protection_wordpress_registration_enable');

        // Disable the local Settings
        $protectionWordPress.on('change', function () {
            if ($protectionWordPressLoginEnable.is(':checked') && $protectionWordPress.is(':checked')) {
                $protectionWordPressLoginEnable.click();
            }

            if ($protectionWordPressRegisterEnable.is(':checked') && $protectionWordPress.is(':checked')) {
                $protectionWordPressRegisterEnable.click();
            }
        });

        // Disable the Global Settings
        $protectionWordPressLoginEnable.on('change', function(){
            if($protectionWordPress.is(':checked') && $protectionWordPressLoginEnable.is(':checked')){
                $protectionWordPress.click();
            }
        });

        // Disable the Global Settings
        $protectionWordPressRegisterEnable.on('change', function(){
            if($protectionWordPress.is(':checked') && $protectionWordPressRegisterEnable.is(':checked')){
                $protectionWordPress.click();
            }
        });

        /**
         * Disable the WooCommerce Login & Registration
         * @type {*|jQuery|HTMLElement}
         */
        var $protectionWoocommerce = $('#protection_woocommerce_enable')
        var $protectionWoocommerceLogin = $('#protection_woocommerce_login_enable');
        var $protectionWoocommerceRegister = $('#protection_woocommerce_registration_enable');

        // Disable the local Settings
        $protectionWoocommerce.on('change', function () {
            if ($protectionWoocommerceLogin.is(':checked') && $protectionWoocommerce.is(':checked')) {
                $protectionWoocommerceLogin.click();
            }

            if ($protectionWoocommerceRegister.is(':checked') && $protectionWoocommerce.is(':checked')) {
                $protectionWoocommerceRegister.click();
            }
        });

        // Disable the Global Settings
        $protectionWoocommerceLogin.on('change', function(){
            if($protectionWoocommerce.is(':checked') && $protectionWoocommerceLogin.is(':checked')){
                $protectionWoocommerce.click();
            }
        });

        // Disable the Global Settings
        $protectionWoocommerceRegister.on('change', function(){
            if($protectionWoocommerce.is(':checked') && $protectionWoocommerceRegister.is(':checked')){
                $protectionWoocommerce.click();
            }
        });
    });
})(jQuery);