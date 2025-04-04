<?php

$setting_site_manager_site_comparison = get_option('setting_site_manager_site_comparison');

if($setting_site_manager_site_comparison == '')
{
	mf_redirect(admin_url("options-general.php?page=settings_mf_base#settings_site_manager"));
}

$obj_site_manager = new mf_site_manager();
$obj_site_manager->fetch_request();
$obj_site_manager->save_data();

echo "<div class='wrap'>
	<h2>".__("Compare Sites", 'lang_site_manager')."</h2>"
	.get_notification();

	$obj_site_manager->get_content_versions();
	$obj_site_manager->get_sites($setting_site_manager_site_comparison);

	if(count($obj_site_manager->arr_sites) > 0)
	{
		echo "<table class='widefat striped'>";

			$arr_header[] = __("Name", 'lang_site_manager');
			$arr_header[] = __("This Site", 'lang_site_manager');

			foreach($obj_site_manager->arr_sites as $site)
			{
				$arr_header[] = remove_protocol(array('url' => $site, 'clean' => true));
			}

			echo show_table_header($arr_header)
			."<tbody>";

				$has_echoed = false;

				// Core
				##############################
				$has_equal_version = true;

				$version = $obj_site_manager->arr_core['this']['version'];

				$out = "<tr>
					<td>".__("Core", 'lang_site_manager')."</td>
					<td>".$version."</td>";

					foreach($obj_site_manager->arr_sites as $site)
					{
						$out .= "<td>";

							if(isset($obj_site_manager->arr_core[$site]) && is_array($obj_site_manager->arr_core[$site]))
							{
								$version_check = $obj_site_manager->arr_core[$site]['version'];
								$obj_site_manager->is_multisite = $obj_site_manager->arr_core[$site]['is_multisite'];

								$out .= $obj_site_manager->get_version_check_cell(array('version' => $version, 'version_check' => $version_check, 'link' => validate_url($site."/wp-admin".($obj_site_manager->is_multisite ? "/network" : "")."/update-core.php")));

								if($version_check != $version)
								{
									$has_equal_version = false;
								}
							}

							else
							{
								$out .= __("Does not support this", 'lang_site_manager')." (".var_export($obj_site_manager->arr_core, true).")";

								$has_equal_version = false;
							}

						$out .= "</td>";
					}

				$out .= "</tr>
				<tr><td colspan='".count($arr_header)."'></td></tr>";

				if($has_equal_version == false)
				{
					echo $out;
					$out = "";

					$has_echoed = true;
				}
				##############################

				// Themes
				##############################
				$obj_site_manager->check_version('themes');

				if($obj_site_manager->echoed == true)
				{
					$has_echoed = true;

					echo "<tr><td colspan='".count($arr_header)."'></td></tr>";
				}
				##############################

				// Plugins
				##############################
				$obj_site_manager->check_version('plugins');

				if($obj_site_manager->echoed == true)
				{
					$has_echoed = true;

					echo "<tr><td colspan='".count($arr_header)."'></td></tr>";
				}
				##############################

				// Media
				##############################
				$has_equal_version = true;

				$uploads_amount = $obj_site_manager->arr_core['this']['uploads'];

				$out = "<tr>
					<td>".__("Media", 'lang_site_manager')."</td>
					<td>".$uploads_amount."</td>";

					foreach($obj_site_manager->arr_sites as $site)
					{
						$out .= "<td>";

							if(isset($obj_site_manager->arr_core[$site]) && isset($obj_site_manager->arr_core[$site]['uploads']))
							{
								$uploads_amount_check = $obj_site_manager->arr_core[$site]['uploads'];

								if($uploads_amount_check == $uploads_amount)
								{
									$class = "fa fa-check green";
									$version_out = "";
								}

								else
								{
									if($uploads_amount_check > $uploads_amount)
									{
										$class = "fa fa-less-than green";
									}

									else
									{
										$class = "fa fa-greater-than red";
									}

									$version_out = $uploads_amount_check;

									$has_equal_version = false;
								}

								$out .= "<i class='".$class." fa-lg'></i> ".$version_out;

								if($uploads_amount_check != $uploads_amount)
								{
									$out .= $obj_site_manager->copy_differences(array('dir' => 'uploads'));
								}
							}

							else
							{
								$out .= __("Does not support this", 'lang_site_manager');

								$has_equal_version = false;
							}

						$out .= "</td>";
					}

				$out .= "</tr>
				<tr><td colspan='".count($arr_header)."'></td></tr>";

				if($has_equal_version == false)
				{
					echo $out;
					$out = "";

					$has_echoed = true;
				}
				##############################

				if($obj_site_manager->echoed == true)
				{
					$has_echoed = true;
				}

				if($has_echoed == true)
				{
					$setting_site_manager_site_clone_path = get_option('setting_site_manager_site_clone_path');

					if($setting_site_manager_site_clone_path != '')
					{
						$arr_setting_site_manager_site_clone_path = array_map('trim', explode(",", $setting_site_manager_site_clone_path));

						echo "<tr>
							<td>".__("Copy differences", 'lang_site_manager')."</td>
							<td></td>";

							$i = 0;

							foreach($obj_site_manager->arr_sites as $site)
							{
								$site_key = (count($arr_setting_site_manager_site_clone_path) > 1 ? $i : 0);

								echo "<td>";

									if($obj_site_manager->compare_site_url == $site)
									{
										if(isset($_GET['type']) && $_GET['type'] == 'debug_copy')
										{
											echo sprintf(__("The differences were test copied into %s", 'lang_site_manager'), $arr_setting_site_manager_site_clone_path[$site_key]);
										}

										else
										{
											echo sprintf(__("The differences were copied into %s", 'lang_site_manager'), $arr_setting_site_manager_site_clone_path[$site_key]);
										}
									}

									else
									{
										echo "<a href='".wp_nonce_url(admin_url("admin.php?page=".check_var('page')."&btnDifferencesCopy&strSiteURL=".$site."&intSiteKey=".$site_key), 'differences_copy_'.$site_key, '_wpnonce_differences_copy')."' class='button' rel='confirm' title='".sprintf(__("Copy Differences Into %s", 'lang_site_manager'), $arr_setting_site_manager_site_clone_path[$site_key])."'>"
											.__("Copy Differences", 'lang_site_manager')
										."</a>";

										echo " <a href='".wp_nonce_url(admin_url("admin.php?page=".check_var('page')."&btnDifferencesCopy&type=debug_copy&strSiteURL=".$site."&intSiteKey=".$site_key), 'differences_copy_'.$site_key, '_wpnonce_differences_copy')."' class='button'>"
											.__("Test Copy", 'lang_site_manager')
										."</a>";
									}

								echo "</td>";

								$i++;
							}

						echo "</tr>";
					}
				}

				else
				{
					echo "<tr><td colspan='".count($arr_header)."'>".__("I could not find any differences", 'lang_site_manager')."</td></tr>";
				}

				echo "<tr>
					<td>".__("Source", 'lang_site_manager')."</td>
					<td></td>";

					foreach($obj_site_manager->arr_sites as $site)
					{
						echo "<td>
							<a href='".$site."/wp-admin/'><i class='fas fa-sign-in-alt'></i></a>&nbsp;
							<a href='".$site.$obj_site_manager->compare_uri."'><i class='fas fa-link'></i></a>
						</td>";
					}

				echo "</tr>";

			echo "</tbody>
		</table>";
	}

	if(count($obj_site_manager->arr_sites_error) > 0)
	{
		echo "<br>
		<table class='widefat striped'>";

			$arr_header = array();

			$arr_header[] = __("Site", 'lang_site_manager');
			$arr_header[] = __("Error", 'lang_site_manager');

			echo show_table_header($arr_header)
			."<tbody>";

				foreach($obj_site_manager->arr_sites_error as $key => $value)
				{
					$value['content'] = htmlspecialchars($value['content']);

					echo "<tr>
						<td>".$key."</td>
						<td>".$value['headers']['http_code']." (".var_export($value, true).")</td>
					</tr>";
				}

			echo "</tbody>
		</table>";
	}

	else if(count($obj_site_manager->arr_sites) == 0)
	{
		echo "<em>".sprintf(__("I could not find any sites to compare with. Convert to MultiSite and add sites or add external ones in %sMy Settings%s", 'lang_site_manager'), "<a href='".admin_url("options-general.php?page=settings_mf_base#settings_site_manager")."'>", "</a>")."</em>";
	}

echo "</div>";