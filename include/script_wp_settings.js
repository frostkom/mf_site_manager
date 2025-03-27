jQuery(function($)
{
	function run_ajax(obj)
	{
		if(obj.button.is("a"))
		{
			obj.button.addClass('hide');
		}

		else
		{
			obj.button.addClass('is_disabled');
		}

		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			url: script_site_manager_settings.ajax_url,
			type: 'post',
			dataType: 'json',
			data: {
				action: obj.action
			},
			success: function(data)
			{
				obj.selector.html(data.html);
			}
		});

		return false;
	}

	$(document).on('click', "button[name='btnGetServerIP']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'api_site_manager_force_server_ip',
			'selector': $(".api_site_manager_force_server_ip")
		});
	});
});