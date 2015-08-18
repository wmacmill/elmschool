<?php

/**
 * Plugin Name: GPP Plugin Updates
 * Description: Updates your Graph Paper Press plugins automatically
 * Plugin URI: http://graphpaperpress.com/plugins/gpp-plugin-updates/
 * Version: 1.0.3
 * License: GPL
 * Author: Graph Paper Press
 * Author URI: http://graphpaperpress.com
 */

if ( ! is_admin() ) return;

function gpp_plugin_updates_check( $arg ) {

	global $wp_version;

	/**
	 * Retrive list of installed plugins. Then build a list
	 * containing only plugins authored by us.
	 */
	$installed_plugins = get_plugins() ;
	$plugins = array();
	foreach( $installed_plugins as $k => $v ) {
		if ( $v['Author'] == 'Graph Paper Press' )
			$plugins[ $k ] = $v['Version'];
	}

	$options = array(
		'timeout'    => 10,
		'body'		 => array( 'plugins' => serialize( $plugins ) ),
		'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);

	// post the local data to the remote server to check the available updates
	$raw_response = wp_remote_post( 'http://demo.graphpaperpress.com/wp-content/api/', $options );

	if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) )
		return false;

	$response = maybe_unserialize( wp_remote_retrieve_body( $raw_response ) );

	if ( ! is_object( $arg ) ){
		$arg = new stdClass;
	}

	if ( ! empty( $response ) && is_array( $response ) )
		$arg->response = array_merge( $response, (array)$arg->response );

	return $arg;
}
add_filter( 'site_transient_update_plugins', 'gpp_plugin_updates_check' );