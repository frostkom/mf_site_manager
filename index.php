<?php
/*
Plugin Name: MF Site Manager
Plugin URI: 
Description: 
Version: 4.1.0
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_site_manager
Domain Path: /lang
*/

if(is_admin())
{
	include_once("include/classes.php");
	include_once("include/functions.php");

	add_action('admin_menu', 'menu_cloner');
	add_action('admin_init', 'settings_cloner');

	load_plugin_textdomain('lang_site_manager', false, dirname(plugin_basename(__FILE__)).'/lang/');
}