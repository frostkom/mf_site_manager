<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_site_manager/include", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

require_once(ABSPATH."wp-admin/includes/plugin.php");
require_once("classes.php");
require_once("functions.php");

$json_output = array();

$type = check_var('type', 'char');

$arr_input = explode("/", $type);

$type_action = $arr_input[0];
$type_table = isset($arr_input[1]) ? $arr_input[1] : "";

$strDataIP = $_SERVER['REMOTE_ADDR'];

$setting_server_ips_allowed = get_option('setting_server_ips_allowed');

if($setting_server_ips_allowed != '' && $setting_server_ips_allowed == $strDataIP)
{
	if($type_action == "compare")
	{
		header("Status: 200 OK");

		include_once("../../mf_log/include/classes.php");
		include_once("../../mf_log/include/functions.php");

		$obj_site_manager = new mf_site_manager();
		$obj_site_manager->get_content_versions();

		$json_output['core'] = $obj_site_manager->arr_core['this'];
		$json_output['themes'] = $obj_site_manager->arr_themes['this'];
		$json_output['plugins'] = $obj_site_manager->arr_plugins['this'];
	}

	else
	{
		header("Status: 503 Unknown action");

		$json_output['error'] = "Wrong type: ".$type_action;
	}
}

else
{
	header("Status: 503 Unknown IP-address");

	$json_output['error'] = "Wrong IP: ".$strDataIP;
}

echo json_encode($json_output);