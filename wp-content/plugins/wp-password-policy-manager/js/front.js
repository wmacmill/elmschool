jQuery(function($){
    "use strict";
    if(wppm_ModifyForm){
        var d = wppm_ModifyForm;

        // update form fields
        $('#user_login').val('').val(d.CurrentUserLogin).attr('readonly', true);
        var p = $('#user_pass').val(d.CurrentUserPass).attr('readonly', true).parents('p:first');
        p.find('label').contents()[0].textContent = d.TextOldPass;

        // update form button
        $('#wp-submit').val(d.BtnChangeAndLogin);

        // update form width + add rules
        var w = 280;
        if(d.NewPasswordRules.length){
            $('#login').width($('#login').width() + w);
            $('#loginform').css({'padding-right': w, 'position': 'relative'});
            var u = $('<ul/>').css({'list-style': 'inside disc'});
            for(var i in d.NewPasswordRules)
                u.append($('<li/>').text(d.NewPasswordRules[i]));
            $('#loginform').append(
                $('<div/>').css({
                    'position': 'absolute',
                    'right': '24px',
                    'top': '188px',
                    'width': (w - 48) + 'px'
                }).append(
                    $('<label/>').text(d.NewPassRulesHead),
                    u, $('<div/>').html(d.NewPassRulesFoot).css({
                        'margin-top': '24px',
                        'font-size': '10px',
                        'font-weight': 'bold',
                        'line-height': '12px',
                        'text-align': 'center'
                    })
                )
            );
        }
    }
});
