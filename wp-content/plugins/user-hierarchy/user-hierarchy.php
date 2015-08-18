<?php
/*
Plugin Name: User Hierarchy
Description: Implement a hierarchy in adding and editing users by allowing specific roles to only manage users from another specific role. Restrict user management on a per-role basis.
Version: 0.1.2
Author: Jesper van Engelen
Author URI: http://www.jepps.nl
License: GPLv2 or later
*/

// Plugin information
define('JWUH_VERSION', '0.1.2');

// Paths
define('JWUH_PATH', dirname(__FILE__));
define('JWUH_LIBRARY_PATH', JWUH_PATH . '/lib');
define('JWUH_URL', untrailingslashit(plugins_url('', __FILE__)));

// Library
require_once JWUH_LIBRARY_PATH . '/roles.php';

if (is_admin()) {
	require_once JWUH_LIBRARY_PATH . '/admin.php';
	require_once JWUH_LIBRARY_PATH . '/adminmenu.php';
}

// Localization
load_plugin_textdomain('userhierarchy', false, dirname(plugin_basename(__FILE__)) . '/languages/');
?>