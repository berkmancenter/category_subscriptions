<?php
class CategorySubscriptionsMessage {
    var $message = '';
    var $cat_sub = '';
    var $message_type = 'individual';
    
    public function __construct(&$message,&$cat_sub,&$formatted_msg){
        $this->message = $message;
        $this->cat_sub = $cat_sub;
        $this->formatted_msg = $formatted_msg;
    }
    
    # PHP 4 constructor
    public function CategorySubscriptionsMessage(&$message,&$cat_sub,&$formatted_msg) {
        return $this->__construct($message,$cat_sub,$formatted_msg);
    }

    public function deliver(){
        $user = get_userdata($this->message->user_ID);

        $to = $user->user_email;
        $subject = $this->formatted_msg['subject'];
        $content = $this->formatted_msg['content'];
        $headers = array();

        if(get_user_meta($user->ID, 'cat_sub_delivery_format_pref',true) == 'html')){
            // HTML
            $headers = array(
                "From: " . (($cat_sub->from_address == '') ? get_bloginfo('admin_email') : $cat_sub->from_address ),
                "Reply-To: " . (($cat_sub->reply_to_address == '') ? get_bloginfo('admin_email') : $cat_sub->reply_to_address ),
                "Content-Type: multipart/alternative; boundary=bcaec548a17f9ae98304a1c2c386"
            );
        } else {
            // Text
            $headers = array(
                "From: " . (($cat_sub->from_address == '') ? get_bloginfo('admin_email') : $cat_sub->from_address ),
                "Reply-To: " . (($cat_sub->reply_to_address == '') ? get_bloginfo('admin_email') : $cat_sub->reply_to_address ),
                "Content-Type: text/plain; charset=" . get_bloginfo('charset')
            );
        }

//        $h = implode("\n",$headers) . "\n";

        wp_mail($to, $sub, $msg, $h);
        

    }

}
