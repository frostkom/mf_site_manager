<?php

$strBlogUrl = check_var('strBlogUrl');

if(isset($_POST['btnSiteChangeUrl']) && isset($_POST['intSiteChangeUrlAccept']) && $_POST['intSiteChangeUrlAccept'] == 1 && wp_verify_nonce($_POST['_wpnonce'], 'site_change_url_'.$wpdb->blogid))
{
	$old_url = get_site_url();
	$new_url = $strBlogUrl;

	if($new_url != $old_url)
	{
		$wpdb->query("UPDATE ".$wpdb->prefix."options SET option_value = replace(option_value, '".$old_url."', '".$new_url."') WHERE option_name = 'home' OR option_name = 'siteurl'");

		$wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid = replace(guid, '".$old_url."', '".$new_url."')");

		$wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_content = replace(post_content, '".$old_url."', '".$new_url."')");

		$done_text = sprintf(__("I have changed the URL from %s to %s", 'lang_site_manager'), $old_url, $new_url);
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