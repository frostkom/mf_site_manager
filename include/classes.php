<?php

class mf_site_manager
{
	function __construct()
	{
		$this->arr_core = $this->arr_themes = $this->arr_plugins = $this->arr_sites = $this->arr_sites_error = array();

		$this->lang_key = 'lang_site_manager';
	}

	function get_sites_for_select($data = array())
	{
		if(!isset($data['exclude'])){	$data['exclude'] = array();}

		$result = get_sites(array('site__not_in' => $data['exclude'], 'deleted' => 0, 'orderby' => 'domain'));

		$arr_data = array(
			'' => "-- ".__("Choose Here", $this->lang_key)." --"
		);

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
		$arr_data = array();

		$arr_themes = wp_get_themes(array('errors' => false, 'allowed' => true));

		foreach($arr_themes as $key => $value)
		{
			$arr_data[$key] = $value['Name'];
		}

		return $arr_data;
	}

	function admin_init()
	{
		global $pagenow;

		if(!is_plugin_active("mf_base/index.php"))
		{
			deactivate_plugins(str_replace("include/classes.php", "index.php", plugin_basename(__FILE__)));
		}

		if($pagenow == 'options-general.php' && check_var('page') == 'settings_mf_base')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_script('script_site_manager', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);
		}
	}

	function settings_site_manager()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area.'_callback'), BASE_OPTIONS_PAGE);

			$arr_settings = array();
			$arr_settings['setting_server_ip'] = __("Server IP", $this->lang_key);
			$arr_settings['setting_server_ips_allowed'] = __("Server IPs allowed", $this->lang_key);
			$arr_settings['setting_site_comparison'] = __("Sites to compare with", $this->lang_key);

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_site_manager_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Site Manager", $this->lang_key));
	}

	function setting_server_ip_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		if($option == '')
		{
			$obj_site_manager = new mf_site_manager();

			$option = $obj_site_manager->get_server_ip();
		}

		echo "<p>".$option."</p>
		<div>"
			.show_button(array('type' => 'button', 'name' => 'btnGetServerIP', 'text' => __("Get Server IP", $this->lang_key), 'class' => 'button-secondary'))
		."</div>
		<div id='ip_debug'></div>";
	}

	function setting_server_ips_allowed_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		echo show_textfield(array('name' => $setting_key, 'value' => $option));
	}

	function setting_site_comparison_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		$site_url = remove_protocol(array('url' => get_option('siteurl'), 'clean' => true));

		echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $site_url.", test.".$site_url));
	}

	function admin_menu()
	{
		$menu_root = 'mf_site_manager/';
		$menu_start = $menu_root."compare/index.php";
		$menu_capability = override_capability(array('page' => $menu_start, 'default' => 'update_core'));

		$menu_title = __("Site Manager", $this->lang_key);
		add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-images-alt2', 100);

		if(get_option('setting_site_comparison') != '')
		{
			$menu_title = __("Compare Sites", $this->lang_key);
			add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."compare/index.php");
		}

		if(is_multisite())
		{
			global $wpdb;

			$result = $wpdb->get_results("SELECT blog_id FROM ".$wpdb->base_prefix."blogs");

			if($wpdb->num_rows > 1)
			{
				$menu_title = __("Clone Site", $this->lang_key);
				add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."clone/index.php");

				$menu_title = __("Switch Sites", $this->lang_key);
				add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."switch/index.php");
			}
		}

		$menu_title = __("Change URL", $this->lang_key);
		add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."change/index.php");

		$menu_title = __("Change Theme", $this->lang_key);
		add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, $menu_root."theme/index.php");
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

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->options." SET option_value = replace(option_value, %s, %s) WHERE option_name = 'home' OR option_name = 'siteurl'", $this->site_url, $this->new_url));
		if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE guid LIKE %s", "%".$this->site_url."% LIMIT 0, 1"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET guid = replace(guid, %s, %s)", $this->site_url, $this->new_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_content LIKE %s", "%".$this->site_url."% LIMIT 0, 1"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_content = replace(post_content, %s, %s)", $this->site_url, $this->new_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->postmeta." WHERE meta_value LIKE %s", "%".$this->site_url."% LIMIT 0, 1"));

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

				update_option($option_name, $option_value);
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
		// Clone
		$this->blog_id = check_var('intBlogID');

		if(is_plugin_active("mf_backup/index.php"))
		{
			$this->site_backup = check_var('intSiteBackup', 'int', true);
		}

		$this->keep_title = check_var('intSiteKeepTitle', 'int', true);
		$this->empty_plugins = check_var('intSiteEmptyPlugins', 'int', true);

		// Change URL
		$this->site_url = get_home_url();
		$this->new_url = check_var('strBlogUrl', 'char', true, $this->site_url);

		$this->site_url_clean = remove_protocol(array('url' => $this->site_url, 'clean' => true));
		$this->new_url_clean = remove_protocol(array('url' => $this->new_url, 'clean' => true));
	}

	function save_data()
	{
		global $wpdb, $error_text, $done_text;

		if(isset($_POST['btnSiteChangeUrl']) && isset($_POST['intSiteChangeUrlAccept']) && $_POST['intSiteChangeUrlAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce_site_change_url'], 'site_change_url_'.$wpdb->blogid.'_'.get_current_user_id()))
		{
			if($this->new_url != $this->site_url || defined('WP_HOME'))
			{
				$this->arr_errors = array();

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
					$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", $this->lang_key), $count_temp);

					do_log("Errors while changing URL: ".var_export($this->arr_errors, true));
				}

				else
				{
					$done_text = sprintf(__("I have changed the URL from %s to %s. Go to %sDashboard%s", $this->lang_key), $this->site_url, "<a href='".$this->new_url."'>".$this->new_url."</a>", "<a href='".$this->new_url."/wp-admin"."'>", "</a>");

					do_log(sprintf("%s changed the URL from %s to %s", get_user_info(), $this->site_url, $this->new_url), 'notification');
				}
			}

			else
			{
				$error_text = __("You have to choose another URL than the current one", $this->lang_key);
			}
		}

		else if(isset($_POST['btnSiteClone']) && isset($_POST['intSiteCloneAccept']) && $_POST['intSiteCloneAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce_site_clone'], 'site_clone_'.$wpdb->blogid.'_'.get_current_user_id()))
		{
			if($this->blog_id > 0 && $this->blog_id != $wpdb->blogid)
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

					$arr_tables_from = array();

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
					$strBasePrefixTo = $this->blog_id > 1 ? $wpdb->base_prefix.$this->blog_id."_" : $wpdb->base_prefix;
					$strBlogDomainTo = get_site_url_clean(array('id' => $this->blog_id, 'trim' => "/"));

					$arr_tables_to = array();

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
						$error_text = __("There appears to be no tables on the source site", $this->lang_key)." (".$strBasePrefixFrom.": ".count($arr_tables_from)." -> ".$strBasePrefixTo.": ".count($arr_tables_to).")";
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
									if(isset($_POST['intSiteKeepTitle']) && $_POST['intSiteKeepTitle'] == 1)
									{
										$strBlogName_orig = $wpdb->get_var("SELECT option_value FROM ".$table_name_to." WHERE option_name = 'blogname'");
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

									if(isset($_POST['intSiteKeepTitle']) && $_POST['intSiteKeepTitle'] == 1)
									{
										$wpdb->query("UPDATE ".$table_name_to." SET option_value = '".$strBlogName_orig."' WHERE option_name = 'blogname'");
										$str_queries .= $wpdb->last_query.";\n";
									}

									if(isset($_POST['intSiteEmptyPlugins']) && $_POST['intSiteEmptyPlugins'] == 1)
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
						global $obj_theme_core;

						if(!isset($obj_theme_core))
						{
							$obj_theme_core = new mf_theme_core();
						}

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

							$obj_theme_core->file_dir_from = str_replace(array($upload_url_to, $upload_url_from), $upload_path_from, $post_url);
							$obj_theme_core->file_dir_to = str_replace(array($upload_url_to, $upload_url_from), $upload_path_to, $post_url);

							$obj_theme_core->copy_file();
							$str_queries .= "Copied: ".$obj_theme_core->file_dir_from." -> ".$obj_theme_core->file_dir_to."\n";

							if(wp_attachment_is_image($post_id))
							{
								foreach($arr_sizes as $size)
								{
									$arr_image = wp_get_attachment_image_src($post_id, $size);
									$post_url = $arr_image[0];

									$obj_theme_core->file_dir_from = str_replace(array($upload_url_to, $upload_url_from), $upload_path_from, $post_url);
									$obj_theme_core->file_dir_to = str_replace(array($upload_url_to, $upload_url_from), $upload_path_to, $post_url);

									$obj_theme_core->copy_file();
									$str_queries .= "Copied: ".$obj_theme_core->file_dir_from." -> ".$obj_theme_core->file_dir_to."\n";
								}
							}
						}
						#######################

						$done_text = __("All data was cloned", $this->lang_key);
						//$done_text .= " (".$strBasePrefixFrom." -> ".$strBasePrefixTo.")";
						//$done_text .= " [".nl2br($str_queries)."]";

						do_log(sprintf("%s cloned %s to %s", get_user_info(), $strBlogDomainFrom, $strBlogDomainTo), 'notification');
					}
				}

				else
				{
					$error_text = __("The backup was not successful so I could not clone the site for you", $this->lang_key);
				}
			}

			else
			{
				$error_text = __("You have to choose a site other than this site", $this->lang_key);
			}
		}
	}
	###########################

	/* Admin */
	###########################
	function manage_plugins_columns($cols)
	{
		if(IS_SUPER_ADMIN)
		{
			$cols['activated'] = __("Activated On", $this->lang_key);
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
					if(is_plugin_active_for_network($plugin_file))
					{
						//echo "<span class='grey'>".__("All Sites", $this->lang_key)."<span>";
					}

					else
					{
						$arr_sites = array();

						$result = get_sites();

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
		$cols['ssl'] = __("SSL", $this->lang_key);

		return $cols;
	}

	function sites_column_cell($col, $id)
	{
		if(get_blog_status($id, 'deleted') == 0)
		{
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
			}
		}
	}

	function sites_row_actions($actions)
	{
		unset($actions['archive']);
		unset($actions['spam']);

		return $actions;
	}

	function admin_footer()
	{
		$screen = get_current_screen();

		if(in_array($screen->base, array('site-info-network', 'site-settings-network')))
		{
			$blog_id = check_var('id', 'int');

			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_script('script_site_manager_url', $plugin_include_url."script_wp_url.js", array('change_url_link' => get_admin_url($blog_id, "admin.php?page=mf_site_manager/change/index.php"), 'change_url_text' => __("Change URL", $this->lang_key)), $plugin_version);
		}
	}
	###########################

	function get_content_versions()
	{
		$core_version = get_bloginfo('version');
		$arr_themes = wp_get_themes();
		$arr_plugins = get_plugins();

		$arr_themes_this_site = array();

		foreach($arr_themes as $key => $value)
		{
			$arr_data = array();

			switch($key)
			{
				case 'mf_parallax':
				case 'mf_theme':
					$arr_data['array'] = get_theme_mods();
				break;
			}

			$arr_themes_this_site[$key] = array(
				'name' => $value->Name,
				'version' => $value->Version,
				'data' => $arr_data,
			);
		}

		$arr_plugins_this_site = array();

		foreach($arr_plugins as $key => $value)
		{
			$arr_data = array();

			switch($key)
			{
				case 'mf_log/index.php':
					$tbl_group = new mf_log_table();

					$tbl_group->select_data(array(
						'select' => "ID",
						'debug' => ($_SERVER['REMOTE_ADDR'] == ""),
					));

					$arr_data['value'] = count($tbl_group->data);
					$arr_data['link'] = admin_url("admin.php?page=mf_log/list/index.php");
				break;
			}

			$arr_plugins_this_site[$key] = array(
				'name' => $value['Name'],
				'version' => $value['Version'],
				'data' => $arr_data,
			);
		}

		$this->arr_core['this'] = array('version' => $core_version, 'is_multisite' => is_multisite());
		$this->arr_themes['this'] = $arr_themes_this_site;
		$this->arr_plugins['this'] = $arr_plugins_this_site;
	}

	function get_sites($setting_site_comparison)
	{
		if($setting_site_comparison != '')
		{
			$this->arr_sites = array_map('trim', explode(",", $setting_site_comparison));
		}

		$count_temp = count($this->arr_sites);

		if($count_temp > 0)
		{
			for($i = 0; $i < $count_temp; $i++)
			{
				$this->arr_sites[$i] = $site = $this->arr_sites[$i];

				$site_ajax = $site."/wp-content/plugins/mf_site_manager/include/api/?type=compare";

				list($content, $headers) = get_url_content(array('url' => $site_ajax, 'catch_head' => true));

				if($headers['http_code'] != 200) //Fallback until all sites are updated
				{
					$site_ajax = $site."/wp-content/plugins/mf_site_manager/include/ajax.php?type=compare";

					list($content, $headers) = get_url_content(array('url' => $site_ajax, 'catch_head' => true));
				}

				if($headers['http_code'] == 200)
				{
					$arr_content = json_decode($content, true);

					$this->arr_core[$site] = isset($arr_content['core']) ? $arr_content['core'] : "";
					$this->arr_themes[$site] = $arr_content['themes'];
					$this->arr_plugins[$site] = $arr_content['plugins'];
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
		if($this->type == 'plugins' && $plugin_name != '')
		{
			return validate_url($site."/wp-admin".($this->is_multisite ? "/network" : "")."/plugin-install.php?tab=search&s=".$plugin_name);
		}

		else
		{
			return validate_url($site."/wp-admin".($this->is_multisite ? "/network" : "")."/update-core.php"); //".$this->type."
		}
	}

	function clear_domain_from_urls($url, $domain)
	{
		$url = remove_protocol(array('url' => $url));
		$domain = remove_protocol(array('url' => $domain));

		$url = str_replace($domain, "[".__("domain", $this->lang_key)."]", $url);

		return $url;
	}

	function check_version($type)
	{
		$this->echoed = false;

		$this->type = $type;

		switch($this->type)
		{
			case 'themes':
				$array = $this->arr_themes;
			break;

			case 'plugins':
				$array = $this->arr_plugins;
			break;

			default:
				$array = array();
			break;
		}

		foreach($array['this'] as $key => $value)
		{
			$name = $value['name'];
			$version = $value['version'];
			$arr_data = isset($value['data']) ? $value['data'] : array();

			$has_equal_version = true;

			$out = "<tr>
				<td>".$name."</td>
				<td>".$version."</td>";

				foreach($this->arr_sites as $site)
				{
					if(isset($array[$site][$key]))
					{
						$version_check = $array[$site][$key]['version'];
						$this->is_multisite = $this->arr_core[$site]['is_multisite'];

						$out .= $this->get_version_check_cell($version, $version_check, $this->get_type_url($site));

						if($version_check != $version)
						{
							$has_equal_version = false;
						}

						if(count($arr_data) == 0)
						{
							unset($array[$site][$key]);
						}
					}

					else
					{
						$out .= "<td><a href='".$this->get_type_url($site, $name)."' class='italic'>(".__("does not exist", $this->lang_key).")</a></td>";

						$has_equal_version = false;
					}
				}

			$out .= "</tr>";

			if(count($arr_data) > 0)
			{
				$out .= "<tr>
					<td><i class='fa fa-info-circle fa-lg'></i></td>";

					if(isset($arr_data['value']))
					{
						$out .= "<td>".($arr_data['value'] > 0 ? "<i class='fa fa-times fa-lg red'></i> <a href='".$arr_data['link']."'>".$arr_data['value']."</a>" : "<i class='fa fa-check fa-lg green'></i>")."</td>";

						foreach($this->arr_sites as $site)
						{
							$arr_data_check = isset($array[$site][$key]['data']) ? $array[$site][$key]['data'] : array();

							if(count($arr_data_check) > 0)
							{
								$out .= "<td>";

									if($arr_data_check['value'] > 0)
									{
										$out .= "<i class='fa fa-times fa-lg red'></i> <a href='".$arr_data_check['link']."'>".$arr_data_check['value']."</a>";

										$has_equal_version = false;
									}

									else
									{
										$out .= "<i class='fa fa-check fa-lg green'></i>";
									}

								$out .= "</td>";
							}

							else
							{
								$out .= "<td><a href='".$this->get_type_url($site)."' class='italic'>(".__("does not exist", $this->lang_key).")</a></td>";

								$has_equal_version = false;
							}

							unset($array[$site][$key]);
						}
					}

					else if(isset($arr_data['array']))
					{
						$arr_exclude = array('style_source');

						$out .= "<td></td>";

						foreach($this->arr_sites as $site)
						{
							$arr_data_check = isset($array[$site][$key]['data']) ? $array[$site][$key]['data'] : array();

							if(count($arr_data_check) > 0)
							{
								$out_temp = "";

								foreach($arr_data['array'] as $key2 => $value2)
								{
									$value_check = isset($arr_data_check['array'][$key2]) ? $arr_data_check['array'][$key2] : "";

									$value2 = $this->clear_domain_from_urls($value2, get_site_url());
									$value_check = $this->clear_domain_from_urls($value_check, "//".$site);

									if(!in_array($key2, $arr_exclude) && $value2 != $value_check)
									{
										$out_temp .= "<li><i class='fa fa-times fa-lg red'></i> <strong>".$key2.":</strong> <span class='color_red'>".shorten_text(array('string' => $value_check, 'limit' => 50, 'count' => true))."</span> <strong>-></strong> ".shorten_text(array('string' => $value2, 'limit' => 50, 'count' => true))."</li>";

										$has_equal_version = false;
									}

									unset($arr_data_check['array'][$key2]);
								}

								foreach($arr_data_check['array'] as $key2 => $value2)
								{
									$value_check = isset($arr_data['array'][$key2]) ? $arr_data['array'][$key2] : "";

									$value2 = $this->clear_domain_from_urls($value2, get_site_url());
									$value_check = $this->clear_domain_from_urls($value_check, "//".$site);

									if(!in_array($key2, $arr_exclude) && $value2 != $value_check)
									{
										$out_temp .= "<li><i class='fa fa-times fa-lg red'></i> <strong>".$key2.":</strong> <span class='color_red'>".shorten_text(array('string' => $value2, 'limit' => 50, 'count' => true))."</span> <strong>-></strong> ".shorten_text(array('string' => $value_check, 'limit' => 50, 'count' => true))."</li>";

										$has_equal_version = false;
									}
								}

								$out .= "<td>";

									if($out_temp != '')
									{
										$out .= "<ul>".$out_temp."</ul>";

										$has_equal_version = false;
									}

									else
									{
										$out .= "<i class='fa fa-check fa-lg green'></i>";
									}

								$out .= "</td>";
							}

							else
							{
								$out .= "<td><a href='".$this->get_type_url($site)."' class='italic'>(".__("does not exist", $this->lang_key).")</a></td>";

								$has_equal_version = false;
							}

							unset($array[$site][$key]);
						}
					}

				$out .= "</tr>";
			}

			if($has_equal_version == false)
			{
				echo $out;

				$this->echoed = true;
			}

			unset($array['this'][$key]);
		}

		foreach($this->arr_sites as $site2)
		{
			if(is_array($array[$site2]))
			{
				foreach($array[$site2] as $key => $value)
				{
					$name = $value['name'];
					$version = 0;

					echo "<tr>
						<td>".$name."</td>
						<td><em>(".__("does not exist", $this->lang_key).")</em></td>";

						foreach($this->arr_sites as $site)
						{
							if(isset($array[$site][$key]))
							{
								$version_check = $array[$site][$key]['version'];

								echo $this->get_version_check_cell($version, $version_check);

								unset($array[$site][$key]);
							}

							else
							{
								echo "<td><a href='".$this->get_type_url($site, $name)."' class='italic'>(".__("does not exist", $this->lang_key).")</a></td>";
							}
						}

					echo "</tr>";

					$this->echoed = true;
				}
			}

			else
			{
				do_log("check_version(): ".$site2." does not exist in ".var_export($array, true));
			}
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
	}

	function get_version_check_cell($version, $version_check, $link = '')
	{
		if($version_check == $version)
		{
			$class = "fa fa-check green";
			$version_out = "";
		}

		else
		{
			if(version_compare($version_check, $version, ">"))
			{
				$class = "fa fa-arrow-up green";
			}

			else
			{
				$class = "fa fa-arrow-down red";
			}

			$version_out = $version_check;
		}

		$out = "<td>"
			."<i class='".$class." fa-lg'></i> ";

			if($version_check != $version && $link != '')
			{
				$out .= "<a href='".$link."'>";
			}

				$out .= $version_out;

			if($version_check != $version && $link != '')
			{
				$out .= "</a>";
			}

		$out .= "</td>";

		return $out;
	}

	function get_server_ip()
	{
		$url = get_site_url()."/wp-content/plugins/mf_base/include/my_ip/";

		$this->server_ip_old = get_option('setting_server_ip');
		$this->server_ip_new = "";

		list($content, $headers) = get_url_content(array('url' => $url, 'catch_head' => true));

		switch($headers['http_code'])
		{
			case 200:
				$this->server_ip_new = $content;

				do_log("I could not get the IP", 'trash');
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

				do_log("I could not get the IP (".htmlspecialchars(var_export($headers, true)).")");
			break;
		}

		if($this->server_ip_new != '' && $this->server_ip_new != $this->server_ip_old)
		{
			update_option('setting_server_ip', $this->server_ip_new, 'no');

			if($this->server_ip_old != '')
			{
				do_log(sprintf("The server has changed IP address from %s to %s", $this->server_ip_old, $this->server_ip_new));
			}

			return $this->server_ip_new;
		}
	}

	function force_server_ip()
	{
		global $done_text, $error_text;

		$result = array();

		//delete_transient('server_ip_transient');

		$this->get_server_ip();

		if($this->server_ip_new != '' && $this->server_ip_new != $this->server_ip_old)
		{
			$done_text = sprintf(__("I successfully fetched the server IP (%s) for you", $this->lang_key), $this->server_ip_old." -> ".$this->server_ip_new);
		}

		else if($this->server_ip_new == $this->server_ip_old)
		{
			$done_text = sprintf(__("The IP (%s) is the same as before", $this->lang_key), $this->server_ip_new);
		}

		else
		{
			$error_text = sprintf(__("I could not fetch the server IP (%s) for you", $this->lang_key), $this->server_ip_old." -> ".$this->server_ip_new);
		}

		$out = get_notification();

		if($done_text != '')
		{
			$result['success'] = true;
			$result['message'] = $out;
		}

		else
		{
			$result['error'] = $out;
		}

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function cron_base()
	{
		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			if(is_main_site())
			{
				$this->get_server_ip();
			}
		}

		$obj_cron->end();
	}
}