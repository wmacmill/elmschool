<?php
require_once dirname(__FILE__) . '/class.Abstract.php';

class JWUH_AdminMenuPage_Access extends JWUH_AdminMenuPage_Abstract
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Menu item settings
		$this->id = 'userhierarchy-access';
		$this->parent_id = 'options-general.php';
		$this->page_title = __('User Hierarchy', 'userhierarchy');
		$this->menu_title = __('User Hierarchy', 'userhierarchy');
		$this->capability = 'manage_options';
		
		// Actions
		add_action('admin_init', array(&$this, 'handle_settings'));
		add_action('update_option_jwuh_role_caps', array(&$this, 'handle_capabilities'));
		add_action('add_option_jwuh_role_caps', array(&$this, 'handle_capabilities'));
		
		// Construct
		parent::__construct();
	}
	
	/**
	 * Handle page logic
	 */
	public function handle()
	{
		// Actions
		add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
	}
	
	/**
	 * Action: admin_enqueue_scripts
	 */
	public function enqueue_scripts()
	{
		// Scripts
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jwuh-admin-access');
	}
	
	/**
	 * Register and handle settings sections and settings
	 */
	public function handle_settings()
	{
		register_setting('jwuh_access', 'jwuh_role_access');
		register_setting('jwuh_access', 'jwuh_role_caps');
		
		// Settings sections
		add_settings_section('jwuh_role_access', __('Access', 'userhierarchy'), array(&$this, 'section_role_access'), 'userhierarchy-access');
		add_settings_section('jwuh_role_caps', '', array(&$this, 'section_role_caps'), 'userhierarchy-access');
	}
	
	/**
	 * Output the settings section for the enabled access per role
	 */
	public function section_role_access()
	{
		global $wp_roles;
		
		$role_access = get_option('jwuh_role_access', array());
		$role_access = is_array($role_access) ? $role_access : array();
		
		// Get roles
		$roles = $wp_roles->roles;
		
		// Get caps that should be remapped
		$caps = JWUH_Roles::get_role_mapcaps();
		
		// Main caps
		$maincaps = JWUH_Roles::get_user_caps();
		?>
		
		<?php foreach ($roles as $index => $role) : ?>
			<?php $role_object = get_role($index); ?>
			<div class="jwuh-role<?php if (!empty($role_access[$index]['enabled'])) echo ' jwuh-enabled'; ?>">
				<div class="jwuh-header">
					<div class="jwuh-help">
						<?php _e('Default WordPress capabilities, which can be overridden by the per-role settings below.', 'userhierarchy'); ?>
					</div>
					<h4>
						<label for="jwuh-role-access-<?php echo $index; ?>-enabled">
							<input type="checkbox" name="jwuh_role_access[<?php echo $index; ?>][enabled]" id="jwuh-role-access-<?php echo $index; ?>-enabled" class="jwuh-role-access-enabled" value="1" <?php checked($role_access[$index]['enabled']); ?> />
							<?php echo $role['name']; ?>
						</label>
					</h4>
				</div>
				<div<?php if (empty($role_access[$index]['enabled'])) echo ' class="jwuh-hide"'; ?>>
					<table class="widefat">
						<thead>
							<tr>
								<th><?php _e('Main capabilities', 'userhierarchy'); ?></th>
								<td colspan="<?php echo strval(count($caps)); ?>">
									<?php foreach ($maincaps as $index2 => $cap) : ?>
										<label for="jwuh-role-capabilities-<?php echo $index; ?>-<?php echo $cap; ?>" class="jwuh-maincap">
											<input type="checkbox" name="jwuh-role-capabilities[<?php echo $index; ?>][<?php echo $cap; ?>]" id="jwuh-role-capabilities-<?php echo $index; ?>-<?php echo $cap; ?>" value="1" <?php checked($role_object->has_cap($cap)); ?> />
											<?php echo $cap; ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td></td>
								<?php foreach ($caps as $index2 => $cap) : ?>
									<th><abbr title="<?php echo esc_attr($cap); ?>"><?php echo preg_replace('/\s?users?\s?/i', '', ucwords(implode(' ', explode('_', $cap)))); ?></abbr></th>
								<?php endforeach; ?>
							</tr>
							<?php foreach ($roles as $index2 => $roleaccess) : ?>
								<tr>
									<th>
										<?php echo $roleaccess['name']; ?>
										<?php if (end($roles) != $roleaccess) : ?>
											<a href="#" class="jwuh-capset-push-down" title="<?php esc_attr_e('Copy permissions to role below', 'userhierarchy'); ?>"></a>
										<?php endif; ?>
										<a href="#" class="jwuh-capset-switch" title="<?php esc_attr_e('Toggle all permissions', 'userhierarchy'); ?>"></a>
									</th>
									<?php foreach ($caps as $index3 => $cap) : ?>
										<?php
										$input_id = 'jwuh-role-access-' . $index . '-roles-' . $index2 . '-' . $cap;
										$input_name = 'jwuh_role_access[' . $index . '][roles][' . $index2 . '][' . $cap . ']';
										?>
										<td>
											<label for="<?php echo $input_id; ?>" style="display: block; width: 100%; height: 100%;">
												<input type="checkbox" name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" value="1" <?php checked($role_access[$index]['roles'][$index2][$cap]); ?> />
											</label>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="5">
									<div class="jwuh-help"><?php printf(__('For each role, you can select which permissions %s users have', 'userhierarchy'), $role['name']); ?></div>
									<div class="jwuh-help"><?php printf(__('Select what operations (Assign, Edit, Delete and View) %s users can perform per role', 'userhierarchy'), $role['name']); ?></div>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}
	
	/**
	 * Output the hidden field for the role capabilities
	 */
	function section_role_caps()
	{
		?>
		<input type="hidden" name="jwuh_role_caps" value="<?php echo microtime(true); ?>" />
		<?php
	}
	
	/**
	 * Handle request
	 */
	public function handle_capabilities()
	{
		if ($_SERVER['REQUEST_METHOD'] != 'POST' || $_POST['option_page'] != 'jwuh_access' || $_POST['action'] != 'update') {
			return;
		}
		
		$caps = $_POST['jwuh-role-capabilities'];
		
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && is_array($caps) && !empty($caps)) {
			global $wp_roles;
			
			// Get roles
			$roles = $wp_roles->roles;
			
			$maincaps = JWUH_Roles::get_user_caps();
			
			foreach ($roles as $index => $role) {
				$role_object = get_role($index);
				
				foreach ($maincaps as $index2 => $cap) {
					if (isset($caps[$index][$cap]) && $caps[$index][$cap]) {
						$role_object->add_cap($cap);
					}
					else {
						$role_object->remove_cap($cap);
					}
				}
			}
		}
	}
	
	/**
	 * Output the menu page contents
	 */
	public function display()
	{
		?>
		<div class="wrap">
			<h2><?php _e('User Hierarchy', 'userhierarchy'); ?></h2>
			<form action="<?php echo admin_url('options.php'); ?>" method="post" id="jwuh-general-options">
				<?php settings_fields('jwuh_access'); ?>
				<?php do_settings_sections('userhierarchy-access'); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

}
?>