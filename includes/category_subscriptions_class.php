<?php
class CategorySubscriptions {
	var $user_subscriptions_table_name = '';
	var $category_subscription_version = '1.2';
	var $message_queue_table_name = '';
	var $wpdb = '';
	var $bulk_category_cache = NULL;

	var $max_batch = 50;
	var $send_delay = 120;
	var $category_separator = ' > ';

	// 0 == Sunday, 6 == Saturday
	var $send_weekly_email_on = 0;

	var $from_address = '';
	var $from_name = '';
	var $reply_to_address = '';
	var $bcc_address = '';

	var $daily_email_subject = '';
	var $daily_email_html_template = '';
	var $daily_email_text_template = '';

	var $weekly_email_subject = '';
	var $weekly_email_html_template = '';
	var $weekly_email_text_template = '';

	var $individual_email_subject = '';
	var $individual_email_html_template = '';
	var $individual_email_text_template = '';

	var $user_profile_custom_message = 'Please select the types of updates you\'d like to receive';

	var $header_row_html_template = '';
	var $header_row_text_template = '';

	var $email_row_html_template = '';
	var $email_row_text_template = '';

	var $email_toc_html_template = '';
	var $email_toc_text_template = '';

	var $editable_options = array(
		'max_batch',
		'send_delay',
		'category_separator',
		'send_weekly_email_on',
		'from_address',
		'from_name',
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

		'user_profile_custom_message',

		'email_row_text_template',
		'email_row_html_template',

		'email_toc_text_template',
		'email_toc_html_template',

		'header_row_html_template',
		'header_row_text_template'
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
			$this->init_db_structure();
			update_option("category_subscription_version", $this->category_subscription_version);
		}

		$this->initialize_templates();

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

	// for using in a callback. 
	public function from_name(){
		return $this->from_name;
	}

	public function init_db_structure(){
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
			digest_message_for date NOT NULL DEFAULT '0000-00-00',
			user_ID bigint(20) UNSIGNED,
			post_ID bigint(20) UNSIGNED,
			subject varchar(250),
			message varchar(100000),
			to_send boolean DEFAULT TRUE,
			message_sent boolean DEFAULT FALSE,
			delivered_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			UNIQUE KEY id (id),
			KEY user_ID (user_ID),
			KEY post_ID (post_ID),
			KEY to_send (to_send),
			KEY digest_message_for (digest_message_for),
			KEY delivered_at (delivered_at)
			) DEFAULT CHARSET=utf8";
		dbDelta($sql);
	}

	public function category_subscriptions_install(){
		$this->init_db_structure();

		update_option("category_subscription_version", $this->category_subscription_version);
		// Schedule the first daily message check for 2 hours out.
		wp_schedule_event(time() + 7200, 'daily', 'my_cat_sub_prepare_daily_messages');
		wp_schedule_event(time() + 7200, 'daily', 'my_cat_sub_prepare_weekly_messages');

		// Ensure we're not grabbing all messages from the beginning of time by setting the last_run time to now.

		$install_time = date('Y-m-d H:i:s');
		update_option('cat_sub_last_daily_message_run', $install_time);
		update_option('cat_sub_last_weekly_message_run', $install_time);
		update_option('cat_sub_install_unixtime', time());
	}

	public function category_subscriptions_deactivate(){
		wp_clear_scheduled_hook('my_cat_sub_prepare_daily_messages');
		wp_clear_scheduled_hook('my_cat_sub_prepare_weekly_messages');
	}

	public function add_cat_sub_custom_column($columns){
		$columns['cat_sub_subscriptions'] = __('Subscriptions');
		return $columns;
	}

	public function manage_users_custom_column($empty = '', $column_name, $user_id){
		if( $column_name == 'cat_sub_subscriptions' ) {
			wp_enqueue_style('admin.css');
			wp_enqueue_script('admin.js');
			$user = get_userdata($user_id);
			return $this->bulk_category_list($user);
		} 
		// allows other plugins to hook into manage_users_custom_column
		return $empty;
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

		$install_time = get_option('cat_sub_install_unixtime');
		if(strtotime($post->post_date_gmt) <= $install_time){
			# Don't do anything with posts that existed before this plugin was installed.
			return;
		}

		$categories = wp_get_post_categories($post->ID);

		$category_conditions = array_map(create_function('$a','return "category_ID = %d";'),$categories);
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

		$install_time = get_option('cat_sub_install_unixtime');
		if(strtotime($post->post_date_gmt) <= $install_time){
			# Don't do anything with posts that existed before this plugin was installed.
			# This should fix the "recategorize post, get messages sent again" issue. Perhaps.
			return;
		}

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

	public function prepare_daily_messages(){
		$this->prepare_digested_messages('daily');
	}

	public function prepare_weekly_messages() {
		if(date('w') == $this->send_weekly_email_on){
			// Tonight's the night!
			$this->prepare_digested_messages('weekly');
		}
	}

	public function send_digested_messages($frequency = 'daily', $nothing_to_see_here = 0){
		// This function picks up and sends the messages that've been prepared by the "prepare_TIMEPERIOD_messages" functions.
		$to_send = $this->wpdb->get_results( $this->wpdb->prepare("SELECT * FROM $this->message_queue_table_name WHERE message_type = %s AND to_send = true LIMIT %d", array( $frequency, $this->max_batch )));

		$delivered_at = date('Y-m-d H:i:s');

		foreach($to_send as $msg){
			$this->wpdb->update($this->message_queue_table_name, 
				array('to_send' => 0, 'message_sent' => 1, 'delivered_at' => $delivered_at),
				array('id' => $msg->id),
				array('%d','%d', '%s'),
				array('%d')
			);
			// Do the update before sending the message just to ensure we don't get stuck messages
			// if the sending errors out.
			$user = get_userdata($msg->user_ID);

			// passing by reference.
			$message_content = array('subject' => $msg->subject, 'content' => $msg->message);
			$sender = new CategorySubscriptionsMessage($user,$this,$message_content);
			$sender->deliver();
		}

		$message_count = $this->wpdb->get_var($this->wpdb->prepare("SELECT count(*) from $this->message_queue_table_name WHERE message_type = %s AND to_send = true", array($frequency)));

		if($message_count > 0){
			// more messages to send. Reschedule.
			wp_schedule_single_event(time() + 60, 'my_cat_sub_send_digested_messages', array($frequency,rand()));
		}
	}

	public function cat_sub_filter_where_daily( $where = '' ) {
		$last_run = get_option('cat_sub_last_daily_message_run');
		$where .= " AND post_date_gmt >= '$last_run'";
		return $where;
	}

	public function cat_sub_filter_where_weekly( $where = '' ) {
		$last_run = get_option('cat_sub_last_weekly_message_run');
		$where .= " AND post_date_gmt >= '$last_run'";
		return $where;
	}

	public function prepare_digested_messages($frequency = 'daily') {
		// So - Find all daily subscriptions. Uniquify based on the user_id, as it'd be
		// stupid to send out one email per subscription.
		// For each user we'll send out one message with all their subscriptions.
		// spawn a cron run to deliver the messages after creating them.
		// Rather than lazily preparing messages like we do for individual messages,
		// we prepare them all at once for each user so as to capture a point in time more accurately.

		$user_subscriptions = $this->wpdb->get_results($this->wpdb->prepare("select user_ID,group_concat(category_ID) as category_IDs from $this->user_subscriptions_table_name where delivery_time_preference = %s group by user_ID",array($frequency)));

		$tmpl = new CategorySubscriptionsTemplate($this);

		if($frequency == 'daily'){
			add_filter( 'posts_where', array($this, 'cat_sub_filter_where_daily') );
		} else {
			add_filter( 'posts_where', array($this, 'cat_sub_filter_where_weekly') );
		}

		// $digest_message_for is a stopgap to try and ensure we don't send duplicates.
		// This could be tricked if the last_run option doesn't get updated because of a fatal error (maybe a memory limit)
		// and this run happens over a date change.

		$digest_message_for = date('Y-m-d');

		$already_getting = $this->wpdb->get_results($this->wpdb->prepare("select user_ID from $this->message_queue_table_name where message_type = %s and digest_message_for = %s",array($frequency,$digest_message_for)), OBJECT_K);

		// error_log('Already getting: ' . print_r($already_getting,true));
		// error_log('User Subscriptions: ' . print_r($user_subscriptions,true));

		foreach($user_subscriptions as $usub){
			if(! isset($already_getting[$usub->user_ID])){
				// So get published messages greater than $last_run in these categories that have a post_status of "publish".
				$query = new WP_Query(
					array(
						'cat' => $usub->category_IDs,
						'post_type' => 'post',
						'post_status' => 'publish',
						'posts_per_page' => -1
					)
				);

				if(count($query->posts) > 0){
					// There are messages to send for this user.
					$user = get_userdata($usub->user_ID);
					$digested_message = $tmpl->fill_digested_message($user, $query->posts, $frequency);

					$this->wpdb->insert($this->message_queue_table_name, 
						array('user_ID' => $usub->user_ID, 'message_type' => $frequency, 'subject' => $digested_message['subject'], 'message' => $digested_message['content'], 'digest_message_for' => $digest_message_for), 
						array('%d','%s','%s','%s', '%s')
					);
					wp_schedule_single_event(time() + 60, 'my_cat_sub_send_digested_messages', array($frequency,rand()));
				}
				wp_reset_postdata();
			}
		}

		if($frequency == 'daily'){
			remove_filter( 'posts_where', array($this, 'cat_sub_filter_where_daily') );
		} else {
			remove_filter( 'posts_where', array($this, 'cat_sub_filter_where_weekly') );
		}

		$this_run_time = date('Y-m-d H:i:s');
		update_option('cat_sub_last_' . $frequency . '_message_run', $this_run_time);
	}

	/*
	 * Get the individual messages to send up to the max_batch size. Template them and deliver, rescheduling if there are still more
	 * to deliver for this post.
	 *
	 * Invoked via wp-cron.
	 *
	 */
	public function send_individual_messages_for($post_ID){
		// So we're "lazy" here, in that we only prepare the messages as we're sending them. We can do this because it's 
		// a single message and a bit less dependent on capturing a point in time - it's only this message to consider.
		// So by deferring message preparation we perhaps spread out CPU time a bit.

		$post = get_post($post_ID);

		$to_send = $this->wpdb->get_results( $this->wpdb->prepare("SELECT * FROM $this->message_queue_table_name WHERE post_ID = %d AND message_type = 'individual' AND to_send = true LIMIT %d", array( $post_ID, $this->max_batch )));

		$tmpl = new CategorySubscriptionsTemplate($this);

		foreach($to_send as $message){
			$user = get_userdata($message->user_ID);
			// Get the user object and fill template variables based on the user's preference.
			// We need to fill the template variables dynamically for every string.

			$message_content = $tmpl->fill_individual_message($user, $post);

			$delivered_at = date('Y-m-d H:i:s');
			// update table to ensure it's not sent again. Do this before sending - so yeah, we only make one attempt.
			// Ideally, email is smarthosted and retries and whatnot happen properly
			$this->wpdb->update($this->message_queue_table_name, 
				array('subject' => $message_content['subject'], 'message' => $message_content['content'], 'to_send' => 0, 'message_sent' => 1, 'delivered_at' => $delivered_at),
				array('id' => $message->id),
				array('%s','%s','%d','%d', '%s'),
				array('%d')
			);
			$sender = new CategorySubscriptionsMessage($user,$this,$message_content);
			$sender->deliver();

		}

		$message_count = $this->wpdb->get_var($this->wpdb->prepare("SELECT count(*) from $this->message_queue_table_name WHERE post_ID = %d AND message_type = 'individual' AND to_send = true", array($post_ID)));
		if($message_count > 0){
			// more messages to send. Reschedule.
			wp_schedule_single_event(time() + 60, 'my_cat_sub_send_individual_messages', array($post->ID));
		}
	}

	public function trash_messages($post_ID){
		// Remove messages for this post unless they have already been sent.
		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->message_queue_table_name WHERE message_sent is false and post_ID = %d", array($post_ID) ) );

	}

	public function show_profile_fields( $user ) {
		wp_enqueue_style('admin.css');
		wp_enqueue_script('jquery.cookie.js');
		wp_enqueue_script('admin.js');

		echo '<h3>' . __('Email Updates') . '</h3>';
		echo '<p>' . $this->user_profile_custom_message . '</p>';
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

/*
	// Doesn't work - you can only remove items from the bulk actions menu.
	// http://core.trac.wordpress.org/ticket/16031

		public function custom_bulk_action($actions){
			error_log(print_r($actions,true));
			$actions['catsub'] = __('Update Subscriptions');
			error_log(print_r($actions,true));
			return $actions;
		}
 */

	public function update_bulk_edit_changes(){
		// error_log('Bulk update');

		// Doing this to save precious characters in the GET request.
		$value_map = array('i' => 'individual', 'd' => 'daily', 'w' => 'weekly','h' => 'html', 't' => 'text');

		$user_ids = isset($_GET['csi']) ? $_GET['csi'] : array();
		foreach($user_ids as $user_ID){
			update_user_meta($user_ID,'cat_sub_delivery_format_pref', $value_map[stripslashes($_GET['csdf' . $user_ID])]);
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->user_subscriptions_table_name WHERE user_ID = %d", array($user_ID) ) );
			$user_cat_ids = isset($_GET['cscs' . $user_ID]) ? $_GET['cscs' . $user_ID] : array();
			foreach($user_cat_ids as $cat_ID){
				$delivery_time_preference = $value_map[$_GET['csdt' . $user_ID . '_' . $cat_ID]];
				//error_log('User Id: ' . $user_ID . ' Category ID: ' . $cat_ID . ' DTP: ' . $delivery_time_preference);
				$this->wpdb->insert($this->user_subscriptions_table_name, array('category_ID' => $cat_ID, 'user_ID' => $user_ID, 'delivery_time_preference' => stripslashes($delivery_time_preference)), array('%d','%d','%s') );
			}
		}
	}

	private function set_bulk_category_cache(&$categories){
		$this->bulk_category_cache = $categories;
	}

	private function bulk_category_list($user) {
		$categories = NULL;

		// implement our own internal object-level cache. Probably stupid, but this would be ridiculously slow
		// on sites without an object cache in place.
	
		if(! $this->bulk_category_cache){
			$categories = get_categories(array('hierarchical' => 1, 'hide_empty' => 0));
			$cats_by_parent = array();
			$this->cats_by_parent($cats_by_parent, $categories);
			$cat_tree = array();
			$this->collect_cats($cat_tree, $cats_by_parent[0], $cats_by_parent);
			$this->set_bulk_category_cache($cat_tree);
			$categories = $this->bulk_category_cache;
		} else {
			$categories = $this->bulk_category_cache;
		}

		$sql = $this->wpdb->prepare("SELECT category_ID, delivery_time_preference from $this->user_subscriptions_table_name where user_ID = %d", array($user->ID));
		$subscriptions = $this->wpdb->get_results($sql, OBJECT_K);
		$output = '<input type="hidden" name="csi[]" value="' . $user->ID . '" />';

		$output .= "<div class='cat_sub_bulk_edit_wrapper'>";

			$output .= "<a class='button-secondary cat_sub_bulk_edit_open' href='#'>Edit Subscriptions</a>";
			$output .= "<div class='cat_sub_bulk_edit_center'>";
				$output .= "<div class='cat_sub_bulk_edit'>";

					$output .= "<div class='cat_sub_bulk_edit_content'>";

						foreach ($categories as $cat){
							$this->bulk_user_profile_cat_row($cat,$subscriptions,$user,$output);
						}

					$output .= "</div>";
					$output .= "<div class='cat_sub_bulk_edit_footer'>";

						$output .= __('Format: ') . "<select name='csdf" . $user->ID . "' id='csdf" . $user->ID . "'>";
						$output .= "<option value='h' " . ((get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html') ? 'selected="selected"' : '') . ">HTML</option>";
						$output .= "<option value='t' " . ((get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'text') ? 'selected="selected"' : '') . ">Text</option>";
						$output .= '</select>';

						$output .= "<a class='cat_sub_bulk_edit_close' href='#'>[Close]</a>";

					$output .= "</div>";

				$output .= "</div>";

			$output .= "</div>";

		$output .= "</div>";

		return $output;
	}

	private function bulk_user_profile_cat_row(&$cat,&$subscriptions,&$user,&$output){
			$subscription_pref = isset($subscriptions[$cat->cat_ID]) ? $subscriptions[$cat->cat_ID] : NULL;
			$output .= "<input type='checkbox' name='cscs" . $user->ID . "[]' value='" . esc_attr($cat->cat_ID). "' id='cs_c_" . $user->ID. "_".$cat->cat_ID."'" . (( $subscription_pref ) ? "checked='checked'" : ''). " > ";
			$output .= "<label for='cs_c_" . $user->ID ."_".$cat->cat_ID ."'>". (($cat->depth > 1) ? str_repeat('&#8212;&nbsp;',$cat->depth - 1) : '') . htmlspecialchars($cat->cat_name) . "</label>";
			$output .= "<select name='csdt" . $user->ID . "_" . $cat->cat_ID ."'>";
			$output .= "<option value='i'" . (($subscription_pref && $subscription_pref->delivery_time_preference == 'individual') ? ' selected=\'selected\' ' : '') . ">" . __('Immediately') . "</option>";
			$output .= "<option value='d'" . (($subscription_pref && $subscription_pref->delivery_time_preference == 'daily') ? ' selected=\'selected\' ' : '') . ">" . __('Daily') . "</option>";
			$output .= "<option value='w'" . (($subscription_pref && $subscription_pref->delivery_time_preference == 'weekly') ? ' selected=\'selected\' ' : '') . ">" . __('Weekly') . "</option>";
			$output .="</select><br/>";
			if(count($cat->children) > 0){
				foreach($cat->children as $cat_child){
					$this->bulk_user_profile_cat_row($cat_child,$subscriptions,$user,$output);
				}
			}

	}

	private function collect_cats(&$cats, &$children, &$cats_by_parent){
		foreach ($children as $child_cat){
			$child_id = $child_cat->cat_ID;
			if (array_key_exists($child_id, $cats_by_parent)){
				$child_cat->children = array();
				$this->collect_cats($child_cat->children, $cats_by_parent[$child_id], $cats_by_parent);
			}
			$cats[$child_id] = $child_cat;
		}
	}

	private function cats_by_parent(&$cats_by_parent,&$categories){
		foreach ($categories as $cat){
			$parent_id = $cat->category_parent;
			// Seems a hacky but effective way to calculate depth using wordpress builtins. The separator is chosen to be a string
			// that's highly unlikely to appear in a category name.
			$parent_list = get_category_parents($cat->term_id,FALSE,'|-sdfkl342934824dkfjdf|');
			$cat->depth = substr_count($parent_list,'|-sdfkl342934824dkfjdf|');
			if (!array_key_exists($parent_id, $cats_by_parent)){
				$cats_by_parent[$parent_id] = array();
			}
			$cats_by_parent[$parent_id][] = $cat;
		}
	}

	private function category_list($user) {
		$categories = get_categories(array('hierarchical' => 1, 'hide_empty' => 0));

		$cats_by_parent = array();
		$this->cats_by_parent($cats_by_parent, $categories);

		$cat_tree = array();
		$this->collect_cats($cat_tree, $cats_by_parent[0], $cats_by_parent);

		$sql = $this->wpdb->prepare("SELECT category_ID, delivery_time_preference from $this->user_subscriptions_table_name where user_ID = %d", array($user->ID));
		$subscriptions = $this->wpdb->get_results($sql, OBJECT_K);

?>
				<table class="wp-list-table widefat fixed cat_sub_user_cat_list" style="margin-top: 1em; width: 500px;">
					<thead>
						<tr>
						<th><?php _e('Category'); ?></th>
						</tr>
					</thead><tbody>
<?php 
		foreach ($cat_tree as $cat){
			echo '<tr><td>';
			$this->user_profile_cat_row($cat,$subscriptions);
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function user_profile_cat_row(&$cat,&$subscriptions){
		$subscription_pref = isset($subscriptions[$cat->cat_ID]) ? $subscriptions[$cat->cat_ID] : NULL;
?>

            <?php echo ($cat->depth > 1) ? str_repeat('&#8212;&nbsp;',$cat->depth - 1) : '' ?><input type="checkbox" class="depth-<?php echo $cat->depth; ?>" name="category_subscription_categories[]" value="<?php echo esc_attr($cat->cat_ID); ?>" id="category_subscription_category_<?php echo $cat->cat_ID; ?>" <?php echo (( $subscription_pref ) ? 'checked="checked"' : '') ?> >
						<label for="category_subscription_category_<?php echo $cat->cat_ID; ?>"><?php echo htmlspecialchars($cat->cat_name); ?></label>
						<select name="delivery_time_preference_<?php echo $cat->cat_ID; ?>" style="float: right;">
								<option value="individual"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'individual') ? ' selected="selected" ' : ''); ?>><?php _e('Immediately'); ?></option>
								<option value="daily"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'daily') ? ' selected="selected" ' : ''); ?>><?php _e('Daily'); ?></option>
								<option value="weekly"<?php echo (($subscription_pref && $subscription_pref->delivery_time_preference == 'weekly') ? ' selected="selected" ' : ''); ?>><?php _e('Weekly'); ?></option>
						</select><div style="clear:both;"></div>

<?php
		if(isset($cat->children)){
			foreach($cat->children as $cat_child){
				$this->user_profile_cat_row($cat_child,$subscriptions);
			}
		}
	}

	public function clean_up_removed_user($user_ID, $blog_id = 0){
		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM $this->user_subscriptions_table_name WHERE user_ID = %d", array($user_ID) ) );
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
				<br/><span class="description"><?php _e('If you unpublish a post before this time limit is reached, the notices will not be sent out. This gives you a handy "undo" time period in case you notice an error right after publishing a post. Only valid for messages sent immediately.'); ?></span>
			</td>
		</tr>
		<tr>
			<th><label for="cat_sub_category_separator"><?php _e('Category Separator'); ?></label></th>
			<td><input type="text" id="cat_sub_category_separator" name="cat_sub_category_separator" value="<?php echo esc_attr($this->category_separator); ?>" size="70" /><br/>
				<span class="description"><?php _e('This is separator used in email templates to separate the hierarchical list of categories. If you use " > " (note the spaces!), then a hierarchical category will be shown like: "Food > Fruit > Tree > Apple".'); ?></span>
			</td>
		</tr>
		<tr>
			<th><label for="cat_sub_from_address"><?php _e('From address'); ?></label></th>
			<td><input type="text" id="cat_sub_from_address" name="cat_sub_from_address" value="<?php echo esc_attr($this->from_address); ?>" size="70" /><br/>
				<span class="description"><?php _e('Defaults to your "Admin Email" setting, sets the "from" address used on outgoing messages.'); ?></span>
			</td>
		</tr>
		<tr>
			<th><label for="cat_sub_from_name"><?php _e('From name'); ?></label></th>
			<td><input type="text" id="cat_sub_from_name" name="cat_sub_from_name" value="<?php echo esc_attr($this->from_name); ?>" size="70" /><br/>
				<span class="description"><?php _e('The "from" name for these messages. Defaults to the title of your blog.'); ?></span>
			</td>
		</tr>
		<tr>
				<th><label for="cat_sub_reply_to_address"><?php _e('Reply to address'); ?></label></th>
				<td><input type="text" id="cat_sub_reply_to_address" name="cat_sub_reply_to_address" value="<?php echo esc_attr($this->reply_to_address); ?>" size="70" /><br/>
				<span class="description"><?php _e('Defaults to your "Admin Email" setting, sets the "reply-to" address used on outgoing messages.'); ?></span>
				</td>
		</tr>
		<tr>
				<th><label for="cat_sub_bcc_address"><?php _e('BCC all messages to'); ?></label></th>
				<td><input type="text" id="cat_sub_bcc_address" name="cat_sub_bcc_address" value="<?php echo esc_attr($this->bcc_address); ?>" size="70" /><br/>
				<span class="description"><?php _e('BCC all messages to this address - useful for debugging.'); ?></span>
				</td>
		</tr>
		<tr>
				<th><label for="cat_sub_user_profile_custom_message"><?php _e('Custom message for user profile page'); ?></label></th>
				<td><textarea rows="5" cols="70" name="cat_sub_user_profile_custom_message"><?php echo esc_textarea($this->user_profile_custom_message); ?></textarea><br/>
				<span class="description"><?php _e("This message will appear above the list of categories on a user's profile page."); ?></span>
				</td>
		</tr>
		</table>

		<h3><?php _e('Email Templates'); ?></h3>

		<h4 class="cat_sub_toggler" id="documentation_toggler"><?php _e('Template Tag Documentation'); ?><span><?php _e('expand. . .'); ?></span></h4>
		<div id="documentation_target" class="toggler_target">
				<p><?php _e('You should be sure both the HTML and plain text templates are kept up to date, as your subscribers have the ability to choose the format themselves.') ?></p>
			<p><?php _e('Template tags have three contexts: global, post, and digested messages.  Template tags are case sensitive and must be enclosed in square brackets.'); ?></p>
			<h3><?php _e('Global Template Tags'); ?></h3>
			<div class="doc_container">
					<p><?php _e('Global template tags are applied everywhere - to the email row templates, to the individual email templates, etc. Below is a list of the global template tags.'); ?></p>
					<dl>
						<dt>[PROFILE_URL]</dt>
						<dd><?php _e('A link to the recipient\'s profile url, allowing them to manage their subscriptions.'); ?></dd>

						<dt>[SITE_TITLE]</dt>
						<dd><?php _e('The site title as configured in "Settings -> General"'); ?></dd>

						<dt>[DESCRIPTION]</dt>
						<dd><?php _e('The site description (AKA "tagline") as configured in "Settings -> General"'); ?></dd>

						<dt>[SITE_URL]</dt>
						<dd><?php _e('The URL to this blog.'); ?></dd>

						<dt>[ADMIN_EMAIL]</dt>
						<dd><?php _e('The administrator email address, as configured in "Settings -> General"'); ?></dd>

						<dt>[DATE], [TIME]</dt>
						<dd><?php _e('The current date /  formatted according to the settings in "Settings -> General"'); ?></dd>

						<dt>[STYLESHEET_DIRECTORY]</dt>
						<dd><?php _e('The stylesheet directory of the currently active theme. Useful if you want to load remote resources into your email.'); ?></dd>

						<dt>[USER_LOGIN], [USER_NICENAME], [USER_EMAIL], [DISPLAY_NAME], [USER_FIRSTNAME], [USER_LASTNAME], [NICKNAME]</dt>
						<dd><?php _e('These template variables contain the profile information for the user a message is being sent to.'); ?></dd>

					</dl>
				</div>

				<h3><?php _e('Post Template Tags'); ?></h3>
				<div class="doc_container">
						<p><?php _e('Post template tags are applied to individual email and email row templates.'); ?></p>
						<dl>
								<dt>[POST_AUTHOR], [POST_DATE], [POST_CONTENT], [POST_TITLE], [GUID], [POST_EXCERPT]</dt>
								<dd><?php _e('These variables are pulled directly from the post information. [POST_AUTHOR] is the author\'s id.'); ?></dd>

								<dt>[CATEGORIES], [CATEGORIES_WITH_URLS], [TAGS], [TAGS_WITH_URLS]</dd>
								<dd><?php _e('The list of categories or tags applied to this post, joined with a comma.') ?></dd>

								<dt>[EXCERPT]</dt>
								<dd><?php _e("This will output the manually created excerpt if it exists or auto-create one for you if it doesn't. [POST_EXCERPT] only outputs the manually created excerpt. [EXCERPT] is probably more useful generally."); ?></dd>

								<dt>[AUTHOR_LOGIN], [AUTHOR_NICKNAME], [AUTHOR], [AUTHOR_URL], [AUTHOR_FIRST_NAME], [AUTHOR_LAST_NAME]</dt>
								<dd><?php _e('The author information for this post, primarily pulled from the author\'s profile information. You probably want simply [AUTHOR_LOGIN] or [AUTHOR].'); ?></dd>

								<dt>[FORMATTED_POST_DATE], [FORMATTED_POST_TIME]</dt>
								<dd><?php _e('The date / time of this post after being formatted by the settings in "Settings -> General."'); ?></dd>

								<dt>[EMAIL_SUBJECT]</dt>
								<dd><?php _e('The custom field \'email_subject\' as defined when you wrote the post.'); ?></dd>
						</dl>
				</div>

				<h3><?php _e('Digested Message Template Tags'); ?></h3>
				<div class="doc_container">
						<p><?php _e('These tags are available only to daily or weekly digested messages.') ?></p>
						<dl>
								<dt>[EMAIL_LIST]</dt>
								<dd><?php _e('The list of messages, sorted by post date. These messages have the "email row" templates applied to them.'); ?></dd>

								<dt>[CATEGORY_GROUPED_EMAIL_LIST]</dt>
								<dd><?php _e('The list of messages, grouped by category and then sorted by post date. These messages have the "email row" templates applied to them.  The category header will be formatted according to the "category row template" for the appropriate email format (text or html).'); ?></dd>

								<dt>[TOC]</dt>
								<dd><?php _e('The list of messages in an [EMAIL_LIST]. [TOC] used to create the Table of Contents, sorted by post date. These messages have the "email toc" templates applied to them. The [TOC] will match the messages in the [EMAIL_LIST], but not the [CATEGORY_GROUPED_EMAIL_LIST].'); ?></dd>

								<dt>[EMAIL_SUBJECTS]</dt>
								<dd><?php _e('A comma seperated list of the custom field \'email_subject\' (as defined when you wrote the posts).'); ?></dd>

						</dl>
				</div>

		</div>

		<?php $this->create_email_template_form_elements('individual') ?>
		<?php $this->create_email_template_form_elements('daily') ?>
		<?php $this->create_email_template_form_elements('weekly') ?>

		<h4 class="cat_sub_toggler" id="email_row_toggler"><?php _e('Email Rows and TOC Entries'); ?><span><?php _e('expand. . .'); ?></span></h4>
		<table class="form-table toggler_target" id="email_target">
				<tr>
						<th><label for="cat_sub_header_row_html_template"><?php _e('HTML category header row template'); ?></label>
						</th>
						<td><textarea rows="10" cols="70" name="cat_sub_header_row_html_template"><?php echo esc_textarea($this->header_row_html_template); ?></textarea></td>
				</tr>
				<tr>
						<th><label for="cat_sub_header_row_text_template"><?php _e('Text category header row template'); ?></label>
						</th>
						<td><textarea rows="10" cols="70" name="cat_sub_header_row_text_template"><?php echo esc_textarea($this->header_row_text_template); ?></textarea></td>
				</tr>
				<tr>
						<th><label for="cat_sub_email_row_html_template"><?php _e('HTML email row template'); ?></label>
						</th>
						<td><textarea rows="10" cols="70" name="cat_sub_email_row_html_template"><?php echo esc_textarea($this->email_row_html_template); ?></textarea></td>
				</tr>
				<tr>
						<th><label for="cat_sub_email_toc_html_template"><?php _e('HTML email TOC entry'); ?></label>
						</th>
						<td><textarea rows="5" cols="70" name="cat_sub_email_toc_html_template"><?php echo esc_textarea($this->email_toc_html_template); ?></textarea></td>
				</tr>
				<tr>
						<th><label for="cat_sub_email_row_text_template"><?php _e('Text email row template'); ?></label>
						</th>
						<td><textarea rows="10" cols="70" name="cat_sub_email_row_text_template"><?php echo esc_textarea($this->email_row_text_template); ?></textarea></td>
				</tr>
				<tr>
						<th><label for="cat_sub_email_toc_text_template"><?php _e('Text email TOC entry'); ?></label>
						</th>
						<td><textarea rows="5" cols="70" name="cat_sub_email_toc_text_template"><?php echo esc_textarea($this->email_toc_text_template); ?></textarea><br />
						<span class="description"><?php _e('Be sure to leave a blank line after so the entries in the plain text TOC don\'t run together.'); ?></span>
						</td>
				</tr>
		</table>



	<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Update Options'); ?>"  /></p> 
	</form>
</div> 
<?php

	}

    function initialize_templates(){
    // Set default templates.
    $this->daily_email_subject = 'Daily Digest for [SITE_TITLE], [DATE]';
    $this->daily_email_html_template = '<h3>A daily email summary for your subscriptions at "<a href="[SITE_URL]">[SITE_TITLE]</a>"</h3>

<p><strong>Table of contents</strong></p>
<ol>
[TOC]
</ol>

<div>
[EMAIL_LIST]
</div>

<hr />
<p>&copy; 2011 [SITE_TITLE]<br/>
You can manage your subscriptions <a href="[PROFILE_URL]">here</a>.</p>
';

    $this->daily_email_text_template = 'A daily email summary for your subscriptions at "[SITE_TITLE]"

__________________________________________

[TOC]
__________________________________________

[EMAIL_LIST]

(c) 2011 [SITE_TITLE]

You can manage your subscriptions at the link below:
[PROFILE_URL]
';

    $this->weekly_email_subject = 'Weekly Digest for [SITE_TITLE], Week ending [DATE]';
    $this->weekly_email_html_template = '<h3>A weekly email summary for your subscriptions at "<a href="[SITE_URL]">[SITE_TITLE]</a>"</h3>

<p><strong>Table of contents</strong></p>
<ol>
[TOC]
</ol>

<div>
[EMAIL_LIST]
</div>

<hr />
<p>
&copy; 2011 [SITE_TITLE]<br/>
You can manage your subscriptions <a href="[PROFILE_URL]">here</a>.</p>';

$this->weekly_email_text_template = 'A weekly email summary for your subscriptions at "[SITE_TITLE]"

Table of Contents:
__________________________________________

[TOC]
__________________________________________

[EMAIL_LIST]

(c) 2011 [SITE_TITLE]
You can manage your subscriptions at the link below:
[PROFILE_URL]';

    $this->individual_email_subject = 'A new post at [SITE_TITLE] - [POST_TITLE]';

    $this->individual_email_html_template = '<p>Dear [USER_LOGIN],</p>
        <p>A new post has been added to one of your subscriptions at <a href="[SITE_URL]">[SITE_TITLE]</a>.</p>
        <hr />
        <h2><a href="[GUID]">[POST_TITLE]</a></h2>
        <h3>by [AUTHOR] on [FORMATTED_POST_DATE] in [CATEGORIES_WITH_URLS]</h3>
        <blockquote>[EXCERPT]</blockquote>

        <hr />
        <p>
				&copy; 2011 [SITE_TITLE]<br/>
				You can manage your subscriptions <a href="[PROFILE_URL]">here</a>.</p>';

    $this->individual_email_text_template = 'Dear [USER_LOGIN],

A new post has been added to one of your subscriptions at [SITE_TITLE].

______________________________________
[POST_TITLE]
[GUID]

by [AUTHOR] on [FORMATTED_POST_DATE] at [FORMATTED_POST_TIME] in [CATEGORIES]

[EXCERPT]

______________________________________

(c) 2011 [SITE_TITLE]

You can manage your subscriptions at the link below:
[PROFILE_URL]
';


		$this->header_row_html_template = '<h1><a href="[CATEGORY_URL]">[CATEGORY_NAME]</a></h1>';
		$this->header_row_text_template = '[CATEGORY_NAME]
';
    $this->email_row_html_template = '<h2><a href="[GUID]">[POST_TITLE]</a><a name="[POST_ID]"></a></h2>
<p><strong>by</strong> [AUTHOR] on [FORMATTED_POST_DATE] at [FORMATTED_POST_TIME] <strong>in</strong> [CATEGORIES_WITH_URLS]</p>
<div>
[EXCERPT]
</div>
<hr />';

    $this->email_row_text_template = '[POST_TITLE]
[GUID]

by [AUTHOR] on [FORMATTED_POST_DATE] at [FORMATTED_POST_TIME] in [CATEGORIES]

[EXCERPT]
_____________________________________________
';

$this->email_toc_html_template = '<li><a href="#[POST_ID]"><strong>[POST_TITLE]</strong></a></li>';
$this->email_toc_text_template = '* [POST_TITLE]
';

    }


} // CategorySubscriptions class
