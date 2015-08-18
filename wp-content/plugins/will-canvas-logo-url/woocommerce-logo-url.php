<?php
/*
Plugin Name: Will - Canvas Logo URL Change
Plugin URI:
Description: This plugin changes the url that is set when you click on the logo (default is the homepage).  Set url on line 34
Version: 0.1
Author: Will MacMillan
Author URI: http://www.facebook.com/macmillan.will
Text Domain: 
Domain Path: 
*/
/* Start Adding Functions Below this Line */

// Remove logo code from Canvas
add_action('wp_head', 'remove_woo_logo');
function remove_woo_logo() {
	remove_action('woo_header_inside','woo_logo');
}

/*-----------------------------------------------------------------------------------*/
/* Load custom logo code */
/*-----------------------------------------------------------------------------------*/

if ( ! function_exists( 'custom_woo_logo' ) ) {
function custom_woo_logo () {
	$settings = woo_get_dynamic_values( array( 'logo' => '' ) );
	// Setup the tag to be used for the header area (`h1` on the front page and `span` on all others).
	$heading_tag = 'span';
	if ( is_home() || is_front_page() ) { $heading_tag = 'h1'; }

	// Get our website's name, description and URL. We use them several times below so lets get them once.
	$site_title = get_bloginfo( 'name' );
	// $site_url = home_url( '/' );
	$site_url = '/profile/';
	$site_description = get_bloginfo( 'description' );
?>
<div id="logo">
<?php
	// Website heading/logo and description text.
	if ( ( '' != $settings['logo'] ) ) {
		$logo_url = $settings['logo'];
		if ( is_ssl() ) $logo_url = str_replace( 'http://', 'https://', $logo_url );

		echo '<a href="' . esc_url( $site_url ) . '" title="' . esc_attr( $site_description ) . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_title ) . '" /></a>' . "\n";
	} // End IF Statement

	echo '<' . $heading_tag . ' class="site-title"><a href="' . esc_url( $site_url ) . '">' . $site_title . '</a></' . $heading_tag . '>' . "\n";
	if ( $site_description ) { echo '<span class="site-description">' . $site_description . '</span>' . "\n"; }
?>
</div>
<?php
} // End custom_woo_logo()
}

add_action( 'woo_header_inside', 'custom_woo_logo', 10 );
/*end changes the url when clicking on the logo Canvas*/


/* Stop Adding Functions Below this Line */
?>