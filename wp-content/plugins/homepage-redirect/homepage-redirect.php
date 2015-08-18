<?php
/*
Plugin Name: Will - Logged in Redirect
Plugin URI:
Description: This plugin redirects the user to a page if they are logged in (stops them from going to the homepage)
Version: 0.1
Author: Will MacMillan
Author URI: http://www.facebook.com/macmillan.will
Text Domain: 
Domain Path: 
*/
/* Start Adding Functions Below this Line */


function homepage_redirect () {
	//This will check if they're logged in - if they are redirect to the profile page
	if (is_user_logged_in() && is_front_page() ) {
		wp_redirect('/profile');
	exit;
	}
}

add_action('wp_head', 'homepage_redirect');



/* Stop Adding Functions Below this Line */
?>