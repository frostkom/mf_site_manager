jQuery(function($)
{
	$("#url, #siteurl, #home").prop('readonly', true).css({'margin-right': '1em', 'width': '50%'}).after("<a href='" + script_site_manager_url.change_url_link + "'>" + script_site_manager_url.change_url_text + "</a>");
});