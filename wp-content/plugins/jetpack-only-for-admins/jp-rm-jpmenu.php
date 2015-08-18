<?php
/*
 * Plugin Name: Jetpack Only for Admins
 * Plugin URI: http://appsomobile.com/
 * Description: Hides the Jetpack menu for all non-admins
 * Author: Andrija Naglic
 * Version: 1.1
 * Author URI: http://profiles.wordpress.org/andrija
 * License: GPL2+
 * Text Domain: jetpack
 */
 
/*  Copyright 2013  Andrija Naglic  (email : info@appsomobile.com)

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
 
function jp_rm_menu() {
	if( class_exists( 'Jetpack' ) && !current_user_can( 'manage_options' ) ) {
	
		// This removes the page from the menu in the dashboard
		remove_menu_page( 'jetpack' );
	}
}
add_action( 'admin_init', 'jp_rm_menu' ); 

function jp_rm_icon() {
	if( class_exists( 'Jetpack' ) && !current_user_can( 'manage_options' ) ) {
	
		// This removes the small icon in the admin bar
		echo "\n" . '<style type="text/css" media="screen">#wp-admin-bar-notes { display: none; }</style>' . "\n";
	}
}
add_action( 'admin_head', 'jp_rm_icon' );

