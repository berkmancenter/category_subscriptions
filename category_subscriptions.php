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

require_once('includes/category_subscriptions_class.php');

$cat_sub = new CategorySubscriptions($wpdb);

// Hooks and actions

register_activation_hook(__FILE__,array($cat_sub,'category_subscriptions_install'));

// In user_functions.php
// show options on profile page
add_action( 'edit_user_profile', array($cat_sub, 'cat_subscribe_show_profile_fields') );
add_action( 'profile_personal_options', array($cat_sub, 'cat_subscribe_show_profile_fields') );

// save options from profile page
add_action( 'personal_options_update', array($cat_sub, 'cat_subscribe_update_profile_fields') );
add_action( 'edit_user_profile_update', array($cat_sub, 'cat_subscribe_update_profile_fields') );

// Admin functions
add_action( 'admin_menu', array($cat_sub, 'cat_subscribe_admin_menu') );

?>
