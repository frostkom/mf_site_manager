<?php

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

echo "<div class='wrap'>
	<h2>".__("Switch Sites", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('name' => 'intBlogID_old', 'text' => __("Switch this site...", 'lang_site_manager'), 'value' => get_site_url_clean(), 'xtra' => "readonly"));

				if(is_multisite())
				{
					$arr_data = $obj_site_manager->get_sites_for_select(array('exclude' => $wpdb->blogid));

					if(count($arr_data) > 1)
					{
						echo show_select(array('data' => $arr_data, 'name' => 'intBlogID', 'value' => $obj_site_manager->blog_id, 'text' => __("...with this", 'lang_site_manager'), 'required' => true))
						.show_checkbox(array('name' => 'intSiteSwitchAccept', 'text' => __("Are you really sure? This will switch domain for the two sites.", 'lang_site_manager'), 'value' => 1, 'required' => true))
						.show_button(array('name' => 'btnSiteSwitch', 'text' => __("Perform", 'lang_site_manager')))
						.wp_nonce_field('site_switch_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_switch', true, false);
					}

					else
					{
						echo "<em>".__("I could not find any sites to switch to", 'lang_site_manager')."</em>";
					}
				}

				else
				{
					echo __("You have to have a MultiSite to be able to switch sites", 'lang_site_manager');
				}

			echo "</form>
		</div>
	</div>
</div>";