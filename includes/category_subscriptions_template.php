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
        $pcats = wp_get_post_categories( $post->ID );
        $cat_names = array();
        $cat_urls = array();
        foreach($pcats as $cat){
            $c = get_category( $cat );
            array_push($cat_names, $c->name);
            // Not happy about this, but I can't seem to make "get_category_link" work in this context. I would rather that the categories
            // reflect their permalink structure.
            array_push($cat_urls, '<a href="' . get_bloginfo('url') .'/?cat=' . $c->cat_ID . '">' . $c->name . '</a>');
        }
        array_push($this->post_template_values, implode(', ', $cat_names));
        array_push($this->post_template_values, implode(', ', $cat_urls));

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
        if(get_user_meta($user_ID, 'cat_sub_delivery_format_pref',true) == 'html'){
            $content = preg_replace($patterns, $variables, (($in_digest) ? $this->cat_sub->email_row_html_template : $this->cat_sub->individual_email_html_template));
        } else {
            $content = preg_replace($patterns, $variables, (($in_digest) ? $this->cat_sub->email_row_text_template : $this->cat_sub->individual_email_text_template));
        } 

        return array('subject' => $subject, 'content' => $content);
    }

    public function fill_digested_message(&$user_ID,&$message_list,$frequency = 'daily'){
        $user = get_userdata($user_ID);
        $this->create_user_replacements($user);

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
        return array('subject' => $subject, 'content' => $content);
    }

}
