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

//adding filters for other file types in media library
function modify_post_mime_types( $post_mime_types ) {

    // select the mime type, here: 'application/pdf'
    // then we define an array with the label values

    $post_mime_types['application/pdf'] = array( __( 'PDFs' ), __( 'Manage PDFs' ), _n_noop( 'PDF', 'PDF' ) );
    $post_mime_types['application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] = array( __( 'XLSs' ), __( 'Manage XLSs' ), _n_noop( 'Excel', 'Excel' ) );
    $post_mime_types['application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'] = array( __( 'Words' ), __( 'Manage Words' ), _n_noop( 'Word', 'Word' ) );
    $post_mime_types['application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation'] = array( __( 'PPTs' ), __( 'Manage PPTs' ), _n_noop( 'PPT', 'PPTs' ) );
    // then we return the $post_mime_types variable
    return $post_mime_types;

}

// Add Filter Hook
add_filter( 'post_mime_types', 'modify_post_mime_types' );

/*
* In this section we're doing a few things.
* 1. Adding support for categories to topics
* 2. Giving permission to edit "Quebec" category courses if they are a particular user
* 3. Cleaning up the admin backend for those peopel that get the new permissions so they only have access to what's needed
*
*/

add_action('init','will_add_topic_support_for_categories', 15);

function will_add_topic_support_for_categories () {
    register_taxonomy_for_object_type ( 'category', 'sfwd-topic' );
}


function will_give_permissions( $allcaps, $cap, $args ) {
   $user_id = get_current_user_id();
   $post = $args[2];//get_post( $args[2] );
   $post_type = get_post_type ( $post );


   if ( $user_id === 397 && in_category ( 'quebec' , $post ) ) {

        $allcaps[$cap[0]] = true;

        return $allcaps;
    }

    return $allcaps;

}
add_filter( 'user_has_cap', 'will_give_permissions', 0, 3 );

function will_remove_menus_for_user_permissions () {
    $user_ID = get_current_user_id();

    if ( $user_ID == 397 ) {
        remove_menu_page( 'jetpack' );
        //remove_menu_page( 'index.php' );                  //Dashboard
        remove_menu_page( 'edit.php' );                   //Posts
        remove_menu_page( 'upload.php' );                 //Media
        remove_menu_page( 'edit.php?post_type=page' );    //Pages
        remove_menu_page( 'edit-comments.php' );          //Comments
        remove_menu_page( 'themes.php' );                 //Appearance
        remove_menu_page( 'plugins.php' );                //Plugins
        remove_menu_page( 'users.php' );                  //Users
        remove_menu_page( 'tools.php' );                  //Tools
        remove_menu_page( 'options-general.php' );        //Settings
        remove_menu_page( 'link-manager.php' );
        remove_menu_page( 'edit.php?post_type=acf' );
        remove_menu_page( 'admin.php?page=branding' );
        remove_menu_page( 'edit.php?post_type=sfwd-assignment' );
        remove_submenu_page('edit.php?post_type=sfwd-courses', 'edit.php?post_type=sfwd-assignment' );


        echo '<style type="text/css">
        #toplevel_page_branding {display:none !important;}
        #toplevel_page_wpbi {display:none !important;}
        #toplevel_page_gmw-add-ons {display:none !important;}
        .nav-tab-sfwd-quiz {display:none !important;}
        .nav-tab-edit-sfwd-quiz {display:none !important;}
        .nav-tab-admin_page_ldAdvQuiz_globalSettings {display:none !important;}
        .nav-tab-admin_page_ldAdvQuiz {display:none !important;}
        .nav-tab-admin_page_ldAdvQuiz_statistics {display:none !important;}
        .nav-tab-admin_page_ldAdvQuiz_toplist {display:none !important;}
        #woothemes-settings {display:none !important;}
        </style>';

        $post = get_the_ID();
        $post_type = get_post_type ( $post );

        if ( $post_type != 'sfwd-quiz' ) {
            echo '<style type="text/css">
            .nav-tab-wrapper {display:none !important;}
            </style>';
        }

        //this isn't working for some reason - take it out for now and deal with it
        /*if ( $post_type != 'sfwd-courses'  ) {
            echo '<style type="text/css">
            .row-actions {display:none !important;}
            </style>';
        }*/
    }
}

add_action( 'admin_menu', 'will_remove_menus_for_user_permissions', 999 );

function will_remove_support_for_this_guy () {
    $user_ID = get_current_user_id();

    if ( $user_ID == 397 ) {
        $remove_things = array (
                    'sfwd-topic',
                    'sfwd-courses',
                    'sfwd-quiz',
                    'sfwd-lessons',
                );

            foreach ( $remove_things as $remove_thing ) {
                remove_post_type_support ( $remove_thing , 'author' );
                remove_post_type_support ( $remove_thing , 'comments' );
            }
    }

}

add_action ( 'admin_init' , 'will_remove_support_for_this_guy', 10 );


function wpcodex_set_capabilities() {
    $user_ID = get_current_user_id();
    if( $user_ID == 397 ) {
        $user = new WP_user ( $user_ID );
                $caps = array(
                    'read_assignment',
                    'edit_assignment',
                    'edit_assignments',
                    'publish_assignments',
                    'edit_published_assignments',
                    'edit_groups',
                    'group_leader',
                    'delete_course',
                    'delete_courses',
                );

                foreach ( $caps as $cap ) {
                    $user->remove_cap ($cap);
                }
    }
}
add_action( 'init', 'wpcodex_set_capabilities', 1000 );

/*
*
* Redirecting users to a page if they're set as "terminated"
*/
function redirect_terminated_users_to_page () {
  global $wp;

  if ( current_user_can ('manage_options') ) { //jump ship if admin
    return;
  }

  if( is_user_logged_in() && current_user_can('terminated') && !is_page(2996)  ) {
    wp_redirect ('/disabled');
    exit;
  }
}

add_action ( 'wp', 'redirect_terminated_users_to_page' );

//removing my-sites from admin bar except for super admin
add_filter( 'admin_bar_menu', 'my_favorite_actions', 999 );
function my_favorite_actions($wp_toolbar) {
    if( is_super_admin() )
        return $wp_toolbar;

    if( current_user_can('read') )
        $wp_toolbar->remove_node( 'my-sites' );
}
//removes it from the back end menu as well
function remove_mysites_menu_from_dashboard() {
  if( is_super_admin() )
      return;

    $page = remove_submenu_page( 'index.php', 'my-sites.php' );
}
add_action( 'admin_menu', 'remove_mysites_menu_from_dashboard', 999 );

/**
* This is the end. Ensure the file closes with a php tag ?>
*
*
**/
?>
