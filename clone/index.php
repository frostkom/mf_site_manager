<?php

$obj_site_manager = new mf_site_manager();

$intBlogID = check_var('intBlogID');

if(isset($_POST['btnSiteClone']) && isset($_POST['intSiteCloneAccept']) && $_POST['intSiteCloneAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce_site_clone'], 'site_clone_'.$wpdb->blogid.'_'.get_current_user_id()))
{
	if($intBlogID > 0 && $intBlogID != $wpdb->blogid)
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
		$strBasePrefixTo = $intBlogID > 1 ? $wpdb->base_prefix.$intBlogID."_" : $wpdb->base_prefix;
		$strBlogDomainTo = get_site_url_clean(array('id' => $intBlogID, 'trim' => "/"));

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
			$obj_theme_core = new mf_theme_core();

			$upload_path_global = WP_CONTENT_DIR."/uploads/";
			$upload_url_global = WP_CONTENT_URL."/uploads/";

			$upload_path_from = $upload_path_global."sites/".$wpdb->blogid."/";
			$upload_url_from = $upload_url_global."sites/".$wpdb->blogid."/";

			$upload_path_to = $upload_path_global."sites/".$intBlogID."/";
			$upload_url_to = $upload_url_global."sites/".$intBlogID."/";

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

			$done_text = __("All data was cloned", 'lang_site_manager');
			//$done_text .= " (".$strBasePrefixFrom." -> ".$strBasePrefixTo.")";
			//$done_text .= " [".nl2br($str_queries)."]";

			$user_data = get_userdata(get_current_user_id());

			do_log(sprintf(__("%s cloned %s to %s", 'lang_site_manager'), $user_data->display_name, $strBlogDomainFrom, $strBlogDomainTo), 'auto-draft');
		}
	}

	else
	{
		$error_text = __("You have to choose a site other than this site", 'lang_site_manager');
	}
}

echo "<div class='wrap'>
	<h2>".__("Clone Site", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('name' => 'intBlogID_old', 'text' => __("From", 'lang_site_manager'), 'value' => get_site_url_clean(), 'xtra' => "readonly"));

				if(is_multisite())
				{
					$arr_data = $obj_site_manager->get_sites_for_select();

					if(count($arr_data) > 1)
					{
						echo show_select(array('data' => $arr_data, 'name' => 'intBlogID', 'value' => $intBlogID, 'text' => __("To", 'lang_site_manager'), 'required' => true))
						.show_checkbox(array('name' => 'intSiteKeepTitle', 'text' => __("Would you like to keep the original title of the receiving site?", 'lang_site_manager'), 'value' => 1))
						.show_checkbox(array('name' => 'intSiteEmptyPlugins', 'text' => __("Would you like to empty Active Plugins field?", 'lang_site_manager'), 'value' => 1))
						.show_checkbox(array('name' => 'intSiteCloneAccept', 'text' => __("Are you really sure? This will erase all previous data on the recieving site.", 'lang_site_manager'), 'value' => 1, 'required' => true))
						.show_button(array('name' => 'btnSiteClone', 'text' => __("Perform", 'lang_site_manager')))
						.wp_nonce_field('site_clone_'.$wpdb->blogid.'_'.get_current_user_id(), '_wpnonce_site_clone', true, false);
					}

					else
					{
						echo "<em>".__("I could not find any sites to clone to", 'lang_site_manager')."</em>";
					}
				}

				else
				{
					echo __("You have to have a MultiSite to be able to clone this site", 'lang_site_manager');
				}

			echo "</form>
		</div>
	</div>
</div>";