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

  public function __construct(&$wpdb){
    $this->wpdb = $wpdb;
    $this->user_subscriptions_table_name = $this->wpdb->prefix .'cat_sub_categories_users';
    $this->message_queue_table_name = $this->wpdb->prefix . 'cat_sub_messages';
    $this->category_subscription_version = '0.1';
    if(get_option('cat_sub_max_batch'))
      $this->max_batch = get_option('cat_sub_max_batch');

    if(get_option('cat_sub_use_wp_cron'))
      $this->use_wp_cron = get_option('cat_sub_use_wp_cron');

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
      add_submenu_page('options-general.php', __('Category Subscriptions Configuration'), __('Category Subscriptions'), 'manage_options', 'category-subscriptions-config', array($this,'cat_subscribe_config'));
  }

  function cat_subscribe_config(){
    if ( isset($_POST['submit']) ) {
      if ( function_exists('current_user_can') && !current_user_can('manage_options') ){
        die(__('how about no?'));
      };
      // Save options

      $options_to_save = array(
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


      // TODO - dynamically assign variables.
      $this->max_batch = $_POST['cat_sub_max_batch'];
      update_option('cat_sub_max_batch', $this->max_batch);

      $this->use_wp_cron = $_POST['cat_sub_use_wp_cron'];
      update_option('cat_sub_use_wp_cron', $this->use_wp_cron);
    }
    // emit form
    $messages = array(
      'welcome' => __('Configure Category Subscriptions'),
      'max_batch_label' => __('Maximum outgoing email batch size'),
      'max_batch_description' => __('How many emails should we send per cron run?'),
      'use_wp_cron_label' => __('Use built-in cron?'),
      'use_wp_cron_description' => __("Deliver email via Wordpress's built-in cron features? If you uncheck this, you'll need to set up a separate cron job to deliver email. You might want to do this if you have large subscriber lists."),
      'yes' => __('Yes'),
      'no' => __('No'),
      'update_options' => __('Update Options'),
      'email_attributes' => __('Outgoing emails'),
      'daily_email_subject' => __('Subject line for hourly digest emails'),
      'weekly_email_subject' => __('Subject line for weekly digest emails'),
      'individual_email_subject' => __('Subject line for emails sent individually')
  
    );
?>
<div class="wrap">
  <form action="" method="post">
  <h2><?php echo $messages['welcome']; ?></h2>
  <table class="form-table">
    <tr>
    <th><label for="cat_sub_max_batch"><?php echo $messages['max_batch_label']?></label></th>
      <td>
      <input type="text" name="cat_sub_max_batch" value="<?php echo esc_attr($this->max_batch); ?>" size="10" /><br />
        <span class="description"><?php echo $messages['max_batch_description'] ?></span>
      </td>
    </tr>
    <tr>
    <th><label for="cat_sub_use_wp_cron"><?php echo $messages['use_wp_cron_label']; ?></label></th>
      <td>
        <select name="cat_sub_use_wp_cron">
        <option value="yes" <?php echo (($this->use_wp_cron == 'yes') ? 'selected="selected"' : ''); ?>><?php echo $messages['yes']; ?></option>
        <option value="no" <?php echo (($this->use_wp_cron == 'no') ? 'selected="selected"' : ''); ?>><?php echo $messages['no']; ?></option>
        </select><br/>
        <span class="description"><?php echo $messages['use_wp_cron_description']; ?></span>
      </td>
    </tr>
    <tr>
    <td colspan="2"><h3><?php echo $messages['email_attributes']; ?></h3></td>
    </tr>
    <tr>
    <th><label for="cat_sub_daily_email_subject"><?php echo $messages['daily_email_subject']; ?></label></th>
    <td><input type="
      
  </table>
  <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php echo $messages['update_options']; ?>"  /></p> 
  </form>
</div> 
<?php

  }
  

} // CategorySubscriptions class
