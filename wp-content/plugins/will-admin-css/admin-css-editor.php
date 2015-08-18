<?php
/*
Plugin Name: Will - Admin Customization
Plugin URI:
Description: This plugin changes way the admin looks
Version: 0.1
Author: Will MacMillan
Author URI: http://www.facebook.com/macmillan.will
Text Domain: 
Domain Path: 
*/
/* Start Adding Functions Below this Line */

// let's start by enqueuing our styles correctly
//we're checking for the capabilities edit_others_posts=editors so that role isn't affected
function wptutsplus_admin_styles() {
    $user = wp_get_current_user();
    if( ! $user->has_cap ( 'edit_others_posts') ) {
    	wp_register_style( 'wptuts_admin_stylesheet', plugins_url( '/css/admin.css', __FILE__ ) );
    	wp_enqueue_style( 'wptuts_admin_stylesheet' );
    }
}
add_action( 'admin_enqueue_scripts', 'wptutsplus_admin_styles' );







/* Stop Adding Functions Below this Line */
?>