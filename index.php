<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description:
Version: 5.6.25
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://martinfors.se
Text Domain: lang_site_manager
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_site_manager
*/

if(!function_exists('is_plugin_active') || function_exists('is_plugin_active') && is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	$obj_site_manager = new mf_site_manager();

	add_action('cron_base', array($obj_site_manager, 'cron_base'), mt_rand(1, 10));

	add_action('wp_before_admin_bar_render', array($obj_site_manager, 'wp_before_admin_bar_render'));

	if(is_admin())
	{
		register_uninstall_hook(__FILE__, 'uninstall_site_manager');

		if(is_multisite())
		{
			add_action('admin_bar_menu', array($obj_site_manager, 'admin_bar_menu'));
		}

		add_action('admin_init', array($obj_site_manager, 'admin_init'), 0);
		add_action('admin_notices', array($obj_site_manager, 'admin_notices'));
		add_action('admin_init', array($obj_site_manager, 'settings_site_manager'));
		add_action('admin_menu', array($obj_site_manager, 'admin_menu'));

		if(is_multisite())
		{
			add_filter('filter_sites_table_pages', array($obj_site_manager, 'filter_sites_table_pages'));

			add_action('manage_plugins_columns', array($obj_site_manager, 'manage_plugins_columns'));
			add_action('manage_plugins_custom_column', array($obj_site_manager, 'manage_plugins_custom_column'), 10, 3);

			add_filter('manage_sites-network_columns', array($obj_site_manager, 'sites_column_header'), 5);
			add_action('manage_sites_custom_column', array($obj_site_manager, 'sites_column_cell'), 5, 2);
			add_filter('manage_sites_action_links', array($obj_site_manager, 'sites_row_actions'));

			add_action('admin_footer', array($obj_site_manager, 'admin_footer'), 0);
		}
	}

	add_filter('option_blogname', array($obj_site_manager, 'option_blogname'), 10, 2);
	add_filter('get_site_icon_url', array($obj_site_manager, 'get_site_icon_url'), 10, 3);

	add_action('wp_ajax_api_site_manager_force_server_ip', array($obj_site_manager, 'api_site_manager_force_server_ip'));

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__))."/lang/");

	function uninstall_site_manager()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_site_manager_multisite', 'setting_site_manager_server_ip', 'setting_site_manager_server_ip_target', 'setting_site_manager_server_ips_allowed', 'setting_site_manager_site_comparison', 'setting_site_manager_site_clone_path'),
		));
	}
}