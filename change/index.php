<?php

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

echo "<div class='wrap'>
	<h2>".__("Change URL", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form".apply_filters('get_form_attr', "").">"
				.show_textfield(array('type' => 'url', 'name' => 'strBlogUrl', 'text' => __("Change to this URL", 'lang_site_manager'), 'value' => $obj_site_manager->new_url))
				.show_checkbox(array('name' => 'intSiteChangeUrlAccept', 'text' => __("Are you really sure? This will change the URL of the site", 'lang_site_manager'), 'value' => 1, 'required' => true))
				.show_button(array('name' => 'btnSiteChangeUrl', 'text' => __("Perform", 'lang_site_manager')))
				.wp_nonce_field('site_change_url_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_change_url', true, false)
			."</form>
		</div>
	</div>
</div>";