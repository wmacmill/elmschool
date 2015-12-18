<?php
/**
 * Manage notification emails settings page class
 *
 * Displays the settings page.
 * 
 * This file is part of the Manage Notification Emails plugin
 * You can find out more about this plugin at http://www.freeamigos.mx
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2015
 *
 * version: 1.2.0
 */


class FAMNESettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'plugins_loaded', array($this, 'update_check') );
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'Notification e-mails', 
            'manage_options', 
            'famne-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'famne_options' );

        ?>
        <div class="wrap">
            <h2>Manage the notification e-mails</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'famne_option_group' );   
                do_settings_sections( 'famne-admin' );
                submit_button(); 
            ?>
            </form>

            <div style="padding:40px;text-align:center;color:rgba(0,0,0,0.7);">
                <p class="description">If you find this plugin useful, you can show your appreciation here :-)
 </p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="LTZWTLEDPULFE">
<input type="image" src="https://www.paypalobjects.com/nl_NL/NL/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal, de veilige en complete manier van online betalen.">
<img alt="" border="0" src="https://www.paypalobjects.com/nl_NL/i/scr/pixel.gif" width="1" height="1">
</form>
            </div>

</div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'famne_option_group', // Option group
            'famne_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'famne-admin' // Page
        );  

        add_settings_field(
            'wp_new_user_notification_to_admin', // ID
            'New user notification to admin', // Title 
            array( $this, 'field1_callback' ), // Callback
            'famne-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'wp_new_user_notification_to_user', // ID
            'New user notification to user', // Title 
            array( $this, 'field7_callback' ), // Callback
            'famne-admin', // Page
            'setting_section_id' // Section           
        );      
        
        add_settings_field(
            'wp_notify_postauthor', 
            'Notify post author', 
            array( $this, 'field2_callback' ), 
            'famne-admin', 
            'setting_section_id'
        );      

        add_settings_field(
            'wp_notify_moderator', 
            'Notify moderator', 
            array( $this, 'field3_callback' ), 
            'famne-admin', 
            'setting_section_id'
        );      

        add_settings_field(
            'wp_password_change_notification', 
            'Password change notification to admin', 
            array( $this, 'field4_callback' ), 
            'famne-admin', 
            'setting_section_id'
        );      

        add_settings_field(
            'send_password_change_email', 
            'Password change notification to user', 
            array( $this, 'field5_callback' ), 
            'famne-admin', 
            'setting_section_id'
        );      

        add_settings_field(
            'send_email_change_email', 
            'E-mail address change notification to user', 
            array( $this, 'field6_callback' ), 
            'famne-admin', 
            'setting_section_id'
        ); 
        
        add_settings_field(
            'send_password_forgotten_email', 
            'Password forgotten e-mail to user', 
            array( $this, 'field8_callback' ), 
            'famne-admin', 
            'setting_section_id'
        ); 

        add_settings_field(
            'send_password_admin_forgotten_email', 
            'Password forgotten e-mail to administrator', 
            array( $this, 'field9_callback' ), 
            'famne-admin', 
            'setting_section_id'
        ); 
    
    
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        if (empty($input)) $input = array();
        $new_input = array();
        foreach( $input as $key=>$val )
            $new_input[$key] = $val == '1'?'1':'';

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        echo 'Manage your notification e-mail preferences below. <br/>By unchecking the checkbox you prevent sending the specific e-mails.';
    }

    /** 
     * Get the settings option array and print one of its values
     */

    public function print_checkbox($name,$id,$message='')
    {
        $checked = isset( $this->options[$id]) && $this->options[$id] =='1' ?true:false;

        if ($checked) {$add_check = 'checked="checked"';}else {$add_check='';};
        print '<label><input type="checkbox" name="famne_options['.$id.']" value="1" '.$add_check.' />&nbsp;Enable sending e-mail</label>';
        print '<p class="description">'.$message.'</p>';
    }
    
    
    public function field1_callback()
    {
        $this->print_checkbox('field1','wp_new_user_notification_to_admin','Sends an e-mail to the site admin after a new user is registered.');
    }

    public function field7_callback()
    {
        $this->print_checkbox('field7','wp_new_user_notification_to_user','Send e-mail with login credentials to a newly-registered user.');
    }

    public function field2_callback()
    {
        $this->print_checkbox('field2','wp_notify_postauthor','Send e-mail to an author (and/or others) of a comment/trackback/pingback on a post.');
    }

    public function field3_callback()
    {
        $this->print_checkbox('field3','wp_notify_moderator','Send e-mail to the moderator of the blog about a new comment that is awaiting approval.');
    }
    
    public function field4_callback()
    {
        $this->print_checkbox('field4','wp_password_change_notification','Send e-mail to the blog admin of a user changing his or her password.');
    }
    
    public function field5_callback()
    {
        $this->print_checkbox('field5','send_password_change_email','Send e-mail to registered user about changing his or her password. Be careful with this option, because when unchecked, the forgotten password request e-mails will be blocked too.');
    }

    public function field6_callback()
    {
        $this->print_checkbox('field6','send_email_change_email','Send e-mail to registered user about changing his or her E-mail address.');
    }
    
    public function field8_callback()
    {
        $this->print_checkbox('field8','send_password_forgotten_email','Send the forgotten password e-mail to registered user.<br/>(To prevent locking yourself out, sending of the forgotten password e-mail for administrators will still work)');
    }    
    public function field9_callback()
    {
        $this->print_checkbox('field9','send_password_admin_forgotten_email','Send the forgotten password e-mail to administrators. Okay, this is a <strong style="color:#900">DANGEROUS OPTION !</strong><br/> So be warned, because unchecking this option prevents sending out the forgotten password e-mail to all administrators. So hold on to your own password and uncheck this one at your own risk ;-)');
    }        

    public function update_check()
    {
        if (get_site_option( 'fa_mne_version' ) != FA_MNE_VERSION) {
       
            
            $this->options = get_option( 'famne_options' );
            
            
            /* Is this the first install, then set all defaults to active */
            if ($this->options === false)
            {
                $options = array(
                    'wp_new_user_notification_to_user'      => '1', 
                    'wp_new_user_notification_to_admin'     => '1', 
                    'wp_notify_postauthor'                  => '1',
                    'wp_notify_moderator'                   => '1',
                    'wp_password_change_notification'       => '1',
                    'send_password_change_email'            => '1',
                    'send_email_change_email'               => '1',
                    'send_password_forgotten_email'         => '1',
                    'send_password_admin_forgotten_email'   => '1'
                );
                
                update_option('famne_options',$options);
                $this->options = $options;

            }
            
            if (get_site_option( 'fa_mne_version' ) == '1.1.0')
            {
            
                /** update 1.1.0 to 1.2.0
                 * setting the newly added options to checked as default
                 */

                $this->options['send_password_forgotten_email'] = '1';
                $this->options['send_password_admin_forgotten_email'] ='1';

                update_option('famne_options',$this->options);
            }
            
            
            
            /** update 1.0 to 1.1 fix:
             * update general wp_new_user_notification option into splitted options    
             */
            if (!empty($this->options['wp_new_user_notification']))
            {
                unset($this->options['wp_new_user_notification']);
                $this->options['wp_new_user_notification_to_user'] ='1';  
                $this->options['wp_new_user_notification_to_admin'] ='1';  
                update_option('famne_options',$this->options);
            }

            /* UPDATE DONE! */
            update_site_option('fa_mne_version',FA_MNE_VERSION);
        }
    }
}