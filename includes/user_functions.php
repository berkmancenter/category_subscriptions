<?php function cat_subscribe_show_profile_fields( $user ) { 
 global $cat_sub; ?>
  <h3><?php _e('Email Updates') ?></h3>
    <table class="form-table">
      <tr>
      <th><label for="category_subscription_categories"><?php _e("Please select the types of updates you'd like to receive") ?></label><?php echo $cat_sub->user_subscriptions_table_name; ?></th>
        <td>
        <?php echo cat_subscribe_category_list() ?>
        <input type="text" name="category_subscription_categories" id="category_subscription_categories" value="<?php esc_attr( get_the_author_meta( 'category_subscription_categories', $user->ID ) ) ?>" class="regular-text" /><br />
        <span class="description"><?php _e("Please select the categories you'd like to get updates from.") ?></span>
        </td>
      </tr>
  </table>
<?php } ?> 

<?php function cat_subscribe_category_list() {
  $categories = get_categories('hide_empty=0&orderby=name');
  $output = '';
  foreach ($categories as $cat){
    $output .= '<input type="checkbox" name="category_subscription_categories" id="category_subscription_category_' . $cat->cat_ID . '">';
    $output .= '<label for="category_subscription_category_' . $cat->cat_ID . '">' . htmlspecialchars($cat->cat_name) .'</label>';
  }
  return $output;
} 
