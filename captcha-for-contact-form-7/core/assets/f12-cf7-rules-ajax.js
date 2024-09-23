function doSyncBlacklist() {
    jQuery.ajax({
        type: 'POST',
        url: f12_cf7_captcha_rules.ajaxurl,
        data: {
            action: 'f12_cf7_blacklist_sync',
        },
        success: function(data, textStatus, XMLHttpRequest){
            data = JSON.parse(data);
            jQuery('#rule_blacklist_value').val(data.value);
        },
        error: function(XMLHttpRequest, textstatus, errorThrown){
            console.log(errorThrown);
        }
    })
}

jQuery(document).on('click', '#syncblacklist', function(){
    doSyncBlacklist();
});