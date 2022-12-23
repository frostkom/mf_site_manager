document.createElement("mf-form-row");

jQuery(function($)
{
	function toggle_settings()
	{
		$(".show_replace").addClass('hide');

		switch($("#strTableAction").val())
		{
			case 'replace':
				$(".show_replace").removeClass('hide');
			break;
		}
	}

	toggle_settings();

	$(document).on('change', "#strTableAction", function()
	{
		toggle_settings();
	});
});