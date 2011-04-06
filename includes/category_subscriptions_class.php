<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
class CategorySubscriptions {
  var $user_subscriptions_table_name = '';
  var $category_subscription_version = '';
  var $message_queue_table_name = '';
  var $wpdb = '';

  public function __construct(&$wpdb){
    $this->wpdb = $wpdb;
    $this->user_subscriptions_table_name = $this->wpdb->prefix .'cat_sub_categories_users';
    $this->message_queue_table_name = $this->wpdb->prefix . 'cat_sub_messages';
    $this->category_subscription_version = '0.1';
  }

  # PHP 4 constructor
  function CategorySubscriptions() {
    return $this->__construct();
  }

  function category_subscriptions_install(){
      $sql = "CREATE TABLE " . $this->user_subscriptions_table_name . ' (
          id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          category_ID bigint(20) UNSIGNED,
          user_ID bigint(20) UNSIGNED,
          UNIQUE KEY id (id),
          KEY category_ID (category_ID),
          KEY user_ID (user_ID)
      ) DEFAULT CHARSET=utf8';

      dbDelta($sql);

      $sql = "CREATE TABLE " . $this->message_queue_table_name . " (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          user_ID bigint(20) UNSIGNED,
          subject varchar(250),
          message varchar(10000),
          to_send boolean DEFAULT TRUE,
          deliver_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
          UNIQUE KEY id (id),
          KEY user_ID (user_ID)
      ) DEFAULT CHARSET=utf8";

      dbDelta($sql);

      add_option("category_subscription_version", $this->category_subscription_version);
  } 
    function cat_subscribe_update_profile_fields ( $user ){
        // TODO - save options.

        $cats_to_save = $_POST['category_subscription_categories'];
		    $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->user_subscriptions_table_name} WHERE user_ID = %s", $user->ID ) );
    
    }
    function cat_subscribe_show_profile_fields( $user ) { ?>
  <h3><?php _e('Email Updates') ?></h3>
    <table class="form-table">
      <tr>
      <th><label for="category_subscription_categories"><?php _e("Please select the types of updates you'd like to receive") ?></label></th>
        <td>
        <?php echo $this->cat_subscribe_category_list() ?><br />
        <span class="description"><?php _e("Please select the categories you'd like to get updates from.") ?></span>
        </td>
      </tr>
  </table>
    <?php } 

    function cat_subscribe_category_list() {
        $categories = get_categories('hide_empty=0&orderby=name');
        $output = '';
        foreach ($categories as $cat){
            $output .= '<input type="checkbox" name="category_subscription_categories[]" id="category_subscription_category_' . $cat->cat_ID . '">';
            $output .= '<label for="category_subscription_category_' . $cat->cat_ID . '">' . htmlspecialchars($cat->cat_name) .'</label>';
        }
        return $output;
    }

} // CategorySubscriptions class

 ?>
