jQuery(function($)
{
	var dom_trs = $(".wp-list-table tbody tr:not(.site-deleted)");

	dom_trs.each(function()
	{
		var dom_obj_parent = $(this);

		dom_obj_parent.find(".settings.column-settings div > a").each(function()
		{
			var dom_obj = $(this),
				data_setting = dom_obj.data('setting'),
				data_color = dom_obj.data('color'),
				settings_are_equal = true;

			dom_obj_parent.siblings("tr").find(".settings.column-settings div > a[data-setting='" + data_setting + "']").each(function()
			{
				if($(this).data('color') != data_color)
				{
					settings_are_equal = false;
				}
			});

			if(settings_are_equal == true)
			{
				dom_obj.addClass('hide');

				dom_obj_parent.siblings("tr").find(".settings.column-settings div > a[data-setting='" + data_setting + "']").addClass('hide');
			}
		});

		return false;
	});

	$(document).on('click', ".wp-list-table tbody tr .settings.column-settings .toggle_all", function()
	{
		$(this).parents(".column-settings").find("div > a").each(function()
		{
			var dom_obj = $(this);

			if(dom_obj.hasClass('hide'))
			{
				dom_obj.addClass('was_hidden').removeClass('hide');
			}

			else if(dom_obj.hasClass('was_hidden'))
			{
				dom_obj.addClass('hide').removeClass('was_hidden');
			}
		});
	})
});