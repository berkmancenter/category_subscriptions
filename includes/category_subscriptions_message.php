<?php
class CategorySubscriptionsMessage {
    var $user = '';
    var $cat_sub = '';
    var $formatted_msg = '';
    
    public function __construct(&$user,&$cat_sub,&$formatted_msg){
        $this->user = $user;
        $this->cat_sub = $cat_sub;
        $this->formatted_msg = $formatted_msg;
    }
    
    # PHP 4 constructor
    public function CategorySubscriptionsMessage(&$user,&$cat_sub,&$formatted_msg) {
        return $this->__construct($user,$cat_sub,$formatted_msg);
    }

    public function deliver(){
        $user = $this->user;
        $to = $user->user_email;
        $subject = $this->formatted_msg['subject'];

        $headers = array(
            'From: ' . (($this->cat_sub->from_address == '') ? get_bloginfo('admin_email') : $this->cat_sub->from_address ),
            'Reply-To: ' . (($this->cat_sub->reply_to_address == '') ? get_bloginfo('admin_email') : $this->cat_sub->reply_to_address ),
        );

        if(strlen($this->cat_sub->bcc_address) > 5){
            array_push($headers, 'Bcc: ' . $this->cat_sub->bcc_address);
        }

        if(strlen($this->cat_sub->from_name) > 0){
          add_filter('wp_mail_from_name', array($this->cat_sub,'from_name') );
        } else {
          add_filter('wp_mail_from_name', create_function('','return get_bloginfo("name");') );
        }

        $content = $this->formatted_msg['content'];
        if(get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html'){
            // HTML
            add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));
        } else {
            // Text
            add_filter('wp_mail_content_type',create_function('', 'return "text/plain"; '));
        }

        $headers_for_email = implode("\n",$headers) . "\n";

        //error_log('attempting to send message. . . ');
        //error_log('To: ' . $to);
        //error_log('Subject: ' . $subject);
        //error_log('Content: ' . $content);

        wp_mail($to, $subject, $content, $headers_for_email);
    }

}
