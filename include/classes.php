<?php

class mf_site_manager
{
	var $arr_core = [];
	var $arr_themes = [];
	var $arr_plugins = [];
	var $arr_sites = [];
	var $arr_sites_error = [];
	var $server_ip_old = "";
	var $server_ip_new = "";
	var $arr_errors = [];
	var $blog_id = "";
	var $site_backup = "";
	var $keep_title = "";
	var $keep_language;
	var $empty_plugins = "";
	var $site_url = "";
	var $new_url = "";
	var $site_url_clean = "";
	var $new_url_clean = "";
	var $compare_site_url = "";
	var $compare_site_key = "";
	var $site_theme = "";
	var $current_theme = "";
	var $table_action = "";
	var $table_prefix = "";
	var $table_prefix_destination = "";
	var $uploads_amount = 0;
	var $compare_uri = "/wp-content/plugins/mf_site_manager/include/api/?type=compare";
	var $echoed;
	var $type;
	var $is_multisite;
	var $file_dir_from;
	var $file_dir_to;
	var $editor_block_parts = array('wp_global_styles', 'wp_template', 'wp_template_part');

	function __construct(){}

	function get_sites_for_select($data = [])
	{
		if(!isset($data['exclude'])){	$data['exclude'] = [];}

		$arr_data = array(
			'' => "-- ".__("Choose Here", 'lang_site_manager')." --"
		);

		$result = get_sites(array('site__not_in' => $data['exclude'], 'deleted' => 0, 'orderby' => 'domain'));

		foreach($result as $r)
		{
			$blog_id = $r->blog_id;
			$domain = $r->domain;
			$path = $r->path;

			$arr_data[$blog_id] = get_blog_option($blog_id, 'blogname')." (".trim($domain.$path, "/").")";
		}

		return $arr_data;
	}

	function get_themes_for_select()
	{
		$arr_data = [];

		foreach(wp_get_themes(array('errors' => false, 'allowed' => true)) as $key => $value)
		{
			$arr_data[$key] = $value['Name'];
		}

		return $arr_data;
	}

	function get_table_action_for_select()
	{
		$arr_data = array(
			'' => "-- ".__("Choose Here", 'lang_site_manager')." --",
			'create_copy' => __("Create Copy", 'lang_site_manager'),
			'replace' => __("Replace", 'lang_site_manager'),
		);

		return $arr_data;
	}

	function get_tables_for_select()
	{
		global $wpdb, $obj_base;

		$first_wp_table = "_commentmeta";

		$arr_data = array(
			'' => "-- ".__("Choose Here", 'lang_site_manager')." --",
		);

		$result = $wpdb->get_results("SHOW TABLES", ARRAY_N);

		$table_prefix = "";
		$table_size = 0;

		foreach($result as $r)
		{
			$table_name = $r[0];

			if(preg_match('/'.$first_wp_table.'$/', $table_name))
			{
				$table_prefix = str_replace($first_wp_table, "", $table_name);
				$table_size = 0;
			}

			$table_size += $wpdb->get_var($wpdb->prepare("SELECT (DATA_LENGTH + INDEX_LENGTH) FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", DB_NAME, $table_name));

			if($table_prefix != '')
			{
				if(is_multisite())
				{
					$site_id = str_replace($wpdb->base_prefix, "", $table_prefix);

					if(!($site_id > 0))
					{
						$site_id = 1;
					}

					$site_name = get_blog_option($site_id, 'blogname');
				}

				else
				{
					$site_name = get_bloginfo('name');
				}

				$arr_data[$table_prefix] = $site_name." (".$table_prefix.", ".show_final_size($table_size).")";
			}
		}

		$arr_data = $obj_base->array_sort(array('array' => $arr_data, 'order' => 'asc', 'keep_index' => true));

		return $arr_data;
	}

	function get_language_code($language)
	{
		switch($language)
		{
			case 'da-DK':
			case 'da_DK':
				return "dk";
			break;

			case 'de-DE':
			case 'de_DE':
				return "de";
			break;

			case 'nn-NO':
			case 'nb-NO':
			case 'nn_NO':
			case 'nb_NO':
				return "no";
			break;

			case 'sv-SE':
			case 'sv_SE':
				return "se";
			break;

			case 'en-UK':
			case 'en_UK':
				return "uk";
			break;

			case 'en-US':
			case 'en_US':
			case '':
				return "us";
			break;

			default:
				if($id > 0)
				{
					do_log("Someone chose '".$blog_language."' as the language for the site '".$id."'. Please add the flag for this language");
				}

				else
				{
					do_log("Someone chose '".$blog_language."' as the language. Please add the flag for this language");
				}

				return "";
			break;
		}
	}

	function get_flag_image($id = 0)
	{
		global $wpdb;

		if($id > 0)
		{
			switch_to_blog($id);

			$blog_language = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM ".$wpdb->options." WHERE option_name = %s", 'WPLANG'));

			restore_current_blog();
		}

		else
		{
			$blog_language = get_bloginfo('language');
		}

		$language_code = $this->get_language_code($blog_language);

		$plugin_url = str_replace("/include", "", plugin_dir_url(__FILE__));

		return $plugin_url."images/flags/flag_".$language_code.".png";
	}

	function get_site_status()
	{
		if(get_option('setting_activate_maintenance') == 'yes' && get_option('setting_maintenance_page') > 0)
		{
			return 'maintenance_mode';
		}

		else if(get_option('setting_no_public_pages') == 'yes')
		{
			return 'not_public';
		}

		else if(get_option('setting_theme_core_login') == 'yes')
		{
			return 'requires_login';
		}

		else if(get_option('blog_public') == 0)
		{
			return 'no_index';
		}

		else
		{
			return 'public';
		}
	}

	function get_site_status_data($data = [])
	{
		$status = $this->get_site_status();

		$arr_out = array(
			'status' => $status,
			'url' => "",
			'color' => "",
			'icon' => "",
			'text' => "",
		);

		switch($status)
		{
			case 'maintenance_mode':
				$arr_out['url'] = admin_url("options-general.php?page=settings_mf_base#settings_theme_core_public");
				$arr_out['color'] = "color_red";
				$arr_out['icon'] = "fas fa-hard-hat";
				$arr_out['text'] = __("Maintenance Mode Activated", 'lang_site_manager');
			break;

			case 'not_public':
				if($data['type'] == 'admin_bar')
				{
					global $wp_admin_bar;

					$wp_admin_bar->remove_menu('site-name');
				}

				$arr_out['color'] = "color_red";
				$arr_out['icon'] = "fas fa-eye-slash";
				$arr_out['text'] = __("No Public Pages", 'lang_site_manager');
			break;

			case 'requires_login':
				$arr_out['url'] = get_home_url();
				$arr_out['color'] = "color_red";
				$arr_out['icon'] = "fas fa-user-lock";
				$arr_out['text'] = __("Requires Login", 'lang_site_manager');
			break;

			case 'no_index':
				$arr_out['url'] = get_home_url();
				$arr_out['color'] = "color_yellow";
				$arr_out['icon'] = "fas fa-robot";
				$arr_out['text'] = __("No Index", 'lang_site_manager');
			break;

			default:
			case 'public':
				$arr_out['url'] = get_home_url();
				$arr_out['color'] = "color_green";
				$arr_out['icon'] = "fas fa-eye";
				$arr_out['text'] = __("Public", 'lang_site_manager');
			break;
		}

		return $arr_out;
	}

	function wp_before_admin_bar_render()
	{
		global $wp_admin_bar;

		if(is_multisite())
		{
			if(!IS_SUPER_ADMIN && count($wp_admin_bar->user->blogs) < 2)
			{
				$wp_admin_bar->remove_menu('my-sites');
			}

			$wp_admin_bar->remove_menu('site-editor');
		}

		/*else
		{
			$wp_admin_bar->remove_menu('updates');
		}*/

		$wp_admin_bar->remove_menu('site-name');

		if(is_admin())
		{
			$arr_site_status = $this->get_site_status_data(array('type' => 'admin_bar'));

			$flag_image = $this->get_flag_image();

			$title = "";

			if($arr_site_status['url'] != '')
			{
				$title .= "<a href='".$arr_site_status['url']."' class='".$arr_site_status['color']."'>";
			}

			else
			{
				$title .= "<span class='".$arr_site_status['color']."'>";
			}

				if($flag_image != '')
				{
					$title .= "<div class='flex_flow tight'>
						<img src='".$flag_image."'>
						<span>";
				}

					$title .= $arr_site_status['text'];

				if($flag_image != '')
				{
						$title .= "</span>
					</div>";
				}

			if($arr_site_status['url'] != '')
			{
				$title .= "</a>";
			}

			else
			{
				$title .= "</span>";
			}

			$wp_admin_bar->add_node(array(
				'id' => 'live',
				'title' => $title,
			));
		}

		else
		{
			$arr_site_status = $this->get_site_status_data(array('type' => 'admin_bar'));

			$flag_image = $this->get_flag_image();

			$title = "<a href='".admin_url("index.php")."'>";

				if($flag_image != '')
				{
					$title .= "<div class='flex_flow tight'>
						<img src='".$flag_image."'>
						<span>";
				}

					$title .= __("Admin", 'lang_site_manager');

				if($flag_image != '')
				{
						$title .= "</span>
					</div>";
				}

			$title .= "</a>";

			$wp_admin_bar->add_node(array(
				'id' => 'live',
				'title' => $title,
			));
		}

		if($arr_site_status['status'] == 'public')
		{
			$wp_admin_bar->add_node(array(
				'id' => 'sitemap',
				'title' => "<a href='".get_site_url()."/sitemap.xml'>".__("Sitemap", 'lang_site_manager')."</a>",
			));
		}
	}

	function admin_bar_menu()
	{
		global $wp_admin_bar;

		if(count($wp_admin_bar->user->blogs) > 1)
		{
			$main_site_id = get_main_site_id();

			$arr_names = [];
			$arr_sites = $wp_admin_bar->user->blogs;

			foreach($arr_sites as $site_id => $site)
			{
				$arr_names[$site_id] = strtoupper($site->blogname);

				if($site_id == $main_site_id)
				{
					$wp_admin_bar->user->blogs[$site_id]->blogname .= " <i class='far fa-star yellow'></i>";
				}
			}

			asort($arr_names);

			$wp_admin_bar->user->blogs = [];

			foreach($arr_names as $site_id => $name)
			{
				$wp_admin_bar->user->blogs[$site_id] = $arr_sites[$site_id];
			}
		}
	}

	function admin_init()
	{
		global $pagenow;

		$page = check_var('page');

		$plugin_include_url = plugin_dir_url(__FILE__);

		switch($pagenow)
		{
			case 'admin.php':
				if($page == 'mf_site_manager/tables/index.php')
				{
					mf_enqueue_script('script_site_manager_tables', $plugin_include_url."script_wp_tables.js");
				}
			break;

			case 'my-sites.php':
				if(is_multisite() && IS_SUPER_ADMIN)
				{
					mf_redirect(network_admin_url("sites.php")); //?status=public // Removed because this will also filter out active not published sites
				}
			break;

			case 'options-general.php':
				if($page == 'settings_mf_base')
				{
					mf_enqueue_script('script_site_manager_settings', $plugin_include_url."script_wp_settings.js", array(
						'ajax_url' => admin_url('admin-ajax.php'),
						'loading_animation' => apply_filters('get_loading_animation', ''),
					));
				}
			break;

			case 'sites.php':
				mf_enqueue_script('script_site_manager_sites', $plugin_include_url."script_wp_sites.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')));
			break;

			default:
				//do_log("Unknown page: ".$pagenow." -> ".$page);
			break;
		}
	}

	function admin_notices()
	{
		global $done_text, $error_text;

		$setting_site_manager_server_ip_target = get_option('setting_site_manager_server_ip_target');

		if($setting_site_manager_server_ip_target != '')
		{
			$setting_site_manager_server_ip = get_option('setting_site_manager_server_ip');

			if($setting_site_manager_server_ip == $setting_site_manager_server_ip_target)
			{
				$done_text = sprintf(__("This is the new server and now the setting can be removed %shere%s", 'lang_site_manager'), "<a href='".admin_url("options-general.php?page=settings_mf_base#settings_site_manager")."'>", "</a>");
			}

			else
			{
				$error_text = sprintf(__("This is the old server and you can find the setting %shere%s", 'lang_site_manager'), "<a href='".admin_url("options-general.php?page=settings_mf_base#settings_site_manager")."'>", "</a>");

				$error_text .= " (".$setting_site_manager_server_ip." != ".$setting_site_manager_server_ip_target.")";
			}

			echo get_notification();
		}
	}

	function settings_site_manager()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area.'_callback'), BASE_OPTIONS_PAGE);

			$arr_settings = [];

			if(is_multisite() == false)
			{
				$arr_settings['setting_site_manager_multisite'] = __("Convert to MultiSite", 'lang_site_manager');
			}

			$arr_settings['setting_site_manager_server_ip'] = __("Server IP", 'lang_site_manager');
			$arr_settings['setting_site_manager_server_ip_target'] = __("Target Server IP", 'lang_site_manager');
			$arr_settings['setting_site_manager_server_ips_allowed'] = __("Server IPs allowed", 'lang_site_manager');
			$arr_settings['setting_site_manager_site_comparison'] = __("Sites to compare with", 'lang_site_manager');

			if(get_option('setting_site_manager_site_comparison') != '')
			{
				$arr_settings['setting_site_manager_site_clone_path'] = __("Path to Clone to", 'lang_site_manager');
			}

			//$arr_settings['setting_site_manager_template_site'] = __("Template Site", 'lang_site_manager');

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_site_manager_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Site Manager", 'lang_site_manager'));
	}

		function setting_site_manager_multisite_callback()
		{
			$multisite_step_1 = "define('WP_ALLOW_MULTISITE', true);";
			$multisite_step_2 = "define('MULTISITE', true);
			define('SUBDOMAIN_INSTALL', true);
			define('DOMAIN_CURRENT_SITE', (isset(\$_SERVER['HTTP_HOST']) ? \$_SERVER['HTTP_HOST'] : '".get_site_url_clean(array('trim' => "/"))."'));
			define('PATH_CURRENT_SITE', '/');";

			echo "<ol>
				<li>
					<strong>".sprintf(__("Add this to %s", 'lang_site_manager'), "wp-config.php")."</strong>
					<p class='input'>".nl2br(htmlspecialchars($multisite_step_1))."</p>
				</li>
				<li>
					<strong><a href='/wp-admin/plugins.php' rel='external'>".__("Deactivate all plugins", 'lang_site_manager')."</a></strong>
					<br><br>
				</li>
				<li>
					<strong><a href='/wp-admin/network.php' rel='external'>".__("Go to Network Settings", 'lang_site_manager')."</a></strong>
					<p>".__("...and follow the instructions on that page...", 'lang_site_manager')."</p>
				</li>
				<li>
					<strong>".sprintf(__("...but add this to %s instead", 'lang_site_manager'), "wp-config.php")."</strong>
					<p class='input'>".nl2br(htmlspecialchars($multisite_step_2))."</p>
				</li>
				<li>
					<strong><a href='/wp-admin/' rel='external'>".__("Login", 'lang_site_manager')."</a></strong>
					<br><br>
				</li>
				<li>
					<strong><a href='/wp-admin/network/plugins.php' rel='external'>".__("Activate all plugins", 'lang_site_manager')."</a></strong>
					<br><br>
				</li>
			</ul>";
		}

		function setting_site_manager_server_ip_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			if($option == '')
			{
				$obj_site_manager = new mf_site_manager();

				$option = $obj_site_manager->get_server_ip();
			}

			echo "<p>".$option."</p>
			<div".get_form_button_classes().">"
				.show_button(array('type' => 'button', 'name' => 'btnGetServerIP', 'text' => __("Get Server IP", 'lang_site_manager'), 'class' => 'button-secondary'))
			."</div>
			<div class='api_site_manager_force_server_ip'></div>";
		}

		function setting_site_manager_server_ip_target_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			echo show_textfield(array('name' => $setting_key, 'value' => $option));
		}

		function setting_site_manager_server_ips_allowed_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			echo show_textfield(array('name' => $setting_key, 'value' => $option));
		}

		function setting_site_manager_site_comparison_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			$site_url = get_option('siteurl');

			echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $site_url.", ".str_replace("//", "//test.", $site_url)));
		}

		function setting_site_manager_site_clone_path_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => "/live, /test", 'description' => __("The absolute path to receiving WP root", 'lang_site_manager')));
		}

		function setting_site_manager_template_site_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			$placeholder = get_site_url();

			if($option != '')
			{
				if($option == $placeholder)
				{
					$option = "";
				}

				else
				{
					$option = trim($option, "/");
				}
			}

			echo show_textfield(array('type' => 'url', 'name' => $setting_key, 'value' => $option, 'placeholder' => $placeholder));
		}

	function admin_menu()
	{
		$menu_root = 'mf_site_manager/';
		$menu_start = $menu_root."compare/index.php";
		$menu_capability = 'update_core';

		$menu_title = __("Site Manager", 'lang_site_manager');
		add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-images-alt2', 100);

		if(get_option('setting_site_manager_site_comparison') != '')
		{
			$menu_title = __("Compare Sites", 'lang_site_manager');
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."compare/index.php");
		}

		if(IS_SUPER_ADMIN)
		{
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
		}

		if(IS_EDITOR)
		{
			if(get_option('setting_site_manager_site_comparison') != '')
			{
				global $menu, $submenu;

				foreach($menu as $key => $menu_item)
				{
					if(isset($menu_item[2]) && $menu_item[2] == 'themes.php' && isset($submenu[$menu_item[2]]))
					{
						foreach($submenu[$menu_item[2]] as $submenu_key => $submenu_item)
						{
							if(isset($submenu[$menu_item[2]][$submenu_key][2]) && $submenu[$menu_item[2]][$submenu_key][2] == 'site-editor.php')
							{
								$submenu[$menu_item[2]][$submenu_key][0] .= " <i class='fa fa-exclamation-triangle yellow' title='".__("This site is using a template site and any changes in the editor might be overriden", 'lang_site_manager')."'></i>";
								break;
							}
						}
					}
				}
			}

			$menu_title = __("Settings", 'lang_site_manager');
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, admin_url("options-general.php?page=settings_mf_base#settings_site_manager"));
		}
	}

	/* Change URL */
	###########################
	function change_multisite_url()
	{
		global $wpdb;

		@list($new_domain_clean, $new_path_clean) = explode("/", $this->new_url_clean, 2);
		$new_path_clean = "/".($new_path_clean != '' ? trim($new_path_clean, "/")."/" : ''); // Make sure that the path is "/" or "/chosen-path/"

		@list($site_domain_clean, $site_path_clean) = explode("/", $this->site_url_clean, 2);
		$site_path_clean = "/".$site_path_clean;

		if($this->new_url_clean != $this->site_url_clean)
		{
			$wpdb->get_results($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs." WHERE domain = %s AND path = %s AND blog_id != '%d'", $new_domain_clean, $new_path_clean, $wpdb->blogid));

			if($wpdb->num_rows > 0)
			{
				do_log("That URL already exists (".$wpdb->last_query.")");
			}

			else
			{
				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->blogs." SET domain = %s, path = %s WHERE blog_id = '%d'", $new_domain_clean, $new_path_clean, $wpdb->blogid));

				if($wpdb->rows_affected == 0)
				{
					$this->arr_errors[] = $wpdb->last_query;
				}
			}
		}

		$wpdb->get_results($wpdb->prepare("SELECT id FROM ".$wpdb->site." WHERE domain = %s AND path = %s LIMIT 0, 1", $site_domain_clean, $site_path_clean));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->site." SET domain = %s, path = %s WHERE domain = %s AND path = %s", $new_domain_clean, $new_path_clean, $site_domain_clean, $site_path_clean));

			if($wpdb->rows_affected == 0)
			{
				$this->arr_errors[] = $wpdb->last_query;
			}
		}

		$result = $wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->sitemeta." WHERE meta_key = 'siteurl' AND (meta_value = %s OR meta_value = %s)", $this->site_url, $this->site_url."/"));

		if($wpdb->num_rows > 1)
		{
			do_log("Multiple 'siteurl' exists with those values (".$wpdb->last_query.")");
		}

		else
		{
			foreach($result as $r)
			{
				$meta_id = $r->meta_id;

				$this->new_url_temp = substr($this->new_url, -1) == "/" ? $this->new_url : $this->new_url."/";

				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->sitemeta." SET meta_value = %s WHERE meta_key = 'siteurl' AND meta_id = '%d'", $this->new_url_temp, $meta_id));

				if($wpdb->rows_affected == 0)
				{
					$this->arr_errors[] = $wpdb->last_query;
				}
			}
		}
	}

	function change_url()
	{
		global $wpdb;

		//$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->options." SET option_value = REPLACE(option_value, %s, %s) WHERE (option_name = 'home' OR option_name = 'siteurl')", $this->site_url, $this->new_url));
		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->options." SET option_value = REPLACE(option_value, %s, %s) WHERE (option_name = 'home' OR option_name = 'siteurl')", $this->site_url_clean, $this->new_url_clean));
		if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE guid LIKE %s LIMIT 0, 1", "%".$this->site_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET guid = replace(guid, %s, %s)", $this->site_url, $this->new_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_content LIKE %s LIMIT 0, 1", "%".$this->site_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_content = replace(post_content, %s, %s)", $this->site_url, $this->new_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->postmeta." WHERE meta_value LIKE %s LIMIT 0, 1", "%".$this->site_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->postmeta." SET meta_value = replace(meta_value, %s, %s)", $this->site_url, $this->new_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}
	}

	function change_widgets()
	{
		global $wpdb;

		$result = $wpdb->get_results($wpdb->prepare("SELECT option_id, option_name FROM ".$wpdb->options." WHERE option_name LIKE %s AND option_value LIKE %s", "widget_%", "%".$this->site_url."%"));

		if($wpdb->num_rows > 0)
		{
			foreach($result as $r)
			{
				$option_id = $r->option_id;
				$option_name = $r->option_name;

				$option_value = get_option($option_name);

				if(is_array($option_value))
				{
					foreach($option_value as $key => $value)
					{
						$option_value[$key] = str_replace($this->site_url, $this->new_url, $value);
					}
				}

				else
				{
					$option_value = str_replace($this->site_url, $this->new_url, $option_value);
				}

				update_option($option_name, $option_value, false);
			}
		}
	}

	function change_theme_mods()
	{
		$arr_theme_mods = get_theme_mods();

		if(is_array($arr_theme_mods))
		{
			foreach($arr_theme_mods as $key => $value)
			{
				if(is_object($value) || is_array($value))
				{
					// What now?
				}

				else
				{
					$value_new = str_replace($this->site_url, $this->new_url, $value);

					if($value_new != $value)
					{
						set_theme_mod($key, $value_new);
					}
				}
			}
		}
	}
	###########################

	###########################
	function fetch_request()
	{
		// Clone / Switch
		$this->blog_id = check_var('intBlogID');

		// Clone
		if(is_plugin_active("mf_backup/index.php"))
		{
			$this->site_backup = check_var('intSiteBackup');
		}

		$this->keep_title = check_var('strSiteKeepTitle', 'char', true, 'yes');
		$this->keep_language = check_var('strSiteKeepLanguage', 'char', true, 'yes');
		$this->empty_plugins = check_var('strSiteEmptyPlugins', 'char', true, 'no');

		// Change URL
		$this->site_url = get_home_url();
		$this->new_url = check_var('strBlogUrl', 'url', true, $this->site_url);

		$this->site_url_clean = remove_protocol(array('url' => $this->site_url, 'clean' => true));
		$this->new_url_clean = remove_protocol(array('url' => $this->new_url, 'clean' => true));

		// Copy Diff
		$this->compare_site_url = check_var('strSiteURL');
		$this->compare_site_key = check_var('intSiteKey');

		// Theme
		$this->site_theme = check_var('strSiteTheme');

		$this->arr_themes = $this->get_themes_for_select();
		$this->current_theme = get_option('stylesheet');

		// Edit Tables
		$this->table_action = check_var('strTableAction');
		$this->table_prefix = check_var('strTablePrefix');
		$this->table_prefix_destination = check_var('strTablePrefixDestination');
	}

	function copy_file($file_dir_from, $file_dir_to)
	{
		if(file_exists($file_dir_to))
		{
			if(file_exists($file_dir_from))
			{
				// Some files are still in use in the old hierarchy
				//unlink($file_dir_from);
			}
		}

		else
		{
			if(file_exists($file_dir_from))
			{
				if(!file_exists($file_dir_to))
				{
					@mkdir(dirname($file_dir_to), 0755, true);
				}

				if(!copy($file_dir_from, $file_dir_to))
				{
					do_log("File was NOT copied: ".$file_dir_from." -> ".$file_dir_to);
				}
			}
		}
	}

	function save_data()
	{
		global $wpdb, $error_text, $done_text;

		if(isset($_POST['btnSiteChangeUrl']))
		{
			if(!(isset($_POST['intSiteChangeUrlAccept']) && $_POST['intSiteChangeUrlAccept'] == 1))
			{
				$error_text = __("You have to check the box to agree to change the URL", 'lang_site_manager');
			}

			else if(!wp_verify_nonce($_POST['_wpnonce_site_change_url'], 'site_change_url_'.$wpdb->blogid.'_'.get_current_user_id()))
			{
				$error_text = __("I could not verify that you were allowed to change the URL. I this problem persists, please contact an admin", 'lang_site_manager');
			}

			else if($this->new_url != $this->site_url || defined('WP_HOME'))
			{
				$this->arr_errors = [];

				if(is_multisite())
				{
					$this->change_multisite_url();
				}

				$this->change_url();
				$this->change_widgets();
				$this->change_theme_mods();

				$count_temp = count($this->arr_errors);

				if($count_temp > 0)
				{
					$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

					do_log("Errors while changing URL: ".var_export($this->arr_errors, true));
				}

				else
				{
					$done_text = sprintf(__("I have changed the URL from %s to %s. Go to %sDashboard%s", 'lang_site_manager'), $this->site_url, "<a href='".$this->new_url."'>".$this->new_url."</a>", "<a href='".$this->new_url."/wp-admin"."'>", "</a>");

					do_log(sprintf("%s changed the URL from %s to %s", get_user_info(), $this->site_url, $this->new_url), 'notification');
				}
			}

			else
			{
				$error_text = __("You have to choose another URL than the current one", 'lang_site_manager');
			}
		}

		else if(isset($_POST['btnSiteClone']))
		{
			if(!(isset($_POST['intSiteCloneAccept']) && $_POST['intSiteCloneAccept'] == 1))
			{
				$error_text = __("You have to check the box to agree to clone the site", 'lang_site_manager');
			}

			else if(!wp_verify_nonce($_POST['_wpnonce_site_clone'], 'site_clone_'.$wpdb->blogid.'_'.get_current_user_id()))
			{
				$error_text = __("I could not verify that you were allowed to clone the site. I this problem persists, please contact an admin", 'lang_site_manager');
			}

			else if($this->blog_id > 0 && $this->blog_id != $wpdb->blogid)
			{
				if(is_plugin_active("mf_backup/index.php") && $this->site_backup == 1)
				{
					global $obj_backup;

					if(!isset($obj_backup))
					{
						$obj_backup = new mf_backup();
					}

					$success = $obj_backup->do_backup(array('site' => $this->blog_id));
				}

				else
				{
					$success = true;
				}

				if($success == true)
				{
					$str_queries = "";

					/* Get From Table */
					####################
					$strBasePrefixFrom = $wpdb->prefix;
					$strBlogDomainFrom = get_site_url_clean(array('trim' => "/"));

					$arr_tables_from = [];

					$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixFrom."%'");
					$str_queries .= $wpdb->last_query.";\n";

					foreach($result as $r)
					{
						foreach($r as $s)
						{
							$arr_tables_from[] = $s;
						}
					}
					####################

					/* Get To Table */
					####################
					$strBasePrefixTo = ($this->blog_id > 1 ? $wpdb->base_prefix.$this->blog_id."_" : $wpdb->base_prefix);
					$strBlogDomainTo = get_site_url_clean(array('id' => $this->blog_id, 'trim' => "/"));

					$arr_tables_to = [];

					$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixTo."%'");
					$str_queries .= $wpdb->last_query.";\n";

					foreach($result as $r)
					{
						foreach($r as $s)
						{
							$arr_tables_to[] = $s;
						}
					}
					####################

					$str_queries .= "# Tables: ".count($arr_tables_from)." -> ".count($arr_tables_to)."\n";

					if(count($arr_tables_to) == 0)
					{
						$arr_tables_to = array($strBasePrefixTo."commentmeta", $strBasePrefixTo."comments", $strBasePrefixTo."links", $strBasePrefixTo."options", $strBasePrefixTo."postmeta", $strBasePrefixTo."posts", $strBasePrefixTo."terms", $strBasePrefixTo."term_relationships", $strBasePrefixTo."term_taxonomy");
					}

					if(count($arr_tables_from) == 0) // || count($arr_tables_to) == 0
					{
						$error_text = __("There appears to be no tables on the source site", 'lang_site_manager')." (".$strBasePrefixFrom.": ".count($arr_tables_from)." -> ".$strBasePrefixTo.": ".count($arr_tables_to).")";
					}

					else
					{
						/* Clone Tables */
						#######################
						foreach($arr_tables_from as $r)
						{
							$table_name_from = $r;

							$table_name_prefixless = str_replace($strBasePrefixFrom, "", $table_name_from);

							$table_name_to = $strBasePrefixTo.$table_name_prefixless;

							if(in_array($table_name_to, $arr_tables_to))
							{
								if($table_name_prefixless == "options")
								{
									if($this->keep_title == 'yes')
									{
										$strBlogName_orig = $wpdb->get_var("SELECT option_value FROM ".$table_name_to." WHERE option_name = 'blogname'");
									}

									if($this->keep_language == 'yes')
									{
										$strBlogLanguage_orig = $wpdb->get_var("SELECT option_value FROM ".$table_name_to." WHERE option_name = 'WPLANG'");
									}
								}

								$wpdb->query("DROP TABLE IF EXISTS ".$table_name_to);
								$str_queries .= $wpdb->last_query.";\n";

								$wpdb->query("CREATE TABLE IF NOT EXISTS ".$table_name_to." LIKE ".$table_name_from);
								$str_queries .= $wpdb->last_query.";\n";

								$wpdb->query("INSERT INTO ".$table_name_to." (SELECT * FROM ".$table_name_from.")");
								$str_queries .= $wpdb->last_query.";\n";

								if($table_name_prefixless == "options")
								{
									$wpdb->query("UPDATE ".$table_name_to." SET option_value = REPLACE(option_value, '".$strBlogDomainFrom."', '".$strBlogDomainTo."') WHERE (option_name = 'home' OR option_name = 'siteurl')");
									$str_queries .= $wpdb->last_query.";\n";

									$wpdb->query("UPDATE ".$table_name_to." SET option_name = '".$strBasePrefixTo."user_roles' WHERE option_name = '".$strBasePrefixFrom."user_roles'");
									$str_queries .= $wpdb->last_query.";\n";

									if($this->keep_title == 'yes')
									{
										$wpdb->query("UPDATE ".$table_name_to." SET option_value = '".$strBlogName_orig."' WHERE option_name = 'blogname'");
										$str_queries .= $wpdb->last_query.";\n";
									}

									if($this->keep_language == 'yes')
									{
										$wpdb->query("UPDATE ".$table_name_to." SET option_value = '".$strBlogLanguage_orig."' WHERE option_name = 'WPLANG'");
										$str_queries .= $wpdb->last_query.";\n";
									}

									if($this->empty_plugins == 'yes') //isset($_POST['intSiteEmptyPlugins']) && $_POST['intSiteEmptyPlugins'] == 1
									{
										$wpdb->query("UPDATE ".$table_name_to." SET option_value = '' WHERE option_name = 'active_plugins'");
										$str_queries .= $wpdb->last_query.";\n";
									}
								}

								/*else
								{
									$str_queries .= "# Table: ".$table_name_prefixless."\n";
								}*/
							}
						}
						#######################

						/* Clone Files */
						#######################
						$upload_path_global = WP_CONTENT_DIR."/uploads/";
						$upload_url_global = WP_CONTENT_URL."/uploads/";

						if($wpdb->blogid == 1)
						{
							$upload_path_from = $upload_path_global;
							$upload_url_from = $upload_url_global;
						}

						else
						{
							$upload_path_from = $upload_path_global."sites/".$wpdb->blogid."/";
							$upload_url_from = $upload_url_global."sites/".$wpdb->blogid."/";
						}

						if($this->blog_id == 1)
						{
							$upload_path_to = $upload_path_global;
							$upload_url_to = $upload_url_global;
						}

						else
						{
							$upload_path_to = $upload_path_global."sites/".$this->blog_id."/";
							$upload_url_to = $upload_url_global."sites/".$this->blog_id."/";
						}

						$arr_sizes = array('thumbnail', 'medium', 'large');

						$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title FROM ".$wpdb->posts." WHERE post_type = %s", 'attachment'));

						foreach($result as $r)
						{
							$post_id = $r->ID;
							$post_url = wp_get_attachment_url($post_id);

							$file_dir_from = str_replace(array($upload_url_to, $upload_url_from), $upload_path_from, $post_url);
							$file_dir_to = str_replace(array($upload_url_to, $upload_url_from), $upload_path_to, $post_url);

							$this->copy_file($file_dir_from, $file_dir_to);
							$str_queries .= "Copied: ".$file_dir_from." -> ".$file_dir_to."\n";

							if(wp_attachment_is_image($post_id))
							{
								foreach($arr_sizes as $size)
								{
									$arr_image = wp_get_attachment_image_src($post_id, $size);
									$post_url = $arr_image[0];

									$file_dir_from = str_replace(array($upload_url_to, $upload_url_from), $upload_path_from, $post_url);
									$file_dir_to = str_replace(array($upload_url_to, $upload_url_from), $upload_path_to, $post_url);

									$this->copy_file($file_dir_from, $file_dir_to);
									$str_queries .= "Copied: ".$file_dir_from." -> ".$file_dir_to."\n";
								}
							}
						}
						#######################

						$done_text = __("All data was cloned", 'lang_site_manager');
						//$done_text .= " (".$strBasePrefixFrom." -> ".$strBasePrefixTo.")";
						//$done_text .= " [".nl2br($str_queries)."]";

						do_log(sprintf("%s cloned %s to %s", get_user_info(), $strBlogDomainFrom, $strBlogDomainTo), 'notification');
					}
				}

				else
				{
					$error_text = __("The backup was not successful so I could not clone the site for you", 'lang_site_manager');
				}
			}

			else
			{
				$error_text = __("You have to choose a site other than this site", 'lang_site_manager');
			}
		}

		else if(isset($_REQUEST['btnDifferencesCopy']))
		{
			$this->setting_site_manager_site_clone_path = "";

			if($this->compare_site_key != '' && wp_verify_nonce($_REQUEST['_wpnonce_differences_copy'], 'differences_copy_'.$this->compare_site_key))
			{
				$setting_site_manager_site_clone_path = get_option('setting_site_manager_site_clone_path');

				if($setting_site_manager_site_clone_path != '')
				{
					$arr_setting_site_manager_site_clone_path = array_map('trim', explode(",", $setting_site_manager_site_clone_path));

					$this->setting_site_manager_site_clone_path = $arr_setting_site_manager_site_clone_path[$this->compare_site_key];

					if(substr($this->setting_site_manager_site_clone_path, -1) != "/")
					{
						$this->setting_site_manager_site_clone_path .= "/";
					}

					//$done_text = sprintf(__("I am going to copy the differences into %s", 'lang_site_manager'), $arr_setting_site_manager_site_clone_path[$this->compare_site_key]);
				}
			}
		}

		else if(isset($_POST['btnSiteSwitch']))
		{
			if(!(isset($_POST['intSiteSwitchAccept']) && $_POST['intSiteSwitchAccept'] == 1))
			{
				$error_text = __("You have to check the box to agree that the sites should be switched", 'lang_site_manager');
			}

			else if(!wp_verify_nonce($_POST['_wpnonce_site_switch'], 'site_switch_'.$wpdb->blogid.'_'.get_current_user_id()))
			{
				$error_text = __("I could not verify that you were allowed to switch sites. I this problem persists, please contact an admin", 'lang_site_manager');
			}

			else if($this->blog_id > 0 && $this->blog_id != $wpdb->blogid)
			{
				$str_queries = "";

				$strBasePrefixFrom = $wpdb->prefix;
				$strBlogDomainFrom = get_site_url_clean(array('trim' => "/"));

				$arr_tables_from = [];

				$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixFrom."%'");
				$str_queries .= $wpdb->last_query.";\n";

				foreach($result as $r)
				{
					foreach($r as $s)
					{
						$arr_tables_from[] = $s;
					}
				}

				$strBasePrefixTo = ($this->blog_id > 1 ? $wpdb->base_prefix.$this->blog_id."_" : $wpdb->base_prefix);
				$strBlogDomainTo = get_site_url_clean(array('id' => $this->blog_id, 'trim' => "/"));

				$strBlogDomain_temp = "mf_cloner.com";
				$arr_tables_to = [];

				$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixTo."%'");
				$str_queries .= $wpdb->last_query.";\n";

				foreach($result as $r)
				{
					foreach($r as $s)
					{
						$arr_tables_to[] = $s;
					}
				}

				$str_queries .= "# Tables: ".count($arr_tables_from)." -> ".count($arr_tables_to)."\n";

				if(count($arr_tables_from) == 0 || count($arr_tables_to) == 0)
				{
					$error_text = __("There appears to be no tables on either of the sites", 'lang_site_manager')." (".$strBasePrefixFrom.": ".count($arr_tables_from)." -> ".$strBasePrefixTo.": ".count($arr_tables_to).")";
				}

				else
				{
					//Step 1
					$wpdb->query("UPDATE ".$wpdb->blogs." SET domain = REPLACE(domain, '".$strBlogDomainFrom."', '".$strBlogDomain_temp."') WHERE domain LIKE '%".$strBlogDomainFrom."%'");

					//Step 2
					$wpdb->query("UPDATE ".$wpdb->blogs." SET domain = REPLACE(domain, '".$strBlogDomainTo."', '".$strBlogDomainFrom."') WHERE domain LIKE '%".$strBlogDomainTo."%'");

					//Step 3
					$wpdb->query("UPDATE ".$wpdb->blogs." SET domain = REPLACE(domain, '".$strBlogDomain_temp."', '".$strBlogDomainTo."') WHERE domain LIKE '%".$strBlogDomain_temp."%'");

					//Step 1
					foreach($arr_tables_from as $r)
					{
						$table_name = $r;
						$domain_from = $strBlogDomainFrom;
						$domain_to = $strBlogDomain_temp;

						if(substr($table_name, -5) == "posts")
						{
							$wpdb->query("UPDATE ".$table_name." SET guid = REPLACE(guid, '".$domain_from."', '".$domain_to."'), post_content = REPLACE(post_content, '".$domain_from."', '".$domain_to."')");
							$str_queries .= $wpdb->last_query.";\n";
						}

						else if(substr($table_name, -7) == "options")
						{
							$wpdb->query("UPDATE ".$table_name." SET option_value = REPLACE(option_value, '".$domain_from."', '".$domain_to."') WHERE (option_name = 'home' OR option_name = 'siteurl')");
							$str_queries .= $wpdb->last_query.";\n";

							$wpdb->query("UPDATE ".$table_name." SET option_name = '".$strBasePrefixTo."user_roles' WHERE option_name = '".$strBasePrefixFrom."user_roles'");
							$str_queries .= $wpdb->last_query.";\n";
						}
					}

					//Step 2
					foreach($arr_tables_to as $r)
					{
						$table_name = $r;
						$domain_from = $strBlogDomainTo;
						$domain_to = $strBlogDomainFrom;

						if(substr($table_name, -5) == "posts")
						{
							$wpdb->query("UPDATE ".$table_name." SET guid = REPLACE(guid, '".$domain_from."', '".$domain_to."'), post_content = REPLACE(post_content, '".$domain_from."', '".$domain_to."')");
							$str_queries .= $wpdb->last_query.";\n";
						}

						else if(substr($table_name, -7) == "options")
						{
							$wpdb->query("UPDATE ".$table_name." SET option_value = REPLACE(option_value, '".$domain_from."', '".$domain_to."') WHERE (option_name = 'home' OR option_name = 'siteurl')");
							$str_queries .= $wpdb->last_query.";\n";
						}
					}

					//Step 3
					foreach($arr_tables_from as $r)
					{
						$table_name = $r;
						$domain_from = $strBlogDomain_temp;
						$domain_to = $strBlogDomainTo;

						if(substr($table_name, -5) == "posts")
						{
							$wpdb->query("UPDATE ".$table_name." SET guid = REPLACE(guid, '".$domain_from."', '".$domain_to."'), post_content = REPLACE(post_content, '".$domain_from."', '".$domain_to."')");
							$str_queries .= $wpdb->last_query.";\n";
						}

						else if(substr($table_name, -7) == "options")
						{
							$wpdb->query("UPDATE ".$table_name." SET option_value = REPLACE(option_value, '".$domain_from."', '".$domain_to."') WHERE (option_name = 'home' OR option_name = 'siteurl')");
							$str_queries .= $wpdb->last_query.";\n";
						}
					}

					$done_text = __("I have switched all the data on the two domain as you requested.", 'lang_site_manager')." (".$strBasePrefixFrom." -> ".$strBasePrefixTo.")";
					//$done_text .= " [".nl2br($str_queries)."]";

					do_log(sprintf("%s switched %s with %s", get_user_info(), $strBlogDomainFrom, $strBlogDomainTo), 'notification');
				}
			}

			else
			{
				$error_text = __("You have to choose a site other than this site", 'lang_site_manager');
			}
		}

		else if(isset($_POST['btnSiteChangeTheme']))
		{
			$old_theme = $this->current_theme;
			$new_theme = $this->site_theme;

			if(!(isset($_POST['intSiteChangeThemeAccept']) && $_POST['intSiteChangeThemeAccept'] == 1))
			{
				$error_text = __("You have to check the box to agree that the theme should be changed", 'lang_site_manager');
			}

			else if(!wp_verify_nonce($_POST['_wpnonce_site_change_theme'], 'site_change_theme_'.$wpdb->blogid.'_'.get_current_user_id()))
			{
				$error_text = __("I could not verify that you were allowed to switch theme on this site. I this problem persists, please contact an admin", 'lang_site_manager');
			}

			else if(!isset($this->arr_themes[$new_theme]))
			{
				$error_text = __("You have to choose a theme that is allowed for this site", 'lang_site_manager');
			}

			else if($new_theme == $old_theme)
			{
				$error_text = __("You have to choose another theme than the current one", 'lang_site_manager');
			}

			else
			{
				$arr_errors = [];

				update_option('stylesheet', $new_theme, false);

				//Make sure it doesn't already exist before trying to use it since it'll return a duplicate error if that is the case
				$wpdb->query("DELETE FROM ".$wpdb->options." WHERE option_name = 'theme_mods_".$new_theme."'");

				$wpdb->query("UPDATE ".$wpdb->options." SET option_name = 'theme_mods_".$new_theme."' WHERE option_name = 'theme_mods_".$old_theme."'");
				if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}

				$count_temp = count($arr_errors);

				if($count_temp > 0)
				{
					update_option('stylesheet', $old_theme, false);

					$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

					do_log("Change Theme Errors: ".var_export($arr_errors, true));
				}

				else
				{
					$done_text = sprintf(__("I have changed the theme from %s to %s", 'lang_site_manager'), $this->arr_themes[$old_theme], $this->arr_themes[$new_theme]);

					do_log(sprintf("%s changed theme from %s to %s", get_user_info(), $old_theme, $new_theme), 'notification');
				}
			}
		}

		else if(isset($_POST['btnEditTable']))
		{
			if(!(isset($_POST['intEditTableAccept']) && $_POST['intEditTableAccept'] == 1))
			{
				$error_text = __("You have to check the box to agree to perform the action that you have chosen", 'lang_site_manager');
			}

			else if(!wp_verify_nonce($_POST['_wpnonce_edit_table'], 'edit_table_'.$wpdb->blogid.'_'.get_current_user_id()))
			{
				$error_text = __("I could not verify that you were allowed to perform the action that you were requesting. I this problem persists, please contact an admin", 'lang_site_manager');
			}

			else
			{
				switch($this->table_action)
				{
					case 'create_copy':
						$table_prefix_destination = $this->table_prefix."_copy".date("ymd");

						$result = $wpdb->get_results("SHOW TABLES LIKE '".$this->table_prefix."%'", ARRAY_N);

						foreach($result as $r)
						{
							$table_source = $r[0];
							$table_destination = str_replace($this->table_prefix, $table_prefix_destination, $table_source);

							if(does_table_exist($table_destination))
							{
								$error_text = sprintf(__("The table %s already exists", 'lang_site_manager'), $table_destination);

								break;
							}

							else
							{
								$wpdb->query("CREATE TABLE ".$table_destination." LIKE ".$table_source);
								$wpdb->query("INSERT ".$table_destination." SELECT * FROM ".$table_source);
							}
						}

						if($error_text == '')
						{
							$done_text = __("I have copied all the tables for you", 'lang_site_manager');
						}
					break;

					case 'replace':
						if($this->table_prefix == $this->table_prefix_destination)
						{
							$error_text = __("You have to choose another destination than the source table", 'lang_site_manager');
						}

						else
						{
							$site_id = str_replace($wpdb->base_prefix, "", $this->table_prefix);
							$site_url = get_blog_option($site_id, 'siteurl'); //home
							$site_url_clean = remove_protocol(array('url' => $site_url, 'clean' => true));

							$new_id = str_replace($wpdb->base_prefix, "", $this->table_prefix_destination);
							$new_url = get_blog_option($new_id, 'siteurl'); //home
							$new_url_clean = remove_protocol(array('url' => $new_url, 'clean' => true));

							$table_prefix_backup = $this->table_prefix."_backup".date("ymd");

							$result = $wpdb->get_results("SHOW TABLES LIKE '".$this->table_prefix_destination."%'", ARRAY_N);

							foreach($result as $r)
							{
								$table_name = $r[0];
								$table_name_backup = str_replace($this->table_prefix_destination, $table_prefix_backup, $table_name);

								if(does_table_exist($table_name_backup))
								{
									//$done_text = "Drop ".$table_name.", ";
									$wpdb->query("DROP TABLE IF EXISTS ".$table_name);
								}

								else
								{
									$wpdb->query("ALTER TABLE ".$table_name." RENAME ".$table_name_backup);
								}
							}

							$arr_errors = [];

							$result = $wpdb->get_results("SHOW TABLES LIKE '".$this->table_prefix."%'", ARRAY_N);

							foreach($result as $r)
							{
								$table_source = $r[0];
								$table_destination = str_replace($this->table_prefix, $this->table_prefix_destination, $table_source);

								if(does_table_exist($table_destination))
								{
									$error_text = sprintf(__("The table %s already exists", 'lang_site_manager'), $table_destination);

									break;
								}

								else
								{
									//$done_text = "Create and insert ".$table_source." -> ".$table_destination.", ";
									$wpdb->query("CREATE TABLE ".$table_destination." LIKE ".$table_source);
									$wpdb->query("INSERT ".$table_destination." SELECT * FROM ".$table_source);

									switch(str_replace($this->table_prefix_destination, "", $table_destination))
									{
										case 'options':
											$wpdb->query($wpdb->prepare("UPDATE ".$table_destination." SET option_value = REPLACE(option_value, %s, %s) WHERE (option_name = 'home' OR option_name = 'siteurl')", $site_url_clean, $new_url_clean));
											if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}

											$result = $wpdb->get_results($wpdb->prepare("SELECT option_id, option_name FROM ".$table_destination." WHERE option_name LIKE %s AND option_value LIKE %s", "widget_%", "%".$site_url."%"));

											if($wpdb->num_rows > 0)
											{
												foreach($result as $r)
												{
													$option_id = $r->option_id;
													$option_name = $r->option_name;

													$option_value = get_option($option_name);

													if(is_array($option_value))
													{
														foreach($option_value as $key => $value)
														{
															$option_value[$key] = str_replace($site_url, $new_url, $value);
														}
													}

													else
													{
														$option_value = str_replace($site_url, $new_url, $option_value);
													}

													update_option($option_name, $option_value, false);
												}
											}
										break;

										case 'posts':
											$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$table_destination." WHERE guid LIKE %s LIMIT 0, 1", "%".$site_url."%"));

											if($wpdb->num_rows > 0)
											{
												$wpdb->query($wpdb->prepare("UPDATE ".$table_destination." SET guid = replace(guid, %s, %s)", $site_url, $new_url));
												if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
											}

											$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$table_destination." WHERE post_content LIKE %s LIMIT 0, 1", "%".$site_url."%"));

											if($wpdb->num_rows > 0)
											{
												$wpdb->query($wpdb->prepare("UPDATE ".$table_destination." SET post_content = replace(post_content, %s, %s)", $site_url, $new_url));
												if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
											}
										break;

										case 'postmeta':
											$wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$table_destination." WHERE meta_value LIKE %s LIMIT 0, 1", "%".$site_url."%"));

											if($wpdb->num_rows > 0)
											{
												$wpdb->query($wpdb->prepare("UPDATE ".$table_destination." SET meta_value = replace(meta_value, %s, %s)", $site_url, $new_url));
												if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
											}
										break;
									}

									/*function change_theme_mods()
									{
										$arr_theme_mods = get_theme_mods();

										if(is_array($arr_theme_mods))
										{
											foreach($arr_theme_mods as $key => $value)
											{
												if(is_object($value) || is_array($value))
												{
													// What now?
												}

												else
												{
													$value_new = str_replace($site_url, $new_url, $value);

													if($value_new != $value)
													{
														set_theme_mod($key, $value_new);
													}
												}
											}
										}
									}*/
								}
							}

							$count_temp = count($arr_errors);

							if($count_temp > 0)
							{
								$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

								do_log("Errors while changing URL: ".var_export($arr_errors, true));
							}

							if($error_text == '')
							{
								$done_text = __("I have copied all the tables for you", 'lang_site_manager');
							}
						}
					break;

					default:
						do_log("The action ".$this->table_action." needs to be dealt with...");
					break;
				}
			}
		}
	}
	###########################

	/* Admin */
	###########################
	function filter_sites_table_pages($arr_pages)
	{
		$arr_pages['post'] = array(
			'icon' => "fas fa-thumbtack",
			'title' => __("Posts", 'lang_site_manager'),
		);

		$arr_pages['page'] = array(
			'icon' => "fas fa-copy",
			'title' => __("Pages", 'lang_site_manager'),
		);

		$arr_pages['attachment'] = array(
			'icon' => "fas fa-photo-video",
			'title' => __("Media", 'lang_site_manager'),
		);

		return $arr_pages;
	}

	function manage_plugins_columns($cols)
	{
		if(IS_SUPER_ADMIN)
		{
			$cols['activated'] = __("Activated On", 'lang_site_manager');
		}

		return $cols;
	}

	function manage_plugins_custom_column($column_name, $plugin_file, $plugin_data)
	{
		if(IS_SUPER_ADMIN)
		{
			switch($column_name)
			{
				case 'activated':
					if(!is_plugin_active_for_network($plugin_file))
					{
						$arr_sites = [];

						$result = get_sites(array('deleted' => 0));

						foreach($result as $r)
						{
							switch_to_blog($r->blog_id);

							if(is_plugin_active($plugin_file))
							{
								$arr_sites[] = $r->domain.$r->path;
							}

							restore_current_blog();
						}

						$site_amount = count($arr_sites);

						if($site_amount > 0)
						{
							echo "<span title='".implode("\n", $arr_sites)."'>";
						}

							echo $site_amount;

						if($site_amount > 0)
						{
							echo "</span>";
						}
					}
				break;
			}
		}
	}

	function sites_column_header($cols)
	{
		unset($cols['registered']);
		unset($cols['lastupdated']);

		$cols['ssl'] = __("SSL", 'lang_site_manager');
		$cols['settings'] = __("Settings", 'lang_site_manager');
		$cols['pages'] = __("Pages", 'lang_site_manager');
		$cols['site_status'] = __("Status", 'lang_site_manager');
		$cols['theme'] = __("Theme", 'lang_site_manager');
		$cols['email'] = __("E-mail", 'lang_site_manager');
		$cols['last_updated'] = __("Updated", 'lang_site_manager');

		return $cols;
	}

	function sites_column_cell($col, $id)
	{
		global $wpdb, $obj_base;

		if(get_blog_status($id, 'deleted') == 0 && get_blog_status($id, 'archived') == 0)
		{
			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

			switch_to_blog($id);

			switch($col)
			{
				case 'ssl':
					if(substr(get_home_url($id, '/'), 0, 5) == 'https')
					{
						echo "<i class='fa fa-lock fa-2x green'></i>";
					}

					else
					{
						echo "<a href='".get_admin_url($id, "admin.php?page=mf_site_manager/change/index.php")."'>
							<span class='fa-stack fa-lg'>
								<i class='fa fa-lock fa-stack-1x'></i>
								<i class='fa fa-ban fa-stack-2x red'></i>
							</span>
						</a>";
					}
				break;

				case 'settings':
					$arr_settings_types = apply_filters('filter_sites_table_settings', []);

					$out_temp = "";

					foreach($arr_settings_types as $type_key => $arr_settings)
					{
						foreach($arr_settings as $key => $arr_value)
						{
							$color = $icon = $title = "";

							if(is_multisite() && is_main_site($id) || $arr_value['global'] == false)
							{
								$option = ($arr_value['global'] ? get_site_option($key) : get_blog_option($id, $key));

								switch($arr_value['type'])
								{
									case 'bool':
										$color = ($option == 'yes' ? "green" : "red");
										$icon = $arr_value['icon'];
										$title = $arr_value['name'];
									break;

									case 'open':
										$color = ($option == 'open' ? "green" : "red");
										$icon = $arr_value['icon'];
										$title = $arr_value['name'];
									break;

									case 'post':
										$color = ($option > 0 ? "green" : "red");
										$icon = $arr_value['icon'];
										$title = $arr_value['name'];
									break;

									case 'posts':
										$color = (is_array($option) && count($option) > 0 ? "green" : "red");
										$icon = $arr_value['icon'];
										$title = $arr_value['name'];
									break;

									case 'string':
										$color = ($option != '' ? "green" : "red");
										$icon = $arr_value['icon'];
										$title = $arr_value['name'];
									break;

									default:
										do_log("filter_sites_table_settings - Unknown type: ".$arr_value['type']);
									break;
								}
							}

							else
							{
								$color = "grey";
								$icon = $arr_value['icon'];
								$title = $arr_value['name']." (".__("This can only be saved on the main site", 'lang_site_manager').")";
							}

							if($color != '' || $title != '')
							{
								$out_temp .= "<a href='".get_admin_url($id, "options-general.php?page=settings_mf_base#".$type_key)."' data-setting='".$key."' data-color='".$color."'>"
									." <i class='".$arr_value['icon']." ".$color."' title='".$title."'></i>"
								."</a>";
							}
						}
					}

					if($out_temp != '')
					{
						echo "<div class='nowrap'>".$out_temp."</div>
						<div class='row-actions'>"
							."<a class='toggle_all' href='#'>".__("Toggle All", 'lang_site_manager')."</a>"
						."</div>";
					}
				break;

				case 'pages':
					$arr_pages = apply_filters('filter_sites_table_pages', []);

					if(count($arr_pages) > 0)
					{
						echo "<div class='nowrap'>";

							foreach($arr_pages as $key => $arr_value)
							{
								switch_to_blog($id);

								switch($key)
								{
									case 'attachment':
										$post_status = 'inherit';
									break;

									default:
										$post_status = 'publish';
									break;
								}

								$amount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s", $key, $post_status));

								if($amount > 0)
								{
									echo "<a href='".get_admin_url($id, "edit.php?post_type=".$key)."'>"
										." <i class='".$arr_value['icon']." ".($amount > 0 ? "green" : "grey")."' title='".$arr_value['title']." (".$amount.")'></i>"
									."</a>";
								}

								restore_current_blog();
							}

						echo "</div>";
					}
				break;

				case 'site_status':
					$flag_image = $this->get_flag_image($id);

					if($flag_image != '')
					{
						echo "<img src='".$flag_image."' class='alignleft'>&nbsp;";
					}

					$arr_site_status = $this->get_site_status_data(array('type' => 'sites_column'));

					echo "<i class='".$arr_site_status['icon']." fa-2x ".$arr_site_status['color']."' title='".$arr_site_status['text']."'></i>";

					if($arr_site_status['status'] == 'public')
					{
						echo "&nbsp;<a href='".get_site_url($id)."/sitemap.xml'><i class='fas fa-sitemap fa-2x' title='".__("Sitemap", 'lang_site_manager')."'></i></a>";
					}
				break;

				case 'theme':
					echo get_blog_option($id, 'stylesheet');
				break;

				case 'email':
					$admin_email = get_option('admin_email');

					if($admin_email != '')
					{
						list($prefix, $domain) = explode("@", $admin_email);

						echo "<a href='mailto:".$admin_email."'>".$prefix."</a>
						<div class='row-actions'>"
							."@".$domain
						."</div>";
					}
				break;

				case 'last_updated':
					$arr_post_types = $obj_base->get_post_types_for_metabox();
					$last_updated_manual_post_types = array_diff($arr_post_types, apply_filters('filter_last_updated_post_types', [], 'manual'));

					$result = $wpdb->get_results("SELECT ID, post_title, post_modified FROM ".$wpdb->posts." WHERE post_type IN ('".implode("','", $last_updated_manual_post_types)."') AND post_status != 'auto-draft' ORDER BY post_modified DESC LIMIT 0, 1");

					foreach($result as $r)
					{
						$post_id_manual = $r->ID;
						$post_title = ($r->post_title != '' ? $r->post_title : "(".__("unknown", 'lang_site_manager').")");
						$post_modified_manual = $r->post_modified;

						if($post_modified_manual > DEFAULT_DATE)
						{
							$row_actions = "";

							echo format_date($post_modified_manual);

							$row_actions .= ($row_actions != '' ? " | " : "")."<a href='".admin_url("post.php?action=edit&post=".$post_id_manual)."'>".shorten_text(array('string' => get_the_title($post_id_manual), 'limit' => 10))."</a>";

							$last_updated_automatic_post_types = array_diff($arr_post_types, apply_filters('filter_last_updated_post_types', array('post', 'page'), 'auto'));

							$result_auto = $wpdb->get_results("SELECT ID, post_title, post_modified FROM ".$wpdb->posts." WHERE post_type IN ('".implode("','", $last_updated_automatic_post_types)."') ORDER BY post_modified DESC LIMIT 0, 1");

							foreach($result_auto as $r)
							{
								$post_id_auto = $r->ID;
								$post_title = ($r->post_title != '' ? $r->post_title : "(".__("unknown", 'lang_site_manager').")");
								$post_modified_auto = $r->post_modified;

								if($post_modified_auto > $post_modified_manual)
								{
									$row_actions .= ($row_actions != '' ? " | " : "").__("Background", 'lang_site_manager').": ".format_date($post_modified_auto)." (<a href='".admin_url("post.php?action=edit&post=".$post_id_auto)."'>".shorten_text(array('string' => $post_title, 'limit' => 10))."</a>)";
								}

								if($row_actions != '')
								{
									echo "<div class='row-actions'>"
										.$row_actions
									."</div>";
								}
							}
						}

						/*else
						{
							do_log("last_updated: ".$wpdb->last_query);
						}*/
					}
				break;
			}
		}
	}

	function sites_row_actions($arr_actions)
	{
		unset($arr_actions['archive']);
		unset($arr_actions['spam']);

		return $arr_actions;
	}

	function admin_footer()
	{
		$screen = get_current_screen();

		if(in_array($screen->base, array('site-info-network', 'site-settings-network')))
		{
			$blog_id = check_var('id', 'int');

			$plugin_include_url = plugin_dir_url(__FILE__);

			mf_enqueue_script('script_site_manager_url', $plugin_include_url."script_wp_url.js", array('change_url_link' => get_admin_url($blog_id, "admin.php?page=mf_site_manager/change/index.php"), 'change_url_text' => __("Change URL", 'lang_site_manager')));
		}
	}
	###########################

	function count_uploads_callback($data)
	{
		$this->uploads_amount++;
	}

	function get_block_parts($theme_slug)
	{
		global $wpdb;

		$array = [];

		$site_icon = get_option('site_icon');

		if($site_icon > 0)
		{
			$site_icon_url = mf_get_post_content($site_icon, 'guid');

			$array['favicon'] = $site_icon_url;
		}

		$result = $wpdb->get_results("SELECT post_name, post_type, post_title, post_content, post_modified FROM ".$wpdb->posts." WHERE post_type IN ('".implode("', '", $this->editor_block_parts)."') AND post_status = 'publish'");

		foreach($result as $r)
		{
			if($r->post_type != 'wp_global_styles' || $r->post_name == 'wp-global-styles-'.$theme_slug)
			{
				$array[$r->post_name] = array(
					'post_type' => $r->post_type,
					'post_title' => $r->post_title,
					//'post_content' => utf8_encode($r->post_content),
					'post_content' => mb_convert_encoding($r->post_content, 'UTF-8', 'ISO-8859-1'),
					//'post_modified' => $r->post_modified,
				);
			}
		}

		return $array;
	}

	function get_content_versions()
	{
		global $wpdb;

		list($upload_path, $upload_url) = get_uploads_folder();

		$this->uploads_amount = 0;
		get_file_info(array('path' => $upload_path, 'callback' => array($this, 'count_uploads_callback')));

		$this->arr_core['this'] = array(
			'version' => get_bloginfo('version'),
			'is_multisite' => is_multisite(),
			'uploads' => $this->uploads_amount,
		);

		// Themes
		#############################
		$arr_themes_this_site = [];

		$current_theme = wp_get_theme();
		$theme_name = $current_theme->get('Name');
		$theme_version = $current_theme->get('Version');

		$theme_slug = get_stylesheet();

		$arr_data_this = [];

		switch($theme_slug)
		{
			case 'mf_parallax':
			case 'mf_theme':
				$arr_data_this['array'] = get_theme_mods();
			break;

			case 'twentytwentyfour':
			case 'twentytwentyfive':
				$arr_data_this['array'] = $this->get_block_parts($theme_slug);
			break;
		}

		$arr_themes_this_site[$theme_slug] = array(
			'name' => $theme_name,
			'dir' => $theme_slug,
			'version' => $theme_version,
			'data' => $arr_data_this,
		);

		$this->arr_themes['this'] = $arr_themes_this_site;
		#############################

		// Plugins
		#############################
		$arr_plugins = get_plugins();
		$arr_plugins_this_site = [];

		foreach($arr_plugins as $key => $value)
		{
			$arr_data_this = [];

			switch($key)
			{
				case 'mf_log/index.php':
					/*$tbl_group = new mf_log_table();

					$tbl_group->select_data(array(
						'select' => "ID",
						'debug' => true,
						'debug_type' => 'log',
					));*/

					$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status NOT IN ('notification', 'ignore', 'trash')", 'mf_log'));

					$arr_data_this['value'] = $wpdb->num_rows;
					$arr_data_this['link'] = admin_url("admin.php?page=mf_log/list/index.php");
				break;
			}

			list($plugin_dir, $plugin_file) = explode("/", $key);

			$arr_plugins_this_site[$key] = array(
				'name' => $value['Name'],
				'dir' => $plugin_dir,
				'is_active' => is_plugin_active($key),
				'version' => $value['Version'],
				'data' => $arr_data_this,
			);
		}

		$this->arr_plugins['this'] = $arr_plugins_this_site;
		#############################
	}

	function get_sites($setting_site_manager_site_comparison)
	{
		if($setting_site_manager_site_comparison != '')
		{
			$this->arr_sites = array_map('trim', explode(",", $setting_site_manager_site_comparison));
		}

		$count_temp = count($this->arr_sites);

		if($count_temp > 0)
		{
			for($i = 0; $i < $count_temp; $i++)
			{
				$this->arr_sites[$i] = $site = $this->arr_sites[$i];

				$site_ajax = $site.$this->compare_uri;

				list($content, $headers) = get_url_content(array('url' => $site_ajax, 'catch_head' => true));

				if($headers['http_code'] == 200)
				{
					$arr_content = json_decode($content, true);

					$this->arr_core[$site] = (isset($arr_content['core']) ? $arr_content['core'] : '');
					$this->arr_themes[$site] = (isset($arr_content['themes']) ? $arr_content['themes'] : '');
					$this->arr_plugins[$site] = (isset($arr_content['plugins']) ? $arr_content['plugins'] : '');
				}

				else
				{
					$this->arr_sites_error[$site] = array('headers' => $headers, 'content' => $content);

					unset($this->arr_sites[$i]);
				}
			}
		}
	}

	function get_type_url($site, $plugin_name = '')
	{
		$site = trim($site, "/");

		if($this->type == 'plugins' && $plugin_name != '')
		{
			return validate_url($site."/wp-admin".($this->is_multisite ? "/network" : "")."/plugin-install.php?tab=search&s=".$plugin_name);
		}

		else
		{
			return validate_url($site."/wp-admin".($this->is_multisite ? "/network" : "")."/update-core.php");
		}
	}

	/*function clear_domain_from_urls($url, $domain)
	{
		$url = remove_protocol(array('url' => $url));
		$domain = remove_protocol(array('url' => $domain));

		$url = str_replace($domain, "[".__("domain", 'lang_site_manager')."]", $url);

		return $url;
	}*/

	function upload_image_from_url_to_media_library($image_url)
	{
		require_once(ABSPATH.'wp-admin/includes/file.php');
		require_once(ABSPATH.'wp-admin/includes/image.php');
		require_once(ABSPATH.'wp-admin/includes/media.php');

		$temp_file = download_url($image_url);

		if(is_wp_error($temp_file))
		{
			return $temp_file->get_error_message();
		}

		$file = array(
			'name' => basename($image_url),
			'type' => mime_content_type($temp_file),
			'tmp_name' => $temp_file,
			'error' => 0,
			'size' => filesize($temp_file),
		);

		$file_id = media_handle_sideload($file);

		if(is_wp_error($file_id))
		{
			@unlink($temp_file);
			return $file_id->get_error_message();
		}

		return $file_id;
	}

	function replace_content($post_content_parent, $post_content_child = "")
	{
		if($post_content_child != '')
		{
			$navigation_id = (int)get_match("/wp\:mf\/navigation \{\"navigation_id\"\:\"(.*?)\"\}/is", $post_content_child, false);

			if($navigation_id > 0)
			{
				$post_content_parent = preg_replace("/wp\:mf\/navigation \{\"navigation_id\"\:\"(.*?)\"\}/is", 'wp:mf/navigation {"navigation_id":"'.$navigation_id.'"}', $post_content_parent);
			}
		}

		return $post_content_parent;
	}

	function check_version($type)
	{
		global $wpdb, $done_text, $error_text;

		$out = "";

		$site_url = get_site_url();

		$column_count = 1;
		$columns_total = (count($this->arr_sites) + 2);

		$this->echoed = false;
		$this->type = $type;

		switch($this->type)
		{
			case 'themes':
				$array = $this->arr_themes;

				if(IS_SUPER_ADMIN)
				{
					//$out .= "Test ".__LINE__.": ".var_export($array, true);
				}
			break;

			case 'plugins':
				$array = $this->arr_plugins;
			break;

			default:
				$array = [];
			break;
		}

		foreach($array['this'] as $key => $arr_value)
		{
			/*if(isset($array['this'][$key]))
			{*/
				$name = $arr_value['name'];
				$directory = $arr_value['dir'];
				$is_active = (isset($arr_value['is_active']) ? ($arr_value['is_active'] ? 'yes' : 'no') : '');
				$version = $arr_value['version'];
				$arr_data_this = (isset($arr_value['data']) ? $arr_value['data'] : []);

				$has_equal_version = true;

				$out_temp = "";

				if(IS_SUPER_ADMIN && $this->type == 'themes')
				{
					//$out .= "Test ".__LINE__.": ".var_export($arr_value, true);
				}

				$out_temp .= "<td title='".$directory."'>".$name."</td>
				<td rel='version'>
					<span".($this->type == 'plugins' && $is_active == 'no' ? " class='grey' style='text-decoration: line-through' title='".__("Inactive", 'lang_site_manager')."'" : "").">"
						.$version
					."</span>
				</td>";

				foreach($this->arr_sites as $site)
				{
					$out_temp .= "<td title='".$site.": ".$key."'>";

						if(isset($array[$site][$key]))
						{
							$is_active_check = (isset($array[$site][$key]['is_active']) ? ($array[$site][$key]['is_active'] ? 'yes' : 'no') : '');
							$version_check = $array[$site][$key]['version'];
							$this->is_multisite = $this->arr_core[$site]['is_multisite'];

							$out_temp .= $this->get_version_check_cell(array('version' => $version, 'version_check' => $version_check, 'is_active_check' => $is_active_check, 'link' => $this->get_type_url($site), 'dir' => $this->type."/".$array[$site][$key]['dir']));

							if($version_check != $version || $is_active != $is_active_check)
							{
								$has_equal_version = false;
							}

							if(count($arr_data_this) == 0)
							{
								unset($array[$site][$key]);
							}
						}

						else
						{
							$has_equal_version = false;

							$out_temp .= "<a href='".$this->get_type_url($site, $name)."' class='italic'>(".__("does not exist", 'lang_site_manager').")</a>"
							.$this->copy_differences(array('dir' => $this->type."/".$directory));
						}

					$out_temp .= "</td>";
				}

				if(count($arr_data_this) > 0)
				{
					$out_temp .= "<tr rel='data'>
						<td>
							<i class='fa fa-info-circle fa-lg blue'></i>
						</td>";

						if(isset($arr_data_this['value']))
						{
							$out_temp .= "<td>"
								.($arr_data_this['value'] > 0 ? "<i class='fa fa-times fa-lg red'></i> <a href='".$arr_data_this['link']."'>".$arr_data_this['value']."</a>" : "<i class='fa fa-check fa-lg green'></i>")
							."</td>";

							foreach($this->arr_sites as $site)
							{
								$arr_data_remote = (isset($array[$site][$key]['data']) ? $array[$site][$key]['data'] : []);

								if(count($arr_data_remote) > 0)
								{
									$out_temp .= "<td title='".$key."'>";

										if($arr_data_remote['value'] > 0)
										{
											$out_temp .= "<i class='fa fa-times fa-lg red'></i> <a href='".$arr_data_remote['link']."'>".$arr_data_remote['value']."</a>";

											$has_equal_version = false;
										}

										else
										{
											$out_temp .= "<i class='fa fa-check fa-lg green'></i>";
										}

									$out_temp .= "</td>";
								}

								else
								{
									$out_temp .= "<td title='".$key."'><a href='".$this->get_type_url($site)."' class='italic'>(".__("does not exist", 'lang_site_manager').")</a></td>";

									$has_equal_version = false;
								}

								unset($array[$site][$key]);
							}
						}

						else if(isset($arr_data_this['array']))
						{
							$arr_exclude = array('style_source');

							$out_temp .= "<td></td>";

							foreach($this->arr_sites as $site)
							{
								$arr_data_remote = (isset($array[$site][$key]['data']) ? $array[$site][$key]['data'] : []);

								if(count($arr_data_remote) > 0)
								{
									$out_li_temp = "";

									$arr_data_keys = [];

									foreach($arr_data_this['array'] as $key_this => $rest)
									{
										if(!isset($arr_data_keys[$key_this]))
										{
											$arr_data_keys[$key_this] = '';
										}
									}

									foreach($arr_data_remote['array'] as $key_remote => $rest)
									{
										if(!isset($arr_data_keys[$key_remote]))
										{
											$arr_data_keys[$key_remote] = '';
										}
									}

									foreach($arr_data_keys as $key_all => $rest)
									{
										$arr_value_remote = (isset($arr_data_remote['array'][$key_all]) ? $arr_data_remote['array'][$key_all] : '');
										$arr_value_this = (isset($arr_data_this['array'][$key_all]) ? $arr_data_this['array'][$key_all] : '');

										//$arr_value_remote = $this->clear_domain_from_urls($arr_value_remote, $site_url);
										//$arr_value_this = $this->clear_domain_from_urls($arr_value_this, "//".$site);

										if($key_all == 'favicon')
										{
											$arr_value_remote_orig = $arr_value_remote;
											$arr_value_this_orig = $arr_value_this;

											$arr_value_remote = basename($arr_value_remote);
											$arr_value_this = basename($arr_value_this);

											if(isset($_POST['btnFaviconCopy']) && isset($_POST['_wpnonce_favicon_copy']) && wp_verify_nonce($_POST['_wpnonce_favicon_copy'], 'favicon_copy_'.get_current_user_id()))
											{
												$result = $this->upload_image_from_url_to_media_library($arr_value_remote_orig);

												if(is_numeric($result))
												{
													update_option('site_icon', $result, true);

													$arr_value_this_orig = $arr_value_remote_orig;
													$arr_value_this = $arr_value_remote;

													$done_text = __("Image uploaded successfully!", 'lang_site_manager');
												}

												else
												{
													$error_text = __("Error uploading image", 'lang_site_manager')." (".var_export($result, true).")";
												}

												$out_temp .= get_notification();
											}
										}

										$is_different = ($arr_value_remote != $arr_value_this);

										if(isset($arr_value_this['post_type']) && in_array($arr_value_this['post_type'], $this->editor_block_parts) && isset($arr_value_this['post_content']))
										{
											$arr_value_this['post_content'] = mb_convert_encoding($arr_value_this['post_content'], 'ISO-8859-1', 'UTF-8');
										}

										if(isset($arr_value_remote['post_type']) && in_array($arr_value_remote['post_type'], $this->editor_block_parts) && isset($arr_value_remote['post_content']))
										{
											$arr_value_remote['post_content'] = mb_convert_encoding($arr_value_remote['post_content'], 'ISO-8859-1', 'UTF-8');

											$arr_value_remote['post_content'] = $this->replace_content($arr_value_remote['post_content'], (isset($arr_value_this['post_content']) ? $arr_value_this['post_content'] : ''));
										}

										if(isset($arr_value_remote['post_content']) && isset($arr_value_this['post_content']) && $arr_value_remote['post_content'] == $arr_value_this['post_content'])
										{
											$is_different = false;
										}

										if(!in_array($key_all, $arr_exclude) && $is_different)
										{
											if(is_array($arr_value_remote) || is_array($arr_value_this))
											{
												$out_li_temp .= "<li rel='".__LINE__.": ".$key_all."'>
													<i class='fa fa-times fa-lg red'></i> "
													."<strong>";

														if(isset($arr_value_remote['post_type']) && in_array($arr_value_remote['post_type'], $this->editor_block_parts))
														{
															$out_li_temp .= $arr_value_remote['post_title']." (".$key_all.")";
														}

														else
														{
															$out_li_temp .= $key_all;
														}

													$out_li_temp .= ":</strong> "
													."<span class='color_red'>";

														if(isset($arr_value_remote['post_type']) && in_array($arr_value_remote['post_type'], $this->editor_block_parts))
														{
															if(isset($arr_value_this['post_content']) && $arr_value_this['post_content'] != '')
															{
																if(isset($arr_value_remote['post_content']) && $arr_value_remote['post_content'] != '')
																{
																	if(IS_SUPER_ADMIN)
																	{
																		$out_li_temp .= htmlspecialchars($arr_value_remote['post_content']);
																	}

																	else
																	{
																		$out_li_temp .= strlen($arr_value_remote['post_content']);
																	}
																}

																else
																{
																	$out_li_temp .= "(".__("empty", 'lang_site_manager').")";
																}

																$out_li_temp .= "</span><strong> -> </strong>";

																if(isset($arr_value_this['post_content']) && $arr_value_this['post_content'] != '')
																{
																	if(IS_SUPER_ADMIN)
																	{
																		$out_li_temp .= htmlspecialchars($arr_value_this['post_content']);
																	}

																	else
																	{
																		$out_li_temp .= strlen($arr_value_this['post_content']);
																	}
																}

																else
																{
																	$out_li_temp .= "(".__("empty", 'lang_site_manager').")";
																}
															}

															else
															{
																$out_li_temp .= "(".__("empty", 'lang_site_manager').")";
															}
														}

														else
														{
															$out_li_temp .= var_export($arr_value_remote, true)."</span><strong> -> </strong>".var_export($arr_value_this, true);
														}

														if(isset($arr_value_remote['post_type']) && in_array($arr_value_remote['post_type'], $this->editor_block_parts))
														{
															if(isset($_POST['btnBlockPart_'.$key_all.'_Update']) && isset($_POST['_wpnonce_block_part_'.$key_all.'_update']) && wp_verify_nonce($_POST['_wpnonce_block_part_'.$key_all.'_update'], 'block_part_'.$key_all.'_update_'.get_current_user_id()))
															{
																$post_id = 0;

																$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_content FROM ".$wpdb->posts." WHERE post_name = %s AND post_type = %s", $key_all, $arr_value_remote['post_type']));

																if($wpdb->num_rows > 0)
																{
																	foreach($result as $r)
																	{
																		$post_id = $r->ID;
																		$post_content = $r->post_content;

																		$arr_value_remote['post_content'] = $this->replace_content($arr_value_remote['post_content'], $post_content);

																		$post_data = array(
																			'ID' => $post_id,
																			'post_content' => wp_slash($arr_value_remote['post_content']),
																			'post_modified' => date("Y-m-d H:i:s"),
																		);

																		wp_update_post($post_data);

																		$done_text = __("I updated the data for you", 'lang_site_manager');
																	}
																}

																else
																{
																	$post_data = array(
																		'post_name' => $key_all,
																		'post_title' => $arr_value_remote['post_title'],
																		'post_content' => wp_slash($arr_value_remote['post_content']),
																		'post_type' => $arr_value_remote['post_type'],
																		'post_status' => 'publish',
																	);

																	$post_id = wp_insert_post($post_data);

																	$done_text = __("I inserted the data for you", 'lang_site_manager');
																}

																$out_temp .= get_notification();
															}

															else
															{
																$post_content_exists = (isset($arr_value_this['post_content']) && $arr_value_this['post_content'] != '');

																if($arr_value_remote['post_type'] == 'wp_template' || $post_content_exists == true)
																{
																	$out_li_temp .= "<form method='post' action=''>
																		<div".get_form_button_classes().">"
																			.show_button(array('name' => 'btnBlockPart_'.$key_all.'_Update', 'text' => ($post_content_exists == true ? __("Update", 'lang_site_manager') : __("Create", 'lang_site_manager')), 'xtra' => " rel='confirm'"))
																			.wp_nonce_field('block_part_'.$key_all.'_update_'.get_current_user_id(), '_wpnonce_block_part_'.$key_all.'_update', true, false)
																		."</div>
																	</form>";
																}

																else
																{
																	switch($arr_value_remote['post_type'])
																	{
																		case 'wp_template':
																			$editor_url = admin_url("site-editor.php?postType=wp_template");
																		break;

																		case 'wp_template_part':
																			$editor_url = admin_url("site-editor.php");
																		break;

																		case 'wp_global_styles':
																			$editor_url = admin_url("site-editor.php");
																		break;

																		default:
																			$editor_url = "#";

																			do_log(__FUNCTION__.": Unknown post_type (".var_export($arr_value_remote, true).")");
																		break;
																	}

																	$out_li_temp .= "<p class='italic'>".sprintf(__("You have to create it in the %seditor%s first. Then you can update from the source site.", 'lang_site_manager'), "<a href='".$editor_url."'>", "</a>")."</p>";
																}
															}
														}

												$out_li_temp .= "</li>";
											}

											else
											{
												$out_li_temp .= "<li rel='".__LINE__.": ".$key_all."'>
													<i class='fa fa-times fa-lg red'></i> "
													."<strong>".$key_all.":</strong> "
													."<span class='color_red'>";

														if($arr_value_remote != '')
														{
															$out_li_temp .= shorten_text(array('string' => $arr_value_remote, 'limit' => 50, 'count' => true));
														}

														else
														{
															$out_li_temp .= "(".__("empty", 'lang_site_manager').")";
														}

													$out_li_temp .= "</span><strong> -> </strong>";

													if($arr_value_this != '')
													{
														$out_li_temp .= shorten_text(array('string' => $arr_value_this, 'limit' => 50, 'count' => true));
													}

													else
													{
														$out_li_temp .= "(".__("empty", 'lang_site_manager').")";
													}

													if($key_all == 'favicon')
													{
														$out_li_temp .= "<form method='post' action=''>
															<div".get_form_button_classes().">"
																.show_button(array('name' => 'btnFaviconCopy', 'text' => __("Copy", 'lang_site_manager'), 'xtra' => " rel='confirm'"))
																.wp_nonce_field('favicon_copy_'.get_current_user_id(), '_wpnonce_favicon_copy', true, false)
															."</div>
														</form>";
													}

												$out_li_temp .= "</li>";
											}

											$has_equal_version = false;
										}
									}

									$out_temp .= "<td>";

										if($out_li_temp != '')
										{
											$out_temp .= "<ul>".$out_li_temp."</ul>";

											$out_li_temp = "";

											$has_equal_version = false;
										}

										else
										{
											$out_temp .= "<i class='fa fa-check fa-lg green'></i>";
										}

									$out_temp .= "</td>";
								}

								else
								{
									$out_temp .= "<td><a href='".$this->get_type_url($site)."' class='italic'>(".__("does not exist", 'lang_site_manager').")</a></td>";

									$has_equal_version = false;
								}

								unset($array[$site][$key]);
							}
						}

					$out_temp .= "</tr>";
				}

				if($has_equal_version == false)
				{
					$out .= "<tr rel='".$type.", ".$key."'>".$out_temp."</tr>";

					//echo $out;

					$this->echoed = true;
				}

				//unset($array['this'][$key]);
			//}
		}

		$column_count++;

		foreach($this->arr_sites as $site2)
		{
			if(is_array($array[$site2]))
			{
				foreach($array[$site2] as $key => $arr_value)
				{
					$name = $arr_value['name'];
					$version = 0;

					$out .= "<tr>
						<td>".$name."</td>
						<td><a href='".$this->get_type_url($site_url, $name)."' class='italic'>(".__("does not exist", 'lang_site_manager').")</a></td>";

						foreach($this->arr_sites as $site)
						{
							$out .= "<td>";

								if(isset($array[$site][$key]))
								{
									$is_active_check = (isset($array[$site][$key]['is_active']) ? ($array[$site][$key]['is_active'] ? 'yes' : 'no') : '');
									$version_check = $array[$site][$key]['version'];

									$out .= $this->get_version_check_cell(array('version' => $version, 'version_check' => $version_check, 'is_active_check' => $is_active_check, 'dir' => $this->type."/".$array[$site][$key]['dir']));

									unset($array[$site][$key]);
								}

								else
								{
									$out .= "<a href='".$this->get_type_url($site, $name)."' class='italic'>(".__("does not exist", 'lang_site_manager').")</a>";

									$out .= $this->copy_differences(array('dir' => $this->type."/".$directory));
								}

							$out .= "</td>";
						}

					$out .= "</tr>";

					$this->echoed = true;
				}
			}

			else
			{
				do_log(__FUNCTION__.": ".$site2." does not exist in ".var_export($array, true));
			}

			$column_count++;
		}

		switch($this->type)
		{
			case 'themes':
				$this->arr_themes = $array;
			break;

			case 'plugins':
				$this->arr_plugins = $array;
			break;

			default:
				//Do nothing
			break;
		}

		return $out;
	}

	function get_version_check_cell($data)
	{
		if(!isset($data['link'])){				$data['link'] = "";}
		if(!isset($data['is_active_check'])){	$data['is_active_check'] = "";}

		$out = "";

		if($data['version_check'] == $data['version'])
		{
			$class = "fa fa-check ".($data['is_active_check'] == 'no' ? "grey" : "green");
			$version_out = "";
		}

		else
		{
			if(version_compare($data['version_check'], $data['version'], ">"))
			{
				$class = "fa fa-less-than ".($data['is_active_check'] == 'no' ? "grey" : "green");
			}

			else
			{
				$class = "fa fa-greater-than ".($data['is_active_check'] == 'no' ? "grey" : "red");
			}

			$version_out = $data['version_check'];
		}

		$out .= "<i class='".$class." fa-lg'".($data['is_active_check'] == 'no' ? " title='".__("Inactive", 'lang_site_manager')."'" : "")."></i> ";

		if($data['version_check'] != $data['version'] && $data['link'] != '')
		{
			$out .= "<a href='".$data['link']."'>";
		}

			$out .= "<span".($data['is_active_check'] == 'no' ? " class='grey' style='text-decoration: line-through'" : "").">".$version_out."</span>";

		if($data['version_check'] != $data['version'])
		{
			if($data['link'] != '')
			{
				$out .= "</a>";
			}

			$out .= $this->copy_differences($data);
		}

		return $out;
	}

	function custom_copy($src, $dst, $debug_copy)
	{
		if(strpos($src, "//"))
		{
			$src = str_replace("//", "/", $src);
		}

		if(strpos($dst, "//"))
		{
			$dst = str_replace("//", "/", $dst);
		}

		if(is_dir($src))
		{
			if($dir = opendir($src))
			{
				if(!is_dir($dst) && !is_file($dst))
				{
					if($debug_copy)
					{
						$this->debug_copy .= "<li>".$dst."</li>";
					}

					else
					{
						if(!mkdir($dst, 0755, true))
						{
							echo "Could not create folder (".$dst.")";
						}
					}
				}

				while($file = readdir($dir))
				{
					if(($file != '.') && ($file != '..'))
					{
						$source_file = $src.(substr($src, -1, 1) != "/" ? "/" : "").$file;
						$destination_file = $dst.(substr($dst, -1, 1) != "/" ? "/" : "").$file;

						if(is_dir($source_file))
						{
							$this->custom_copy($source_file, $destination_file, $debug_copy);
						}

						else
						{
							$copy_file = true;

							if(strpos($source_file, "//"))
							{
								do_log("The source contained //: ".$src." + ".$file." -> ".$source_file);
							}

							if(strpos($destination_file, "//"))
							{
								do_log("The destination contained //: ".$dst." + ".$file." -> ".$destination_file);
							}

							if(file_exists($destination_file))
							{
								if(filesize($source_file) == filesize($destination_file) && filemtime($source_file) <= filemtime($destination_file))
								{
									$copy_file = false;
								}
							}

							if($copy_file)
							{
								if($debug_copy)
								{
									$this->debug_copy .= "<li>".$file." -> ".$dst."</li>";
								}

								else
								{
									copy($source_file, $destination_file);
								}
							}
						}
					}
				}

				closedir($dir);
			}

			else
			{
				// Log error?
			}
		}
	}

	function copy_differences($data = [])
	{
		global $error_notice;

		if(!isset($data['dir'])){	$data['dir'] = "";}

		$out = "";

		if(isset($_REQUEST['btnDifferencesCopy']) && $this->setting_site_manager_site_clone_path != '')
		{
			$debug_copy = (isset($_GET['type']) && $_GET['type'] == 'debug_copy');

			$source_path = ABSPATH."wp-content/";

			if($data['dir'] != '')
			{
				$source_path .= $data['dir'];
			}

			$destination_path = str_replace(ABSPATH, $this->setting_site_manager_site_clone_path, $source_path);

			if($debug_copy)
			{
				$this->debug_copy = "";
			}

			$this->custom_copy($source_path, $destination_path, $debug_copy);

			if($debug_copy && $this->debug_copy != '')
			{
				$out .= "<br><strong>".__("I would have copied:", 'lang_site_manager')."</strong>
				<ul>".$this->debug_copy."</ul>";
			}
		}

		return $out;
	}

	function get_server_ip()
	{
		$url = get_site_url()."/wp-content/plugins/mf_base/include/api/?type=my_ip";

		$this->server_ip_old = get_option('setting_site_manager_server_ip');
		$this->server_ip_new = "";

		list($content, $headers) = get_url_content(array('url' => $url, 'catch_head' => true));

		$log_message = "I could not get the IP";

		switch($headers['http_code'])
		{
			case 200:
				$json_content = json_decode($content, true);
				$this->server_ip_new = (isset($json_content['ip']) ? $json_content['ip'] : "");

				do_log($log_message, 'trash');
			break;

			case 0:
				// Do nothing
			break;

			default:
				if($headers['redirect_url'] == $headers['url'])
				{
					unset($headers['redirect_url']);
				}

				unset($headers['header_size']);
				unset($headers['request_size']);
				unset($headers['ssl_verify_result']);
				unset($headers['redirect_count']);
				unset($headers['size_upload']);
				unset($headers['size_download']);
				unset($headers['download_content_length']);
				unset($headers['upload_content_length']);
				unset($headers['filetime']);
				unset($headers['total_time']);
				unset($headers['namelookup_time']);
				unset($headers['connect_time']);
				unset($headers['pretransfer_time']);
				unset($headers['speed_download']);
				unset($headers['speed_upload']);
				unset($headers['starttransfer_time']);
				unset($headers['redirect_time']);
				unset($headers['primary_ip']);
				unset($headers['local_ip']);
				unset($headers['local_port']);
				unset($headers['request_header']);
				unset($headers['Date']);
				unset($headers['date']);
				unset($headers['appconnect_time_us']);
				unset($headers['connect_time_us']);
				unset($headers['namelookup_time_us']);
				unset($headers['pretransfer_time_us']);
				unset($headers['starttransfer_time_us']);
				unset($headers['total_time_us']);

				do_log($log_message." (".htmlspecialchars(var_export($headers, true)).")");
			break;
		}

		if($this->server_ip_new != '' && $this->server_ip_new != $this->server_ip_old)
		{
			update_option('setting_site_manager_server_ip', $this->server_ip_new, false);

			if($this->server_ip_old != '')
			{
				do_log(sprintf("The server has changed IP address from %s to %s", $this->server_ip_old, $this->server_ip_new));
			}

			return $this->server_ip_new;
		}
	}

	function option_blogname($value, $option)
	{
		if(!preg_match("/\[/", $value))
		{
			$http_host = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != '' ? $_SERVER['HTTP_HOST'] : get_site_url());

			if($http_host != '')
			{
				$value = trim(str_replace(array("[STAGING]", "[DEV]"), "", $value));

				if(preg_match("/staging/", $http_host))
				{
					$value = "[STAGING] ".$value;
				}

				if(preg_match("/development|dev\./", $http_host))
				{
					$value = "[DEV] ".$value;
				}
			}
		}

		return $value;
	}

	function get_site_icon_url($url, $size, $blog_id)
	{
		$http_host = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != '' ? $_SERVER['HTTP_HOST'] : get_site_url());

		if($http_host != '')
		{
			if(preg_match("/staging|development|dev\./", $http_host))
			{
				$plugin_images_url = str_replace("/include/", "/images/", plugin_dir_url(__FILE__));

				switch($size)
				{
					case 32:
						return $plugin_images_url."staging-favicon-150x150.png";
					break;

					default:
						return $plugin_images_url."staging-favicon-300x300.png";
					break;
				}
			}
		}

		return $url;
	}

	function api_site_manager_force_server_ip()
	{
		global $done_text, $error_text;

		$json_output = array(
			'success' => false,
		);

		//delete_transient('server_ip_transient');

		$this->get_server_ip();

		if($this->server_ip_new != '' && $this->server_ip_new != $this->server_ip_old)
		{
			$done_text = sprintf(__("I successfully fetched the server IP (%s) for you", 'lang_site_manager'), $this->server_ip_old." -> ".$this->server_ip_new);
		}

		else if($this->server_ip_new == $this->server_ip_old)
		{
			$done_text = sprintf(__("The IP (%s) is the same as before", 'lang_site_manager'), $this->server_ip_new);

			$json_output['success'] = true;
		}

		else
		{
			$error_text = sprintf(__("I could not fetch the server IP (%s) for you", 'lang_site_manager'), $this->server_ip_old." -> ".$this->server_ip_new);
		}

		$json_output['html'] = get_notification();

		header('Content-Type: application/json');
		echo json_encode($json_output);
		die();
	}

	function cron_base()
	{
		global $wpdb;

		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			replace_option(array('old' => 'setting_server_ip', 'new' => 'setting_site_manager_server_ip'));
			replace_option(array('old' => 'setting_server_ip_target', 'new' => 'setting_site_manager_server_ip_target'));
			replace_option(array('old' => 'setting_server_ips_allowed', 'new' => 'setting_site_manager_server_ips_allowed'));
			replace_option(array('old' => 'setting_site_comparison', 'new' => 'setting_site_manager_site_comparison'));
			replace_option(array('old' => 'setting_site_clone_path', 'new' => 'setting_site_manager_site_clone_path'));
			replace_option(array('old' => 'setting_base_template_site', 'new' => 'setting_site_manager_template_site'));

			mf_uninstall_plugin(array(
				'options' => array('setting_site_manager_template_site'),
			));

			if(is_main_site())
			{
				$this->get_server_ip();

				// Remove empty tables and revisions from posts on inactive sites
				###################################
				if(is_multisite())
				{
					$result_sites = get_sites(array('deleted' => 1, 'order' => 'ASC'));

					foreach($result_sites as $r)
					{
						switch_to_blog($r->blog_id);

						$table_prefix = $wpdb->prefix;

						restore_current_blog();

						$result_tables = $wpdb->get_results("SHOW TABLES LIKE '".$table_prefix."%'", ARRAY_N);

						foreach($result_tables as $r)
						{
							$table_name = $r[0];

							$wpdb->get_results("SELECT * FROM ".$table_name." LIMIT 0, 1");

							if($wpdb->num_rows == 0)
							{
								// This will create log errors when WP can't find comments table. So maybe if we only drop non-core tables???
								//$wpdb->query("DROP TABLE IF EXISTS ".$table_name);
							}

							else
							{
								switch($table_name)
								{
									case $table_prefix.'posts':
										$result_posts = $wpdb->get_results("SELECT ID FROM ".$table_prefix."posts WHERE post_status IN ('".implode("','", array('auto-draft', 'draft', 'ignore', 'inherit', 'trash'))."')");

										foreach($result_posts as $r)
										{
											$post_id = $r->ID;

											$wpdb->query($wpdb->prepare("DELETE FROM ".$table_prefix."postmeta WHERE post_id = '%d'", $post_id));
											$wpdb->query($wpdb->prepare("DELETE FROM ".$table_prefix."posts WHERE ID = '%d'", $post_id));
										}
									break;
								}
							}
						}
					}
				}
				###################################
			}
		}

		$obj_cron->end();
	}
}