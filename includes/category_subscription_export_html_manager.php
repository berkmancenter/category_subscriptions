<?php

class Category_subscription_export_html_manager{

	public function export_admin_page(){

		global $cat_sub_export_db, $cat_sub;

		$tableData = $cat_sub_export_db->get_aggregate_data();

		// START HTML DOCUMENT
		?>

		<h2><?php _e('Export Category Subscriptions Data'); ?></h2>

		<table style="text-align:left">
			<tr>
				<th>Category</th>
				<th>Subscribers</th>
			</tr>
			<?php
				foreach ($tableData as $tableRow){
					$nameStr = get_category_parents($tableRow->id, FALSE, $cat_sub->category_separator);
					if (substr($nameStr, -strlen($cat_sub->category_separator)) == $cat_sub->category_separator){
						$nameStr = substr($nameStr, 0, strlen($nameStr) - strlen($cat_sub->category_separator));
					}
					echo('<tr><td>' . $nameStr . '</td><td>' . $tableRow->subscribed . '</td></tr>');
				}
			?>
		</table>

		<p class="submit"><a href="options-general.php?page=categories-subscription-export-csv" class="button-primary"><?php _e('Export Individual Data'); ?></a></p> 
		<?php
		// END HTML DOCUMENT

	}

	public function export_CSV(){
		// make sure correct page
		if ($_GET['page'] === "categories-subscription-export-csv" && strstr($_SERVER['REQUEST_URI'], "options-general.php") !== FALSE){
			// make sure correct permissions
			if (current_user_can('remove_users')){
				// grab globals
				global $cat_sub_export_db, $cat_sub;
				// open stream
				$output = fopen("php://output", "w");
				// toss headers
				header("Content-type: application/csv");
				header("Content-Disposition: attachment; filename=categories-subscription-data.csv");
				header("Pragma: no-cache");
				header("Expires: 0");
				// write it
				$head = $cat_sub_export_db->get_individual_header();
				// show heirarchy
				$headers = array();
				foreach ($head as $category){
					$nameStr = get_category_parents($category, FALSE, $cat_sub->category_separator);
					if (substr($nameStr, -strlen($cat_sub->category_separator)) == $cat_sub->category_separator){
						$nameStr = substr($nameStr, 0, strlen($nameStr) - strlen($cat_sub->category_separator));
					}
					$headers[] = $nameStr;
					$headers[] = $nameStr . " Preferences";
				}
				// add some headers
				array_unshift($headers, "Name", "Email");
				fputcsv($output, $headers);
				$data = $cat_sub_export_db->get_individual_data($head);
				foreach ($data as $datum){
					fputcsv($output, $datum);
				}
				// close stream
				fclose($output);
				// prevent further output
				exit();
			}
			else {
				exit("Insufficient Permissions");
			}
		}
	}
}

?>