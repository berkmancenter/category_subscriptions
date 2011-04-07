<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
class CategorySubscriptions {
    var $user_subscriptions_table_name = '';
    var $category_subscription_version = '';
    var $message_queue_table_name = '';
    var $wpdb = '';

    var $max_batch = 50;
    var $use_wp_cron = 'yes';

    var $daily_email_subject = '';
    var $daily_email_html_template = '';
    var $daily_email_text_template = '';
    var $daily_email_type = '';

    var $weekly_email_subject = '';
    var $weekly_email_html_template = '';
    var $weekly_email_text_template = '';
    var $weekly_email_type = '';

    var $individual_email_subject = '';
    var $individual_email_html_template = '';
    var $individual_email_text_template = '';
    var $individual_email_type = '';

    var $email_row_html_template = '';
    var $email_row_text_template = '';

    var $editable_options = array(
        'max_batch', 
        'use_wp_cron',

        'daily_email_subject',
        'daily_email_html_template',
        'daily_email_text_template',
        'daily_email_type',

        'weekly_email_subject',
        'weekly_email_html_template',
        'weekly_email_text_template',
        'weekly_email_type',

        'individual_email_subject',
        'individual_email_html_template',
        'individual_email_text_template',
        'individual_email_type',

        'email_row_text_template',
        'email_row_html_template'
    );

    public function __construct(&$wpdb){

        wp_register_style('admin.css',plugins_url('/stylesheets/admin.css',dirname(__FILE__)));
        wp_register_script('admin.js',plugins_url('/javascripts/admin.js',dirname(__FILE__)));

        $this->wpdb = $wpdb;
        $this->user_subscriptions_table_name = $this->wpdb->prefix .'cat_sub_categories_users';
        $this->message_queue_table_name = $this->wpdb->prefix . 'cat_sub_messages';
        $this->category_subscription_version = '0.1';
        foreach($this->editable_options as $opt){
            if(get_option('cat_sub_' . $opt)){
                $this->{$opt} = get_option('cat_sub_' . $opt);
            }
        }
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

    function update_profile_fields ( $user_ID ){
        $cats_to_save = $_POST['category_subscription_categories'];
        $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->user_subscriptions_table_name WHERE user_ID = %d", array($user_ID) ) );
        foreach ($cats_to_save as $cat){
            $this->wpdb->insert($this->user_subscriptions_table_name, array('category_ID' => $cat, 'user_ID' => $user_ID), array('%d','%d') );
        }
    }

    function show_profile_fields( $user ) {
        wp_enqueue_style('admin.css');
        wp_enqueue_script('admin.js');
?>
    <h3><?php _e('Email Updates'); ?></h3>
    <table class="form-table">
      <tr>
      <th><label for="category_subscription_categories"><?php _e('Please select the types of updates you\'d like to receive'); ?></label></th>
        <td>
        <?php echo $this->category_list($user) ?> <br/>
            <span class="description"><?php _e('Please select the categories you\'d like to get updates from.'); ?></span>
        </td>
      </tr>
    </table>
<?php } 

function create_email_template_form_elements($type){ ?>
    <h4 class="cat_sub_toggler" id="<?php echo $type;?>_toggler"><?php _e($type .' Emails'); ?><span><?php _e('expand. . . '); ?></span></h4>
    <table class="form-table toggler_target" id="<?php echo $type;?>_target">
    <tr>
    <th><label for="cat_sub_<?php echo $type;?>_email_subject"><?php _e('Subject line template for ' . $type .' emails'); ?></label></th>
    <td><input type="text" name="cat_sub_<?php echo $type;?>_email_subject" value="<?php echo esc_attr($this->{$type .'_email_subject'}); ?>" size="70" /><br/>
    </td>
    </tr>
    <tr>
        <th><label for="cat_sub_<?php echo $type;?>_email_html_template"><?php _e('HTML email template for '. $type . ' emails'); ?></label></th>
        <td><textarea rows="10" cols="70" name="cat_sub_<?php echo $type;?>_email_html_template"><?php echo esc_textarea($this->{$type . '_email_html_template'}); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="cat_sub_<?php echo $type;?>_email_text_template"><?php _e('Plain text email template for ' . $type .' emails'); ?></label></th>
        <td><textarea rows="10" cols="70" name="cat_sub_<?php echo $type; ?>_email_text_template"><?php echo esc_textarea($this->{$type . '_email_text_template'}); ?></textarea></td>
    </tr>
        <?php $this->default_email_type_list($type); ?>
    </tr>
  </table>
<?php 
}

function default_email_type_list($email_type) {
    ?>
        <tr>
            <th><label for="cat_sub_<?php echo $email_type; ?>_email_type"><?php _e('Send out this type of email by default for ' . $email_type .' emails.'); ?></label></th>
            <td>
                <select name="cat_sub_<?php echo $email_type; ?>_email_type">
                    <option value="html" <?php ($this->{$email_type . '_email_type'} == 'html') ? ' selected="selected"' : '' ?>><?php _e('Multipart HTML'); ?></option>
                    <option value="text"<?php ($this->{$email_type . '_email_type'} == 'text') ? ' selected="selected"' : '' ?>><?php _e('Text only'); ?></option>
                </select>
            </td>
        </tr>
<?php }

    function category_list($user) {

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

    function admin_menu (){
        wp_enqueue_style('admin.css');
        wp_enqueue_script('admin.js');
        add_submenu_page('options-general.php', __('Category Subscriptions Configuration'), __('Category Subscriptions'), 'manage_options', 'category-subscriptions-config', array($this,'config'));
    }

    function config(){
        if ( isset($_POST['submit']) ) {
            if ( function_exists('current_user_can') && !current_user_can('manage_options') ){
                die(__('how about no?'));
            };
            // Save options

            foreach($this->editable_options as $opt){
                // TODO - dynamically assign variables.
                $this->{$opt} = $_POST['cat_sub_' . $opt];
                update_option('cat_sub_'. $opt, $this->{$opt});
            }
        }
        // emit form
?>
<div class="wrap">
  <form action="" method="post">
  <h2><?php _e('Configure Category Subscriptions'); ?></h2>
  <table class="form-table">
    <tr>
    <th><label for="cat_sub_max_batch"><?php _e('Maximum outgoing email batch size');  ?></label></th>
      <td>
      <input type="text" name="cat_sub_max_batch" value="<?php echo esc_attr($this->max_batch); ?>" size="10" /><br />
        <span class="description"><?php _e('How many emails should we send per cron run?') ?></span>
      </td>
    </tr>
    <tr>
    <th><label for="cat_sub_use_wp_cron"><?php _e('Use built-in cron?'); ?></label></th>
      <td>
        <select name="cat_sub_use_wp_cron">
        <option value="yes" <?php echo (($this->use_wp_cron == 'yes') ? 'selected="selected"' : ''); ?>><?php _e('Yes'); ?></option>
        <option value="no" <?php echo (($this->use_wp_cron == 'no') ? 'selected="selected"' : ''); ?>><?php _e('No'); ?></option>
        </select><br/>
        <span class="description"><?php _e("Deliver email via Wordpress's built-in cron features? If you uncheck this, you'll need to set up a separate cron job to deliver email. You might want to do this if you have large subscriber lists."); ?></span>
      </td>
    </tr>
    </table>

    <h3><?php _e('Email Templates'); ?></h3>
    <p><?php _e('All email templates - daily, weekly, individual - share the same tags. So, for example, you can put [FIRST_NAME] in the subject line or body of any email template and it\'ll work the same.'); ?></p>

    <?php $this->create_email_template_form_elements('individual') ?>
    <?php $this->create_email_template_form_elements('daily') ?>
    <?php $this->create_email_template_form_elements('weekly') ?>

  <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Update Options'); ?>"  /></p> 
  </form>
</div> 
<?php

    }


} // CategorySubscriptions class
