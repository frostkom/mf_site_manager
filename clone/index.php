<?php

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

echo "<div class='wrap'>
	<h2>".__("Clone Site", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('name' => 'intBlogID_old', 'text' => __("From", 'lang_site_manager'), 'value' => get_site_url_clean(), 'xtra' => "readonly"));

				if(is_multisite())
				{
					$arr_data = $obj_site_manager->get_sites_for_select(array('exclude' => $wpdb->blogid));

					if(count($arr_data) > 1)
					{
						echo show_select(array('data' => $arr_data, 'name' => 'intBlogID', 'value' => $obj_site_manager->blog_id, 'text' => __("To", 'lang_site_manager'), 'required' => true));

						if(is_plugin_active('mf_backup/index.php'))
						{
							echo show_checkbox(array('name' => 'intSiteBackup', 'text' => __("Would you like to perform a backup of the old site before replacing it?", 'lang_site_manager'), 'compare' => 1, 'value' => $obj_site_manager->site_backup));
						}

						echo show_checkbox(array('name' => 'intSiteKeepTitle', 'text' => __("Would you like to keep the original title of the receiving site?", 'lang_site_manager'), 'value' => 1))
						.show_checkbox(array('name' => 'intSiteEmptyPlugins', 'text' => __("Would you like to empty Active Plugins field?", 'lang_site_manager'), 'value' => 1))
						.show_checkbox(array('name' => 'intSiteCloneAccept', 'text' => __("Are you really sure? This will erase all previous data on the recieving site.", 'lang_site_manager'), 'value' => 1, 'required' => true));

						if(is_plugin_active("mf_theme_core/index.php"))
						{
							echo show_button(array('name' => 'btnSiteClone', 'text' => __("Perform", 'lang_site_manager')))
							.wp_nonce_field('site_clone_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_clone', true, false);
						}

						else
						{
							require_plugin("mf_theme_core/index.php", "MF Theme Core");
						}
					}

					else
					{
						echo "<em>".__("I could not find any sites to clone to", 'lang_site_manager')."</em>";
					}
				}

				else
				{
					echo __("You have to have a MultiSite to be able to clone this site", 'lang_site_manager');
				}

			echo "</form>
		</div>
	</div>
</div>";