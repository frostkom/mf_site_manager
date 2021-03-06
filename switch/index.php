<?php

$obj_site_manager = new mf_site_manager();

$intBlogID = check_var('intBlogID');

if(isset($_POST['btnSiteSwitch']) && isset($_POST['intSiteSwitchAccept']) && $_POST['intSiteSwitchAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce_site_switch'], 'site_switch_'.$wpdb->blogid.'_'.get_current_user_id()))
{
	if($intBlogID > 0 && $intBlogID != $wpdb->blogid)
	{
		$str_queries = "";

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

		$strBasePrefixTo = $intBlogID > 1 ? $wpdb->base_prefix.$intBlogID."_" : $wpdb->base_prefix;
		$strBlogDomainTo = get_site_url_clean(array('id' => $intBlogID, 'trim' => "/"));

		$strBlogDomain_temp = "mf_cloner.com";
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

echo "<div class='wrap'>
	<h2>".__("Switch Sites", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('name' => 'intBlogID_old', 'text' => __("Switch this site...", 'lang_site_manager'), 'value' => get_site_url_clean(), 'xtra' => "readonly"));

				if(is_multisite())
				{
					$arr_data = $obj_site_manager->get_sites_for_select(array('exclude' => $wpdb->blogid));

					if(count($arr_data) > 1)
					{
						echo show_select(array('data' => $arr_data, 'name' => 'intBlogID', 'value' => $intBlogID, 'text' => __("...with this", 'lang_site_manager'), 'required' => true))
						.show_checkbox(array('name' => 'intSiteSwitchAccept', 'text' => __("Are you really sure? This will switch domain for the two sites.", 'lang_site_manager'), 'value' => 1, 'required' => true))
						.show_button(array('name' => 'btnSiteSwitch', 'text' => __("Perform", 'lang_site_manager')))
						.wp_nonce_field('site_switch_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_switch', true, false);
					}

					else
					{
						echo "<em>".__("I could not find any sites to switch to", 'lang_site_manager')."</em>";
					}
				}

				else
				{
					echo __("You have to have a MultiSite to be able to switch sites", 'lang_site_manager');
				}

			echo "</form>
		</div>
	</div>
</div>";