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


add_filter('page_row_actions','my_action_row', 10, 2);

function my_action_row($actions, $post){
    //check for your post type
    if ($post->post_type =="groups" && !current_user_can( 'manage_options' ) ) {
        return array(); //returns an empty array to remove them all if not admin
    }
    return $actions;
}

//*************testing making parent leaders of children*********************
function make_parent_leaders_all_down () {
    
    //this goes through and builds the array of child groups in the $child_pages variable
    global $post;
    $my_wp_query = new WP_Query();
    $all_wp_pages = $my_wp_query->query(array('post_type' => 'groups', 'posts_per_page' => -1));
    $group_id = $post->ID;//The parent post you want it to run on - default to current group
    $child_pages = get_page_children( $group_id, $all_wp_pages ); //array of the child pages is here
    
    $group_leaders = learndash_get_groups_administrator_ids($group_id); //array of group leader user ids

    //goes through each child group
    foreach ( $child_pages as $child_id ) {
        $child_id_meta = 'learndash_group_leaders_' . $child_id->ID;
        //for each group leader in the parent group update the leaders user meta with the info for child group
        foreach ( $group_leaders as $group_leader ) {
            update_user_meta ( $group_leader, $child_id_meta , $child_id->ID );
        }
    }

}

add_action( 'save_post', 'make_parent_leaders_all_down');

/************end making parents leaders of children****************/

/***********making all groups leaders in a group ************/
function make_all_group_leaders_in_a_group () {
    if ( current_user_can ( 'group_leader' ) ) {
        $user_ID = get_current_user_id ();
        update_user_meta ( $user_ID, 'learndash_group_users_2186', '2186' );
    }
}

add_action ( 'init', 'make_all_group_leaders_in_a_group' );

/* Stop Adding Functions Below this Line */
?>