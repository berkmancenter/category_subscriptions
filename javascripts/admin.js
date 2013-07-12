jQuery(document).ready(function(){

  // Keep toggle states persistent via cookies. Indeed, that is how we roll.
  jQuery('.toggler_target').each(function(){
    var id = jQuery(this).attr('id').split('_')[0];
    if(jQuery.cookie('cat-sub-' + id + '-open') == 1){
      jQuery('#' + id + '_target').show();
    }
  });

  jQuery('.cat_sub_user_cat_list').find('.depth-1').click(function(){
    jQuery(this).closest('td').find('input:checkbox:not(.depth-1)').each(function(){
      jQuery(this).attr('checked', ! jQuery(this).attr('checked'));
    });
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


jQuery(document).ready(function (){

  jQuery('.cat_sub_bulk_edit_open').on('click', function (){
    var jq_bulk_edit = jQuery(this).siblings('.cat_sub_bulk_edit_center').children('.cat_sub_bulk_edit');
    if (jq_bulk_edit.is(':visible')){
      jq_bulk_edit.slideUp(100);
    }
    else {
      jq_bulk_edit.slideDown(100);
    }
  });
  jQuery('.cat_sub_bulk_edit_close').on('click', function (){
    jQuery(this).parents('.cat_sub_bulk_edit').slideUp(100);
  });

});