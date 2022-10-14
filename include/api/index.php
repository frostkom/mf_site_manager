<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_site_manager/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

//do_action('run_cache', array('suffix' => 'json'));

if(!isset($obj_site_manager))
{
	$obj_site_manager = new mf_site_manager();
}

$json_output = array();

$type = check_var('type', 'char');

$arr_input = explode("/", $type);

$type_action = $arr_input[0];
$type_table = (isset($arr_input[1]) ? $arr_input[1] : '');

$remote_server_ip = get_current_visitor_ip();

$setting_server_ips_allowed = get_option('setting_server_ips_allowed');
$arr_setting_server_ips_allowed = array_map('trim', explode(",", $setting_server_ips_allowed));

if(count($arr_setting_server_ips_allowed) > 0 && in_array($remote_server_ip, $arr_setting_server_ips_allowed))
{
	if($type_action == "compare")
	{
		header("Status: 200 OK");

		$obj_site_manager = new mf_site_manager();
		$obj_site_manager->get_content_versions();

		$json_output['core'] = $obj_site_manager->arr_core['this'];
		$json_output['themes'] = $obj_site_manager->arr_themes['this'];
		$json_output['plugins'] = $obj_site_manager->arr_plugins['this'];
	}

	else
	{
		header("Status: 503 Unknown action");

		$json_output['error'] = __("Wrong Type", 'lang_site_manager').": ".$type_action;
	}
}

else
{
	header("Status: 503 Unknown IP-address");

	$json_output['error'] = __("Wrong IP", 'lang_site_manager').": ".$remote_server_ip;
}

echo json_encode($json_output);