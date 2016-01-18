<?php
/**
 * Dashboard template: No Access.
 *
 * Template is displayed when a user tries to access a Dashboard page that he
 * has no permission to view.
 *
 * Important: For security reasons this template also ends in `exit` to
 * prevent any other plugin code to be executed unintentionally.
 */

?>
<div class="error">
	<p>
	<strong><?php _e( 'Error', 'wpmudev' ); ?></strong><br />
	<?php _e( 'You do not have the permission to view this page', 'wpmudev' ); ?>
	</p>
</div>

<?php
exit;	  			 				 	   	 