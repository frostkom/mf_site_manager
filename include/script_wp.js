jQuery(function($)
{
	function run_ajax(obj)
	{
		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			url: script_site_manager.ajax_url,
			type: 'post',
			dataType: 'json',
			data: {
				action: obj.action
			},
			success: function(data)
			{
				obj.selector.empty();

				if(obj.button.is("a"))
				{
					obj.button.addClass('hide');
				}

				else
				{
					obj.button.addClass('is_disabled').attr('disabled', true);
				}

				if(data.success)
				{
					obj.selector.html(data.message);
				}

				else
				{
					obj.selector.html(data.error);
				}
			}
		});

		return false;
	}

	$(document).on('click', "button[name='btnGetServerIP']", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'force_server_ip',
			'selector': $("#ip_debug")
		});
	});
});