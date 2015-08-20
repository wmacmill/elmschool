<?php
/**
 * This is the functions.php file.
 *
 * Use this file to add php funtions to the parent theme so that it doesn't get overwritten
 * 
 */

/*
*
******** Canvas specfic customizations
*
*/

//moving the menu to the right of the logo on all pages
add_action( 'init', 'woo_custom_move_navigation', 10 );
function woo_custom_move_navigation () {
 // Remove main nav from the woo_header_after hook
 remove_action( 'woo_header_after','woo_nav', 10 );
 // Add main nav to the woo_header_inside hook
 add_action( 'woo_header_inside','woo_nav', 10 );
 } // End woo_custom_move_navigation()

/*adds login/logout button to the right side of the primary menu*/
add_filter( 'wp_nav_menu_items', 'add_loginout_link', 10, 2 );
function add_loginout_link( $items, $args ) {
    if (is_user_logged_in() && $args->theme_location == 'primary-menu') {
        $items .= '<li><a href="'. wp_logout_url() .'">Log Out</a></li>';
    }
    elseif (!is_user_logged_in() && $args->theme_location == 'primary-menu') {
        $items .= '<li><a href="'. site_url('wp-login.php') .'">Log In</a></li>';
    }
    return $items;
}
/*end adding login logout button*/

/*
*
********* end Canvas customizations
*
*/

/*removes admin color option from profile page*/
function admin_color_scheme() {
   global $_wp_admin_css_colors;
   $_wp_admin_css_colors = 0;
}
add_action('admin_head', 'admin_color_scheme');

//hides the "add new" button on groups for everyone that doesn't have the "publish_groups" ability
function hide_that_stuff() {
	if( !current_user_can( 'publish_groups' ) ) {
		if('groups' == get_post_type())
		echo '<style type="text/css">
	    #favorite-actions {display:none;}
	    .add-new-h2{display:none;}
	    .tablenav{display:none;}
	    </style>';
	}
    
    global $pagenow;
    
    if ( !current_user_can('update_core') && $pagenow == 'profile.php' ) {
        echo '<style>.user-nickname-wrap{display:none;}.user-url-wrap{display:none;}.user-description-wrap{display:none;}#wppmUserProfilePwdRulesContainer{display:none;}</style>';
    }

    //removes the website box on the user profile
    if ( $pagenow == 'user-new.php' || $pagenow == 'user-edit.php' ) {
        echo "<script>jQuery(document).ready(function(){jQuery('#url').parents('tr').remove();});</script>"; //removes the website field from user profile    
    }

    if ( $pagenow == 'user-edit.php' ) {
        echo '<style>.ms-parent{display:none;}</style>';
    }


    
}
add_action('admin_head', 'hide_that_stuff');
//end hide "add new" button group


/*
*
* This is to add users to a drop down menu as a test
*
*/
add_filter( 'gform_pre_render', 'populate_posts' );
add_filter( 'gform_pre_validation', 'populate_posts' );
add_filter( 'gform_pre_submission_filter', 'populate_posts' );
add_filter( 'gform_admin_pre_render', 'populate_posts' );
function populate_posts( $form ) {

    foreach ( $form['fields'] as &$field ) {

        if ( $field->type != 'post_custom_field' || strpos( $field->cssClass, 'populate-posts' ) === false ) {
            continue;
        }

        // you can add additional parameters here to alter the posts that are retrieved
        // more info: http://codex.wordpress.org/Template_Tags/get_posts
        //$posts = get_posts( 'numberposts=-1&post_status=publish' );
        $args = array(
          'orderby' => 'display_name'
          );
        $posts = get_users ( $args );

        $choices = array();

        foreach ( $posts as $post ) {
            $choices[] = array( 'text' => $post->display_name, 'value' => $post->ID );
        }

        // update 'Select a Post' to whatever you'd like the instructive option to be
        $field->placeholder = 'Select a User';
        $field->choices = $choices;

    }

    return $form;
}

/*This is the piece that enables the email to the learner by populating the learner email field*/
add_action( 'gform_pre_submission', 'pre_submission_handler' );

function pre_submission_handler( $form ) {

    foreach ($form['fields'] as &$field ) {   
    
    if ( $field->type != 'email' || strpos( $field->cssClass, 'feedback-learner-email' ) === false ) {
            continue;
        }

        $learner_id = rgpost( 'input_5' );
        $user_info = get_userdata($learner_id);
        $learner_email = $user_info->user_email;

        $_POST['input_57'] = $learner_email ;
    }
    return $form;
}

//Removing some meta boxes on the feedback form
function remove_custom_taxonomy()
{
    remove_meta_box('skilldiv', 'feedback_form', 'side' );
    remove_meta_box('divisiondiv', 'feedback_form', 'side' );
    remove_meta_box('coauthorsdiv', 'feedback_form', 'side' );
    remove_meta_box('woothemes-settings', 'feedback_form', 'normal');
    remove_meta_box('wppl-meta-box', 'feedback_form', 'normal');
    remove_meta_box('skill_leveldiv', 'feedback_form', 'side');
    remove_meta_box('disable_title', 'feedback_form');

    //removes the skills & divison meta box from the feedback form as I'm going to use ACF to manage it as a dropdown menu
}
add_action( 'do_meta_boxes', 'remove_custom_taxonomy' ); //using do_meta_boxes instead of admin_menu because it fires later

function be_gallery_metabox_page_and_rotator( $post_types ) {
    return array( 'feedback_form' );
}
add_action( 'be_gallery_metabox_post_types', 'be_gallery_metabox_page_and_rotator' );

//this is just to remove the annoying admin notice that shows for all users on the admin from advanced file upload plugin for gravity forms
remove_action ( 'admin_notices' , 'prso_gformsadv_admin_notice' );

//these two are to remove the messages displayed by the "private-only" plugin on the login form which breaks the reset password links
remove_filter ( 'login_message', 'custom_login_message' );
remove_filter('register_message', 'custom_register_message');



/*******
*
*
*   Javascript funtion to go through the options for a course price type and removes everything but open & closed
*
*
*******/
add_action( 'admin_footer', 'wpse_59652_list_terms_exclusions', 9999 );

function wpse_59652_list_terms_exclusions() 
{
    global $current_screen;

    if( 'sfwd-courses' != $current_screen->post_type ) //only fire on the courses page otherwise ABORT!
        return;

    echo "
        <script type='text/javascript'>
            var select = document.querySelector('select[name=sfwd-courses_course_price_type]');
            console.log(select[0].tagName);

            for (i = 0; i < select.length; i++) {
                if (select.options[i].value == 'free' ) {
                    select.remove(i);
                }
                if (select.options[i].value == 'paynow' ) {
                    select.remove(i);
                }
                if (select.options[i].value == 'subscribe' ) {
                    select.remove(i);
                }
            }
        </script>
    ";
}


//This removes the ability for anyone other than admin to upload videos
add_filter('upload_mimes', 'custom_upload_mimes');
function custom_upload_mimes ( $existing_mimes=array() ) {
    
    if (current_user_can('administrator')) {//abort if admin
        return $existing_mimes;
    }
    //see https://codex.wordpress.org/Function_Reference/get_allowed_mime_types for allowed mime types
    //see https://codex.wordpress.org/Plugin_API/Filter_Reference/upload_mimes for how this function is working 
    // Add file extension 'extension' with mime type 'mime/type'
    $existing_mimes['extension'] = 'mime/type';
     
    // add as many as you like e.g. 
    /*

    $existing_mimes['doc'] = 'application/msword';

    */
    // remove all the video upload options...
    unset( $existing_mimes['exe'] );//also executeable files - just asking for trouble
    unset( $existing_mimes['avi'] );
    unset( $existing_mimes['mp4|m4v'] );
    unset( $existing_mimes['wmv'] ); 
    unset( $existing_mimes['asf|asx'] );
    unset( $existing_mimes['wmx'] );
    unset( $existing_mimes['wm'] );
    unset( $existing_mimes['divx'] );
    unset( $existing_mimes['flv'] );
    unset( $existing_mimes['mov|qt'] );
    unset( $existing_mimes['mpeg|mpg|mpe'] );
    unset( $existing_mimes['ogv'] );
    unset( $existing_mimes['webm'] );
    unset( $existing_mimes['mkv'] );


    // and return the new full result
    return $existing_mimes;

}

/**
* This is the end. Ensure the file closes with a php tag ?>
*
*
**/
?>