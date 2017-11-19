<?php

$strBlogUrl = check_var('strBlogUrl');

if(isset($_POST['btnSiteChangeUrl']) && isset($_POST['intSiteChangeUrlAccept']) && $_POST['intSiteChangeUrlAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce'], 'site_change_url_'.$wpdb->blogid))
{
	$old_url = get_site_url();
	$new_url = $strBlogUrl;

	if($new_url != $old_url)
	{
		$arr_errors = array();

		if(is_multisite())
		{
			$old_url_clean = mf_clean_url($old_url);
			$new_url_clean = mf_clean_url($new_url);

			if($new_url_clean != $old_url_clean)
			{
				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->blogs." SET domain = %s WHERE blog_id = '%d'", $new_url_clean, $wpdb->blogid));
				if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
			}

			$wpdb->get_results($wpdb->prepare("SELECT id FROM ".$wpdb->site." WHERE domain = %s", $old_url));

			if($wpdb->num_rows > 0)
			{
				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->site." SET domain = %s WHERE domain = %s", $new_url, $old_url));
				if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
			}

			$wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->sitemeta." WHERE meta_key = 'siteurl' AND meta_value = %s", $old_url));

			if($wpdb->num_rows > 0)
			{
				$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->sitemeta." SET meta_value = %s WHERE meta_key = 'siteurl' AND meta_value = %s", $new_url, $old_url));
				if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
			}
		}

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->options." SET option_value = replace(option_value, %s, %s) WHERE option_name = 'home' OR option_name = 'siteurl'", $old_url, $new_url));
		if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE guid LIKE %s", "%".$old_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET guid = replace(guid, %s, %s)", $old_url, $new_url));
			if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_content LIKE %s", "%".$old_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_content = replace(post_content, %s, %s)", $old_url, $new_url));
			if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
		}

		$wpdb->get_results($wpdb->prepare("SELECT meta_id FROM ".$wpdb->postmeta." WHERE meta_value LIKE %s", "%".$old_url."%"));

		if($wpdb->num_rows > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->postmeta." SET meta_value = replace(meta_value, %s, %s)", $old_url, $new_url));
			if($wpdb->rows_affected == 0){	$arr_errors[] = $wpdb->last_query;}
		}

		$count_temp = count($arr_errors);

		if($count_temp > 0)
		{
			$error_text = sprintf(__("I executed your request but there were %d errors so you need to manually update the database", 'lang_site_manager'), $count_temp);

			do_log("Change URL Errors: ".var_export($arr_errors, true));
		}

		else
		{
			$done_text = sprintf(__("I have changed the URL from %s to %s", 'lang_site_manager'), $old_url, $new_url);
		}
	}

	else
	{
		$error_text = __("You have to choose another URL than the current one", 'lang_site_manager');
	}
}

echo "<div class='wrap'>
	<h2>".__("Change URL", 'lang_site_manager')."</h2>"
	.get_notification()
	."<div id='poststuff' class='postbox'>
		<div class='inside'>
			<form method='post' action='' class='mf_form'>"
				.show_textfield(array('name' => 'strBlogUrl', 'text' => __("Change to this URL", 'lang_site_manager'), 'value' => get_site_url()))
				.show_checkbox(array('name' => 'intSiteChangeUrlAccept', 'text' => __("Are you really sure? This will change the URL of the site", 'lang_site_manager'), 'value' => 1, 'required' => true))
				.show_button(array('name' => 'btnSiteChangeUrl', 'text' => __("Perform", 'lang_site_manager')))
				.wp_nonce_field('site_change_url_'.$wpdb->blogid, '_wpnonce', true, false)
			."</form>
		</div>
	</div>
</div>";