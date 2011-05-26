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

global $wpdb;

// Need get_userdata()
require_once(ABSPATH . 'wp-includes/pluggable.php');

require_once('includes/category_subscriptions_class.php');
require_once('includes/category_subscriptions_message.php');
require_once('includes/category_subscriptions_template.php');

$cat_sub = new CategorySubscriptions($wpdb);

// Debugging. . .
//$cat_sub->prepare_daily_messages();

// Cron functions
add_action( 'my_cat_sub_send_individual_messages', array($cat_sub, 'send_individual_messages_for') );
add_action( 'my_cat_sub_prepare_daily_messages', array($cat_sub, 'prepare_daily_messages') );
add_action( 'my_cat_sub_prepare_weekly_messages', array($cat_sub, 'prepare_weekly_messages') );

add_action( 'my_cat_sub_send_digested_messages', array($cat_sub, 'send_digested_messages') );

// Activation, de-activation
register_activation_hook(__FILE__,array( $cat_sub,'category_subscriptions_install' ));
register_deactivation_hook(__FILE__,array( $cat_sub,'category_subscriptions_deactivate' ));

// Bulk editing
if(current_user_can('manage_options')){
  add_filter('manage_users_columns', array($cat_sub, 'add_cat_sub_custom_column'));
  add_filter('manage_users_custom_column', array($cat_sub, 'manage_users_custom_column'), 10, 3);
  add_action('admin_head', array($cat_sub, 'update_bulk_edit_changes'));
}

// show options on profile page
add_action( 'edit_user_profile', array( $cat_sub, 'show_profile_fields' ) );
add_action( 'profile_personal_options', array( $cat_sub, 'show_profile_fields' ) );

// save options from profile page
add_action( 'personal_options_update', array( $cat_sub, 'update_profile_fields' ) );
add_action( 'edit_user_profile_update', array( $cat_sub, 'update_profile_fields' ) );

// TODO:
// Add hook to clear out subscriptions after a user is deactivated / deleted / spammed, etc.

// Instantiate messages on post publish
add_action( 'save_post', array( $cat_sub, 'instantiate_messages' ) );

// Remove messages when trashed
add_action( 'trashed_post', array( $cat_sub, 'trash_messages' ) );

// Admin functions
add_action( 'admin_menu', array( $cat_sub, 'admin_menu' ) );

?>
