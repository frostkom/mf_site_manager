<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description: 
Version: 4.8.5
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_site_manager
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_site_manager
*/

include_once("include/classes.php");
include_once("include/functions.php");

add_action('cron_base', 'cron_site_manager', mt_rand(1, 10));

if(is_admin())
{
	register_uninstall_hook(__FILE__, 'uninstall_site_manager');

	add_action('admin_menu', 'menu_site_manager');
	add_action('admin_init', 'settings_site_manager');

	if(is_multisite())
	{
		add_filter('manage_sites-network_columns', 'column_header_site_manager', 5);
		add_action('manage_sites_custom_column', 'column_cell_site_manager', 5, 2);
	}

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__)).'/lang/');

	function uninstall_site_manager()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_server_ips_allowed', 'setting_site_comparison', 'mf_server_ip'),
		));
	}
}