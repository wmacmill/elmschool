<?php
class JWUH_Roles
{

	/**
	 * Initialize
	 * Mainly used for registering action and filter hooks
	 */
	public static function init()
	{
		// Actions
		//add_action('pre_user_query', array('JWUH_Roles', 'userlist_query_roles'));
		add_action('editable_roles', array('JWUH_Roles', 'editable_roles'));
		
		// Filters
		add_filter('user_has_cap', array('JWUH_Roles', 'user_has_role_cap'), 100, 3);
	}
	
	/**
	 * Get the highest hierarchy role for a user
	 *
	 * @param int $userid ID of the user to get the role for
	 * @return string User role name
	 */
	public static function get_user_role($userid)
	{
		$userid = intval($userid);
		
		if (!$userid) {
			return false;
		}
		
		$user = get_user_by('id', $userid);
		
		return array_shift($user->roles);
	}

	/**
	 * Get role access for the current user
	 *
	 * @return bool|array Role access for the current role as array with at least key "roles". Returns false if role access is disabled for the role of the current user
	 */
	public static function get_role_access()
	{
		// Current user role
		$currentuser_role = JWUH_Roles::get_user_role(wp_get_current_user()->ID);
		
		$role_access = get_option('jwuh_role_access', array());
		
		if (!is_array($role_access) || !isset($role_access[$currentuser_role])) {
			return false;
		}
		
		// Make sure filtering is enabled for this role
		$role_access = $role_access[$currentuser_role];
		
		if (!is_array($role_access) || !$role_access['enabled']) {
			return false;
		}
		
		return $role_access;
	}
	
	/**
	 * Get main capabilities for user managing
	 *
	 * @return array Capability names
	 */
	public static function get_user_caps()
	{
		// Mappable role capabilities
		$caps = array('add_users', 'create_users', 'delete_users', 'edit_users', 'list_users', 'promote_users', 'remove_users');
		
		return $caps;
	}
	
	/**
	 * Get the post type capabilities that should be remapped by the plugin
	 *
	 * @return array Capability names
	 */
	public static function get_role_mapcaps()
	{
		// Mappable role capabilities
		$caps = array('assign', 'edit', 'delete'/*, 'view'*/);
		
		return $caps;
	}
	
	/**
	 * Check whether the user has a certain capability for a role
	 */
	public static function user_has_role_cap($allcaps, $caps, $args)
	{
		global $wp_roles;
		
		// Get role access
		$role_access = JWUH_Roles::get_role_access();
		
		if (!$role_access) {
			return $allcaps;
		}
		
		$mappings = array(
			'edit_user' => 'edit',
			'delete_user' => 'delete'
		);
		
		// Make sure the requested capability and the user ID are set
		if (!$args[0] || !isset($mappings[$args[0]]) || !$args[1] || !$args[2]) {
			return $allcaps;
		}
		
		// Check for valid role
		$role = JWUH_Roles::get_user_role($args[1]);
		
		if (!$role) {
			return $allcaps;
		}
		
		$available_roles = JWUH_Roles::get_queryable_roles($mappings[$args[0]]);
		
		$access = in_array(JWUH_Roles::get_user_role($args[2]), $available_roles) ? true : false;
		
		foreach ($caps as $index => $cap) {
			$allcaps[$cap] = $access;
		}
		
		return $allcaps;
	}
	
	/**
	 * Get roles on which the current user can query users
	 *
	 * @param string $request Requested query ("view", "assign", "edit" or "delete")
	 * @return array Roles
	 */
	public static function get_queryable_roles($request)
	{
		// Get role access
		$role_access = JWUH_Roles::get_role_access();
		
		if (!$role_access || empty($role_access['roles'])) {
			return array();
		}
		
		// Get roles to filter for
		$available_roles = array();
		
		foreach ($role_access['roles'] as $index => $access) {
			if ($access[$request]) {
				$available_roles[] = $index;
			}
		}
		
		return $available_roles;
	}
	
	/**
	 * Add a constraint to the user query when viewing the user list to only show users with the allowed role
	 */
	public static function userlist_query_roles($query)
	{
		global $wpdb, $pagenow;
		
		if (!is_admin() || $pagenow != 'users.php' || $query->get('fields') != 'all_with_meta') {
			return;
		}
		
		// Get role access
		$role_access = JWUH_Roles::get_role_access();
		
		if (!$role_access) {
			return;
		}
		
		// Get viewable users roles
		$available_roles = JWUH_Roles::get_queryable_roles('view');
		
		if (!is_array($query->meta_query)) {
			$query->query_vars['meta_query'] = array();
		}
		
		$query->query_vars['meta_query']['relation'] = 'OR';
		
		foreach ($available_roles as $index => $role) {
			$query->query_vars['meta_query'][] = array(
				'key' => $wpdb->get_blog_prefix($query->get('blog_id')) . 'capabilities',
				'value' => '"' . $role . '"',
				'compare' => 'LIKE'
			);
		}
		
		$meta_query = new WP_Meta_Query();
		$meta_query->parse_query_vars($query->query_vars);
		
		if (!empty($meta_query->queries)) {
			$clauses = $meta_query->get_sql('user', $wpdb->users, 'ID', $query);
			$query->query_from .= $clauses['join'];
			$query->query_where .= $clauses['where'];
			
			if ('OR' == $meta_query->relation) {
				$query->query_fields = 'DISTINCT ' . $query->query_fields;
			}
		}
	}
	
	/**
	 * Change editable roles
	 */
	public static function editable_roles($roles)
	{
		global $pagenow;
		
		// Get role access
		$role_access = JWUH_Roles::get_role_access();
		
		if (!$role_access) {
			return $roles;
		}
		
		$available_roles = JWUH_Roles::get_queryable_roles('assign');
		
		foreach ($roles as $index => $role) {
			if (!in_array($index, $available_roles) && ($pagenow != 'user-edit.php' || JWUH_Roles::get_user_role($_GET['user_id']) != $index)) {
				unset($roles[$index]);
			}
		}
		
		return $roles;
	}

}

JWUH_Roles::init();
?>