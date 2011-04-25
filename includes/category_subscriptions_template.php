<?php
class CategorySubscriptionsTemplate {
    var $global_callback_variables = array();

    var $global_callback_values = array();

    var $user_template_variables = array('USER_LOGIN','USER_NICENAME','USER_EMAIL','DISPLAY_NAME','USER_FIRSTNAME','USER_LASTNAME','NICKNAME');
    var $user_template_values = array();

    var $post_template_variables = array('POST_AUTHOR','POST_DATE','POST_CONTENT','POST_TITLE','POST_EXCERPT');
    var $post_template_values = array();
    var $cat_sub = '';

    public function __construct(&$cat_sub){
        $this->cat_sub = $cat_sub;
        // Instantiate variables that will be available everywhere.

        $global_variable_callbacks = array(
            'PROFILE_URL' => function () { return admin_url('profile.php'); },
            'SITE_TITLE' => function () { return get_bloginfo('name'); },
            'DESCRIPTION' => function () { return get_bloginfo('description'); },
            'SITE_URL' => function () { return get_bloginfo('url'); },
            'ADMIN_EMAIL' => function () { return get_bloginfo('admin_email');},
            'STYLESHEET_DIRECTORY' => function (){ return get_bloginfo('stylesheet_directory');}
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
    }

    public function create_user_replacements(&$user){
        $this->user_template_values = array();
        foreach($this->user_template_variables as $opt){
            array_push($this->user_template_values,$user->{strtolower($opt)});
        }
    }

    public function fill_individual_message(&$message){
        $user = get_userdata($message->user_ID);
        $post = get_post($message->post_ID);
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
        if(get_user_meta($message->user_ID, 'cat_sub_delivery_format_pref',true) == 'html'){
            $content = preg_replace($patterns, $variables, $this->cat_sub->individual_email_html_template);
        } else {
            $content = preg_replace($patterns, $variables, $this->cat_sub->individual_email_text_template);
        }
        return array('subject' => $subject, 'content' => $content);
    }

    public function fill_digested_message(&$main_message,&$messages){

    }

}
