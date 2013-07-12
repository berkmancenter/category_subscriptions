<?php
/**
 * @package Category Subscriptions
 * @author Dan Collis-Puro
 * @version 1.2
 */
/*
Plugin Name: Category Subscriptions
Plugin URI: http://www.collispuro.com
Description: This plugin allows your registered users to subscribe to categories and receive updates.
Author: Dan Collis-Puro
Version: 1.2
Author URI: http://collispuro.com
*/

global $wpdb;

// Need get_userdata()
require_once(ABSPATH . 'wp-includes/pluggable.php');

require_once('includes/category_subscriptions_class.php');
require_once('includes/category_subscriptions_message.php');
require_once('includes/category_subscriptions_template.php');

require_once("includes/category_subscription_export_html_manager.php");
require_once("includes/category_subscription_export_db_manager.php");

$cat_sub = new CategorySubscriptions($wpdb);

$cat_sub_export_html = new Category_subscription_export_html_manager();
$cat_sub_export_db = new Category_subscription_export_db_manager();

// Debugging. . .
// $cat_sub->prepare_daily_messages();
// $cat_sub->send_digested_messages('daily',0);

// Cron functions
add_action( 'my_cat_sub_send_individual_messages', array($cat_sub, 'send_individual_messages_for') );
add_action( 'my_cat_sub_prepare_daily_messages', array($cat_sub, 'prepare_daily_messages') );
add_action( 'my_cat_sub_prepare_weekly_messages', array($cat_sub, 'prepare_weekly_messages') );

add_action( 'my_cat_sub_send_digested_messages', array($cat_sub, 'send_digested_messages') );

// Activation, de-activation
register_activation_hook(__FILE__,array( $cat_sub,'category_subscriptions_install' ));
register_deactivation_hook(__FILE__,array( $cat_sub,'category_subscriptions_deactivate' ));

// Bulk editing
if(current_user_can('remove_users')){
	add_filter('manage_users_columns', array($cat_sub, 'add_cat_sub_custom_column'));
	add_filter('manage_users_custom_column', array($cat_sub, 'manage_users_custom_column'), 10, 3);
	add_action('admin_head', array($cat_sub, 'update_bulk_edit_changes'));
	// Doesn't work. You can only remove actions from the bulk edit menu. :-(
	//	add_filter('bulk_actions-users', array($cat_sub,'custom_bulk_action'));
}

// edit user profile page
if(current_user_can('remove_users')){
	add_action( 'edit_user_profile', array( $cat_sub, 'show_profile_fields' ) );
	add_action( 'edit_user_profile_update', array( $cat_sub, 'update_profile_fields' ) );
}

// update user edits
add_action( 'profile_personal_options', array( $cat_sub, 'show_profile_fields' ) );
add_action( 'personal_options_update', array( $cat_sub, 'update_profile_fields' ) );

// Instantiate messages on post publish
add_action( 'save_post', array( $cat_sub, 'instantiate_messages' ) );

// Remove messages when trashed
add_action( 'trashed_post', array( $cat_sub, 'trash_messages' ) );

// Admin functions
add_action( 'admin_menu', array( $cat_sub, 'admin_menu' ) );

// Remove subscriptions when a user is removed from a blog, spammed, or deleted.
// RIGHT HERE is where foreign keys prove their worth.

add_action('delete_user', array( $cat_sub, 'clean_up_removed_user'));
add_action('wpmu_delete_user', array( $cat_sub, 'clean_up_removed_user'));

// I cannot confirm that this action ever runs from the site edit page. 
add_action('make_spam_user', array( $cat_sub, 'clean_up_removed_user'));

// Probably need to check for multisite here to instantiate this hook if needed.
add_action('remove_user_from_blog', array( $cat_sub, 'clean_up_removed_user'));

// Export - Create Options Page
add_action('admin_menu', 'add_category_subscription_menu_hook');
function add_category_subscription_menu_hook(){
    global $cat_sub_export_html;
    add_options_page(
        __('Export Category Subscriptions Data'),
        __('Export Category Subscriptions Data'), 
        'manage_options', 
        'categories-subscription-export', 
        array($cat_sub_export_html, 'export_admin_page')
    );
}

// Export - Create CSV File
add_action('init', array($cat_sub_export_html, 'export_CSV'));

?>
