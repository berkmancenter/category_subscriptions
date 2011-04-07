jQuery(document).ready(function(){

    // Keep toggle states persistent via cookies. Indeed, that is how we roll.
    jQuery('.toggler_target').each(function(){
        var id = jQuery(this).attr('id').split('_')[0];
        if(jQuery.cookie('cat-sub-' + id + '-open') == 1){
            jQuery('#' + id + '_target').show();
        }
    });

    jQuery('.cat_sub_toggler').bind({
        click: function(){
            var id = jQuery(this).attr('id').split('_')[0];
            jQuery('#' + id + '_target').toggle();
            if( jQuery('#' + id + '_target').is(':visible') ){
                jQuery.cookie('cat-sub-' + id + '-open', 1, {expires: 365});
            } else {
                jQuery.cookie('cat-sub-' + id + '-open', 0, {expires: 365});
            }
        },
        mouseover: function(){
            jQuery(this).css({cursor: 'pointer'});
        }
    });

});


