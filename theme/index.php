<?php

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

echo "<div class='wrap'>
	<h2>".__("Change Theme", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('text' => __("Site", 'lang_site_manager'), 'value' => get_bloginfo('name')." (#".$wpdb->blogid.")", 'xtra' => "disabled"))
				.show_select(array('data' => $obj_site_manager->arr_themes, 'name' => 'strSiteTheme', 'text' => __("Change to this Theme", 'lang_site_manager'), 'value' => $obj_site_manager->current_theme));

				if(count($obj_site_manager->arr_themes) > 1)
				{
					echo show_checkbox(array('name' => 'intSiteChangeThemeAccept', 'text' => __("Are you really sure? This will change the Theme of the site but not clear menus, widgets and theme modifications like the built-in changer does", 'lang_site_manager'), 'value' => 1, 'required' => true))
					.show_button(array('name' => 'btnSiteChangeTheme', 'text' => __("Perform", 'lang_site_manager')))
					.wp_nonce_field('site_change_theme_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_change_theme', true, false);
				}

				else
				{
					echo "<em>".sprintf(__("There are no other themes activated for this site. %sPlease, add another theme%s", 'lang_site_manager'), "<a href='".admin_url("network/site-themes.php?id=".$wpdb->blogid)."'>", "</a>")."</em>";
				}

			echo "</form>
		</div>
	</div>
</div>";