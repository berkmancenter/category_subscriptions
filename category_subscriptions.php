<?php
/**
 * @package Category Subscriptions
 * @author Dan Collis-Puro
 * @version 0.1
*/
/*
        Plugin Name: Category Subscriptions
        Plugin URI: http://www.collispuro.com
        Description: This plugin allows your registered users to subscribe to categories and receive updates.
        Author: Dan Collis-Puro
        Version: 0.1
        Author URI: http://collispuro.com
*/

require_once('includes/category_subscriptions_class.php');

$cat_sub = new CategorySubscriptions;
$cat_sub->category_subscription_version = '0.1';

require_once('includes/user_functions.php');

function category_subscriptions_install(){
  global $wpdb;
  global $cat_sub;
  $sql = "CREATE TABLE " . $cat_sub->user_subscriptions_table_name . ' (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    category_ID bigint(20) unsigned,
    user_ID bigint(20) unsigned,
    UNIQUE KEY id (id)
  )';

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  add_option("category_subscription_version", $cat_sub->category_subscription_version);
} 

# Hooks and actions

register_activation_hook(__FILE__,'category_subscriptions_install');

# In user_functions.php
add_action( 'edit_user_profile', 'cat_subscribe_show_profile_fields' );
add_action( 'profile_personal_options', 'cat_subscribe_show_profile_fields' );
