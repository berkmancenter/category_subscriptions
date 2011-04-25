<?php
class CategorySubscriptionsTemplate {
    var $name = '';
    var $description = '';
    var $siteurl = '';
    var $home = '';
    var $admin_email = '';
    var $stylesheet_directory = '';
    var $atom_url = '';
    var $rss2_url = '';
    var $today = '';

    var $bloginfo_template_variables = array('NAME','DESCRIPTION', 'URL','ADMIN_EMAIL','STYLESHEET_DIRECTORY');
    var $bloginfo_template_values = array();
    var $user_template_variables = array('USER_LOGIN','USER_NICENAME','USER_EMAIL','DISPLAY_NAME','USER_FIRSTNAME','USER_LASTNAME','NICKNAME');
    var $user_template_values = array();

    var $post_template_variables = array('POST_AUTHOR','POST_DATE','POST_CONTENT','POST_TITLE','POST_EXCERPT');
    var $post_template_values = array();
    var $cat_sub = '';

    public function __construct(&$cat_sub){
        $this->cat_sub = $cat_sub;
        // Instantiate variables that will be available everywhere.
        foreach($this->bloginfo_template_variables as $opt){
            array_push($this->bloginfo_template_values,get_bloginfo(strtolower($opt)));
        }
    }

    public function CategorySubscriptionsTemplate(&$cat_sub){
        return $this->__construct($cat_sub);
    }

    public function create_post_replacements(&$post){
        // TODO
        // CATEGORIES
        // EXCERPT, except as piped through the_excerpt()

        $this->post_template_values = array();
        foreach($this->post_template_variables as $opt){
            array_push($this->post_template_values, $post->{strtolower($opt)});
        }
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
        $patterns_tmp = array_merge($this->bloginfo_template_variables, $this->user_template_variables, $this->post_template_variables);
        foreach($patterns_tmp as $pat){
            array_push($patterns, '/\[' . $pat . '\]/');
        }
        $variables = array_merge($this->bloginfo_template_values, $this->user_template_values, $this->post_template_values);

        $message = preg_replace($patterns, $variables, $this->cat_sub->individual_email_html_template);

        error_log($message);

    }

    public function fill_digested_message(&$main_message,&$messages){

    }

    public function substitute(&$string,$key,$value){

    }

}
