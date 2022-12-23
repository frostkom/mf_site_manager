<?php

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

$arr_data_tables = $obj_site_manager->get_tables_for_select();

echo "<div class='wrap'>
	<h2>".__("Edit Tables", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_select(array('data' => $obj_site_manager->get_table_action_for_select(), 'name' => 'strTableAction', 'value' => $obj_site_manager->table_action, 'text' => __("Action", 'lang_site_manager'), 'required' => true))
				.show_select(array('data' => $arr_data_tables, 'name' => 'strTablePrefix', 'value' => $obj_site_manager->table_prefix, 'text' => __("Table", 'lang_site_manager'), 'required' => true))
				."<div class='show_replace'>"
					.show_select(array('data' => $arr_data_tables, 'name' => 'strTablePrefixDestination', 'value' => $obj_site_manager->table_prefix_destination, 'text' => __("Destination Table", 'lang_site_manager'), 'required' => true))
				."</div>"
				.show_checkbox(array('name' => 'intEditTableAccept', 'text' => __("Are you really sure? This will perform the action that you are requesting.", 'lang_site_manager'), 'value' => 1, 'required' => true))
				.show_button(array('name' => 'btnEditTable', 'text' => __("Perform", 'lang_site_manager')))
				.wp_nonce_field('edit_table_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_edit_table', true, false)
			."</form>
		</div>
	</div>
</div>";