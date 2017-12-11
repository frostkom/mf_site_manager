<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description: 
Version: 4.9.0
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_site_manager
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_site_manager
*/

include_once("include/classes.php");
include_once("include/functions.php");

$obj_site_manager = new mf_site_manager();

add_action('cron_base', array($obj_site_manager, 'cron'), mt_rand(1, 10));
add_action('cron_base', 'activate_site_manager', mt_rand(1, 10));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_site_manager');
	register_uninstall_hook(__FILE__, 'uninstall_site_manager');

	add_action('admin_menu', 'menu_site_manager');
	add_action('admin_init', 'settings_site_manager');

	if(is_multisite())
	{
		add_filter('manage_sites-network_columns', 'column_header_site_manager', 5);
		add_action('manage_sites_custom_column', 'column_cell_site_manager', 5, 2);
	}

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

function activate_site_manager()
{
	replace_option(array('old' => 'mf_server_ip', 'new' => 'setting_server_ip'));
}

function uninstall_site_manager()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_server_ip', 'setting_server_ips_allowed', 'setting_site_comparison'),
	));
}