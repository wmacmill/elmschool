<?php
/*
Plugin Name: Will - Group Author Support
Plugin URI:
Description: Adds author support for groups so you can have multiple people edit the group 
Version: 0.1
Author: Will MacMillan
Author URI: http://www.facebook.com/macmillan.will
Text Domain: 
Domain Path: 
*/
/* Start Adding Functions Below this Line */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', 'learndash_groups_add_author');
	function learndash_groups_add_author() {
		add_post_type_support( 'groups', 'author' );
}

/* Stop Adding Functions Below this Line */
?>