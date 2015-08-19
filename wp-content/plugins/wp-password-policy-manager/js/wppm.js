jQuery(function($){
    "use strict";
	var RemoveSecToken = function(){
		var $this = $(this).parents('span:first');
		$this.addClass('sectoken-del').fadeOut('fast', function(){
			$this.remove();
		});
	};

	$('#ExemptTokenQueryBox').keydown(function(event){
		if(event.keyCode === 13) {
			$('#ExemptTokenQueryAdd').click();
			return false;
		}
	});

	$('#ExemptTokenQueryAdd').click(function(){
		var value = $.trim($('#ExemptTokenQueryBox').val());
		var existing = $('#ExemptTokenList input').filter(function() { return this.value === value; });

		if(!value || existing.length)return; // if value is empty or already used, stop here

		$('#ExemptTokenQueryBox, #ExemptTokenQueryAdd').attr('disabled', true);
		$.post($('#ajaxurl').val(), {action: 'check_security_token', token: value}, function(data){
			$('#ExemptTokenQueryBox, #ExemptTokenQueryAdd').attr('disabled', false);
			if(data==='other' && !confirm('The specified token is not a user nor a role, do you still want to add it?'))return;
			$('#ExemptTokenQueryBox').val('');
			$('#ExemptTokenList').append($('<span class="sectoken-'+data+'"/>').text(value).append(
				$('<input type="hidden" name="ExemptTokens[]"/>').val(value),
				$('<a href="javascript:;" title="Remove">&times;</a>').click(RemoveSecToken)
			));
		});
	});

	$('#ExemptTokenList>span>a').click(RemoveSecToken);
});
