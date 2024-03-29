<?php
/*
Plugin Name: WordPress Password Policy Manager
Plugin URI: http://www.wpwhitesecurity.com/wordpress-security-plugins/wordpress-password-policy-manager-plugin/
Description: WordPress Password Policy Manager allows WordPress administrators to configure password policies for WordPress users to use strong passwords.
Author: wpkytten
Version: 0.11
Text Domain: wp-password-policy-manager
Domain Path: /languages/
Author URI: http://www.wpwhitesecurity.com/
License: GPL2

WordPress Password Policy Manager
Copyright(c) 2014  Robert Abela  (email : robert@wpwhitesecurity.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WpPasswordPolicyManager
{
    // <editor-fold desc="Constants">
    // Session-specific settings determined from hooks
    protected $CurrentPassIsOld = false;
    protected $CurrentUserLogin = false;
    const DEF_PFX = 'wppm';
    const PLG_CONFIG_MENU_NAME = 'password_policy_settings';
    const OPT_NAME_UPM = 'wppm_passmod';
    const OPT_NAME_TTL = 'wppm_ttl_str';
    const OPT_NAME_LEN = 'wppm_len_int';
    const OPT_NAME_BIT = 'wppm_pol_bit';
    const OPT_NAME_XMT = 'wppm_xmt_lst';
    const OPT_NAME_MSP = 'wppm_msp_int';
    const OPT_NAME_OPL = 'wppm_opl_lst';
    const POLICY_MIXCASE = 'C';
    const POLICY_NUMBERS = 'N';
    const POLICY_SPECIAL = 'S';
    const POLICY_OLDPASSWORD = 'O';
    const DEF_OPT_TTL = '';
    const DEF_OPT_LEN = 0;
    const DEF_OPT_CPT = false;
    const DEF_OPT_NUM = false;
    const DEF_OPT_SPL = false;
    const OPT_USER_PWDS = 'wppm_lst_pwd';
    const OPT_USER_RST_PWD = 'wppm_rst_pwd';
    //@since 0.8
    const OPT_DISABLE_ADMINS = 'wppm_daa';
    //@since 0.9
    private static $_instance = null;

    // </editor-fold>

// <editor-fold desc="Entry Points">
    private function __construct(){
        // register actions
        foreach(array(
                    array('admin_menu', 0),
                    array('admin_init', 1),
                    array('network_admin_menu', 0),
                    array('admin_enqueue_scripts', 0),
                    array('admin_footer', 0),
                    array('admin_print_footer_scripts', 0),
                    array('wp_ajax_check_security_token', 0),
                    array('profile_update', 2),
                    array('user_profile_update_errors', 3),
                    array('user_register', 1),
                ) as $filter){
            list($name, $argc) = $filter;
            $cb = isset($filter[2]) ? $filter[2] : array($this, $name);
            add_action($name, $cb, 10, $argc);
        }
        //-- wp internals
        add_filter('plugin_action_links_'.$this->GetBaseName(), array($this, 'plugin_action_links'), 10, 1);
        add_filter('password_reset', array($this, 'password_reset'), 10, 2);
        //-- login
        add_filter('wp_authenticate_user', array($this, 'ValidateLoginForm'), 10, 2);
        add_action('login_form', array($this, 'ModifyLoginForm'), 10, 2);
        //-- user profile
        add_action( 'show_user_profile', array($this, 'ModifyUserProfilePage')); // user -> own profile
        add_action( 'edit_user_profile', array($this, 'ModifyUserProfilePage')); // admin -> user profile
        add_action( 'user_profile_update_errors', array( $this, 'ValidateUserProfilePage' ), 0, 3 );
        //-- pwd reset
        add_action( 'validate_password_reset', array($this,'ValidatePasswordReset'), 10, 2 );
        add_action( 'validate_password_reset', array($this,'ModifyWpResetForm'), 10);
        //-- Load plugin's text language files
        add_action( 'plugins_loaded', array($this, 'LoadTextDomain'));
    }

    /**
     * Standard singleton pattern.
     * @return \self Returns the current plugin instance.
     */
    public static function GetInstance(){
        if(is_null(self::$_instance) || !(self::$_instance instanceof self)){
            self::$_instance = new self;
        }
        return self::$_instance;
    }
// </editor-fold desc=">>> Entry Points">


// <editor-fold desc=">>> WP Internals">


    /**
     * Load plugin textdomain.
     */
    public function LoadTextDomain() {
        load_plugin_textdomain('wp-password-policy-manager', false, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    protected function GetPasswordRules(){
        $rules = array(
            __('not be the same as your username', 'wp-password-policy-manager'),
        );
        $_nMaxSamePass = $this->GetMaxSamePass();
        if ($_nMaxSamePass) {
            $rules[] = sprintf(__('not be one of the previous %d used passwords.', 'wp-password-policy-manager'), $_nMaxSamePass);
        }
        else {
            $rules[] = __('not be the same as the previous one', 'wp-password-policy-manager');
        }

        if (!!($c = $this->GetPasswordLen()))
            $rules[] = sprintf(__('be at least %d characters long', 'wp-password-policy-manager'), $c);
        if ($this->IsPolicyEnabled(self::POLICY_MIXCASE))
            $rules[] = sprintf(__('contain mixed case characters', 'wp-password-policy-manager'));
        if ($this->IsPolicyEnabled(self::POLICY_NUMBERS))
            $rules[] = sprintf(__('contain numeric digits', 'wp-password-policy-manager'));
        if ($this->IsPolicyEnabled(self::POLICY_SPECIAL))
            $rules[] = sprintf(__('contain special characters', 'wp-password-policy-manager'));
        return $rules;
    }

    protected $pwd = '';
    function ModifyLoginForm(){
        if(!empty($this->CurrentUserLogin)){
            $username = $this->CurrentUserLogin;
            if (!username_exists($username)) {
                return;
            }
        }
        else {
            $username = (isset($_REQUEST['log'])&&!empty($_REQUEST['log']) ? $_REQUEST['log'] : '');
            if(empty($username)){
                return;
            }
            if (!username_exists($username)) {
                return;
            }
        }

        if(!empty($this->pwd)){
            $password = $this->pwd;
        }
        else {
            $password = isset($_REQUEST['pwd']) ? stripslashes($_REQUEST['pwd']) : '';
            if(empty($password)){
                return;
            }
        }

        $user = new WP_User($username);
        if($this->IsUserExemptFromPolicies($user)){
            // policies do not apply in this case
            return;
        }
        if(!wp_check_password($password, $user->data->user_pass, $user->ID)){
            // let WP handle this
            return;
        }
        if(!$this->IsUserPasswordOld($user)){
            return;
        }

        wp_enqueue_script('front-js', $this->GetBaseUrl().'js/front.js', array('jquery'), rand(1,1234));
        wp_localize_script('front-js', 'wppm_ModifyForm', array(
            'CurrentUserLogin' => $username,
            'CurrentUserPass' => $password,
            'TextOldPass' => __('Old Password', 'wp-password-policy-manager'),
            'BtnChangeAndLogin' => __('Change & Log in', 'wp-password-policy-manager'),
            'NewPasswordRules' => $this->GetPasswordRules(),
            //'NewPassRulesHead' => __('New password must...', 'wp-password-policy-manager'),
            /*'NewPassRulesFoot' => __('WordPress Password Policies by', 'wp-password-policy-manager')
                                  . '<br/><a href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-password-policy-manager/" target="_blank">'
                                  . __('WP Password Policy Manager', 'wp-password-policy-manager')
                                  . '</a>'*/
        ));

        ?>
        <p>
            <label for="user_pass_new"><?php _e('New Password', 'wp-password-policy-manager'); ?><br />
                <input type="password" name="user_pass_new" id="user_pass_new" class="input"
                       placeholder="<?php _e('New Password', 'wp-password-policy-manager'); ?>"
                       value="<?php echo ''; ?>" size="25" /></label>
        </p>
        <p>
            <label for="user_pass_vfy"><?php _e('Verify Password', 'wp-password-policy-manager'); ?><br />
                <input type="password" name="user_pass_vfy" id="user_pass_vfy" class="input"
                       placeholder="<?php _e('Verify Password', 'wp-password-policy-manager'); ?>"
                       value="<?php echo ''; ?>" size="25" /></label>
        </p>
        <?php
    }

    protected $shouldModify = false;
    public function ValidateLoginForm($user, $password){
        if(!($user instanceof WP_User)){
            return new WP_Error('expired_password', __('Invalid Request', 'wp-password-policy-manager'));
        }
        if($this->IsUserExemptFromPolicies($user)){
            return $user;
        }
        $wasReset = (bool)absint($this->GetGlobalOption(self::OPT_USER_RST_PWD.'_'.$user->ID));

        if(wp_check_password($password, $user->data->user_pass, $user->ID) && ($wasReset || $this->IsUserPasswordOld($user)))
        {
            $this->CurrentPassIsOld = true;
            $this->CurrentUserLogin = $user->user_login;

            $this->pwd = stripslashes($password);
            $this->shouldModify = true;

            // Apply password policies
            if(isset($_REQUEST['user_pass_new']) && isset($_REQUEST['user_pass_vfy'])){
                if(!trim($_REQUEST['user_pass_new']) || !trim($_REQUEST['user_pass_vfy']))
                    return new WP_Error('expired_password', __('<strong>ERROR</strong>: The new password cannot be empty.', 'wp-password-policy-manager'));
                if($_REQUEST['user_pass_new'] != $_REQUEST['user_pass_vfy'])
                    return new WP_Error('expired_password', __('<strong>ERROR</strong>: Both new passwords must match.', 'wp-password-policy-manager'));
                if(wp_check_password($_REQUEST['user_pass_new'], $user->data->user_pass, $user->ID))
                    return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password cannot be the same as the old one.', 'wp-password-policy-manager'));
                if($_REQUEST['user_pass_new'] == $user->user_login)
                    return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password cannot be the same as the username.', 'wp-password-policy-manager'));
                if($_REQUEST['user_pass_new'] == $user->user_email)
                    return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password cannot be the same as the email.', 'wp-password-policy-manager'));
                if(($c = $this->GetPasswordLen()) != 0)
                    if(strlen($_REQUEST['user_pass_new']) < $c)
                        return new WP_Error('expired_password', sprintf(__('<strong>ERROR</strong>: New password must contain at least %d characters.', 'wp-password-policy-manager'), $c));
                if($this->IsPolicyEnabled(self::POLICY_MIXCASE))
                    if(strtolower($_REQUEST['user_pass_new']) == $_REQUEST['user_pass_new'])
                        return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password must contain both uppercase and lowercase characters.', 'wp-password-policy-manager'));
                if($this->IsPolicyEnabled(self::POLICY_NUMBERS))
                    if(!preg_match('/[0-9]/', $_REQUEST['user_pass_new']))
                        return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password must contain numbers.', 'wp-password-policy-manager'));
                if($this->IsPolicyEnabled(self::POLICY_SPECIAL))
                    if(!preg_match('/[_\W]/', $_REQUEST['user_pass_new']))
                        return new WP_Error('expired_password', __('<strong>ERROR</strong>: New password must contain special characters.', 'wp-password-policy-manager'));
                // update user passwords, if the policy applies
                $_nMaxSamePass = $this->GetMaxSamePass();
                if($_nMaxSamePass){
                    if($this->_pwdHasBeenUsed($user->ID, $_REQUEST['user_pass_new'])){
                        return new WP_Error('expired_password',
                            sprintf(__('<strong>ERROR</strong>: New password must not be one of the previous %d used passwords.', 'wp-password-policy-manager'), $_nMaxSamePass));
                    }
                    $this->_addPwdToList($user->ID, $_REQUEST['user_pass_new']);
                }
                else {self::ClearUserPrevPwds($user->ID); }

                wp_set_password($_REQUEST['user_pass_new'], $user->ID);
                $this->password_reset($user, $_REQUEST['user_pass_new']);
                do_action('edit_user_profile_update', $user->ID);
                // Check if the user's pwd had been reset
                if($wasReset) {
                    $this->DeleteGlobalOption(self::OPT_USER_RST_PWD . '_' . $user->ID);
                }
                return $user;
            }
            else{
                if($wasReset){
                    $diff = __('1 minute', 'wp-password-policy-manager');
                }
                else { $diff = human_time_diff(strtotime($this->GetPasswordTtl(), $this->GetPasswordLastModTime($user->ID)), current_time('timestamp')); }
                return new WP_Error('expired_password', sprintf(__('<strong>ERROR</strong>: The password you entered expired %s ago.', 'wp-password-policy-manager'), $diff));
            }
        }
        return $user;
    }

    public function ModifyUserProfilePage($user=null){
        $rules = $this->GetPasswordRules();
        ?>
        <table class="form-table">
            <?php if($this->IsPolicyEnabled(self::POLICY_OLDPASSWORD) && !$this->UserCanSkipOldPwdPolicy()) { ?>
            <tr>
                <th><label for="wppmoldpass"><?php _e('Current Password', 'wp-password-policy-manager');?></label></th>
                <td>
                    <input type="password" name="wppmoldpass" id="wppmoldpass" class="regular-text" size="16" value=""
                           placeholder="<?php _e('Current Password', 'wp-password-policy-manager');?>"
                           autocomplete="off"><br>
                    <span class="description"><?php _e('Type your current password to be able to change your password.', 'wp-password-policy-manager'); ?></span>
                </td>
            </tr>
            <?php } ?>
            <!--<tr>
                <th><label><?php //_e('New password must', 'wp-password-policy-manager') ;?></label></th>
                <td>
                    <div id="wppmUserProfilePwdRulesContainer">
                        <ul style="list-style: disc inside; margin-top: 5px;">
                            <?php //foreach($rules as $item) { echo "<li>{$item}</li>"; } ?>
                        </ul>
                        <div style="width: 240px;">
                            <p style="text-align: center;"><?php /*echo
                                    __('WordPress Password Policies by', 'wp-password-policy-manager')
                                    . '<br/><a href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-password-policy-manager/" target="_blank">'
                                    . __('WP Password Policy Manager', 'wp-password-policy-manager'). '</a>'*/
                                ?></p>
                        </div>
                    </div>
                </td>
            </tr>-->
        </table>
    <?php }

    public function ValidateUserProfilePage($errors, $update = null, $user = null){
        $pass1 = (isset($_REQUEST['pass1']) ? $_REQUEST['pass1'] : '');
        $pass2 = (isset($_REQUEST['pass2']) ? $_REQUEST['pass2'] : '');
        $oldpass = '';
        if($this->IsPolicyEnabled(self::POLICY_OLDPASSWORD) && !$this->UserCanSkipOldPwdPolicy()) {
            $oldpass = (isset($_REQUEST['wppmoldpass']) ? $_REQUEST['wppmoldpass'] : '');
        }
        return $this->__validateProfile($errors, $user, $pass1, $pass2, $oldpass);
    }

    /**
     * Validates the User profile page
     * @internal
     * @param $errors
     * @param $user
     * @param $pass1
     * @param $pass2
     * @return mixed
     */
    protected function __validateProfile($errors, $user, $pass1, $pass2, $oldpass='') {
        if($user){
            if(! isset($user->ID)){
                return $errors;
            }
            $_user = null;
            if(!($user instanceof WP_User) && $user instanceof stdClass){
                $_user = new WP_User($user->ID);
                $user = $_user;
            }
        }
        else { return $errors; }
        $userInfo = $user->data;

        if(!$this->IsUserExemptFromPolicies($user))
        {
            // If the user updates their password, it should comply with the policies
            if((isset($pass1) && isset($pass2)) && (!empty($pass1) && !empty($pass2)))
            {
                if(empty($pass1) || empty($pass2)){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: The new password cannot be empty.','wp-password-policy-manager'));
                    return $errors;
                }
                if($pass1 <> $pass2){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: Both new passwords must match.', 'wp-password-policy-manager'));
                    return $errors;
                }
                $validateOldPass = ($this->IsPolicyEnabled(self::POLICY_OLDPASSWORD) && !$this->UserCanSkipOldPwdPolicy());
                if($validateOldPass && empty($oldpass)){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: Please enter the current password in the Current Password field.','wp-password-policy-manager'));
                    return $errors;
                }

                // get the current pass
                $crtPwd = $userInfo->user_pass;
                if(wp_check_password($pass1, $crtPwd, $user->ID)){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: New password cannot be the same as the old one.','wp-password-policy-manager'));
                    return $errors;
                }
                // new password cannot be the same as the username
                if($pass1 == $userInfo->user_login){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: New password cannot be the same as the username.','wp-password-policy-manager'));
                    return $errors;
                }
                // new password cannot be the same as the email
                if($pass1 == $userInfo->user_email){
                    $errors->add('expired_password',
                        __('<strong>ERROR</strong>: New password cannot be the same as the email.','wp-password-policy-manager'));
                    return $errors;
                }
                // Apply password policies
                if(($c = $this->GetPasswordLen()) != 0) {
                    if (strlen($pass1) < $c) {
                        $errors->add('expired_password', sprintf(__('<strong>ERROR</strong>: New password must contain at least %d characters.', 'wp-password-policy-manager'), $c));
                        return $errors;
                    }
                }
                if($this->IsPolicyEnabled(self::POLICY_MIXCASE)) {
                    if (strtolower($pass1) == $pass1) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain both uppercase and lowercase characters.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                if($this->IsPolicyEnabled(self::POLICY_NUMBERS)) {
                    if (!preg_match('/[0-9]/', $pass1)) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain numbers.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                if($this->IsPolicyEnabled(self::POLICY_SPECIAL)) {
                    if (!preg_match('/[_\W]/', $pass1)) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain special characters.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                if($validateOldPass) {
                    if (!wp_check_password($oldpass, $crtPwd, $user->ID)) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: Current password is incorrect.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                $_nMaxSamePass = $this->GetMaxSamePass();
                if($_nMaxSamePass){
                    if($this->_pwdHasBeenUsed($user->ID, $pass1)){
                        $errors->add('expired_password',
                            sprintf(__('<strong>ERROR</strong>: New password must not be one of the previous %d used passwords.', 'wp-password-policy-manager'), $_nMaxSamePass));
                        return $errors;
                    }
                    $this->_addPwdToList($user->ID, $pass1);
                }
                else {self::ClearUserPrevPwds($user->ID); }

                $this->SetGlobalOption(self::OPT_USER_RST_PWD . '_' . $user->ID, false);
                update_user_option($user->ID, self::OPT_NAME_UPM, current_time('timestamp')+(strtotime($this->GetPasswordTtl())));
            }
        }
        return $errors;
    }

    public function ModifyWpResetForm() {
        wp_enqueue_style('wppm-reset-css', $this->GetBaseUrl() . 'css/wppm-reset.css', null, filemtime($this->GetBaseDir() . 'css/wppm-reset.css'));
        wp_enqueue_script('wppm-reset-js', $this->GetBaseUrl() . 'js/reset.js', array('jquery'), filemtime($this->GetBaseDir() . 'js/reset.js'), true);

        wp_localize_script('wppm-reset-js', 'wppm_ModifyForm', array(
            'NewPasswordRules' => $this->GetPasswordRules(),
            //'NewPassRulesHead' => __('New password must...', 'wp-password-policy-manager'),
            /*'NewPassRulesFoot' => __('WordPress Password Policies by', 'wp-password-policy-manager')
                . '<br/><a href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-password-policy-manager/" target="_blank">'
                . __('WP Password Policy Manager', 'wp-password-policy-manager')
                . '</a>'*/
        ));
    }

    public function ValidatePasswordReset( WP_Error $errors, $user ) {
        $rm = strtoupper($_SERVER['REQUEST_METHOD']);
        if ('POST' == $rm) {
            if (!isset($_POST['pass1']) || !isset($_POST['pass2'])) {
                $errors->add('expired_password', __('The form is not valid. Please refresh the page and try again.', 'wp-password-policy-manager'));
                return $errors;
            }
            if (empty($_POST['pass1'])) {
                $errors->add('expired_password', __('Please provide your new password.', 'wp-password-policy-manager'));
                return $errors;
            }
            if (empty($_POST['pass2'])) {
                $errors->add('expired_password', __('Please confirm your new password.', 'wp-password-policy-manager'));
                return $errors;
            }

            $password = trim(strip_tags($_POST['pass1']));
            $p2 = trim(strip_tags($_POST['pass2']));

            if ($password != $p2) {
                $errors->add('expired_password', __('Passwords must match.', 'wp-password-policy-manager'));
                return $errors;
            }

            //-- new password must not be the same as the current one
            if (wp_check_password($password, $user->data->user_pass, $user->ID)) {
                $errors->add('expired_password', __('The new password cannot be the same as the current one.', 'wp-password-policy-manager'));
                return $errors;
            }
            //-- Enforce password policies
            if (!$this->IsUserExemptFromPolicies($user))
            {
                $this->CurrentPassIsOld = true;
                $this->CurrentUserLogin = $user->user_login;

                if ($password == $user->user_login) {
                    $errors->add('expired_password', __('<strong>ERROR</strong>: New password cannot be the same as the username.', 'wp-password-policy-manager'));
                    return $errors;
                }
                if ($password == $user->user_email) {
                    $errors->add('expired_password', __('<strong>ERROR</strong>: New password cannot be the same as the email.', 'wp-password-policy-manager'));
                    return $errors;
                }
                if (($c = $this->GetPasswordLen()) != 0) {
                    if (strlen($password) < $c) {
                        $errors->add('expired_password', sprintf(__('<strong>ERROR</strong>: New password must contain at least %d characters.', 'wp-password-policy-manager'), $c));
                        return $errors;
                    }
                }
                if ($this->IsPolicyEnabled(self::POLICY_MIXCASE)) {
                    if (strtolower($password) == $password) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain both uppercase and lowercase characters.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                if ($this->IsPolicyEnabled(self::POLICY_NUMBERS)) {
                    if (!preg_match('/[0-9]/', $password)) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain numbers.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }
                if ($this->IsPolicyEnabled(self::POLICY_SPECIAL)) {
                    if (!preg_match('/[_\W]/', $password)) {
                        $errors->add('expired_password', __('<strong>ERROR</strong>: New password must contain special characters.', 'wp-password-policy-manager'));
                        return $errors;
                    }
                }

                // update user passwords, if the policy applies
                $_nMaxSamePass = $this->GetMaxSamePass();
                if ($_nMaxSamePass) {
                    if ($this->_pwdHasBeenUsed($user->ID, $password)) {
                        $errors->add('expired_password',
                            sprintf(__('<strong>ERROR</strong>: New password must not be one of the previous %d used passwords.', 'wp-password-policy-manager'), $_nMaxSamePass));
                        return $errors;
                    }
                    $this->_addPwdToList($user->ID, $password);
                }
                else {self::ClearUserPrevPwds($user->ID);}

                wp_set_password($password, $user->ID);
                $this->password_reset($user, $password);
                do_action('edit_user_profile_update', $user->ID);
            }
        }
        return $user;
    }

// </editor-fold desc=">>> WP Internals">


// <editor-fold desc="WordPress Extensions">
    /**
     * Get a global (across multiple sites) option.
     * @param string $name Option name.
     * @return mixed Option value or false if option not set.
     */
    protected function GetGlobalOption($name){
        $fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
        return $fn($name, false);
    }
    /**
     * Set a global (across multiple sites) option.
     * @param string $name Option name.
     * @param string $value Option value.
     */
    protected function SetGlobalOption($name, $value){
        $fn = $this->IsMultisite() ? 'update_site_option' : 'update_option';
        $fn($name, $value);
    }
    /**
     * Delete a global (across multiple sites) option.
     * @param string $name Option name.
     */
    protected function DeleteGlobalOption($name){
        $fn = $this->IsMultisite() ? 'delete_site_option' : 'delete_option';
        $fn($name);
    }
    /**
     * Get a user-specific option.
     * @param string $name Option name.
     * @param int $user_id (Optional) User id (default user if not set).
     * @return mixed Option value or false if option not set.
     */
    protected function GetUserOption($name, $user_id = null){
        if(is_null($user_id))$user_id = get_current_user_id();
        return get_user_option($name, $user_id);
    }
    /**
     * Set a user-specific option.
     * @param string $name Option name.
     * @param string $value Option value.
     * @param int $user_id (Optional) User id (default user if not set).
     */
    protected function SetUserOption($name, $value, $user_id = null){
        if(is_null($user_id))$user_id = get_current_user_id();
        update_user_option($user_id, $name, $value, true);
    }
    /**
     * @return string URL to plugin root with final slash.
     */
    public function GetBaseUrl(){
        return rtrim(plugins_url('', __FILE__), '/') . '/';
    }
    /**
     * @return string Get plugin path.
     */
    public function GetBaseDir(){
        return plugin_dir_path(__FILE__);
    }
    /**
     * @return string Get plugin name.
     */
    public function GetBaseName(){
        return plugin_basename(__FILE__);
    }
    /**
     * @return boolean Whether Wordpress is in multisite mode or not.
     */
    protected function IsMultisite(){
        return function_exists('is_multisite') && is_multisite();
    }
    /**
     * @return boolean Whether current user can manage plugin or not.
     */
    protected function IsManagingAdmin(){
        return current_user_can('manage_options');
    }
// </editor-fold desc="WordPress Extensions">

// <editor-fold desc="Misc Functionality">
    public function UserCanSkipOldPwdPolicy(){
        $user = wp_get_current_user();
        if($this->IsUserExemptFromPolicies($user)){
            return true;
        }
        // If this is not his profile & is admin
        return user_can($user->ID, 'manage_options');
    }
    /**
     * @return string Password policy time to live as a string.
     */
    public function GetPasswordTtl(){
        $opt = $this->GetGlobalOption(self::OPT_NAME_TTL);
        return !$opt ? self::DEF_OPT_TTL : trim($opt);
    }
    /**
     * @return integer Password length policy (0=disabled).
     */
    public function GetPasswordLen(){
        $res = $this->GetGlobalOption(self::OPT_NAME_LEN);
        return $res === false ? self::DEF_OPT_LEN : (int)$res;
    }
    /**
     * Set new password time-to-live.
     * @param string $newTtl Password policy time to live as a string.
     * @throws Exception
     */
    public function SetPasswordTtl($newTtl){
        if(trim($newTtl)){
            $now = current_time('timestamp');
            $time = strtotime($newTtl, $now);
            if($time === false || $time < $now)
                throw new Exception(__('Password policy expiration time is not valid.', 'wp-password-policy-manager'));
        }else $newTtl = '';
        $this->SetGlobalOption(self::OPT_NAME_TTL, $newTtl);
    }
    /**
     * @param integer $length Password length policy (0=disable policy).
     */
    public function SetPasswordLen($length){
        $this->SetGlobalOption(self::OPT_NAME_LEN, $length);
    }
    protected $_policy_flag_cache = null;
    /**
     * Returns password policy bitfield.
     * @return string Policy bitfield.
     */
    public function GetPolicyFlags(){
        if(is_null($this->_policy_flag_cache))
            $this->_policy_flag_cache = $this->GetGlobalOption(self::OPT_NAME_BIT);
        if($this->_policy_flag_cache === false)
            $this->_policy_flag_cache = '';
        return $this->_policy_flag_cache;
    }
    /**
     * @return array List of tokens (usernames or roles) exempt from password policies.
     */
    public function GetExemptTokens(){
        $res = $this->GetGlobalOption(self::OPT_NAME_XMT);
        return $res === false ? array() : (array)json_decode($res);
    }
    /**
     * Overwrite list of tokens (usernames or roles) exempt from password policies.
     * @param array $tokens New list of tokens.
     */
    public function SetExemptTokens($tokens){
        $this->SetGlobalOption(self::OPT_NAME_XMT, json_encode($tokens));
    }
    /**
     * Checks whether a policy is enabled or not.
     * @param string $policy Any of the POLICY_* constants.
     * @return boolean True if enabled, false otherwise.
     */
    public function IsPolicyEnabled($policy){
        return strpos($this->GetPolicyFlags(), $policy) !== false;
    }
    /**
     * Enables or disables a particular policy.
     * @param integer $policy Any of the POLICY_* constants.
     * @param boolean $enabled True to enable policy, false otherwise.
     */
    public function SetPolicyState($policy, $enabled){
        $flags = str_replace($policy, '', $this->GetPolicyFlags());
        if($enabled)$flags .= $policy;
        $this->SetGlobalOption(self::OPT_NAME_BIT, $flags);
        $this->_policy_flag_cache = null; // clear cache
    }
    /**
     * @return integer Maximum number of same passwords allowed.
     */
    public function GetMaxSamePass(){
        return (int)$this->GetGlobalOption(self::OPT_NAME_MSP);
    }
    /**
     * @param integer $value New maximum number of same passwords allowed.
     */
    public function SetMaxSamePass($value){
        $this->SetGlobalOption(self::OPT_NAME_MSP, $value);
    }
    //@since 0.8
    public function DisableAdminsAccess($value){
        $this->SetGlobalOption(self::OPT_DISABLE_ADMINS, $value);
    }
    //@since 0.8
    public function IsAdminAccessDisabled(){
        if(is_super_admin()){
            return false;
        }
        return (bool)$this->GetGlobalOption(self::OPT_DISABLE_ADMINS);
    }
    protected function EchoIdent($name){
        echo self::DEF_PFX . '_' . $name;
    }
    protected function IsPostIdent($name){
        return isset($_POST[self::DEF_PFX . '_' . $name]);
    }
    protected function IsJustInstalled(){
        return ($this->GetGlobalOption(self::OPT_NAME_TTL) === false)
        || ($this->GetGlobalOption(self::OPT_NAME_LEN) === false);
    }
    protected function GetPostIdent($name){
        return $_POST[self::DEF_PFX . '_' . $name];
    }
    protected function UpdateWpOptions()
    {
        if (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'nonce_form' )) {
            if($this->IsPostIdent('ttl'))
                $this->SetPasswordTtl($this->GetPostIdent('ttl'));
            if($this->IsPostIdent('len'))
                $this->SetPasswordLen($this->GetPostIdent('len'));
            $this->SetPolicyState(self::POLICY_MIXCASE, $this->IsPostIdent('cpt'));
            $this->SetPolicyState(self::POLICY_NUMBERS, $this->IsPostIdent('num'));
            $this->SetPolicyState(self::POLICY_SPECIAL, $this->IsPostIdent('spc'));
            $this->SetPolicyState(self::POLICY_OLDPASSWORD, $this->IsPostIdent('opw'));
            $this->SetExemptTokens(isset($_REQUEST['ExemptTokens']) ? $_REQUEST['ExemptTokens'] : array());
            if($this->IsPostIdent('msp')) {
                $this->SetMaxSamePass( (int)$this->GetPostIdent( 'msp' ) );
            }
            if($this->IsMultisite()){
                if($this->IsPostIdent('daa'))
                    $this->DisableAdminsAccess((int)$this->GetPostIdent('daa'));
                else $this->DisableAdminsAccess(0);
            }
            // since v0.9
            // Will reset passwords for users using WP Cron
            // Useful when there are many users
            // see: https://wordpress.org/support/topic/timeout-when-resetting-all-passwords
            // requirement: WP_CRON must be available
            $bid = null;
            if(isset($_POST['WPPM_BID'])){
                $bid = intval($_POST['WPPM_BID']);
            }
            if($this->IsPostIdent('wpcron')){
                //exit('INDENT FOUND');
                $this->SetGlobalOption(self::CRON_RESET_PWD_OPT_NAME, 1);
                $this->SetGlobalOption(self::CRON_RESET_PWD_BID_OPT_NAME, $bid);
            }
        }
        else {
            throw new Exception(__('Security check failed', 'wp-password-policy-manager'));
        }
    }

    protected function ResetWpPasswords($blogId = null)
    {
        if (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'nonce_form' ))
        {
            //#Implements #4
            // When the site admin clicks "reset all passwords" all the passwords for that site only should be reset

            // Make sure this is a valid request
            if($this->IsAdminAccessDisabled()){
                throw new Exception(__('Security check failed', 'wp-password-policy-manager'));
            }

            // If this is a request coming from the Super Admin
            $isSuperAdminReq = is_super_admin();

            // All blogs in the network
            if(empty($blogId)){
                global $wpdb;
                $query = "SELECT DISTINCT(blog_id) FROM ".$wpdb->blogs.' WHERE spam = 0 AND deleted = 0';
                $blogs = $wpdb->get_results($query, ARRAY_A);
                if(empty($blogs)){
                    error_log(__FUNCTION__.'() Error: no blogs found.');
                    return false;
                }
                foreach($blogs as $blog){
                    $this->_resetPasswordsHelper($blog['blog_id'], $isSuperAdminReq);
                }
            }
            // Specific site
            else {
                $this->_resetPasswordsHelper(get_current_blog_id(), $isSuperAdminReq);
            }
        }
        else {
            throw new Exception(__('Security check failed', 'wp-password-policy-manager'));
        }
    }

    private function _resetPasswordsHelper($blogId = 0, $exceptSuperAdmin = true, $usingCron = false)
    {
//        if(defined( 'DOING_CRON' )){
//            error_log(__METHOD__.'() triggered by wp cron.');
//        }

        // Select users for the specified blog
        $queryData = null;
        if($blogId){
            $queryData = array('blog_id' => (int)$blogId);
        }
        $usersQuery = new WP_User_Query($queryData);
        if($usersQuery){
            $users = $usersQuery->get_results();
        }
        if(empty($users)){
            error_log('No users found for blog id: '.$blogId);
            return;
        }
//error_log('Blog ID: '.$blogId);
//error_log('Users found: '.count($users));
//error_log(str_repeat('-',80));



        $letters = range('a','z');
        $specials = array('~', '!', '@', '#', '$', '%', '^', '&', '*', '-', '+', '=', '.', ',');

        foreach ($users as $user)
        {
//error_log('PROCESSING USERS OF BLOG: '.$blogId);
//error_log(str_repeat('-',80));
            if(! isset($user->ID)){
                error_log('USER ID NOT FOUND');
                continue;
            }
            $userInfo = $user->data;

            $also = ($usingCron ? true : $exceptSuperAdmin);

            // Ignore Super Admins
            if(is_super_admin($user->ID) && $also) {
//error_log('The user is super admin ('.$user->ID.', '.$userInfo->user_nicename.') Ignoring request for password change.');
                continue;
            }

            // @since v0.9
            // In case wp cron fails to do this in one request and will have to go through
            // all suers all again, make sure it will only process those that didn't make it
            // in the first run
            $updated =  $this->GetGlobalOption(self::OPT_USER_RST_PWD . '_' . $user->ID);
            if($updated){
//error_log('USER PASSWORD ALREADY UPDATED FOR THIS USER: ('.$user->ID.', '.$userInfo->user_nicename.'). Skipping.');
                continue;
            }

            $new_password = wp_generate_password();

            // Ensure the generated password follows the plugin's policies
            if($this->IsPolicyEnabled(self::POLICY_MIXCASE)){
                if(strtolower($new_password) == $new_password){
                    $new_password .= strtoupper($letters[ array_rand($letters) ]);
                }
            }
            if($this->IsPolicyEnabled(self::POLICY_NUMBERS)){
                if(!preg_match('/[0-9]/', $new_password)){
                    $new_password .= rand(0,9);
                }
            }
            if($this->IsPolicyEnabled(self::POLICY_SPECIAL)){
                if(!preg_match('/[_\W]/', $new_password)){
                    $new_password .= strtoupper($specials[ array_rand($specials) ]);
                }
            }

            $new_password = str_shuffle($new_password);
            $_nMaxSamePass = $this->GetMaxSamePass();
            if($_nMaxSamePass){
                while($this->_pwdHasBeenUsed($user->ID, $new_password)){
                    $new_password = str_shuffle($new_password);
                }
            }

            // The blogname option is escaped with esc_html on the way into the database in sanitize_option
            // we want to reverse this for the plain text arena of emails.
            $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

//error_log('Blog ID: '.$blogId.' ('.$blogname.'). Password changed for user: '.$userInfo->user_nicename.'. New password is: ' .$new_password);

            $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"/></head><body>';
            $message .= sprintf(__('<p>Your password for <strong>%s</strong> has been reset.</p>', 'wp-password-policy-manager'), $blogname) . "\r\n\r\n";
            $message .= sprintf(__('<p>New Password: <strong>%s</strong></p>', 'wp-password-policy-manager'), $new_password) . "\r\n\r\n";
            $message .= sprintf(__('<p>Please log in and change your password:', 'wp-password-policy-manager')) . "\r\n";
            $message .= wp_login_url() . "</p>\r\n";
            $message .= '</body></html>';
            $result = self::SendNotificationEmail($user->user_email, $message);
            if ($result) {
                // Set the new password
                wp_set_password($new_password, $user->ID);

                // reset & expire
                $this->SetGlobalOption(self::OPT_USER_RST_PWD . '_' . $user->ID, true);
                update_user_option($user->ID, self::OPT_NAME_UPM, current_time('timestamp'));

//error_log('Username: '.$userInfo->user_nicename);
//error_log('Email: '.$user->user_email);
//error_log($message);
//error_log(str_repeat('-',80));
            }
        }
        // Cleanup
        // @since v0.9
        $this->DeleteGlobalOption(self::CRON_RESET_PWD_OPT_NAME);
        $this->DeleteGlobalOption(self::CRON_RESET_PWD_BID_OPT_NAME);
    }

    /**
     * Retrieve a blog through an AJAX call
     *
     * @since 0.8
     */
    public function get_blogs_ajax(){
        check_ajax_referer( 'nonce_form', 'nonce' );

        if(! isset($_POST) || empty($_POST)){
            wp_send_json_error(__('Invalid request', 'wp-password-policy-manager'));
        }

        if(! isset($_POST['q'])){
            wp_send_json_error(__('Invalid request', 'wp-password-policy-manager'));
        }

        global $wpdb;

        $q = esc_sql($_POST['q']);

        $query = "SELECT * FROM ".$wpdb->blogs." WHERE domain like '%".$q."%' AND spam = 0 AND deleted = 0 ORDER BY blog_id";
        $data = $wpdb->get_results($query);
        if(empty($data)){
            wp_send_json_success( array() );
        }
        $out = array();
        foreach($data as $entry){
            $blogDetails = get_blog_details($entry->blog_id, true);
            array_push($out, array(
                'id' => $entry->blog_id,
                'name' => $blogDetails->blogname
            ));
        }
        wp_send_json_success( $out );
    }

    protected function SendNotificationEmail($emailAddress, $message){
        $headers = sprintf('From: %s <%s>', get_bloginfo('name'), get_bloginfo('admin_email'))."\r\n";
        $headers .= sprintf('Reply-to: %s <%s>', get_bloginfo('name'), get_bloginfo('admin_email'))."\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $subject = __('Password has been reset', 'wp-password-policy-manager');
        //@see: http://codex.wordpress.org/Function_Reference/wp_mail
        add_filter('wp_mail_content_type', array($this, '_set_html_content_type'));
        $result = wp_mail($emailAddress, $subject, $message, $headers);
        // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
        remove_filter('wp_mail_content_type', array($this, '_set_html_content_type'));
        return $result;
    }
    final public function _set_html_content_type(){ return 'text/html'; }
    protected function GetTokenType($token){
        $users = array();

        $blogId = ($this->IsMultisite() ? 0 : get_current_blog_id());

        foreach(get_users('blog_id='.$blogId.'&fields[]=user_login') as $obj)
            $users[] = $obj->user_login;
        $roles = array_keys(get_editable_roles());
        if(in_array($token, $users))return 'user';
        if(in_array($token, $roles))return 'role';
        return 'other';
    }
    /**
     * Renders WordPress settings page.
     */
    public function ManageWpOptions()
    {
        // control access to plugin
        if (!$this->IsManagingAdmin() || $this->IsAdminAccessDisabled()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-password-policy-manager'));
        }
        // update submitted settings
        if(isset($_POST) && count($_POST))
        {
            //since 0.8
            if($this->IsMultisite() && is_super_admin()){
                $__blogId = 0;
                $__allBlogs = false;
                if(isset($_POST['wppm-reset-sites'])){
                    if($_POST['wppm-reset-sites'] < 1){
                        $__allBlogs = true;
                    }
                    else {
                        $__blogId = $_POST['wppm-reset-sites'];
                    }
                }
                $bid = ($__allBlogs ? 0 : $__blogId);
            }
            else {
                $bid = get_current_blog_id();
            }
            $_POST['WPPM_BID'] = $bid;

            try {
                switch(true){
                    case isset($_POST[self::DEF_PFX.'_snt']):
                        $this->UpdateWpOptions();
                        ?><div class="updated"><p><strong><?php _e('Settings saved.', 'wp-password-policy-manager'); ?></strong></p></div><?php
                        break;
                    case isset($_POST[self::DEF_PFX.'_rst']):
                        if(! is_null($bid)){
                            // since v0.9
                            // Check if cron enabled
                            if($this->IsPostIdent('wpcron')){
                                // REGISTER ACTION
                                $this->SetGlobalOption(self::CRON_RESET_PWD_BID_OPT_NAME, $bid);
                                $this->SetGlobalOption(self::CRON_RESET_PWD_OPT_NAME, 1);
                                ?><div class="updated"><p><strong>
                                        <?php _e('Request registered. Passwords will be reset using WP Cron in 10 minutes.','wp-password-policy-manager');?>
                                    </strong></p></div>
                                <?php
                            }
                            else {
                                $this->DeleteGlobalOption(self::CRON_RESET_PWD_OPT_NAME);
                                $this->DeleteGlobalOption(self::CRON_RESET_PWD_BID_OPT_NAME);
                                $this->ResetWpPasswords($bid);
                                ?><div class="updated"><p><strong><?php _e('All passwords have been reset.', 'wp-password-policy-manager'); ?></strong></p></div><?php
                            }
                        }
                        break;
                    default:
                        throw new Exception(__('Unexpected form submission content.', 'wp-password-policy-manager'));
                }
            } catch (Exception $ex) {
                ?><div class="error"><p><strong><?php echo __('Error', 'wp-password-policy-manager').': '.$ex->getMessage(); ?></strong></p></div><?php
            }
        }
        // display settings page
        ?><div class="wrap">
        <h2><?php _e('WordPress Password Policy Manager Settings', 'wp-password-policy-manager'); ?></h2>
        <form method="post" id="wppm_settings">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <input type="hidden" id="ajaxurl" value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>" />
            <?php wp_nonce_field( 'nonce_form' ); ?>

            <div id="wppm-adverts">
                <a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/?utm_source=wppmplugin&utm_medium=settingspage&utm_campaign=notifications" target="_blank">
                    <img src="<?php echo $this->GetBaseUrl();?>/img/notifications_250x150.gif" width="250" height="150" alt="">
                </a>
                <a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/search-filtering-extension/?utm_source=wppmplugin&utm_medium=settingspage&utm_campaign=search" target="_blank">
                    <img src="<?php echo $this->GetBaseUrl();?>/img/search_250x150.gif" width="250" height="150" alt="">
                </a>
                <a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/wordpress-reports-extension/?utm_source=wppmplugin&utm_medium=settingspage&utm_campaign=reports" target="_blank">
                    <img src="<?php echo $this->GetBaseUrl();?>/img/reporting_250x150.gif" width="250" height="150" alt="">
                </a>
            </div>
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('ttl'); ?>"><?php _e('Password Expiration Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <input type="text" id="<?php $this->EchoIdent('ttl'); ?>" name="<?php $this->EchoIdent('ttl'); ?>"
                               value="<?php echo esc_attr($this->GetPasswordTtl()); ?>" size="20" class="regular-text ltr">
                        <p class="description"><?php _e('Examples: <code>5 days</code> <code>20 days 6 hours</code> <code>3 weeks</code>', 'wp-password-policy-manager'); ?></p>
                        <?php _e('Leave blank to disable Password Expiry policy.', 'wp-password-policy-manager'); ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('len'); ?>"><?php _e('Password Length Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <select id="<?php $this->EchoIdent('len'); ?>" name="<?php $this->EchoIdent('len'); ?>"><?php
                            $curr = $this->GetPasswordLen();
                            foreach(array_merge(array(0), range(4, 16)) as $value){
                                $sel = ($value == $curr) ? ' selected="selected"' : '';
                                ?><option value="<?php echo $value; ?>"<?php echo $sel; ?>>
                                <?php echo ($value == 0 ? '' : $value); ?>
                                </option><?php
                            }
                            ?></select> <?php _e('characters', 'wp-password-policy-manager'); ?><br/>
                        <?php _e('Leave blank to disable Password Length policy.', 'wp-password-policy-manager'); ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('cpt'); ?>"><?php _e('Mixed Case Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Mixed Case Policy', 'wp-password-policy-manager'); ?></span></legend>
                            <label for="<?php $this->EchoIdent('cpt'); ?>">
                                <input name="<?php $this->EchoIdent('cpt'); ?>" type="checkbox" id="<?php $this->EchoIdent('cpt'); ?>"
                                       value="1"<?php if($this->IsPolicyEnabled(self::POLICY_MIXCASE))echo ' checked="checked"'; ?>/>
                                <?php _e('Password must contain a mix of uppercase and lowercase characters.', 'wp-password-policy-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('num'); ?>"><?php _e('Numeric Digits Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Numeric Digits Policy', 'wp-password-policy-manager'); ?></span></legend>
                            <label for="<?php $this->EchoIdent('num'); ?>">
                                <input name="<?php $this->EchoIdent('num'); ?>" type="checkbox" id="<?php $this->EchoIdent('num'); ?>"
                                       value="1"<?php if($this->IsPolicyEnabled(self::POLICY_NUMBERS))echo ' checked="checked"'; ?>/>
                                <?php _e('Password must contain numeric digits (<code>0-9</code>).', 'wp-password-policy-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('spc'); ?>"><?php _e('Special Characters Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Special Characters Policy', 'wp-password-policy-manager'); ?></span></legend>
                            <label for="<?php $this->EchoIdent('spc'); ?>">
                                <input name="<?php $this->EchoIdent('spc'); ?>" type="checkbox" id="<?php $this->EchoIdent('spc'); ?>"
                                       value="1"<?php if($this->IsPolicyEnabled(self::POLICY_SPECIAL))echo ' checked="checked"'; ?>/>
                                <?php _e('Password must contain special characters (eg: <code>.,!#$_+</code>).', 'wp-password-policy-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('opw'); ?>"><?php _e('Current Password Policy', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Current Password Policy', 'wp-password-policy-manager'); ?></span></legend>
                            <label for="<?php $this->EchoIdent('opw'); ?>">
                                <input name="<?php $this->EchoIdent('opw'); ?>" type="checkbox" id="<?php $this->EchoIdent('opw'); ?>"
                                       value="1"<?php if($this->IsPolicyEnabled(self::POLICY_OLDPASSWORD))echo ' checked="checked"'; ?>/>
                                <?php _e('When changing password on the profile page, the user must supply the current password.', 'wp-password-policy-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th><label for="<?php $this->EchoIdent('msp'); ?>"><?php _e('Password History Policy','wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <?php _e('Remember','wp-password-policy-manager'); ?>
                            <select id="<?php $this->EchoIdent('msp'); ?>" name="<?php $this->EchoIdent('msp'); ?>"><?php
                                $crt = $this->GetMaxSamePass();
                                foreach(array_merge(array(0), range(2, 10)) as $value){
                                    $sel = ($value == $crt) ? ' selected="selected"' : '';
                                    ?><option value="<?php echo $value; ?>"<?php echo $sel; ?>>
                                    <?php echo ($value == 0 ? '' : $value); ?>
                                    </option><?php
                                }
                                ?></select> <?php _e('old passwords', 'wp-password-policy-manager'); ?><br/>
                            <?php _e('Leave blank to disable password history policy.', 'wp-password-policy-manager'); ?>
                        </fieldset>
                    </td>
                </tr>


<?php if($this->IsMultisite() && is_super_admin()) :?>
                <tr valign="top">
                    <th><label for="<?php $this->EchoIdent('daa'); ?>"><?php _e('Disable Admins Access','wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Disable Admins Access', 'wp-password-policy-manager'); ?></span></legend>
                            <label for="<?php $this->EchoIdent('daa'); ?>">
                                <input type="checkbox"
                                        value="1"
                                        id="<?php $this->EchoIdent('daa');?>"
                                        name="<?php $this->EchoIdent('daa');?>"
                                        <?php echo (((bool)$this->GetGlobalOption(self::OPT_DISABLE_ADMINS)) ? 'checked="checked"' : '');?>
                                    />
                                <?php _e("Disallow site administrators from modifying the plugin's settings.",'wp-password-policy-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
<?php endif; /* End if is multisite */?>


                <tr>
                    <th><label for="ExemptTokenQueryBox"><?php _e('Users and Roles Exempt From Policies', 'wp-password-policy-manager'); ?></label></th>
                    <td>
                        <fieldset>
                            <input type="text" id="ExemptTokenQueryBox" style="float: left; display: block; width: 250px;">
                            <input type="button" id="ExemptTokenQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                            <br style="clear: both;"/>
                            <p class="description">
                                <?php
                                    _e('Users and Roles in this list are free of all Password Policies.', 'wp-password-policy-manager');
                                ?>
                            </p>
                            <div id="ExemptTokenList"><?php
                                foreach($this->GetExemptTokens() as $item){
                                    ?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                    <input type="hidden" name="ExemptTokens[]" value="<?php echo esc_attr($item); ?>"/>
                                    <?php echo esc_html($item); ?>
                                    <a href="javascript:return false;" title="Remove">&times;</a>
                                    </span><?php
                                }
                                ?></div>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="rst-submit-button"><?php _e("Reset All Users' Passwords", 'wp-password-policy-manager');?></label></th>
                    <td>
<?php if($this->IsMultisite() && is_super_admin()){ ?>
<div id="sa_options_container" style="margin: 10px 0 10px 0;">
    <div>
        <label for="wppm-all-sites">
            <?php _e('All sites on network:', 'wp-password-policy-manager');?>
            <input id="wppm-all-sites" name="wppm-reset-sites" type="radio" value="-1" style="margin-left: 10px;"/>
        </label>
        <br/>
        <label for="wppm-specific-site">
            <?php _e('Specific site on Network:', 'wp-password-policy-manager');?>
            <input id="wppm-specific-site" name="wppm-reset-sites" type="radio" style="margin-left: 10px;"
                   placeholder="<?php _e('Search for a site', 'wp-password-policy-manager');?>"/>
        </label>
    </div>
</div>
<script type="text/javascript">
    jQuery(function($)
    {
        var inputSelect = $("#wppm-specific-site");
        inputSelect.select2({
            minimumInputLength: 2,
            ajax: { // Select2's convenient helper
                url: ajaxurl,
                type: "POST",
                dataType: 'json',
                quietMillis: 250,
                data: function (term, page) {
                    return {
                        q: term // search term
                        ,action: 'get_blogs_ajax'
                        ,nonce: $('#_wpnonce').val()
                    };
                },
                results: function (data, page) { // parse the results into the format expected by Select2.
                    // since we are using custom formatting functions we do not need to alter the remote JSON data
                    return { results: data.data };
                },
                cache: false
            },
            initSelection: function(element, callback) {
                var id = $(element).val();
                if (id !== "") {
                    $.post(ajaxurl, {
                        q: <?php echo get_current_blog_id();?>
                        ,action: 'get_blogs_ajax'
                        ,nonce: $('#_wpnonce').val()
                    }).done(function(data) { callback(data); });
                }
            },
            formatResult: function(entry){
                return entry.name
            },
            formatSelection: function(entry){
                return entry.name
            },
            dropdownAutoWidth : true,
            escapeMarkup: function (m) { return m; } // no escaping needed
        })
            .on("select2-close", function() {
                // check value
                var v = parseInt(inputSelect.select2("val"), 10);
                if(isNaN(v) || v < 1){
                    setTimeout(function(){
                        $('#wppm-all-sites').prop('checked', true);
                    }, 250);
                }
            })
            .on("select2-focus", function(e) {
                $('#wppm-all-sites').removeAttr('checked');
            })
            .on("select2-open", function() {
                $('#wppm-all-sites').removeAttr('checked');
            });

        // Set the default value
        inputSelect.select2("val", 0);

        // Check the first option
        $('#wppm-all-sites').prop('checked', true);
    });
</script>
<?php };  /* End if is multisite && SA */?>
<script type="text/javascript">
    jQuery(function($){
        $('#rst-submit-button').on('click', function()
        {
            if(confirm("<?php esc_attr_e(__('Are you sure you want to reset all passwords?', 'wp-password-policy-manager'));?>")){
                if($('#wppm-all-sites').prop('checked') == true) {
                    return true;
                }
                else {
                    var input = $('#wppm-specific-site'),
                        value = input.select2("val");
                    if(input && !isNaN(value) && value >= 1){
                        input.prop('checked','checked').val(value);
                        return true;
                    }
                }
            }
            return false;
        });
    });
</script>
                        <input id="rst-submit-button" type="submit"
                               name="<?php $this->EchoIdent('rst'); ?>"
                               class="button-secondary"
                               value="<?php esc_attr_e(__('Reset All Passwords', 'wp-password-policy-manager')); ?>"/>
                        </td>
                    </tr>
                <?php if(!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) { ?>
                <tr valign="top">
                    <th scope="row"><label for="<?php $this->EchoIdent('wpcron');?>"><?php _e("Use WP Cron",'wp-password-policy-manager');?></label></th>
                    <td>
                        <?php
                            $wpUseCron = $this->GetGlobalOption(self::CRON_RESET_PWD_OPT_NAME);
                        ?>
                        <input type="checkbox" name="<?php $this->EchoIdent('wpcron');?>"
                               id="<?php $this->EchoIdent('wpcron');?>" <?php checked($wpUseCron);?> />
                        <p class="description"><?php _e('Only check this option if your site has many users.','wp-password-policy-manager'); ?></p>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            <!-- Policy Flags: <?php echo $this->_policy_flag_cache; ?> -->
            <p class="submit">
                <input type="submit" name="<?php $this->EchoIdent('snt'); ?>" class="button-primary"
                       value="<?php esc_attr_e(__('Save Changes', 'wp-password-policy-manager')); ?>" />
            </p>
        </form>
        </div><?php
    }

    /**
     * Returns whether policies for specified user are applicable or not.
     * @param WP_User|integer $userOrUid Either user instance or user id.
     * @return boolean True if the policies are disabled, false otherwise.
     */
    public function IsUserExemptFromPolicies($userOrUid){
        $user = is_int($userOrUid) ? get_userdata($userOrUid) : $userOrUid;
        $tokens = $this->GetExemptTokens();
        foreach(array_merge($user->roles, array($user->user_login)) as $token)
            if(in_array($token, $tokens))
                return true;
        return false;
    }
    /**
     * Returns whether the password is too old or not.
     * @param integer $setTime The timestamp of when the password was set.
     * @return boolean True if old, false otherwise.
     */
    public function IsPasswordOld($setTime){
        $ttl = $this->GetPasswordTtl();
        if(!trim($ttl))return false;
        return strtotime($ttl, $setTime) <= current_time('timestamp');
    }
    /**
     * Returns whether password for specified user is too old or not.
     * @param WP_User|integer $userOrUid Either user instance or user id.
     * @return boolean True if the password is old.
     */
    public function IsUserPasswordOld($userOrUid){
        return $this->IsPasswordOld($this->GetPasswordLastModTime(is_object($userOrUid) ? $userOrUid->ID : $userOrUid));
    }
    /**
     * Returns the last modification date of a user's password.
     * @param integer $user_ID The user's id.
     * @return integer Timestamp.
     */
    public function GetPasswordLastModTime($user_ID){
        $time = (int)$this->GetUserOption(self::OPT_NAME_UPM, $user_ID);
        if(!$time)$time = strtotime(get_userdata($user_ID)->user_registered);
        return $time;
    }
    protected function GetOldPass($user_id = null){
        $l = $this->GetUserOption(self::OPT_NAME_OPL, $user_id);
        return $l === false ? array() : json_decode($l);
    }
    protected function AddNewPass($pass, $user_id = null){
        $pass = md5($pass); // security feature
        $l = $this->GetOldPass();
        $l[] = $pass;
        $l = array_slice(array_unique($l), - $this->GetMaxSamePass());
        $this->SetUserOption(self::OPT_NAME_OPL, json_encode($l), $user_id);
    }
    protected function IsNewPass($pass, $user_id = null){
        $pass = md5($pass); // security feature
        $l = $this->GetUserOption(self::OPT_NAME_OPL, $user_id);
        $l = $l === false ? array() : json_decode($l);
        return !in_array($pass, $l);
    }
    private static function _getPwdListOptName($uid){
        return $uid.'_'.self::OPT_USER_PWDS;
    }
    private function _pwdHasBeenUsed($uid, $pwd){
        $name = self::_getPwdListOptName($uid);
        $list = $this->GetGlobalOption($name);
        if(! $list){
            return false;
        }
        return in_array(md5($pwd), $list);
    }
    private function _addPwdToList($uid, $pwd){
        $name = self::_getPwdListOptName($uid);
        $list = $this->GetGlobalOption($name);
        $md5p = md5($pwd);
        if(! $list){
            $list = array($md5p);
        }
        else {
            $count = count($list);
            if ($count == $this->GetMaxSamePass()) {
                array_shift($list);
            }
            array_push($list, $md5p);
        }
        $this->SetGlobalOption($name,$list);
        return true;
    }

    /**
     * Static version of the DeleteGlobalOption method
     * @internal
     * @param $name
     */
    public static function _DeleteGlobalOption($name){
        $fn = (function_exists('is_multisite') && is_multisite()) ? 'delete_site_option' : 'delete_option';
        return $fn($name);
    }

    public static function ClearUserPrevPwds($uid){
        return self::_DeleteGlobalOption(self::_getPwdListOptName($uid));
    }
// </editor-fold desc="Misc Functionality">

// <editor-fold desc="WordPress Hooks and Filters">
    public function profile_update($user_id){
        $this->_addPwdToList($user_id, get_userdata($user_id)->user_pass);
    }
    public function user_profile_update_errors($errors, $update, $user){
        if (!$errors->get_error_data('pass') && !$errors->get_error_data('expired_password'))
            update_user_option($user->ID, self::OPT_NAME_UPM, current_time('timestamp'));
    }
    public function user_register($user_id){
        $this->_addPwdToList($user_id, get_userdata($user_id)->user_pass);
    }
    public function password_reset($user/*, $new_pass*/){
        update_user_option($user->ID, self::OPT_NAME_UPM, current_time('timestamp'));
    }

    public function plugin_action_links($old_links){
        $new_links = array(
            '<a href="' . admin_url('options-general.php?page=password_policy_settings') . '">' .
            __('Configure Password Policies', 'wp-password-policy-manager') .
            '</a>',
        );
        return array_merge($new_links, $old_links);
    }
    public function admin_menu(){
        add_options_page(__('Password Policies', 'wp-password-policy-manager'), __('Password Policies', 'wp-password-policy-manager'), 'manage_options', self::PLG_CONFIG_MENU_NAME, array($this, 'ManageWpOptions'));
    }
    public function network_admin_menu(){
        add_options_page(__('Password Policies', 'wp-password-policy-manager'), __('Password Policies', 'wp-password-policy-manager'), 'manage_network', self::PLG_CONFIG_MENU_NAME, array($this, 'ManageWpOptions'));
        add_submenu_page('settings.php', __('Password Policies', 'wp-password-policy-manager'), __('Password Policies', 'wp-password-policy-manager'), 'manage_network_options', self::PLG_CONFIG_MENU_NAME, array($this, 'ManageWpOptions'));
    }
    public function admin_enqueue_scripts(){
        $baseUrl = trailingslashit($this->GetBaseUrl());
        $baseDir = trailingslashit($this->GetBaseDir());
        wp_enqueue_style('wppm', $baseUrl.'css/wppm.css', array(), filemtime($baseDir.'css/wppm.css'));

        //since 0.8
        if($this->IsMultisite() && is_super_admin()){
            wp_enqueue_style('wppm-select2-css', $baseUrl.'js/select2/select2.css', array(), filemtime($baseDir.'js/select2/select2.css'));
            wp_enqueue_style('wppm-select2-bs-css', $baseUrl.'js/select2/select2-bootstrap.css', array(), filemtime($baseDir.'js/select2/select2-bootstrap.css'));
            wp_enqueue_script('wppm-select2-min-js', $baseUrl.'js/select2/select2.min.js', array('jquery'), filemtime($baseDir.'js/select2/select2.min.js'));
        }
    }
    public function admin_footer(){
        if($this->IsJustInstalled()){
            wp_enqueue_style('wp-pointer');
            wp_enqueue_script('wp-pointer');
        }
        wp_enqueue_script('wppm', $this->GetBaseUrl() . 'js/wppm.js', array(), filemtime($this->GetBaseDir() . 'js/wppm.js'));
    }
    public function admin_print_footer_scripts(){
        $isOnPluginPage = isset($_REQUEST['page']) && $_REQUEST['page']==self::PLG_CONFIG_MENU_NAME;
        if($this->IsJustInstalled() && $this->IsManagingAdmin() && !$isOnPluginPage){
            $tle = __('Configure Password Policies', 'wp-password-policy-manager');
            $txt = __('You have just installed WP Password Policy manager. All password policies are disabled by default. Click the button below to configure the WordPress password policies.', 'wp-password-policy-manager');
            $btn = __('Configure Policies', 'wp-password-policy-manager');
            $url = admin_url('options-general.php?page='.self::PLG_CONFIG_MENU_NAME);
            ?><script type="text/javascript">
                jQuery(function($) {
                    $('#wp-admin-bar-my-account').pointer({
                        buttons: function () {
                            return $(<?php echo json_encode('<a class="button-primary" href="'.$url.'">'.$btn.'</a>'); ?>);
                        },
                        'content': <?php echo json_encode("<h3>$tle</h3><p>$txt</p>"); ?>
                    }).pointer('open');
                });
            </script><?php
        }
    }
    public function wp_ajax_check_security_token(){
        if(!$this->IsManagingAdmin())
            die(__('Access Denied.', 'wp-password-policy-manager'));
        if(!isset($_REQUEST['token']))
            die(__('Token parameter expected.', 'wp-password-policy-manager'));
        die($this->GetTokenType($_REQUEST['token']));
    }
    public static function on_uninstall(){
        if ( ! current_user_can('activate_plugins'))
            return;
        $users = get_users(array('fields' => array('ID')));
        foreach ($users as $user)
            self::ClearUserPrevPwds($user->ID);
    }

    /**
     * Register the ajax request
     * @since 0.8
     */
    public function admin_init() {
        add_action('wp_ajax_get_blogs_ajax', array($this,'get_blogs_ajax'));
    }
// </editor-fold desc="WordPress Hooks and Filters">


//<editor-fold desc="::: WP Cron">
    const WP_CRON_ACTION = 'wppm_cron_task';
    const CRON_RESET_PWD_BID_OPT_NAME = 'wppm_cron_reset_pwd_bid';
    const CRON_RESET_PWD_OPT_NAME = 'wppm_cron_reset_pwds';


    public function _cronSchedule(){
        if( !wp_next_scheduled( self::WP_CRON_ACTION ) ) {
            wp_schedule_event( time(), 'ten_minutes', self::WP_CRON_ACTION );
//            error_log('CRON TASK SCHEDULED');
        }
    }
    public function _cronUnschedule(){
        // find out when the last event was scheduled
        $timestamp = wp_next_scheduled (self::WP_CRON_ACTION);
        // unschedule previous event if any
        wp_unschedule_event($timestamp, self::WP_CRON_ACTION);
    }
    public function _cronDoAction(){
        $blogId = $this->GetGlobalOption(self::CRON_RESET_PWD_BID_OPT_NAME);
        if(false !== $blogId){
            global $wpdb;
            $query = "SELECT DISTINCT(blog_id) FROM ".$wpdb->blogs.' WHERE spam = 0 AND deleted = 0';
            $blogs = $wpdb->get_results($query, ARRAY_A);
            if(empty($blogs)){
//                error_log(__FUNCTION__.'() Error: no blogs found.');
                return false;
            }
            foreach($blogs as $blog){
                $this->_resetPasswordsHelper($blog['blog_id'], false, true);
            }
        }
    }
    public function _cronAddCustomInterval($schedules){
        if(! is_array($schedules)){
            $schedules = array();
        }
        $schedules['ten_minutes'] = array(
            'interval'	=> 600,	// Number of seconds, 600 in 10 minutes
            'display'	=> __('Once Every 10 Minutes','wp-password-policy-manager')
        );
        return $schedules;
    }
//</editor-fold desc="::: WP Cron">
}

$wppm = WpPasswordPolicyManager::GetInstance();
$action = $wppm::WP_CRON_ACTION;
/*
 * WP Cron
 */
add_filter( 'cron_schedules', array($wppm, '_cronAddCustomInterval'), 98, 1 );
add_action( 'wp', array($wppm, '_cronSchedule') ); // frontend
add_action( 'plugins_loaded', array($wppm, '_cronSchedule') ); // backend
add_action( "{$action}", array($wppm, '_cronDoAction') );
register_deactivation_hook( __FILE__, array($wppm, '_cronUnschedule') );

register_uninstall_hook(__FILE__, array('WpPasswordPolicyManager', 'on_uninstall'));

// Instantiate & Run the plugin
return $wppm;
