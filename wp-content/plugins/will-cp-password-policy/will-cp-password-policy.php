<?php
/*
 * Plugin Name: Will - CP Password Policy Plugin
 * Plugin URI: 
 * Version: 0.1
 * Author: Will
 * Description: This plugin modifies some of the other plugins like WP Password Policy and enhances the workflod
 * 
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**************this section contains functions required for the CP password policy ******************/

//updates the "blurb" under the password field to match the restrictions on passwords
function will_password_policy_update () {
    $hint = 'The password should be at least 9 characters long, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ). It also cannot be any of your last 6 passwords.';
    return $hint;
}

add_filter ('password_hint','will_password_policy_update',10,1);

//this is to remove the password policy manager plugin styling on the password reset page for users resetting their password. 
function will_wp_reset_form () {
    echo '<style>#wp-reset-form-rules {display: none;}</style>';
}

add_filter ('login_init', 'will_wp_reset_form', 10);

//changing the error messags to generic to help thwart attacks!
add_filter ( 'login_errors', 'login_error_message' );

function login_error_message ( $error ) {
    global $errors;
    $err_codes = $errors->get_error_codes();

    if ( in_array( 'invalid_username', $err_codes ) ) {
        $error = 'Access Denied';
    }

    if ( in_array( 'incorrect_password', $err_codes ) ) {
        $error = 'Access Denied';
    }

    return $error;
}

/*
* This modifies the capabilities that are required to force a strong password https://github.com/boogah/Force-Strong-Passwords
* This currently only requires update_core (admin) & edit private posts
*/
add_filter( 'slt_fsp_caps_check', 'my_caps_check' );

function my_caps_check( $caps ) {
        unset ($caps);
        $caps[] = 'update_core,edit_private_posts';
        return $caps;
}

/************end password policy code **************************/
