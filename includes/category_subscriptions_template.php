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
        
        array_push($this->post_template_variables,'CATEGORIES');
        array_push($this->post_template_variables,'CATEGORIES_WITH_URLS');
        array_push($this->post_template_variables,'TAGS');
        array_push($this->post_template_variables,'TAGS_WITH_URLS');

        $pcats = wp_get_post_categories( $post->ID, array('fields' => 'all') );
        $cat_names = array();
        $cat_urls = array();
        
        foreach($pcats as $cat){
            array_push($cat_names, $cat->name);
            array_push($cat_urls, '<a href="' . get_bloginfo('url') .'/?cat=' . $cat->term_id . '">' . $cat->name . '</a>');
        }
        array_push($this->post_template_values, implode(', ', $cat_names));
        array_push($this->post_template_values, implode(', ', $cat_urls));

        $ptags = wp_get_post_tags( $post->ID, array('fields' => 'all') );
        $tag_names = array();
        $tag_urls = array();

        foreach($ptags as $tag){
            array_push($tag_names, $tag->name);
            array_push($tag_urls, '<a href="' . get_bloginfo('url') .'/?tag=' . $tag->slug . '">' . $tag->name . '</a>');
        }
        array_push($this->post_template_values, implode(', ', $tag_names));
        array_push($this->post_template_values, implode(', ', $tag_urls));

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

    }

    public function create_user_replacements(&$user){
        // Reset to the empty state.
        $this->user_template_variables = array('USER_LOGIN','USER_NICENAME','USER_EMAIL','DISPLAY_NAME','USER_FIRSTNAME','USER_LASTNAME','NICKNAME');
        $this->user_template_values = array();
        foreach($this->user_template_variables as $opt){
            array_push($this->user_template_values,$user->{strtolower($opt)});
        }
    }

    public function fill_individual_message(&$user_ID,&$post_ID,$in_digest = false){
        $user = get_userdata($user_ID);
        $post = get_post($post_ID);
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

        if(get_user_meta($user_ID, 'cat_sub_delivery_format_pref',true) == 'html'){
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

    public function fill_digested_message(&$user_ID,&$posts,$frequency = 'daily'){
        $user = get_userdata($user_ID);
        $this->create_user_replacements($user);

        $message_list = '';
        $toc = '';

        $category_list = array();

        foreach($posts as $post){
          // So the default TOC is sorted by post date. 
          // Get categories here and start the data structure for category grouping.

          $pcats = wp_get_post_categories( $post->ID, array('fields' => 'all') );

          foreach($pcats as $cat){
            if(! isset($category_list[$cat->term_id])){
              // Initialize the empty array we're going to push the post ID on to.
              $category_list[$cat->term_id]['posts'] = array();
              $category_list[$cat->term_id]['cat'] = array();
            }
            if(! isset($post_seen[$post->ID])){
              // Should be a post that we've not rendered yet.
              array_push($category_list[$cat->term_id]['posts'],$post->ID);
              $category_list[$cat->term_id]['cat'] = $cat;
            }
          }
          // $category_list should be a HoA containing unique posts and the first category they appeared in.

          $message_content = $this->fill_individual_message($user_ID, $post->ID, true);
          $message_list .= $message_content['content'];
          $toc .= $message_content['toc'];
        }

        //TODO

        error_log('Category List: ' . print_r($category_list,true));
        print_r($category_list);

        $patterns = array();

        $patterns_tmp = array_merge($this->global_callback_variables, $this->user_template_variables);
        foreach($patterns_tmp as $pat){
            array_push($patterns, '/\[' . $pat . '\]/');
        }
        $variables = array_merge($this->global_callback_values, $this->user_template_values);

        $subject = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_subject : $this->cat_sub->weekly_email_subject));
        $content = '';

        if(get_user_meta($user_ID, 'cat_sub_delivery_format_pref',true) == 'html'){
            $content = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_html_template : $this->cat_sub->weekly_email_html_template));
        } else {
            $content = preg_replace($patterns, $variables, (($frequency == 'daily') ? $this->cat_sub->daily_email_text_template : $this->cat_sub->weekly_email_text_template));
        }

        $content = preg_replace('/\[EMAIL_LIST\]/', $message_list, $content);
        $content = preg_replace('/\[TOC\]/', $toc, $content);

        return array('subject' => $subject, 'content' => $content);
    }

}
