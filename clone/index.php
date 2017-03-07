<?php

$intBlogID = check_var('intBlogID');

if(isset($_POST['btnSiteClone']) && isset($_POST['intSiteCloneAccept']) && $_POST['intSiteCloneAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce'], 'site_clone'))
{
	if($intBlogID > 0 && $intBlogID != $wpdb->blogid)
	{
		$default_charset = DB_CHARSET != '' ? DB_CHARSET : "utf8";

		$str_queries = "";

		$strBasePrefixFrom = $wpdb->prefix;
		$strBlogDomainFrom = trim($wpdb->get_var($wpdb->prepare("SELECT CONCAT(domain, path) FROM ".$wpdb->base_prefix."blogs WHERE blog_id = '%d'", $wpdb->blogid)), "/");

		$arr_tables_from = array();

		$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixFrom."%'");

			$str_queries .= "SHOW TABLES LIKE '".$strBasePrefixFrom."%';\n";

		foreach($result as $r)
		{
			foreach($r as $s)
			{
				$arr_tables_from[] = $s;
			}
		}

		$strBasePrefixTo = $intBlogID > 1 ? $wpdb->base_prefix.$intBlogID."_" : $wpdb->base_prefix;
		$strBlogDomainTo = trim($wpdb->get_var($wpdb->prepare("SELECT CONCAT(domain, path) FROM ".$wpdb->base_prefix."blogs WHERE blog_id = '%d'", $intBlogID)), "/");

		$arr_tables_to = array();

		$result = $wpdb->get_results("SHOW TABLES LIKE '".$strBasePrefixTo."%'");

			$str_queries .= "SHOW TABLES LIKE '".$strBasePrefixTo."%';\n";

		foreach($result as $r)
		{
			foreach($r as $s)
			{
				$arr_tables_to[] = $s;
			}
		}

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
			foreach($arr_tables_from as $r)
			{
				$table_name_from = $r;

				$table_name_prefixless = str_replace($strBasePrefixFrom, "", $table_name_from);

				$table_name_to = $strBasePrefixTo.$table_name_prefixless;

				if(in_array($table_name_to, $arr_tables_to))
				{
					$wpdb->query("DROP TABLE IF EXISTS ".$table_name_to);

						$str_queries .= "DROP TABLE IF EXISTS ".$table_name_to.";\n";

					$wpdb->query("CREATE TABLE IF NOT EXISTS ".$table_name_to." LIKE ".$table_name_from); //." DEFAULT CHARSET=".$default_charset

						$str_queries .= "CREATE TABLE IF NOT EXISTS ".$table_name_to." LIKE ".$table_name_from.";\n"; // DEFAULT CHARSET=".$default_charset."

					$wpdb->query("INSERT INTO ".$table_name_to." (SELECT * FROM ".$table_name_from.")");

						$str_queries .= "INSERT INTO ".$table_name_to." (SELECT * FROM ".$table_name_from.");\n";

					if($table_name_prefixless == "options")
					{
						$wpdb->query("UPDATE ".$table_name_to." SET option_value = REPLACE(option_value, '".$strBlogDomainFrom."', '".$strBlogDomainTo."') WHERE (option_name = 'siteurl' OR option_name = 'home')");

							$str_queries .= "UPDATE ".$table_name_to." SET option_value = REPLACE(option_value, '".$strBlogDomainFrom."', '".$strBlogDomainTo."') WHERE (option_name = 'siteurl' OR option_name = 'home');\n";

						if(isset($_POST['intSiteEmptyPlugins']) && $_POST['intSiteEmptyPlugins'] == 1)
						{
							$wpdb->query("UPDATE ".$table_name_to." SET option_value = '' WHERE option_name = 'active_plugins'");

								$str_queries .= "UPDATE ".$table_name_to." SET option_value = '' WHERE option_name = 'active_plugins';\n";
						}
					}

					else
					{
						$str_queries .= $table_name_prefixless."\n";
					}
				}
			}

			$done_text = __("All data was cloned", 'lang_site_manager')." (".$strBasePrefixFrom." -> ".$strBasePrefixTo.")";
			$done_text .= " [".nl2br($str_queries)."]";
		}
	}

	else
	{
		$error_text = __("You have to choose a site other than this site", 'lang_site_manager');
	}
}

echo "<div class='wrap'>
	<h2>".__("Clone", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<h3 class='hndle'><span>".__("Choose site to clone", 'lang_site_manager')."</span></h3>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>";

				$strBlogDomain = $wpdb->get_var($wpdb->prepare("SELECT CONCAT(domain, path) FROM ".$wpdb->base_prefix."blogs WHERE blog_id = '%d'", $wpdb->blogid));

				echo show_textfield(array('name' => 'intBlogID_old', 'text' => __("Clone from", 'lang_site_manager'), 'value' => $strBlogDomain, 'xtra' => "readonly"));

				if(is_multisite())
				{
					$result = $wpdb->get_results($wpdb->prepare("SELECT blog_id, domain, path FROM ".$wpdb->base_prefix."blogs WHERE blog_id != %d ORDER BY blog_id ASC", $wpdb->blogid));

					$arr_data = array();
					$arr_data[''] = "-- ".__("Choose here", 'lang_site_manager')." --";

					foreach($result as $r)
					{
						$blog_id = $r->blog_id;
						$domain = $r->domain;
						$path = $r->path;

						$arr_data[$blog_id] = $domain.$path;
					}

					echo show_select(array('data' => $arr_data, 'name' => 'intBlogID', 'value' => $intBlogID, 'text' => __("Clone to", 'lang_site_manager'), 'required' => true))
					.show_checkbox(array('name' => 'intSiteEmptyPlugins', 'text' => __("Would you like to empty Active Plugins field?", 'lang_site_manager'), 'value' => 1))
					.show_checkbox(array('name' => 'intSiteCloneAccept', 'text' => __("Are you really sure? This will erase all previous data on the recieving site.", 'lang_site_manager'), 'value' => 1, 'required' => true))
					.show_button(array('name' => 'btnSiteClone', 'text' => __("Clone", 'lang_site_manager')))
					.wp_nonce_field('site_clone', '_wpnonce', true, false);
				}

				else
				{
					echo __("You have to have a MultiSite to be able to clone this site", 'lang_site_manager');
				}

			echo "</form>
		</div>
	</div>
</div>";