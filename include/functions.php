<?php

function get_sites_for_select()
{
	global $wpdb;

	$result = get_sites(array('site__not_in' => array($wpdb->blogid)));

	$arr_data = array();
	$arr_data[''] = "-- ".__("Choose here", 'lang_site_manager')." --";

	foreach($result as $r)
	{
		$blog_id = $r->blog_id;
		$domain = $r->domain;
		$path = $r->path;

		$arr_data[$blog_id] = $domain.$path;
	}

	$arr_data = array_sort(array('array' => $arr_data, 'keep_index' => true));

	return $arr_data;
}

function get_themes_for_select()
{
	$arr_data = array();

	$themes = wp_get_themes(array('errors' => false, 'allowed' => true));

	foreach($themes as $key => $value)
	{
		$arr_data[$key] = $value['Name'];
	}

	return $arr_data;
}

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

function menu_site_manager()
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

	$menu_title = __("Change URL", 'lang_site_manager');
	add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."change/index.php");

	$menu_title = __("Change Theme", 'lang_site_manager');
	add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."theme/index.php");
}

function settings_site_manager()
{
	if(IS_SUPER_ADMIN)
	{
		$options_area = __FUNCTION__;

		add_settings_section($options_area, "", $options_area.'_callback', BASE_OPTIONS_PAGE);

		$arr_settings = array();
		$arr_settings['setting_server_ips_allowed'] = __("Server IPs allowed", 'lang_site_manager');
		$arr_settings['setting_site_comparison'] = __("Sites to compare with", 'lang_site_manager');

		show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
	}
}

function settings_site_manager_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Site Manager", 'lang_site_manager'));
}

function setting_server_ips_allowed_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$placeholder = $option == "" ? get_or_set_transient(array('key' => "server_ip_transient", 'url' => "http://ipecho.net/plain")) : "";

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $placeholder));
}

function setting_site_comparison_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$site_url = str_replace(array("http://", "https://"), "", get_option('siteurl'));

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $site_url.", test.".$site_url));
}

function column_header_site_manager($cols)
{
	unset($cols['date']);

	$cols['theme'] = __("Theme", 'lang_site_manager');

	return $cols;
}

function column_cell_site_manager($col, $id)
{
	global $wpdb;

	switch($col)
	{
		case 'theme':
			echo get_blog_option($id, 'stylesheet')
			."<div class='row-actions'>"
				."<a href='".get_site_url($id)."/wp-admin/admin.php?page=mf_site_manager/theme/index.php'>".__("Change", 'lang_site_manager')."</a>"
				//.get_blog_option($id, 'current_theme')
			."</div>";
		break;
	}
}