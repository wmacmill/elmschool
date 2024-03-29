<?php
/*
Plugin Name: WP Smush Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smush-pro/
Description: Reduce image file sizes, improve performance and boost your SEO using the <a href="https://premium.wpmudev.org/">WPMU DEV</a> WordPress Smush API.
Author: WPMU DEV
Version: 2.1.4
Author URI: http://premium.wpmudev.org/
Textdomain: wp-smushit
WDP ID: 912164
*/

/*
  Copyright 2009-2015 Incsub (http://incsub.com)
  Author - Aaron Edwards, Sam Najian, Umesh Kumar
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
  the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Constants
 */
$prefix  = 'WP_SMUSH_';
$version = '2.1.4';

/**
 * Set the default timeout for API request and AJAX timeout
 */
$timeout = apply_filters( 'WP_SMUSH_API_TIMEOUT', 90 );

$smush_constants = array(
	'VERSION'           => $version,
	'BASENAME'          => plugin_basename( __FILE__ ),
	'API'               => 'https://smushpro.wpmudev.org/1.0/',
	'UA'                => 'WP Smush/' . $version . '; ' . network_home_url(),
	'DIR'               => plugin_dir_path( __FILE__ ),
	'URL'               => plugin_dir_url( __FILE__ ),
	'MAX_BYTES'         => 1000000,
	'PREMIUM_MAX_BYTES' => 32000000,
	'PREFIX'            => 'wp-smush-',
	'TIMEOUT'           => $timeout
);

foreach ( $smush_constants as $const_name => $constant_val ) {
	if ( ! defined( $prefix . $const_name ) ) {
		define( $prefix . $const_name, $constant_val );
	}
}

//Include main class
require_once WP_SMUSH_DIR . 'lib/class-wp-smush.php';

/**
 * Filters the rating message, include stats if greater than 1Mb
 *
 * @param $message
 *
 * @return string
 */
if ( ! function_exists( 'wp_smush_rating_message' ) ) {
	function wp_smush_rating_message( $message ) {
		global $wpsmushit_admin;
		$savings     = $wpsmushit_admin->global_stats();
		$image_count = $wpsmushit_admin->total_count();
		$show_stats  = false;

		//If there is any saving, greater than 1Mb, show stats
		if ( ! empty( $savings ) && ! empty( $savings['bytes'] ) && $savings['bytes'] > 1048576 ) {
			$show_stats = true;
		}

		$message = "Hey %s, you've been using %s for a while now, and we hope you're happy with it.";

		//Conditionally Show stats in rating message
		if ( $show_stats ) {
			$message .= sprintf( " You've smushed <strong>%s</strong> from %d images already, improving the speed and SEO ranking of this site!", $savings['human'], $image_count );
		}
		$message .= " We've spent countless hours developing this free plugin for you, and we would really appreciate it if you dropped us a quick rating!";

		return $message;
	}
}

/**
 * NewsLetter
 *
 * @param $message
 *
 * @return string
 */
if ( ! function_exists( 'wp_smush_email_message' ) ) {
	function wp_smush_email_message( $message ) {
		$message = "You're awesome for installing %s! Site speed isn't all image optimization though, so we've collected all the best speed resources we know in a single email - just for users of WP Smush!";

		return $message;
	}
}

if ( is_admin() ) {

	$dir_path = plugin_dir_path( __FILE__ );
	//Only for wordpress.org members
	if ( strpos( $dir_path, 'wp-smushit' ) !== false ) {
		require_once( WP_SMUSH_DIR . 'extras/free-dashboard/module.php' );

		// Register the current plugin.
		do_action(
			'wdev-register-plugin',
			/* 1             Plugin ID */
			plugin_basename( __FILE__ ), /* Plugin ID */
			/* 2          Plugin Title */
			'WP Smush',
			/* 3 https://wordpress.org */
			'/plugins/wp-smushit/',
			/* 4      Email Button CTA */
			__( 'Get Fast', 'wp-smushit' ),
			/* 5  getdrip Plugin param */
			'Smush'
		);

		// The rating message contains 2 variables: user-name, plugin-name
		add_filter(
			'wdev-rating-message-' . plugin_basename( __FILE__ ),
			'wp_smush_rating_message'
		);

		// The email message contains 1 variable: plugin-name
		add_filter(
			'wdev-email-message-' . plugin_basename( __FILE__ ),
			'wp_smush_email_message'
		);
	} elseif ( strpos( $dir_path, 'wp-smush-pro' ) !== false ) {

		//Only for WPMU DEV Members
		require_once( WP_SMUSH_DIR . 'extras/dash-notice/wpmudev-dash-notification.php' );

		//register items for the dashboard plugin
		global $wpmudev_notices;
		$wpmudev_notices[] = array(
			'id'      => 912164,
			'name'    => 'WP Smush Pro',
			'screens' => array(
				'media_page_wp-smush-bulk',
				'upload'
			)
		);
	}
}
//Deactivate the .org version, if pro version is active
add_action( 'admin_init', 'deactivate_smush_org' );

if ( ! function_exists( 'deactivate_smush_org' ) ) {
	function deactivate_smush_org() {
		if ( is_plugin_active( 'wp-smush-pro/wp-smush.php' ) && is_plugin_active( 'wp-smushit/wp-smush.php' ) ) {
			deactivate_plugins( 'wp-smushit/wp-smush.php' );
			//Store in database, in order to show a notice on page load
			update_option( 'smush_deactivated', 1 );
		}
	}
}

//Show the required notice
add_action( 'network_admin_notices', 'smush_deactivated' );
add_action( 'admin_notices', 'smush_deactivated' );
//Display a admin Notice about plugin deactivation
if ( ! function_exists( 'smush_deactivated' ) ) {
	function smush_deactivated() {
		if ( get_option( 'smush_deactivated' ) && is_admin() ) { ?>
			<div class="updated">
				<p><?php esc_html_e( 'WP Smush Free was deactivated. You have WP Smush Pro active!', 'wp-smushit' ); ?></p>
			</div> <?php
			delete_option( 'smush_deactivated' );
		}
	}
}