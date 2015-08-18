jQuery(document).ready(function() {
    jQuery( "input[name='sfwd-lessons_visible_after_specific_date']" ).datepicker({
			changeMonth: true,
			changeYear: true,
            dateFormat : 'yy-mm-dd'
		});
        
        jQuery("input[name='sfwd-lessons_visible_after_specific_date']").focus(function() {
             jQuery("input[name='sfwd-lessons_visible_after']").val('0');
             jQuery("input[name='sfwd-lessons_visible_after']").prop('disabled', true);
        });
        jQuery("input[name='sfwd-lessons_visible_after_specific_date']").blur(function() {
            var specific_data = jQuery("input[name='sfwd-lessons_visible_after_specific_date']").val();
            if( specific_data != '') {
            jQuery("input[name='sfwd-lessons_visible_after']").val('0');
           jQuery("input[name='sfwd-lessons_visible_after']").attr("disabled", "disabled");
           }else {
             jQuery("input[name='sfwd-lessons_visible_after']").removeAttr("disabled");
           }
        });
        jQuery("input[name='sfwd-lessons_visible_after']").click(function() {
             var specific_data = jQuery("input[name='sfwd-lessons_visible_after_specific_date']").val();
            if( specific_data != '') {
            jQuery("input[name='sfwd-lessons_visible_after']").val('0');
           jQuery("input[name='sfwd-lessons_visible_after']").attr("disabled", "disabled");
           }else {
             jQuery("input[name='sfwd-lessons_visible_after']").removeAttr("disabled");
           }
            });
  });