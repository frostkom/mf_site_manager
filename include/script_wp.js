jQuery(function($)
{
	function run_ajax(obj)
	{
		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: script_site_manager.ajax_url,
			data: {
				action: obj.action
			},
			success: function(data)
			{
				obj.selector.empty();

				if(obj.button.is('a'))
				{
					obj.button.addClass('hide');
				}

				else
				{
					obj.button.attr('disabled', true);
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

	$(document).on('click', "button[name=btnGetServerIP]", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'force_server_ip',
			'selector': $('#ip_debug')
		});
	});

	/*$(document).on('click', "button[name=btnGetMyIP]", function(e)
	{
		var url = script_site_manager.get_ip_url,
			button = $(e.currentTarget),
			selector = $('#ip_debug');

		selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: url,
			success: function(data)
			{
				selector.empty();

				button.attr('disabled', true);

				if(data.success)
				{
					selector.html(data.message);
				}

				else
				{
					selector.html(data.error);
				}
			}
		});
	});*/
});