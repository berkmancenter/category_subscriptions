<?php

class Category_subscription_export_db_manager{
	public function get_individual_header(){
		// get globals 
		global $wpdb;
		// gather data
		$prepared = $wpdb->prepare("SELECT t.name AS name, t.term_id AS id FROM " . $wpdb->prefix . "terms t INNER JOIN " . $wpdb->prefix . "term_taxonomy n ON t.term_id = n.term_id WHERE n.taxonomy = 'category'");
		$results = $wpdb->get_results($prepared, OBJECT);
		foreach ($results as $result){
			$toReturn[] = $result->id;
		}
		return $toReturn;
	}
	public function get_individual_data($category_ids){
		// get globals 
		global $wpdb, $cat_sub;
		// gather data
		$prepared = $wpdb->prepare("SELECT DISTINCT u.display_name as user_name, u.ID as user_id, u.user_email as user_email FROM " . $wpdb->base_prefix . "users u INNER JOIN " . $wpdb->base_prefix . "usermeta m ON m.user_id = u.ID WHERE m.meta_key = '" . $wpdb->prefix . "capabilities'");
		$people = $wpdb->get_results($prepared, OBJECT);
		$toReturn = array();
		foreach ($people as $person){
			set_time_limit(30);
			$currentIndex = count($toReturn);
			$toReturn[$currentIndex] = array();
			$toReturn[$currentIndex][] = $person->user_name;
			$toReturn[$currentIndex][] = $person->user_email;
			$prepared_subscriptions = $wpdb->prepare("SELECT category_ID, delivery_time_preference FROM " . $cat_sub->user_subscriptions_table_name . " WHERE user_ID = %d", array($person->user_id));
			$subscribed_to = $wpdb->get_results($prepared_subscriptions, OBJECT);
			foreach ($category_ids as $category_id){
				foreach ($subscribed_to as $subscription){
					if ($subscription->category_ID == $category_id){
						$toReturn[$currentIndex][] = 1;
						$toReturn[$currentIndex][] = $subscription->delivery_time_preference;
						continue 2;
					}
				}
				$toReturn[$currentIndex][] = 0;
				$toReturn[$currentIndex][] = '';
			}
			
		}
		return $toReturn;
	}
	public function get_aggregate_data(){
		// get globals
		global $wpdb, $cat_sub;
		// gather data
		$prepared = $wpdb->prepare("SELECT t.name, COUNT(c.category_ID) AS subscribed, t.term_id AS id FROM " . $wpdb->prefix . "terms t INNER JOIN " . $wpdb->prefix . "term_taxonomy n ON t.term_id = n.term_id LEFT JOIN " . $cat_sub->user_subscriptions_table_name . " c ON t.term_id = c.category_ID WHERE n.taxonomy = 'category' GROUP BY c.category_ID");
		$results = $wpdb->get_results($prepared, OBJECT);
		return $results;
	}
}

?>
