<?php
/*
Plugin Name: MF Site Manager
Plugin URI: https://github.com/frostkom/mf_site_manager
Description: 
Version: 4.6.0
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_site_manager
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_site_manager
*/

if(is_admin())
{
	include_once("include/classes.php");
	include_once("include/functions.php");

	add_action('admin_menu', 'menu_site_manager');
	add_action('admin_init', 'settings_site_manager');

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__)).'/lang/');
}