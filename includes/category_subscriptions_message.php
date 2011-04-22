<?php
class CategorySubscriptionsMessage {
    var $message = '';
    var $cat_sub = '';
    var $message_type = 'individual';
    
    public function __construct(&$message,&$cat_sub,&$message_type){
        $this->message = $message;
        $this->cat_sub = $cat_sub;
        $this->message_type = $message_type;
    }
    
    # PHP 4 constructor
    public function CategorySubscriptionsMessage(&$message,&$cat_sub) {
        return $this->__construct($message,$cat_sub);
    }

    public function individual_message_text(){
    }

    public function individual_message_html(){
    }

    public function output(){

    }

}
