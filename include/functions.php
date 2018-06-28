<?php

if(!function_exists('get_sites_for_select'))
{
	function get_sites_for_select()
	{
		global $wpdb;

		$result = get_sites(array('site__not_in' => array($wpdb->blogid)));

		$arr_data = array(
			'' => "-- ".__("Choose Here", 'lang_site_manager')." --"
		);

		foreach($result as $r)
		{
			$blog_id = $r->blog_id;
			$domain = $r->domain;
			$path = $r->path;

			$arr_data[$blog_id] = get_blog_option($blog_id, 'blogname')." (".$domain.$path.")";
		}

		$arr_data = array_sort(array('array' => $arr_data, 'keep_index' => true));

		return $arr_data;
	}
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
		list($content, $headers) = get_url_content($data['url'], true);

		if(!(isset($headers['http_code']) && $headers['http_code'] == 200))
		{
			if(ini_get('allow_url_fopen'))
			{
				$content = @file_get_contents($data['url']);
			}
		}

		if($content != '')
		{
			set_transient($data['key'], $content, DAY_IN_SECONDS); //HOUR_IN_SECONDS, WEEK_IN_SECONDS
		}
	}

	return $out;
}

function menu_site_manager()
{
	$menu_root = 'mf_site_manager/';
	$menu_start = $menu_root."compare/index.php";
	$menu_capability = override_capability(array('page' => $menu_start, 'default' => 'update_core'));

	$menu_title = __("Site Manager", 'lang_site_manager');
	add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-images-alt2', 100);

	if(get_option('setting_site_comparison') != '')
	{
		$menu_title = __("Compare Sites", 'lang_site_manager');
		add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."compare/index.php");
	}

	if(is_multisite())
	{
		global $wpdb;

		$result = $wpdb->get_results("SELECT blog_id FROM ".$wpdb->base_prefix."blogs");

		if($wpdb->num_rows > 1)
		{
			$menu_title = __("Clone Site", 'lang_site_manager');
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."clone/index.php");

			$menu_title = __("Switch Sites", 'lang_site_manager');
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
		$arr_settings['setting_server_ip'] = __("Server IP", 'lang_site_manager');
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

function setting_server_ip_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	if($option == '')
	{
		$obj_site_manager = new mf_site_manager();

		$option = $obj_site_manager->get_server_ip();
	}

	echo $option
	."<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnGetServerIP', 'text' => __("Get Server IP", 'lang_site_manager'), 'class' => 'button-secondary'))
		//.show_button(array('type' => 'button', 'name' => 'btnGetMyIP', 'text' => __("Get My IP", 'lang_site_manager'), 'class' => 'button-secondary'))
	."</div>
	<div id='ip_debug'></div>";
}

function setting_server_ips_allowed_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	echo show_textfield(array('name' => $setting_key, 'value' => $option));
}

function setting_site_comparison_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$site_url = remove_protocol(array('url' => get_option('siteurl'), 'clean' => true));

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $site_url.", test.".$site_url));
}