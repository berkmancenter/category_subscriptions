// Coming soon!
//
jQuery(document).ready(function(){
    jQuery('.cat_sub_toggler').bind({
        click: function(){
            var id = jQuery(this).attr('id').split('_')[0];
            jQuery('#' + id + '_target').toggle();
        },
        mouseover: function(){
            jQuery(this).css({cursor: 'pointer'});
        }
    });
});


