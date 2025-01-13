<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description:
Version: 5.5.25
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

	if(is_admin())
	{
		register_uninstall_hook(__FILE__, 'uninstall_site_manager');

		add_action('init', array($obj_site_manager, 'init'));

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

	add_action('wp_ajax_force_server_ip', array($obj_site_manager, 'force_server_ip'));

	function uninstall_site_manager()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_site_manager_multisite', 'setting_server_ip', 'setting_server_ip_target', 'setting_server_ips_allowed', 'setting_site_comparison', 'setting_site_clone_path'),
		));
	}
}