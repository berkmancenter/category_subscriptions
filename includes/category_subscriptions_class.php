<?php
class CategorySubscriptions {
  var $user_subscriptions_table_name = '';
  var $category_subscription_version = '';

  public function __construct(){
    global $wpdb;
    $this->user_subscriptions_table_name = $wpdb->prefix .'cat_sub_categories_users';
  }

  # PHP 4 constructor
  function CategorySubscriptions() {
    return $this->__construct();
  }

}
