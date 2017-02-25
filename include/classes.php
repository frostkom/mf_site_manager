<?php

class mf_site_manager
{
	function __construct()
	{
		$this->arr_core = $this->arr_themes = $this->arr_plugins = $this->arr_sites = $this->arr_sites_error = array();
	}

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
					$arr_data['link'] = get_site_url()."/wp-admin/admin.php?page=mf_log/list/index.php";
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
		$this->arr_sites = explode(",", $setting_site_comparison);

		$count_temp = count($this->arr_sites);

		for($i = 0; $i < $count_temp; $i++)
		{
			$this->arr_sites[$i] = $site = trim($this->arr_sites[$i]);

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

	function check_version($type)
	{
		$this->echoed = false;

		switch($type)
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
						$is_multisite = $this->arr_core[$site]['is_multisite'];

						$out .= $this->get_version_check_cell($version, $version_check, validate_url($site."/wp-admin".($is_multisite ? "/network" : "")."/".$type.".php"));

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
						$out .= "<td><em>(".__("does not exist", 'lang_site_manager').")</em></td>";

						$has_equal_version = false;
					}
				}

			$out .= "</tr>";

			if(count($arr_data) > 0)
			{
				//$has_equal_version = false;

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
								$out .= "<td><em>(".__("does not exist", 'lang_site_manager').")</em></td>";

								$has_equal_version = false;
							}

							unset($array[$site][$key]);
						}
					}

					else if(isset($arr_data['array']))
					{
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

									$value2 = str_replace(get_site_url(), "[".__("domain", 'lang_site_manager')."]", $value2);
									$value_check = str_replace("http://".$site, "[".__("domain", 'lang_site_manager')."]", $value_check);

									if($value2 != $value_check)
									{
										$out_temp .= "<li><i class='fa fa-lg fa-close red'></i> <strong>".$key2.":</strong> ".$value_check." -> ".$value2."</li>";

										$has_equal_version = false;
									}
									
									unset($arr_data_check['array'][$key2]);
								}

								foreach($arr_data_check['array'] as $key2 => $value2)
								{
									$value_check = isset($arr_data['array'][$key2]) ? $arr_data['array'][$key2] : "";

									$value2 = str_replace(get_site_url(), "[".__("domain", 'lang_site_manager')."]", $value2);
									$value_check = str_replace("http://".$site, "[".__("domain", 'lang_site_manager')."]", $value_check);

									if($value2 != $value_check)
									{
										$out_temp .= "<li><i class='fa fa-lg fa-close red'></i> <strong>".$key2.":</strong> ".$value2." -> ".$value_check."</li>";

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
								$out .= "<td><em>(".__("does not exist", 'lang_site_manager').")</em></td>";

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
							echo "<td><em>(".__("does not exist", 'lang_site_manager').")</em></td>";
						}
					}

				echo "</tr>";

				$this->echoed = true;
			}
		}

		switch($type)
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
}