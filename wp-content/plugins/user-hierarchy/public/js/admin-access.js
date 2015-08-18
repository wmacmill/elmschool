jQuery(function($) {
	// Push permission set to next role
	$('.jwuh-capset-push-down').click(function() {
		$(this).parents('th').siblings('td').each(function() {
			$(this).parents('tr').next().children('td').eq($(this).index() - 1).find('input[type="checkbox"]').attr('checked', $(this).find('input[type="checkbox"]').is(':checked') ? true : false);
		});
		
		return false;
	});
	
	// Toggle all permissions for a role within a role's settings
	$('.jwuh-capset-switch').click(function() {
		$(this).parents('tr').find('input[type="checkbox"]').each(function() {
			$(this).attr('checked', $(this).is(':checked') ? false : true);
		});
		
		return false;
	});
	
	// Toggle role permissions enabled
	$('.jwuh-role-access-enabled').change(function() {
		var target = $(this).parents('.jwuh-role').children('div').last();
		
		if ($(this).is(':checked')) {
			$(this).parents('.jwuh-role').addClass('jwuh-enabled');
			
			target.slideDown();
		}
		else {
			$(this).parents('.jwuh-role').removeClass('jwuh-enabled');
			
			target.slideUp();
		}
	});
});