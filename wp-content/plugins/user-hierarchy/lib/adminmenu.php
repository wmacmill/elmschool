<?php
class JWUH_AdminMenu
{

	/**
	 * Initialize
	 * Mainly used for registering action and filter hooks
	 */
	public function init()
	{
		// Pages
		require_once JWUH_LIBRARY_PATH . '/classes/AdminMenuPage/class.Access.php';
		
		// Actions
		add_action('plugins_loaded', array('JWUH_AdminMenu', 'menu'));
	}
	
	/**
	 * Add menu pages and tabs
	 */
	public function menu()
	{
		$page = new JWUH_AdminMenuPage_Access();
	}

}

JWUH_AdminMenu::init();
?>