<?php

function get_or_set_transient($data)
{
	$out = get_transient($data['key']);

	if($out == "")
	{
		$out = file_get_contents($data['url']);

		set_transient($data['key'], $out, WEEK_IN_SECONDS);
	}

	return $out;
}

function menu_cloner()
{
	$menu_root = 'mf_site_manager/';
	$menu_start = $menu_root."compare/index.php";
	$menu_capability = "update_core";

	$menu_title = __("Site Manager", 'lang_site_manager');
	add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-images-alt2');

	if(get_option('setting_site_comparison') != '')
	{
		$menu_title = __("Compare", 'lang_site_manager');
		add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."compare/index.php");
	}

	if(is_multisite())
	{
		global $wpdb;

		$result = $wpdb->get_results("SELECT blog_id FROM ".$wpdb->base_prefix."blogs");

		if($wpdb->num_rows > 1)
		{
			$menu_title = __("Clone", 'lang_site_manager');
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."clone/index.php");

			$menu_title = __("Switch", 'lang_site_manager');
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."switch/index.php");
		}
	}
}

function settings_cloner()
{
	$options_area = __FUNCTION__;

	add_settings_section($options_area, "", $options_area.'_callback', BASE_OPTIONS_PAGE);

	$arr_settings = array();

	$arr_settings["setting_server_ip"] = __("This server IP", 'lang_site_manager');
	$arr_settings["setting_server_ips_allowed"] = __("Server IPs allowed", 'lang_site_manager');
	$arr_settings["setting_site_comparison"] = __("Sites to compare with", 'lang_site_manager');

	foreach($arr_settings as $handle => $text)
	{
		add_settings_field($handle, $text, $handle."_callback", BASE_OPTIONS_PAGE, $options_area);

		register_setting(BASE_OPTIONS_PAGE, $handle);
	}
}

function settings_cloner_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Site Manager", 'lang_site_manager'));
}

function setting_server_ip_callback()
{
	echo get_or_set_transient(array('key' => "server_ip_transient", 'url' => "http://ipecho.net/plain"));
}

function setting_server_ips_allowed_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$server_ip = get_or_set_transient(array('key' => "server_ip_transient", 'url' => "http://ipecho.net/plain"));

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'xtra' => " class='widefat'", 'placeholder' => $server_ip));
}

function setting_site_comparison_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$site_url = str_replace(array("http://", "https://"), "", get_option('siteurl'));

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'xtra' => " class='widefat'", 'placeholder' => $site_url.", test.".$site_url));
}