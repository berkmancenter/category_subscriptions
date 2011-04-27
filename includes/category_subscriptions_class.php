<?php
class CategorySubscriptions {
    var $user_subscriptions_table_name = '';
    var $category_subscription_version = '0.1';
    var $message_queue_table_name = '';
    var $wpdb = '';

    var $max_batch = 50;
    var $send_delay = 120;

    // 0 == Sunday, 6 == Saturday
    var $send_weekly_email_on = 0;

    var $from_address = '';
    var $reply_to_address = '';
    var $bcc_address = '';

    var $daily_email_subject = 'Daily Digest for [DAY], [CATEGORY] - [SITE_TITLE]';
    var $daily_email_html_template = '';
    var $daily_email_text_template = '';

    var $weekly_email_subject = 'Weekly Digest for [WEEK], [CATEGORY] - [SITE_TITLE]';
    var $weekly_email_html_template = '';
    var $weekly_email_text_template = '';

    var $individual_email_subject = '[POST_TITLE], [CATEGORIES] - [SITE_TITLE]';

    var $individual_email_html_template = '<p>Dear [USER_LOGIN],</p>
        <p>A new post has been added to one of your subscriptions at <a href="[SITE_URL]">[SITE_TITLE]</a>.</p>
        <hr />
        <h2>[POST_TITLE] - [CATEGORIES]</h2>
        <h3>by [AUTHOR] on [DATE]</h3>
        [POST_CONTENT]

        <hr />
        <p>You can manage your subscriptions <a href="[PROFILE_URL]">here</a>.</p>';

    var $individual_email_text_template = 'Dear [USER_LOGIN],

A new post has been added to one of your subscriptions at:
[SITE_TITLE]

_____________________________________________________________

[POST_TITLE] - [CATEGORIES]

by [AUTHOR] on [DATE]

[POST_CONTENT]

_____________________________________________________________

You can manage your subscriptions here:
[PROFILE_URL]
';

    var $email_row_html_template = '';
    var $email_row_text_template = '';

    var $editable_options = array(
        'max_batch',
        'send_delay',
        'send_weekly_email_on',
        'from_address',
        'reply_to_address',
        'bcc_address',

        'daily_email_subject',
        'daily_email_html_template',
        'daily_email_text_template',

        'weekly_email_subject',
        'weekly_email_html_template',
        'weekly_email_text_template',

        'individual_email_subject',
        'individual_email_html_template',
        'individual_email_text_template',

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

        if(get_option('category_subscription_version') != $this->category_subscription_version){
            // Re-init the plugin to apply database changes. Hizz-ott.

            $this->category_subscriptions_install();
        }

        foreach($this->editable_options as $opt){
            if(get_option('cat_sub_' . $opt)){
                $this->{$opt} = get_option('cat_sub_' . $opt);
            }
        }
    }

    # PHP 4 constructor
    public function CategorySubscriptions(&$wpdb) {
        return $this->__construct($wpdb);
    }

    public function category_subscriptions_install(){
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE " . $this->user_subscriptions_table_name . ' (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category_ID bigint(20) UNSIGNED,
            delivery_time_preference ENUM("individual","daily","weekly") not null default "individual",
            user_ID bigint(20) UNSIGNED,
            UNIQUE KEY id (id),
          KEY category_ID (category_ID),
          KEY delivery_time_preference (delivery_time_preference),
          KEY user_ID (user_ID)
      ) DEFAULT CHARSET=utf8';

        dbDelta($sql);

        $sql = "CREATE TABLE " . $this->message_queue_table_name . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_type ENUM('individual','daily','weekly') not null default 'individual',
            user_ID bigint(20) UNSIGNED,
            post_ID bigint(20) UNSIGNED,
            subject varchar(250),
            message varchar(10000),
            to_send boolean DEFAULT TRUE,
            message_sent boolean DEFAULT FALSE,
            deliver_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            UNIQUE KEY id (id),
            KEY user_ID (user_ID),
            KEY post_ID (post_ID),
            KEY to_send (to_send),
            KEY deliver_at (deliver_at)
        ) DEFAULT CHARSET=utf8";
    
        dbDelta($sql);

        update_option("category_subscription_version", $this->category_subscription_version);
        wp_schedule_event(time(), 'daily', 'my_cat_sub_send_daily_messages');
        wp_schedule_event(time(), 'daily', 'my_cat_sub_send_weekly_messages');

    }

    public function category_subscriptions_deactivate(){
        wp_clear_scheduled_hook('my_cat_sub_send_daily_messages');
        wp_clear_scheduled_hook('my_cat_sub_send_weekly_messages');
    } 

    public function update_profile_fields ( $user_ID ){
        $cats_to_save = (isset($_POST['category_subscription_categories'])) ? $_POST['category_subscription_categories'] : false;
        $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->user_subscriptions_table_name WHERE user_ID = %d", array($user_ID) ) );
        if($cats_to_save){
            foreach ($cats_to_save as $cat){
                $this->wpdb->insert($this->user_subscriptions_table_name, array('category_ID' => $cat, 'user_ID' => $user_ID, 'delivery_time_preference' => stripslashes($_POST['delivery_time_preference_' . $cat ])), array('%d','%d','%s') );
            }
        }
        update_user_meta($user_ID,'cat_sub_delivery_format_pref', stripslashes($_POST['cat_sub_delivery_format_pref_' . $user_ID]));
    }


    private function create_individual_messages(&$post){
        // Create stubs for individual messages.
        // You get here if you are published and don't already have sent messages in the queue.
        //
        // Get the categories for this post, find the users, and then add the rows to the message queue table.
        // It'd be nice to de-duplicate users because it'd simplify the conditions under instantiate_messages().

        function messages_conditions($a){
            // nested functions. Yuck. Here's the perl version of what I'm trying to do here:
            // my $category_conditions = join(' and ', map{'category_ID = ?' } @categories);
            return 'category_ID = %d';
        }

        $categories = wp_get_post_categories($post->ID);

        $category_conditions = array_map('messages_conditions',$categories);
        $parameters = $categories;
        array_unshift($parameters, 'individual');
        $conditions = implode(' OR ', $category_conditions);

        $subscribers = $this->wpdb->get_col($this->wpdb->prepare("SELECT DISTINCT user_ID from $this->user_subscriptions_table_name where delivery_time_preference = %s AND (" . $conditions . " )", $parameters));

        $already_getting = $this->wpdb->get_results($this->wpdb->prepare("select user_ID from $this->message_queue_table_name where post_ID = %d and message_type = 'individual'",array($post->ID)), OBJECT_K);

//        error_log('Subscribers: ' . print_r($subscribers,true) ); 
//        error_log('Already getting: ' . print_r($already_getting,true) ); 

        if($subscribers){
            // There are subscribers to this message.
            foreach($subscribers as $user_ID){
                if(! isset($already_getting[$user_ID]) ){
                    // If they aren't already getting this message, get them in there.
                    $this->wpdb->insert($this->message_queue_table_name, array('user_ID' => $user_ID, 'post_ID' => $post->ID, 'message_type' => 'individual'), array('%d','%d','%s'));
                }
            }
            $next_scheduled = wp_next_scheduled('my_cat_sub_send_individual_messages',array($post->ID));

            //error_log('Next scheduled value for:' . print_r($next_scheduled,true));

            if( $next_scheduled == 0 ){
                // Not currently scheduled.
                wp_schedule_single_event(time() + $this->send_delay, 'my_cat_sub_send_individual_messages', array($post->ID));
            }
        } 
    }

/*
 * If a message is published and there aren't any messages sent previously,
 * add rows to the database to cause individual messages to be sent.
 * We check to see if any messages were successfully sent previously to ensure
 * we aren't sending a message out every time it's edited and published again.
 *
 * If a post isn't published, or if it was published but it isn't any more, then we need
 * to take a slightly different tack.
 * Remove the unsent messages if it was previously published.
 * One way we will know if it was previously published is by looking 
 * in the messages queue for existing sent messages.
 *
*/
    public function instantiate_messages($post_ID){
    
        $post = get_post($post_ID);

        $sent_messages = $this->wpdb->get_var($this->wpdb->prepare("select count(*) from $this->message_queue_table_name where post_ID = %d and message_sent is true and message_type = 'individual'", array($post_ID)));

        if( $post->post_status == 'publish' && $sent_messages <= 0){
            $this->create_individual_messages($post);
        } else {
            // We could be a little more precise in how we target removing messages to send
            // and possibly avoid a few queries, but if a non-published post_type gets scheduled 
            // to be emailed that would be a pretty big problem.
            
            $this->wpdb->query($this->wpdb->prepare("DELETE from $this->message_queue_table_name where post_ID = %d and message_type = 'individual' and to_send is true", array($post_ID)));
            wp_unschedule_event('my_cat_sub_send_individual_messages',array($post->ID));
        }

    }

    public function send_daily_messages() {
        // So - Find all daily subscriptions. Uniquify based on the user_id, as it'd be
        // stupid to send out one email per subscription.
        // For each user we'll send out one message with all their subscriptions.
        // spawn a cron run to deliver the messages after creating them.

        $frequency = 'daily';
        $last_run = get_option('last_' . $frequency .'_message_run');
        
        //get users with daily subscriptions.

        $user_subscriptions = $this->wpdb->get_results("select user_ID,group_concat(category_ID) as category_IDs from $this->user_subscriptions_table_name where delivery_time_preference = %s group by user_ID",array($frequency));

        foreach($user_subscriptions as $usub){
            $cats = explode(',',$usub->category_IDs);
            // So get messages greater than $last_run in these categories that have a post_status of "publish".
            // It probably makes sense to pipe this through WP_Query to ensure rules get applied.
            // TODO



        }
        update_option('last_' . $frequency . '_message_run', );

    }

    public function send_weekly_messages() {
        if(date('w') == $this->send_weekly_email_on){
            // Tonight's the night!

        }
    }

    /*
     * Get the messages to send up to the max_batch size. Template them and deliver, rescheduling if there are still more
     * to deliver for this post.
     *
     * Invoked via wp-cron.
     *
     */
    public function send_individual_messages_for($post_ID){
        $post = get_post($post_ID);
    
        $to_send = $this->wpdb->get_results( $this->wpdb->prepare("SELECT * FROM $this->message_queue_table_name WHERE post_ID = %d AND message_type = 'individual' AND to_send = true LIMIT %d", array( $post_ID, $this->max_batch )));

        $tmpl = new CategorySubscriptionsTemplate($this);

        foreach($to_send as $message){
            // Get the user object and fill template variables based on the user's preference.
            // We need to fill the template variables dynamically for every string.
            $message_content = $tmpl->fill_individual_message($message);

            // Haw haw.
            $stand_and  = new CategorySubscriptionsMessage($message,$this,$message_content);
            $stand_and->deliver();

            // update table to ensure it's not sent again.
            $this->wpdb->update($this->message_queue_table_name, 
                array('subject' => $message_content['subject'], 'message' => $message_content['content'], 'to_send' => 0, 'message_sent' => 1),
                array('id' => $message->id),
                array('%s','%s','%d','%d'),
                array('%d')
            );
        }

        $message_count = $this->wpdb->get_var($this->wpdb->prepare("SELECT count(*) from $this->message_queue_table_name WHERE post_ID = %d AND message_type = 'individual' AND to_send = true", array($post_ID)));
        if($message_count > 0){
            // more messages to send. Reschedule.
            wp_schedule_single_event(time() + 60, 'my_cat_sub_send_individual_messages', array($post->ID));
        }



    }

    public function trash_messages($post_ID){
        // Remove messages for this post unless they have already been sent.

    }

    public function show_profile_fields( $user ) {
      wp_enqueue_style('admin.css');
      wp_enqueue_script('jquery.cookie.js');
      wp_enqueue_script('admin.js');

      echo '<h3>' . __('Email Updates') . '</h3>';
      echo '<p>' . __('Please select the types of updates you\'d like to receive') . '</p>';
      echo $this->category_list($user);
?><table class="form-table">
<tr>
<th><label for="cat_sub_delivery_format_pref_<?php echo $user->ID ?>"><?php _e('Email format preference'); ?></label></th>
<td><select name="cat_sub_delivery_format_pref_<?php echo $user->ID ?>" id="cat_sub_delivery_format_pref_<?php echo $user->ID ?>">
<option value="html" <?php echo (get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html') ? 'selected="selected"' : ''; ?>>HTML</option>
<option value="text" <?php echo (get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'text') ? 'selected="selected"' : ''; ?>>Text</option>
</select></td>
</tr>
</table>
<?php
    } 

    private function create_email_template_form_elements($type){ 
    // dynamically creating i18n is probably not going to work. . . 
?>
    <h4 class="cat_sub_toggler" id="<?php echo $type;?>_toggler"><?php _e(ucfirst($type) .' Emails'); ?><span><?php _e('expand. . .'); ?></span></h4>
    <table class="form-table toggler_target" id="<?php echo $type;?>_target">
    <tr>
    <th><label for="cat_sub_<?php echo $type;?>_email_subject"><?php _e('Subject line template for ' . $type .' emails'); ?></label></th>
    <td><input type="text" id="cat_sub_<?php echo $type;?>_email_subject" name="cat_sub_<?php echo $type;?>_email_subject" value="<?php echo esc_attr($this->{$type .'_email_subject'}); ?>" size="70" />
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
    </tr>
  </table>
<?php 
}

    private function category_list($user) {
    //TODO
    $categories = get_categories('hide_empty=0&orderby=name');
    $sql = $this->wpdb->prepare("SELECT category_ID, delivery_time_preference from $this->user_subscriptions_table_name where user_ID = %d", array($user->ID));
    $subscriptions = $this->wpdb->get_results($sql, OBJECT_K);

    //error_log(print_r($subscriptions,true));
    // TODO - Fix persistence below.
?>
        <table class="wp-list-table widefat fixed" style="width: 50%; margin-top: 1em;">
          <thead>
            <tr>
            <th><?php _e('Category'); ?></th>
            <th><?php _e('Frequency'); ?></th>
            </tr>
          </thead><tbody>
<?php foreach ($categories as $cat){ 
    $subscription_pref = isset($subscriptions[$cat->cat_ID]) ? $subscriptions[$cat->cat_ID] : NULL;
/*    if($subscription_pref) {
        error_log('Sub pref: ' . print_r($subscription_pref,true));
}
 */
?>
    <tr>
        <td>
            <input type="checkbox" name="category_subscription_categories[]" value="<?php echo esc_attr($cat->cat_ID); ?>" id="category_subscription_category_<?php echo $cat->cat_ID; ?>" <?php echo (( $subscription_pref ) ? 'checked="checked"' : '') ?> >
            <label for="category_subscription_category_<?php echo $cat->cat_ID; ?>"><?php echo htmlspecialchars($cat->cat_name); ?></label>
        </td>
        <td>
            <select name="delivery_time_preference_<?php echo $cat->cat_ID; ?>">
                <option value="individual"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'individual') ? ' selected="selected" ' : ''); ?>><?php _e('Immediately'); ?></option>
                <option value="daily"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'daily') ? ' selected="selected" ' : ''); ?>><?php _e('Daily'); ?></option>
                <option value="weekly"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'weekly') ? ' selected="selected" ' : ''); ?>><?php _e('Weekly'); ?></option>
            </select>
        </td>
    </tr>
<?php
    }
    echo '</tbody></table>';
}

    public function admin_menu (){
        wp_enqueue_style('admin.css');
        wp_enqueue_script('jquery.cookie.js');
        wp_enqueue_script('admin.js');
        add_submenu_page('options-general.php', __('Category Subscriptions Configuration'), __('Category Subscriptions'), 'manage_options', 'category-subscriptions-config', array($this,'config'));
    }

    public function config(){
        $updated = false;
        if ( isset($_POST['submit']) ) {
            if ( function_exists('current_user_can') && !current_user_can('manage_options') ){
                die(__('how about no?'));
            };
            // Save options

            $updated = true;
            foreach($this->editable_options as $opt){
                $this->{$opt} = stripslashes($_POST['cat_sub_' . $opt]);
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
    <th><label for="cat_sub_send_weekly_email_on"><?php _e('Send weekly digest emails on');  ?></label></th>
      <td>
        <select name="cat_sub_send_weekly_email_on">
        <option value="0" <?php echo (($this->send_weekly_email_on == 0) ? 'selected="selected"' : '') ?>><?php _e('Sunday'); ?></option>
        <option value="1" <?php echo (($this->send_weekly_email_on == 1) ? 'selected="selected"' : '') ?>><?php _e('Monday'); ?></option>
        <option value="2" <?php echo (($this->send_weekly_email_on == 2) ? 'selected="selected"' : '') ?>><?php _e('Tuesday'); ?></option>
        <option value="3" <?php echo (($this->send_weekly_email_on == 3) ? 'selected="selected"' : '') ?>><?php _e('Wednesday'); ?></option>
        <option value="4" <?php echo (($this->send_weekly_email_on == 4) ? 'selected="selected"' : '') ?>><?php _e('Thursday'); ?></option>
        <option value="5" <?php echo (($this->send_weekly_email_on == 5) ? 'selected="selected"' : '') ?>><?php _e('Friday'); ?></option>
        <option value="6" <?php echo (($this->send_weekly_email_on == 6) ? 'selected="selected"' : '') ?>><?php _e('Saturday'); ?></option>
        </select>
      </td>
    </tr>
    <tr>
    <th><label for="cat_sub_send_delay"><?php _e('Wait this long before sending out published posts');  ?></label></th>
      <td>
        <select name="cat_sub_send_delay">
        <option value="0" <?php echo (($this->send_delay == 0) ? 'selected="selected"' : '') ?>><?php _e("Don't wait, send ASAP"); ?></option>
        <option value="60" <?php echo (($this->send_delay == 60) ? 'selected="selected"' : '') ?>><?php _e('1 minute'); ?></option>
        <option value="120" <?php echo (($this->send_delay == 120) ? 'selected="selected"' : '') ?>><?php _e('2 minutes'); ?></option>
        <option value="300" <?php echo (($this->send_delay == 300) ? 'selected="selected"' : '') ?>><?php _e('5 minutes'); ?></option>
        <option value="600" <?php echo (($this->send_delay == 600) ? 'selected="selected"' : '') ?>><?php _e('10 minutes'); ?></option>
        <option value="3600" <?php echo (($this->send_delay == 3600) ? 'selected="selected"' : '') ?>><?php _e('1 hour'); ?></option>
        </select>
        <br/><span class="description"><?php _e('If you unpublish a post before this time limit is reached, the notices will not be sent out. This gives you a handy "undo" time period in case you notice an error right after publishing a post.'); ?></span>
      </td>
    </tr>
    <tr>
        <th><label for="cat_sub_from_address"><?php _e('From address for messages'); ?></label></th>
        <td><input type="text" id="cat_sub_from_address" name="cat_sub_from_address" value="<?php echo esc_attr($this->from_address); ?>" size="70" /><br/>
        <span class="description"><?php _e('Defaults to your "Admin Email" setting'); ?></span>
        </td>
    </tr>
        <th><label for="cat_sub_reply_to_address"><?php _e('Reply to address for messages'); ?></label></th>
        <td><input type="text" id="cat_sub_reply_to_address" name="cat_sub_reply_to_address" value="<?php echo esc_attr($this->reply_to_address); ?>" size="70" /><br/>
        <span class="description"><?php _e('Defaults to your "Admin Email" setting'); ?></span>
        </td>
    </tr>
    <tr>
        <th><label for="cat_sub_bcc_address"><?php _e('BCC all messages to'); ?></label></th>
        <td><input type="text" id="cat_sub_bcc_address" name="cat_sub_bcc_address" value="<?php echo esc_attr($this->bcc_address); ?>" size="70" /><br/>
        <span class="description"><?php _e('BCC all messages to this address - useful for debugging.'); ?></span>
        </td>
    </tr>
    <tr>
    </table>

    <h3><?php _e('Email Templates'); ?></h3>
    <ul>
        <li><?php _e('All email templates - daily, weekly, individual - share the same tags. So, for example, you can put [FIRST_NAME] in the subject line or body of any email template and it\'ll work the same.'); ?></li>
        <li><?php _e('An "email row template" defines the template used in digest emails to display the list of messages. Each individual message has this template applied to it, and is then put in the [EMAILLIST] template tag for your daily or weekly digest.'); ?></li>
        <li><?php _e('You should be sure both the HTML and plain text templates are kept up to date, as your subscribers have the ability to choose the format themselves.') ?></li>
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
