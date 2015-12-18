<?php
/*
Plugin Name: Manage Notification E-mails
Plugin URI: http://www.freeamigos.mx/wp-plugins/manage-notification-emails/1.2.0
Description: This plugin gives you the option to disable some of the notification e-mails send by Wordpress. It's a simple plugin but effective.


Version: 1.2.0
Author: Virgial Berveling
Author URI: http://www.freeamigos.mx
License:     GPLv2
*/


/*  
	Copyright (c) 2006-2015  Virgial Berveling

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

	You may also view the license here:
	http://www.gnu.org/licenses/gpl.html
*/


/*
	A NOTE ABOUT LICENSE:

	While this plugin is freely available and open-source under the GPL2
	license, that does not mean it is "public domain." You are free to modify
	and redistribute as long as you comply with the license. Any derivative 
	work MUST be GPL licensed and available as open source.  You also MUST give 
	proper attribution to the original author, copyright holder, and trademark
	owner.  This means you cannot change two lines of code and claim copyright 
	of the entire work as your own.  The GPL2 license requires that if you
	modify this code, you must clearly indicate what section(s) you have
	modified and you may only claim copyright of your modifications and not
	the body of work.  If you are unsure or have questions about how a 
	derivative work you are developing complies with the license, copyright, 
	trademark, or if you do not understand the difference between
	open source and public domain, contact the original author at:
	http://www.freeamigos.mx/contact/.


	INSTALLATION PROCEDURE:
	
	Just put it in your plugins directory.
*/

if (!defined('ABSPATH')) die();

define( 'FA_MNE_VERSION', '1.2.0' );
define( 'FA_MNE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FA_MNE_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );


/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */

function famne_init() {
    
    if (is_admin()) :
        
    include_once( FA_MNE_PLUGIN_DIR . '/includes/class.FAMNESettingsPage.php' );
    $fa_MNESettingsPage = new FAMNESettingsPage();    
    endif;
}


include_once( FA_MNE_PLUGIN_DIR . '/includes/pluggable-functions.php' );

famne_init();
