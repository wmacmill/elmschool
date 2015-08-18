<?php
class JWUH_Admin
{

	/**
	 * Initialize
	 * Mainly used for registering action and filter hooks
	 */
	public static function init()
	{
		// Actions
		add_action('admin_enqueue_scripts', array('JWUH_Admin', 'enqueue_scripts'));
	}
	
	/**
	 * Register and enqueue scripts
	 */
	public static function enqueue_scripts()
	{
		// Scripts
		wp_register_script('jwuh-admin-access', JWUH_URL . '/public/js/admin-access.js', array('jquery', 'jquery-ui-core'));
		
		// Styles
		wp_register_style('jwuh-admin', JWUH_URL . '/public/css/admin.css');
		wp_enqueue_style('jwuh-admin');
	}

}

JWUH_Admin::init();
?>