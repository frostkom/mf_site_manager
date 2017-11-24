<?php

class mf_site_manager
{
	function __construct()
	{
		$this->arr_core = $this->arr_themes = $this->arr_plugins = $this->arr_sites = $this->arr_sites_error = array();
	}

	// Change URL
	###########################
	function fetch_request()
	{
		$this->site_url = get_site_url();
		$this->new_url = check_var('strBlogUrl', 'char', true, $this->site_url);

		$this->site_url_clean = remove_protocol(array('url' => $this->site_url, 'clean' => true));
		$this->new_url_clean = remove_protocol(array('url' => $this->new_url, 'clean' => true));
	}
	
	function change_multisite_url()
	{
		global $wpdb;

		if($this->new_url_clean != $this->site_url_clean)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->blogs." SET domain = %s WHERE blog_id = '%d'", $this->new_url_clean, $wpdb->blogid));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT id FROM ".$wpdb->site." WHERE domain = %s LIMIT 0, 1", $this->site_url));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->site." SET domain = %s WHERE domain = %s", $this->new_url, $this->site_url));
			if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
		}

		$result = $wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->sitemeta." WHERE meta_key = 'siteurl' AND (meta_value = %s OR meta_value = %s)", $this->site_url, $this->site_url."/"));

		if($wpdb->num_rows > 0)
		{
			foreach($result as $r)
			{
				$meta_id = $r->meta_id;

				$this->new_url_temp = substr($this->new_url, -1) == "/" ? $this->new_url : $this->new_url."/";

				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->sitemeta." SET meta_value = %s WHERE meta_key = 'siteurl' AND meta_id = '%d'", $this->new_url_temp, $meta_id));
				if($wpdb->rows_affected == 0){	$this->arr_errors[] = $wpdb->last_query;}
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

				//do_log("Update: ".$option_name." (".var_export($option_value, true).")");
				update_option($option_name, $option_value);
			}
		}

		/*else
		{
			do_log("No rows: ".$wpdb->last_query);
		}*/
	}

	function change_theme_mods()
	{
		$arr_theme_mods = get_theme_mods();

		foreach($arr_theme_mods as $key => $value)
		{
			$value_new = str_replace($this->site_url, $this->new_url, $value);

			if($value_new != $value)
			{
				set_theme_mod($key, $value_new);
			}
		}
	}

	function save_data()
	{
		global $wpdb, $error_text, $done_text;

		if(isset($_POST['btnSiteChangeUrl']) && isset($_POST['intSiteChangeUrlAccept']) && $_POST['intSiteChangeUrlAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce'], 'site_change_url_'.$wpdb->blogid))
		{
			if($this->new_url != $this->site_url)
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
					$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

					do_log(__("Errors while changing URL", 'lang_site_manager').": ".var_export($this->arr_errors, true));
				}

				else
				{
					$done_text = sprintf(__("I have changed the URL from %s to %s", 'lang_site_manager'), $this->site_url, $this->new_url);
				}
			}

			else
			{
				$error_text = __("You have to choose another URL than the current one", 'lang_site_manager');
			}
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
						//'select' => "*",
						//'debug' => true,
					));

					$arr_data['value'] = count($tbl_group->data);
					//$arr_data['link'] = get_site_url()."/wp-admin/admin.php?page=mf_log/list/index.php";
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
		global $wpdb;

		if($setting_site_comparison != '')
		{
			$this->arr_sites = explode_and_trim(",", $setting_site_comparison);
		}

		$count_temp = count($this->arr_sites);

		if($count_temp > 0)
		{
			for($i = 0; $i < $count_temp; $i++)
			{
				$this->arr_sites[$i] = $site = $this->arr_sites[$i];

				$site_ajax = $site."/wp-content/plugins/mf_site_manager/include/ajax.php?type=compare";

				list($content, $headers) = get_url_content($site_ajax, true);

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

		$url = str_replace($domain, "[".__("domain", 'lang_site_manager')."]", $url);

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
						$out .= "<td><a href='".$this->get_type_url($site, $name)."' class='italic' rel='external'>(".__("does not exist", 'lang_site_manager').")</a></td>";

						$has_equal_version = false;
					}
				}

			$out .= "</tr>";

			if(count($arr_data) > 0)
			{
				$out .= "<tr>
					<td><i class='fa fa-lg fa-info-circle'></i></td>";

					if(isset($arr_data['value']))
					{
						$out .= "<td>".($arr_data['value'] > 0 ? "<i class='fa fa-lg fa-close red'></i> <a href='".$arr_data['link']."'>".$arr_data['value']."</a>" : "<i class='fa fa-lg fa-check green'></i>")."</td>";

						foreach($this->arr_sites as $site)
						{
							$arr_data_check = isset($array[$site][$key]['data']) ? $array[$site][$key]['data'] : array();

							if(count($arr_data_check) > 0)
							{
								$out .= "<td>";

									if($arr_data_check['value'] > 0)
									{
										$out .= "<i class='fa fa-lg fa-close red'></i> <a href='".$arr_data_check['link']."' rel='external'>".$arr_data_check['value']."</a>";

										$has_equal_version = false;
									}

									else
									{
										$out .= "<i class='fa fa-lg fa-check green'></i>";
									}

								$out .= "</td>";
							}

							else
							{
								$out .= "<td><a href='".$this->get_type_url($site)."' class='italic' rel='external'>(".__("does not exist", 'lang_site_manager').")</a></td>";

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
										$out_temp .= "<li><i class='fa fa-lg fa-close red'></i> <strong>".$key2.":</strong> <span class='color_red'>".shorten_text(array('text' => $value_check, 'limit' => 50, 'count' => true))."</span> <strong>-></strong> ".shorten_text(array('text' => $value2, 'limit' => 50, 'count' => true))."</li>";

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
										$out_temp .= "<li><i class='fa fa-lg fa-close red'></i> <strong>".$key2.":</strong> <span class='color_red'>".shorten_text(array('text' => $value2, 'limit' => 50, 'count' => true))."</span> <strong>-></strong> ".shorten_text(array('text' => $value_check, 'limit' => 50, 'count' => true))."</li>";

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
										$out .= "<i class='fa fa-lg fa-check green'></i>";
									}

								$out .= "</td>";
							}

							else
							{
								$out .= "<td><a href='".$this->get_type_url($site)."' class='italic' rel='external'>(".__("does not exist", 'lang_site_manager').")</a></td>";

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
			foreach($array[$site2] as $key => $value)
			{
				$name = $value['name'];
				$version = 0;

				echo "<tr>
					<td>".$name."</td>
					<td><em>(".__("does not exist", 'lang_site_manager').")</em></td>";

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
							echo "<td><a href='".$this->get_type_url($site, $name)."' class='italic' rel='external'>(".__("does not exist", 'lang_site_manager').")</a></td>";
						}
					}

				echo "</tr>";

				$this->echoed = true;
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
			$class = "fa-check green";
			$version_out = "";
		}

		else
		{
			if(point2int($version_check) > point2int($version))
			{
				$class = "fa-arrow-up green";
			}

			else
			{
				$class = "fa-arrow-down red";
			}

			$version_out = $version_check;
		}

		$out = "<td>"
			."<i class='fa fa-lg ".$class."'></i> ";

			if($version_check != $version && $link != '')
			{
				$out .= "<a href='".$link."' rel='external'>";
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
		$server_ip_old = get_option('setting_server_ip');
		$server_ip_new = get_or_set_transient(array('key' => "server_ip_transient", 'url' => "http://ipecho.net/plain"));

		if($server_ip_new != '' && $server_ip_new != $server_ip_old)
		{
			update_option('setting_server_ip', $server_ip_new, 'no');

			if($server_ip_old != '')
			{
				do_log(sprintf(__("The server has changed IP address from %s to %s"), $server_ip_old, $server_ip_new));
			}

			return $server_ip_new;
		}
	}

	function cron()
	{
		$this->get_server_ip();
	}
}