<?php

$obj_site_manager = new mf_site_manager();

$intBlogID = $wpdb->blogid;
$arr_themes = $obj_site_manager->get_themes_for_select();
$option_theme_dir = get_option('stylesheet');

$strSiteTheme = check_var('strSiteTheme');

if(isset($_POST['btnSiteChangeTheme']))
{
	$old_theme = $option_theme_dir;
	$new_theme = $strSiteTheme;

	if(!(isset($_POST['intSiteChangeThemeAccept']) && $_POST['intSiteChangeThemeAccept'] == 1))
	{
		$error_text = __("You have to check the box to agree that the theme should be changed", $obj_site_manager->lang_key);
	}

	else if(!wp_verify_nonce($_POST['_wpnonce_site_change_theme'], 'site_change_theme_'.$intBlogID.'_'.get_current_user_id()))
	{
		$error_text = __("I could not verify that you were allowed to switch theme on this site. I this problem persists, please contact an admin", $obj_site_manager->lang_key);
	}

	else if(!isset($arr_themes[$new_theme]))
	{
		$error_text = __("You have to choose a theme that is allowed for this site", $obj_site_manager->lang_key);
	}

	else if($new_theme == $old_theme)
	{
		$error_text = __("You have to choose another Theme than the current one", $obj_site_manager->lang_key);
	}

	else
	{
		$arr_errors = array();

		update_option('stylesheet', $new_theme, 'no');

		//Make sure it doesn't already exist before trying to use it since it'll return a duplicate error if that is the case
		$wpdb->query("DELETE FROM ".$wpdb->options." WHERE option_name = 'theme_mods_".$new_theme."'");

		$wpdb->query("UPDATE ".$wpdb->options." SET option_name = 'theme_mods_".$new_theme."' WHERE option_name = 'theme_mods_".$old_theme."'");
		if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}

		$count_temp = count($arr_errors);

		if($count_temp > 0)
		{
			update_option('stylesheet', $old_theme, 'no');

			$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", $obj_site_manager->lang_key), $count_temp);

			do_log("Change Theme Errors: ".var_export($arr_errors, true));
		}

		else
		{
			$done_text = sprintf(__("I have changed the Theme from %s to %s", $obj_site_manager->lang_key), $arr_themes[$old_theme], $arr_themes[$new_theme]);

			do_log(sprintf("%s changed theme from %s to %s", get_user_info(), $old_theme, $new_theme), 'notification');
		}
	}
}

echo "<div class='wrap'>
	<h2>".__("Change Theme", $obj_site_manager->lang_key)."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				//.show_textfield(array('name' => 'strSiteName', 'text' => __("Site", $obj_site_manager->lang_key), 'value' => get_bloginfo('name')." (".$intBlogID.")", 'xtra' => "readonly"))
				.show_select(array('data' => $arr_themes, 'name' => 'strSiteTheme', 'text' => __("Change to this Theme", $obj_site_manager->lang_key), 'value' => $option_theme_dir));

				if(count($arr_themes) > 1)
				{
					echo show_checkbox(array('name' => 'intSiteChangeThemeAccept', 'text' => __("Are you really sure? This will change the Theme of the site but not clear menus, widgets and theme modifications like the built-in changer does", $obj_site_manager->lang_key), 'value' => 1, 'required' => true))
					.show_button(array('name' => 'btnSiteChangeTheme', 'text' => __("Perform", $obj_site_manager->lang_key)))
					.wp_nonce_field('site_change_theme_'.$intBlogID.'_'.get_current_user_id(), '_wpnonce_site_change_theme', true, false);
				}

				else
				{
					echo "<em>".sprintf(__("There are no other themes activated for this site. %sPlease, add another theme%s", $obj_site_manager->lang_key), "<a href='".admin_url("network/site-themes.php?id=".$intBlogID)."'>", "</a>")."</em>";
				}

			echo "</form>
		</div>
	</div>
</div>";