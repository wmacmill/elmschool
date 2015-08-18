<?php
/*
 * Plugin Name: Will - NeverEmailPasswords *customized*
 * Plugin URI: https://github.com/pressable/never-email-passwords/
 * Version: 0.4
 * Author: Will & Pressable
 * Description: Customized by Will - Send new users a reset password link when their account is created. *changed the link sent in the email to that of the password reset page*
 * Also updated the line 31 if check to have it only run on the add new password
 */

class NeverEmailPasswords
{
    protected $userData;

    public function registerHooks()
    {
        add_action('user_register', array($this, 'handleActivationRequest'));
        add_action('admin_print_scripts', array($this, 'registerUIHooks'));
    }

    public function registerUIHooks()
    {
        if (defined('IS_PROFILE_PAGE')) {
          if(IS_PROFILE_PAGE === true) {
            return false;
          }
        }

        global $pagenow;
        //Added a check to make sure it only runs on the add new user page and inputs a 64 character password by default. 
        //Probably should just hide the field then with css? Doesn't really matter because it will never send the password anyway
        if ( $pagenow == 'user-new.php' ) {
            $password = wp_generate_password(64, false);
            wp_enqueue_script(
            'nep_remove_email_checkbox',
            plugins_url('/js/nep_remove_email_checkbox.js', __FILE__),
            array(),
            false,
            true
        );
        }
        wp_localize_script(
            'nep_remove_email_checkbox',
            'NeverEmailPasswords',
            array('password' => $password)
        );
    }

    public function handleActivationRequest($user_id)
    {
        if (!$this->setUserDataById($user_id)) {
            return false;
        }

        $key = $this->updateUserActivationKey();
        $this->sendEmailInvitation($key);

        return true;
    }

    /**
     * Update the user with a new activation key
     */
    protected function updateUserActivationKey()
    {
        global $wpdb;

        if (!$this->userData) {
            return false;
        }

        $key = wp_generate_password(20, false);

        $wpdb->update(
            $wpdb->users,
            array('user_activation_key' => $key),
            array('user_login' => $this->userData->user_login)
        );

        return $key;
    }

    /**
     * Update the instance state for the specified user ID.
     *
     * Generally this will only happen once, since PHP is request-based,
     * but this would allow for potentially multiple calls.
     *
     * @return bool
     */
    protected function setUserDataById($user_id)
    {
        global $wpdb;

        $this->userData = false;

        $user_data = get_userdata($user_id);

        if (is_wp_error($user_data)) {
            $this->reportError(
                'user_register error from get_user_data(%s): %s',
                array(
                    $user_id,
                    $user_data->get_error_message()
                )
            );

            return false;
        }

        $this->userData = $user_data;

        return true;
    }

    /**
     * Send to the end user an invitation email to set their password.
     */
    protected function sendEmailInvitation($key)
    {
        $blog_name = get_bloginfo('name');
        $subject = "Please set your $blog_name password";
        $body = $this->getMessageBody($key);

        if (!wp_mail($this->userData->user_email, $subject, $body)) {
            $this->reportError(
                'Failed sending email to <%s>: %s',
                array(
                    $this->userData->user_email,
                    $subject
                )
            );
            return false;
        }

        $this->reportError(
            'Successfully sent password reset link to %s',
            array($userData->user_email)
        );
    }

    /**
     * Report to the error log an error or other message.
     */
    protected function reportError($message, array $arguments)
    {
        error_log(
            'NeverEmailPasswords: '
            . vsprintf($message, $arguments)
        );
    }

    protected function getMessageBody($key)
    {
        $blog_name = get_bloginfo('name');
        $link = network_site_url("wp-login.php?action=lostpassword");

        return <<<EOB
An account has been created for you at $blog_name, you need to create a password for this account before it can be used.

Click below to set this password. Use this email address for your username:

$link
EOB;
    }
}

// only run on the admin pages to minimize 3rd party conflicts
if (is_admin()) {
    $nep = new NeverEmailPasswords();
    $nep->registerHooks();
}

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
