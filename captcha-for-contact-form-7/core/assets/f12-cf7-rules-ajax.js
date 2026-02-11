function doSyncBlacklist() {
    jQuery.ajax({
        type: 'POST',
        url: f12_cf7_captcha_rules.resturl + 'blacklist/sync',
        contentType: 'application/json',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', f12_cf7_captcha_rules.restnonce);
        },
        success: function(data) {
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
