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

//adding post type supports to groups
add_action('init', 'learndash_groups_add_author');

function learndash_groups_add_author() {
	add_post_type_support( 'groups', 'author' );
	remove_post_type_support ( 'groups', 'menu_order' );
}

//removing post type supports from groups where not needed
add_action('admin_init', 'remove_group_write_panels');

function remove_group_write_panels () {
	if ( !current_user_can ( 'manage_options' ) ) {
		remove_post_type_support( 'groups', 'editor' );	
	}
	
} 


//adds support for hierarchy in groups
function modify_groups() {
	//checks if user is admin - if yes then show them the page attributes box so they can set hierarchy
	if ( current_user_can ( 'manage_options') ) {
    	add_post_type_support('groups','page-attributes');
    }
	
    if ( post_type_exists( 'groups' ) ) {
    	
        /* Give groups hierarchy */
         /* Give products hierarchy (for house plans) */
        global $wp_post_types, $wp_rewrite;
        $wp_post_types['groups']->hierarchical = true;
        $args = $wp_post_types['groups'];
        $wp_rewrite->add_rewrite_tag("%groups%", '(.+?)', $args->query_var ? "{$args->query_var}=" : "post_type=groups=");
        
    }
}
add_action( 'init', 'modify_groups' );

/* Stop Adding Functions Below this Line */
?>