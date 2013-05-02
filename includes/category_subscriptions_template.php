<?php
class CategorySubscriptionsTemplate {
  var $global_callback_variables = array();

  var $global_callback_values = array();

  var $user_template_variables = array();
  var $user_template_values = array();

  var $post_template_variables = array();
  var $post_template_values = array();
  var $cat_sub = '';

  public function __construct(&$cat_sub){
    $this->cat_sub = $cat_sub;
    // Instantiate variables that will be available everywhere.

    $global_variable_callbacks = array(
      'PROFILE_URL' => create_function( '', 'return admin_url("profile.php");' ),
      'SITE_TITLE' => create_function( '', 'return get_bloginfo("name");' ),
      'DESCRIPTION' => create_function( '', 'return get_bloginfo("description");'),
      'SITE_URL' => create_function( '', 'return get_bloginfo("url");' ),
      'ADMIN_EMAIL' => create_function('', 'return get_bloginfo("admin_email");' ),
      'DATE' => create_function('', 'return date(get_option("date_format"));'),
      'TIME' => create_function('', 'return date(get_option("time_format"));'),
      'STYLESHEET_DIRECTORY' => create_function( '', 'return get_bloginfo("stylesheet_directory");' )
    );

    foreach($global_variable_callbacks as $key => $value){
      array_push($this->global_callback_variables, $key);
      array_push($this->global_callback_values, call_user_func( $value ) );
    }
  }

  public function CategorySubscriptionsTemplate(&$cat_sub){
    return $this->__construct($cat_sub);
  }

  public function create_post_replacements(&$post){
    // Reset to the empty state.
    $this->post_template_variables = array('POST_AUTHOR','POST_DATE','POST_CONTENT','POST_TITLE','POST_EXCERPT','GUID');
    $this->post_template_values = array();

    foreach($this->post_template_variables as $opt){
      array_push($this->post_template_values, $post->{strtolower($opt)});
    }

    // Start pulling together categories and tags.
    array_push($this->post_template_variables,'CATEGORIES');
    array_push($this->post_template_variables,'CATEGORIES_WITH_URLS');
    array_push($this->post_template_variables,'PARENT_CATEGORIES');
    array_push($this->post_template_variables,'PARENT_CATEGORIES_WITH_URLS');
    array_push($this->post_template_variables,'TAGS');
    array_push($this->post_template_variables,'TAGS_WITH_URLS');

    $pcats = wp_get_post_categories( $post->ID, array('fields' => 'all') );
    $cat_names = array();
    $cat_urls = array();
    $parent_cat_names = array();
    $parent_cat_urls = array();

    foreach($pcats as $cat){
      // Populate values for categories and categories with URLs.
      // Nick off the last category separator. Sigh. This is stupid, wordpress core. *shakes tiny fist angrily*
      $cat_name = get_category_parents($cat->term_id, FALSE, $this->cat_sub->category_separator);
      $cat_name = substr($cat_name,0,strlen($cat_name) - strlen($this->cat_sub->category_separator));

      if($cat->parent != 0){
        $parent_cat = get_category($cat->parent);
        //        error_log('Parent cat stuff: ' . print_r($cat->parent,true));
        //        error_log('Parent cat data: ' . print_r($parent_cat,true));
        array_push($parent_cat_names, $parent_cat->name);
        array_push($parent_cat_urls, '<a href="' . get_bloginfo('url') .'/?cat=' . $parent_cat->term_id . '">' . $parent_cat->name . '</a>');
      }

      array_push($cat_names, $cat_name);
      array_push($cat_urls, '<a href="' . get_bloginfo('url') .'/?cat=' . $cat->term_id . '">' . $cat_name . '</a>');
    }

    $unique_parent_cat_names = array_unique($parent_cat_names);
    $unique_parent_cat_urls = array_unique($parent_cat_urls);

    array_push($this->post_template_values, implode(', ', $cat_names));
    array_push($this->post_template_values, implode(', ', $cat_urls));
    array_push($this->post_template_values, implode(', ', $unique_parent_cat_names));
    array_push($this->post_template_values, implode(', ', $unique_parent_cat_urls));

    $ptags = wp_get_post_tags( $post->ID, array('fields' => 'all') );
    $tag_names = array();
    $tag_urls = array();

    foreach($ptags as $tag){
      array_push($tag_names, $tag->name);
      array_push($tag_urls, '<a href="' . get_bloginfo('url') .'/?tag=' . $tag->slug . '">' . $tag->name . '</a>');
    }
    array_push($this->post_template_values, implode(', ', $tag_names));
    array_push($this->post_template_values, implode(', ', $tag_urls));

    // Done with categories and tags.

    $excerpt = $post->post_excerpt;
    if(strlen($excerpt) == 0){
      $excerpt = wp_trim_excerpt(strip_tags($post->{'post_content'}));
    }
    array_push($this->post_template_variables,'EXCERPT');
    array_push($this->post_template_values, $excerpt);

    $author = get_userdata($post->post_author);
    array_push($this->post_template_variables,'AUTHOR_LOGIN');
    array_push($this->post_template_values, $author->user_login);

    array_push($this->post_template_variables,'AUTHOR_NICKNAME');
    array_push($this->post_template_values, $author->nickname);

    array_push($this->post_template_variables, 'AUTHOR');
    array_push($this->post_template_values, $author->display_name);

    array_push($this->post_template_variables, 'AUTHOR_URL');
    array_push($this->post_template_values, $author->user_url);

    array_push($this->post_template_variables, 'AUTHOR_FIRST_NAME');
    array_push($this->post_template_values, $author->first_name);

    array_push($this->post_template_variables, 'AUTHOR_LAST_NAME');
    array_push($this->post_template_values, $author->last_name);

    array_push($this->post_template_variables, 'FORMATTED_POST_DATE');
    array_push($this->post_template_values, mysql2date(get_option('date_format'), $post->post_date));

    array_push($this->post_template_variables, 'FORMATTED_POST_TIME');
    array_push($this->post_template_values, mysql2date(get_option('time_format'), $post->post_date));

    array_push($this->post_template_variables, 'POST_ID');
    array_push($this->post_template_values, $post->ID);

    // The user defined field
    array_push($this->post_template_variables, 'EMAIL_SUBJECT');
    array_push($this->post_template_values, get_post_meta($post->ID, 'email_subject', true));

  }

  public function create_user_replacements(&$user){
    // Reset to the empty state.
    $this->user_template_variables = array('USER_LOGIN','USER_NICENAME','USER_EMAIL','DISPLAY_NAME','USER_FIRSTNAME','USER_LASTNAME','NICKNAME');
    $this->user_template_values = array();
    foreach($this->user_template_variables as $opt){
      array_push($this->user_template_values,$user->{strtolower($opt)});
    }
  }

  public function fill_category_header(&$user, &$cat){
    if($cat->name == ''){
      return '';
    }
    $patterns = array();

    $patterns_tmp = array_merge(array('CATEGORY_URL','CATEGORY_NAME','PARENT_CATEGORY_URL','PARENT_CATEGORY_NAME'),$this->global_callback_variables, $this->user_template_variables);
    foreach($patterns_tmp as $pat){
      array_push($patterns, '/\[' . $pat . '\]/');
    }

    $parent_category_name = '';
    $parent_category_url = '';

    if($cat->parent != 0){
      $parent_cat = get_category($cat->parent);
      $parent_category_name = $parent_cat->name;
      $parent_category_url = '<a href="' . get_bloginfo('url') .'/?cat=' . $parent_cat->term_id . '">' . $parent_cat->name . '</a>'; 
    }

    $variables = array_merge(array(get_bloginfo('url') .'/?cat=' . $cat->term_id, $cat->name, $parent_category_url,$parent_category_name), $this->global_callback_values, $this->user_template_values);

    $header_content = '';

    if(get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html'){
      $header_content = preg_replace($patterns, $variables, $this->cat_sub->header_row_html_template);
    } else {
      $header_content = preg_replace($patterns, $variables, $this->cat_sub->header_row_text_template);
    }

    return $header_content;

  }

  public function fill_individual_message(&$user,&$post,$in_digest = false){
    //        $post = get_post($post_ID);
    $this->create_user_replacements($user);
    $this->create_post_replacements($post);

    $patterns = array();

    $patterns_tmp = array_merge($this->global_callback_variables, $this->user_template_variables, $this->post_template_variables);
    foreach($patterns_tmp as $pat){
      array_push($patterns, '/\[' . $pat . '\]/');
    }
    $variables = array_merge($this->global_callback_values, $this->user_template_values, $this->post_template_values);

    $subject = preg_replace($patterns, $variables, $this->cat_sub->individual_email_subject);
    $content = '';
    $toc_entry = '';

    if(get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html'){
      if($in_digest){
        $content = preg_replace($patterns, $variables, $this->cat_sub->email_row_html_template );
        $toc_entry = preg_replace($patterns, $variables, $this->cat_sub->email_toc_html_template );
      } else {
        $content = preg_replace($patterns, $variables, $this->cat_sub->individual_email_html_template);
      }
    } else {
      //Plain text.
      if($in_digest){
        $content = preg_replace($patterns, $variables, $this->cat_sub->email_row_text_template );
        $toc_entry = preg_replace($patterns, $variables, $this->cat_sub->email_toc_text_template );
      } else {
        $content = preg_replace($patterns, $variables, $this->cat_sub->individual_email_text_template);
      }
    } 

    return array('subject' => $subject, 'content' => $content, 'toc' => $toc_entry);
  }

  public function cat_sub_custom_cat_sort($a,$b){
    $a_numeric_start = preg_match('/^\d/',$a);
    $b_numeric_start = preg_match('/^\d/',$b);

    if($a_numeric_start && ! $b_numeric_start){
      return 1;
    }
    if(! $a_numeric_start && $b_numeric_start){
      return -1;
    }
    if($a_numeric_start && $b_numeric_start){
      return ($a < $b) ? -1 : 1;
    }
    if($a == $b){
      return 0;
    }
    return ($a < $b) ? -1 : 1;
  }

  public function fill_digested_message(&$user,&$posts,$frequency = 'daily'){
    $this->create_user_replacements($user);

    $message_list = '';
    $grouped_message_list = '';
    $parent_grouped_message_list = '';
    $toc = '';
    $email_subjects = '';

    $category_list = array();
    $parent_category_list = array();
    $unique_category_list = array();
    $unique_parent_category_list = array();
    $unique_post_list = array();
    $unique_parent_category_post_list = array();
    $post_content = array();

    foreach($posts as $post){
      // create email subjects
      if (get_post_meta($post->ID, 'email_subject', true) != ""){
        if ($email_subjects != ''){
          $email_subjects .= '; ';
        }
        $email_subjects .= get_post_meta($post->ID, 'email_subject', true);
      }

      // So the default TOC is sorted by post date. 
      // Get categories here and start the data structure for category grouping.

      $pcats = wp_get_post_categories( $post->ID, array('fields' => 'all') );

      foreach($pcats as $cat){

        // Collapse to its parent for the PARENT_CATEGORY_GROUPED_EMAIL_LIST
        $parent_cat = 0;
        $parent_cat_name = '';
        if($cat->parent == 0){
          // No parent.
          $parent_cat = $cat;
          $parent_cat_name = $cat->name;
        } else {
          // It has a parent.
          $parent_cat = get_category($cat->parent);
          $parent_cat_name = $parent_cat->name; 
        }
        if(! isset($parent_category_list[$parent_cat_name])){
          $parent_category_list[$parent_cat_name]['posts'] = array();
          $parent_category_list[$parent_cat_name]['cat'] = $parent_cat;
          array_push($unique_parent_category_list, $parent_cat_name);
        }
        if(! isset($unique_parent_category_post_list[$post->ID])){
          // Should be a post that we've not rendered yet.
          array_push($parent_category_list[$parent_cat_name]['posts'],$post->ID);
          $unique_parent_category_post_list[$post->ID] = true;
        }

        // Now go through each category normally for CATEGORY_GROUPED_EMAIL_LIST

        $cat_name = get_category_parents($cat->term_id,FALSE,' &raquo; ');
        if(! isset($category_list[$cat_name])){
          // Initialize the empty array we're going to push the post ID on to.
          $category_list[$cat_name]['posts'] = array();
          $category_list[$cat_name]['cat'] = $cat;
          array_push($unique_category_list, $cat_name);
        }
        if(! isset($unique_post_list[$post->ID])){
          // Should be a post that we've not rendered yet.
          array_push($category_list[$cat_name]['posts'],$post->ID);
          $unique_post_list[$post->ID] = true;
        }
      }

      $message_content = $this->fill_individual_message($user, $post, true);
      $message_list .= $message_content['content'];
      $post_content[$post->ID] = $message_content['content'];
      $toc .= $message_content['toc'];
    }

    usort($unique_category_list,array($this,'cat_sub_custom_cat_sort'));
    usort($unique_parent_category_list,array($this,'cat_sub_custom_cat_sort'));

    foreach($unique_category_list as $ucat){
      $full_cat = $category_list[$ucat]['cat'];
      $grouped_message_list .= $this->fill_category_header($user,$full_cat);
      foreach($category_list[$ucat]['posts'] as $ucatpost){
        $grouped_message_list .= $post_content[$ucatpost];
      }
    }

//    error_log('unique_parent_category_list: ' . print_r($unique_parent_category_list,true));
//    error_log('parent_categoyr_list: ' . print_r($unique_parent_category_list,true));

    foreach($unique_parent_category_list as $ucat){
      $full_cat = $parent_category_list[$ucat]['cat'];
      $parent_grouped_message_list .= $this->fill_category_header($user,$full_cat);
      foreach($parent_category_list[$ucat]['posts'] as $ucatpost){
        $parent_grouped_message_list .= $post_content[$ucatpost];
      }
    }

    $patterns = array();

    $patterns_tmp = array_merge($this->global_callback_variables, $this->user_template_variables);
    foreach($patterns_tmp as $pat){
      array_push($patterns, '/\[' . $pat . '\]/');
    }
    $variables = array_merge($this->global_callback_values, $this->user_template_values);
  
    // create subject
    $subject = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_subject : $this->cat_sub->weekly_email_subject));
    $subject = preg_replace(
      array('/\[EMAIL_SUBJECTS\]/'), 
      array($email_subjects), 
      $subject
    );
    
    // create content
    $content = '';

    if(get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html'){
      $content = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_html_template : $this->cat_sub->weekly_email_html_template));
    } else {
      $content = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_text_template : $this->cat_sub->weekly_email_text_template));
    }

    //error_log('Category Grouped Email List: '. $grouped_message_list);
    //print_r($grouped_message_list);

    $content = preg_replace(
      array('/\[EMAIL_LIST\]/','/\[CATEGORY_GROUPED_EMAIL_LIST\]/','/\[PARENT_CATEGORY_GROUPED_EMAIL_LIST\]/','/\[TOC\]/', '/\[EMAIL_SUBJECTS\]/'), 
      array($message_list, $grouped_message_list, $parent_grouped_message_list, $toc, $email_subjects), 
      $content
    );

    return array('subject' => $subject, 'content' => $content);
  }

}
