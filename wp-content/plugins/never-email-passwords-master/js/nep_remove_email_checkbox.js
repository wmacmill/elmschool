jQuery().ready(function() {
  jQuery('#pass1').val(NeverEmailPasswords.password);
  jQuery('#pass2').val(NeverEmailPasswords.password);
  jQuery('#send_password').parent().parent().parent().hide();
})
