<?php
class CategorySubscriptions {
    var $user_subscriptions_table_name = '';
    var $category_subscription_version = '';
    var $message_queue_table_name = '';
    var $wpdb = '';

    var $max_batch = 50;
    var $use_wp_cron = 'yes';

    var $daily_email_subject = 'Daily Digest for [DAY], [CATEGORY] - [SITETITLE]';
    var $daily_email_html_template = '';
    var $daily_email_text_template = '';
    var $daily_email_type = '';

    var $weekly_email_subject = 'Weekly Digest for [WEEK], [CATEGORY] - [SITETITLE]';
    var $weekly_email_html_template = '';
    var $weekly_email_text_template = '';
    var $weekly_email_type = '';

    var $individual_email_subject = '[SUBJECT], [CATEGORY] - [SITETITLE]';
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
        wp_register_script('jquery.cookie.js',plugins_url('/javascripts/jquery.cookie.js',dirname(__FILE__)));
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE " . $this->user_subscriptions_table_name . ' (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category_ID bigint(20) UNSIGNED,
            delivery_time_preference ENUM("individual","daily","weekly") not null default "individual",
            delivery_format_preference ENUM("html","text") not null default "text",
            user_ID bigint(20) UNSIGNED,
            UNIQUE KEY id (id),
          KEY category_ID (category_ID),
          KEY delivery_time_preference (delivery_time_preference),
          KEY delivery_format_preference (delivery_format_preference),
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
      wp_enqueue_script('jquery.cookie.js');
      wp_enqueue_script('admin.js');
      echo '<h3>' . __('Email Updates') . '</h3>';
      echo '<p>' . __('Please select the types of updates you\'d like to receive') . '</p>';
      echo $this->category_list($user);
    } 

function create_email_template_form_elements($type){ 
    // dynamically creating i18n is probably not going to work. . . 
?>
    <h4 class="cat_sub_toggler" id="<?php echo $type;?>_toggler"><?php _e(ucfirst($type) .' Emails'); ?><span><?php _e('expand. . .'); ?></span></h4>
    <table class="form-table toggler_target" id="<?php echo $type;?>_target">
    <tr>
    <th><label for="cat_sub_<?php echo $type;?>_email_subject"><?php _e('Subject line template for ' . $type .' emails'); ?></label></th>
    <td><input type="text" id="cat_sub_<?php echo $type;?>_email_subject" name="cat_sub_<?php echo $type;?>_email_subject" value="<?php echo esc_attr($this->{$type .'_email_subject'}); ?>" size="70" /><br/>
    </td>
    </tr>
    <tr>
        <th><label for="cat_sub_<?php echo $type;?>_email_html_template"><?php _e('HTML email template for '. $type . ' emails'); ?></label></th>
        <td><textarea id="cat_sub_<?php echo $type;?>_email_html_template" rows="10" cols="70" name="cat_sub_<?php echo $type;?>_email_html_template"><?php echo esc_textarea($this->{$type . '_email_html_template'}); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="cat_sub_<?php echo $type;?>_email_text_template"><?php _e('Plain text email template for ' . $type .' emails'); ?></label></th>
        <td><textarea id="cat_sub_<?php echo $type;?>_email_text_template" rows="10" cols="70" name="cat_sub_<?php echo $type; ?>_email_text_template"><?php echo esc_textarea($this->{$type . '_email_text_template'}); ?></textarea></td>
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
    //TODO
    $categories = get_categories('hide_empty=0&orderby=name');
    $sql = $this->wpdb->prepare("SELECT category_ID, delivery_time_preference, delivery_format_preference from $this->user_subscriptions_table_name where user_ID = %d", array($user->ID));
    $subscriptions = $this->wpdb->get_results($sql, OBJECT_K);
?>
        <table class="wp-list-table widefat fixed">
          <thead>
            <tr>
            <th><?php _e('Category'); ?></th>
            <th><?php _e('Frequency'); ?></th>
            <th><?php _e('Format'); ?></th>
            </tr>
          </thead><tbody>
<?php foreach ($categories as $cat){ ?>
    <tr>
        <td>
            <input type="checkbox" name="category_subscription_categories[]" value="<?php echo esc_attr($cat->cat_ID); ?>" id="category_subscription_category_<?php echo $cat->cat_ID; ?>" <?php echo ((isset($subscriptions[$cat->cat_ID])) ? 'checked="checked"' : '') ?> >
            <label for="category_subscription_category_<?php echo $cat->cat_ID; ?>"><?php echo htmlspecialchars($cat->cat_name); ?></label>
        </td>
        <td>
            <select name="delivery_time_preference_<?php echo $cat->cat_ID; ?>">
                <option name="individual"><?php _e('Immediately'); ?></option>
                <option name="daily"><?php _e('Daily'); ?></option>
                <option name="weekly"><?php _e('Weekly'); ?></option>
            </select>
        </td>
        <td>
            <select name="delivery_format_preference_<?php echo $cat->cat_ID; ?>">
                <option name="text"><?php _e('Plain text'); ?></option>
                <option name="html"><?php _e('HTML'); ?></option>
            </select>
        </td>
    </tr>
<?php
    }
    echo '</tbody></table>';
}

    function admin_menu (){
        wp_enqueue_style('admin.css');
        wp_enqueue_script('jquery.cookie.js');
        wp_enqueue_script('admin.js');
        add_submenu_page('options-general.php', __('Category Subscriptions Configuration'), __('Category Subscriptions'), 'manage_options', 'category-subscriptions-config', array($this,'config'));
    }

    function config(){
        $updated = false;
        if ( isset($_POST['submit']) ) {
            if ( function_exists('current_user_can') && !current_user_can('manage_options') ){
                die(__('how about no?'));
            };
            // Save options

            $updated = true;
            foreach($this->editable_options as $opt){
                $this->{$opt} = $_POST['cat_sub_' . $opt];
                update_option('cat_sub_'. $opt, $this->{$opt});
            }
        }
        // emit form
        if($updated){ 
            echo "<div id='message' class='updated'><p><strong>" . __('Saved options.') ."</strong></p></div>";
        }
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
    <ul>
        <li><?php _e('All email templates - daily, weekly, individual - share the same tags. So, for example, you can put [FIRST_NAME] in the subject line or body of any email template and it\'ll work the same.'); ?></li>
        <li><?php _e('An "email row template" defines the template used in digest emails to display the list of messages. Each individual message has this template applied to it, and is then put in the [EMAILLIST] template tag for your daily or weekly digest.'); ?></li>
        <li><?php _e('You should be sure both the HTML and plain text templates are kept up to date. They will both be used when you create HTML messages so that the maximum number of users can read your content.') ?></li>
    </ul>

    <?php $this->create_email_template_form_elements('individual') ?>
    <?php $this->create_email_template_form_elements('daily') ?>
    <?php $this->create_email_template_form_elements('weekly') ?>

    <h4 class="cat_sub_toggler" id="email_row_toggler"><?php _e('Email Rows'); ?><span><?php _e('expand. . .'); ?></span></h4>
    <table class="form-table toggler_target" id="email_target">
        <tr>
            <th><label for="cat_sub_email_row_html_template"><?php _e('HTML email row template'); ?></label>
            </th>
            <td><textarea rows="10" cols="70" name="cat_sub_email_row_html_template"><?php echo esc_textarea($this->email_row_html_template); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="cat_sub_email_row_text_template"><?php _e('Text email row template'); ?></label>
            </th>
            <td><textarea rows="10" cols="70" name="cat_sub_email_row_text_template"><?php echo esc_textarea($this->email_row_text_template); ?></textarea></td>
        </tr>
    </table>

  <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Update Options'); ?>"  /></p> 
  </form>
</div> 
<?php

    }


} // CategorySubscriptions class
