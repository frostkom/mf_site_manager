<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description: 
Version: 5.2.9
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_site_manager
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_site_manager
*/

include_once("include/classes.php");

$obj_site_manager = new mf_site_manager();

add_action('cron_base', 'activate_site_manager', mt_rand(1, 10));
add_action('cron_base', array($obj_site_manager, 'cron_base'), mt_rand(1, 10));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_site_manager');
	register_uninstall_hook(__FILE__, 'uninstall_site_manager');

	add_action('admin_init', array($obj_site_manager, 'admin_init'), 0);
	add_action('admin_init', array($obj_site_manager, 'settings_site_manager'));
	add_action('admin_menu', array($obj_site_manager, 'admin_menu'));

	if(is_multisite())
	{
		add_filter('manage_sites-network_columns', array($obj_site_manager, 'column_header'), 5);
		add_action('manage_sites_custom_column', array($obj_site_manager, 'column_cell'), 5, 2);
		add_filter('manage_sites_action_links', array($obj_site_manager, 'sites_row_actions'));

		add_action('admin_footer', array($obj_site_manager, 'admin_footer'), 0);
	}

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

add_action('wp_ajax_force_server_ip', array($obj_site_manager, 'force_server_ip'));

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