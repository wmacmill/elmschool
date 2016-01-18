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
	add_post_type_support ( 'groups' , 'author' );
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

/*filtering the user permissions - gives access to all editors to all courses including previews*/
//add_filter ( 'sfwd_lms_has_access', 'make_all_editors_access_all_courses', 10, 2);
//Problem with this because it enrolls everyone in everything so can't do it this way
function make_all_editors_access_all_courses ( $post_id, $user_id ) {
    $user_id = get_current_user_id();

    if ( user_can( $user_id, 'edit_others_courses' ) ) {
        return true;
    }

    $not_editor = sfwd_lms_has_access_fn( $post_id, $user_id );
    return $not_editor;
}

/*
* This code sets the default "per page" for groups to 100 so the nested look works 
*/
function filter_edit_posts_per_page( $per_page, $post_type ) {
    global $pagenow;

    if ( ( $pagenow == 'edit.php' ) && ($_GET['post_type'] == 'groups') ) {
        $per_page = 100;
    }

    return $per_page;
};
        
// add the filter
add_filter( 'edit_posts_per_page', 'filter_edit_posts_per_page', 10, 2 );

/*
* this section is meant to remove full time courses from Franchisee access by restricting group leaders access to them 
* while also creating a warning message when a franchisee is enrolled in a full time course
*/

/*modifies the post titles on full time courses*/
function modify_group_titles_of_courses ( $title, $id ) {

    if ( get_post_type ( $id ) == 'sfwd-courses' && in_category( 'Full Time', $id ) ) {
        $title = 'Full Time: ' . $title;
    }

    return $title;
};

add_filter ( 'the_title', 'modify_group_titles_of_courses', 10, 2 );


/*create a pop up warning if assinging full time courses to franchisee*/
function add_warning_if_franchisee_in_group_with_fulltime_course () {
    global $pagenow;

    if ( $pagenow == 'post.php' && get_post_type() == 'groups' ) {

        $group_id = get_the_id();
        $group_enrolled_courses = learndash_group_enrolled_courses ( $group_id );
        
        foreach ( $group_enrolled_courses as $course ) {
            if ( in_category( 'Full Time', $course) )
        
            $group_user_ids = learndash_get_groups_user_ids ( $group_id );
            
            foreach ( $group_user_ids as $user ) {
                if ( user_can ( $user , 'subscriber' ) ) {
                    
                    ?>
                    <div class="update-nag notice">
                      <p><?php _e( '<strong>WARNING: </strong>You have enrolled a full time course in a group that contains franchisees. Please ensure you mean to do this as full time courses contain content that is not geared toward franchisees.', 'my_plugin_textdomain' );?></p>
                    </div>
                    <?php        

                    return;
                }
            }
        }
    
    }
}


add_action ( 'admin_notices', 'add_warning_if_franchisee_in_group_with_fulltime_course' );

/*this will become a catch all for admins to see which ones have them enrolled
function add_admin_error_for_groups () {
    if ( get_post_type () == 'groups' && current_user_can ( 'edit_others_groups' ) ) {
        
        $group_id = get_the_id();
        $group_enrolled_courses = learndash_group_enrolled_courses ( $group_id );
        
        foreach ( $group_enrolled_courses as $course ) {
            if ( in_category( 'Full Time', $course) )
        
            $group_user_ids = learndash_get_groups_user_ids ( $group_id );
            
            foreach ( $group_user_ids as $user ) {
                if ( user_can ( $user , 'subscriber' ) ) {
                    
                    ?>
                    <div class="update-nag notice">
                      <p><?php _e( '<strong>WARNING: </strong>The following groups have franchisees enrolled in Full Time Courses: ', 'my_plugin_textdomain' ); echo get_the_title($group_id);?></p>
                    </div>
                    <?php        

                    //return;
                }
            }
        }
    
    }

}

add_action ( 'admin_notices', 'add_admin_error_for_groups' );
*/

//this specifically removes the "Full Time" courses from the dropdown on the back end for anyone who can't write a course
function remove_full_time_courses_from_group_leaders () {
    global $pagenow;

    if ( $pagenow == 'post.php' && get_post_type() == 'groups' && !current_user_can ( 'publish_courses' ) ) {
        wp_enqueue_script(
            'ld-group-ft-course-remove', // name your script so that you can attach other scripts and de-register, etc.
            plugins_url( '/js/ld-group-ft-course-remove.js', __FILE__ ), // this is the location of your script file
            array('jquery') // this array lists the scripts upon which your script depends
        );
    }
}

add_action ( 'admin_footer', 'remove_full_time_courses_from_group_leaders', 9999 );

//adds filter to user role editor to remove additional capabiliies from everyone other than admin
add_filter ('ure_show_additional_capabilities_section', 'will_remove_additional_capabilities_section');

function will_remove_additional_capabilities_section ( $show ) {
    if ( !current_user_can ('manage_options') ) {
        return false;
    }
}



/******* Stop Adding Functions Below this Line *************/

?>