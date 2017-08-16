<?php

$arr_themes = get_themes_for_select();
$option_theme_dir = get_option('stylesheet');
$option_theme_name = get_option('current_theme');

$strSiteTheme = check_var('strSiteTheme');

if(isset($_POST['btnSiteChangeTheme']) && isset($_POST['intSiteChangeThemeAccept']) && $_POST['intSiteChangeThemeAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce'], 'site_change_theme_'.$wpdb->blogid))
{
	$old_theme = $option_theme_dir;
	$new_theme = $strSiteTheme;

	if(!isset($arr_themes[$new_theme]))
	{
		$error_text = __("You have to choose a theme that is allowed for this site", 'lang_site_manager');
	}

	else if($new_theme == $old_theme)
	{
		$error_text = __("You have to choose another Theme than the current one", 'lang_site_manager');
	}

	else
	{
		$arr_errors = array();

		update_option('stylesheet', $new_theme);

		$wpdb->query("UPDATE ".$wpdb->options." SET option_name = 'theme_mods_".$new_theme."' WHERE option_name = 'theme_mods_".$old_theme."'");
		if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}

		$count_temp = count($arr_errors);

		if($count_temp > 0)
		{
			update_option('stylesheet', $old_theme);

			$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

			do_log("Change Theme Errors: ".var_export($arr_errors, true));
		}

		else
		{
			$done_text = sprintf(__("I have changed the Theme from %s to %s", 'lang_site_manager'), $arr_themes[$old_theme], $arr_themes[$new_theme]);
		}
	}
}

echo "<div class='wrap'>
	<h2>".__("Change Theme", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_select(array('data' => $arr_themes, 'name' => 'strSiteTheme', 'text' => __("Change to this Theme", 'lang_site_manager'), 'value' => $option_theme_dir))
				.show_checkbox(array('name' => 'intSiteChangeThemeAccept', 'text' => __("Are you really sure? This will change the Theme of the site but not clear menus, widgets and theme modifications like the built-in changer does", 'lang_site_manager'), 'value' => 1, 'required' => true))
				.show_button(array('name' => 'btnSiteChangeTheme', 'text' => __("Perform", 'lang_site_manager')))
				.wp_nonce_field('site_change_theme_'.$wpdb->blogid, '_wpnonce', true, false)
			."</form>
		</div>
	</div>
</div>";