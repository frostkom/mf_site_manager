<?php

$setting_site_comparison = get_option('setting_site_comparison');

if($setting_site_comparison == '')
{
	mf_redirect(admin_url("options-general.php?page=settings_mf_base#settings_site_manager"));
}

$obj_site_manager = new mf_site_manager();

echo "<div class='wrap'>
	<h2>".__("Compare Sites", $obj_site_manager->lang_key)."</h2>"
	.get_notification();

	$obj_site_manager->get_content_versions();
	$obj_site_manager->get_sites($setting_site_comparison);

	if(count($obj_site_manager->arr_sites) > 0)
	{
		echo "<table class='widefat striped'>";

			$arr_header[] = __("Name", $obj_site_manager->lang_key);
			$arr_header[] = __("Version", $obj_site_manager->lang_key);

			foreach($obj_site_manager->arr_sites as $site)
			{
				$arr_header[] = $site;
			}

			echo show_table_header($arr_header)
			."<tbody>";

				$has_echoed = false;

				//Core
				##############################
				$has_equal_version = true;

				$version = $obj_site_manager->arr_core['this']['version'];

				$out = "<tr>
					<td>".__("Core", $obj_site_manager->lang_key)."</td>
					<td>".$version."</td>";

					foreach($obj_site_manager->arr_sites as $site)
					{
						if(isset($obj_site_manager->arr_core[$site]))
						{
							$version_check = $obj_site_manager->arr_core[$site]['version'];
							$obj_site_manager->is_multisite = $obj_site_manager->arr_core[$site]['is_multisite'];

							$out .= $obj_site_manager->get_version_check_cell($version, $version_check, validate_url($site."/wp-admin".($obj_site_manager->is_multisite ? "/network" : "")."/update-core.php"));

							if($version_check != $version)
							{
								$has_equal_version = false;
							}
						}

						else
						{
							$out .= "<td>".__("Really old version...", $obj_site_manager->lang_key)."</td>";

							$has_equal_version = false;
						}
					}

				$out .= "</tr>
				<tr><td colspan='".count($arr_header)."'></td></tr>";

				if($has_equal_version == false)
				{
					echo $out;

					$has_echoed = true;
				}
				##############################

				$obj_site_manager->check_version('themes');

				if($obj_site_manager->echoed == true)
				{
					$has_echoed = true;

					echo "<tr><td colspan='".count($arr_header)."'></td></tr>";
				}

				$obj_site_manager->check_version('plugins');

				if($obj_site_manager->echoed == true)
				{
					$has_echoed = true;
				}

				if($has_echoed == false)
				{
					echo "<tr><td colspan='".count($arr_header)."'>".__("I could not find any differences", $obj_site_manager->lang_key)."</td></tr>";
				}

			echo "</tbody>
		</table>";
	}

	if(count($obj_site_manager->arr_sites_error) > 0)
	{
		echo "<br>
		<table class='widefat striped'>";

			$arr_header = array();

			$arr_header[] = __("Site", $obj_site_manager->lang_key);
			$arr_header[] = __("Error", $obj_site_manager->lang_key);

			echo show_table_header($arr_header)
			."<tbody>";

				foreach($obj_site_manager->arr_sites_error as $key => $value)
				{
					echo "<tr>
						<td>".$key."</td>
						<td>".$value['headers']['http_code']." (".$value['content'].")</td>
					</tr>";
				}

			echo "</tbody>
		</table>";
	}

	else if(count($obj_site_manager->arr_sites) == 0)
	{
		echo "<em>".sprintf(__("I could not find any sites to compare with. Convert to MultiSite and add sites or add external ones in %sMy Settings%s", $obj_site_manager->lang_key), "<a href='".admin_url("options-general.php?page=settings_mf_base#settings_site_manager")."'>", "</a>")."</em>";
	}

echo "</div>";