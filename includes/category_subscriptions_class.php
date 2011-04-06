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

    function cat_subscribe_update_profile_fields ( $user_ID ){
        $cats_to_save = $_POST['category_subscription_categories'];
        $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->user_subscriptions_table_name WHERE user_ID = %d", array($user_ID) ) );
        foreach ($cats_to_save as $cat){
            $this->wpdb->insert($this->user_subscriptions_table_name, array('category_ID' => $cat, 'user_ID' => $user_ID), array('%d','%d') );
        }
    }

  function cat_subscribe_show_profile_fields( $user ) {

      // i18n apparently can't be embedded directly into a heredoc. This makes me sad. :(
      $header = __('Email Updates');
      $label = __('Please select the types of updates you\'d like to receive');
      $description = __('Please select the categories you\'d like to get updates from.');

  echo <<<FIELDS
    <h3>$header</h3>
    <table class="form-table">
      <tr>
      <th><label for="category_subscription_categories">$label</label></th>
        <td>
            {$this->cat_subscribe_category_list($user)} <br/>
        <span class="description">$description</span>
        </td>
      </tr>
    </table>
FIELDS;
 } 

  function cat_subscribe_category_list($user) {

        $categories = get_categories('hide_empty=0&orderby=name');
        $sql = $this->wpdb->prepare("SELECT category_ID from $this->user_subscriptions_table_name where user_ID = %d", array($user->ID));
        $subscriptions = $this->wpdb->get_results($sql, OBJECT_K);
        $output = '';
        foreach ($categories as $cat){
            $output .= '<input type="checkbox" name="category_subscription_categories[]" value="' . esc_attr($cat->cat_ID) . '" id="category_subscription_category_' . $cat->cat_ID . '" ' . ((isset($subscriptions[$cat->cat_ID])) ? 'checked="checked"' : '') . '>';
            $output .= ' <label for="category_subscription_category_' . $cat->cat_ID . '">' . htmlspecialchars($cat->cat_name) .'</label><br/>';
        }
        return $output;
  }

  function cat_subscribe_admin_menu (){
      // TODO - build out the config page.
      add_submenu_page('plugins.php', __('Category Subscriptions Configuration'), __('Category Subscriptions Configuration'), 'manage_options', 'category-subscriptions-config', 'cat_subscribe_config');
  }

} // CategorySubscriptions class
