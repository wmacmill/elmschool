<?php
//Ajax Email sending script
function learndash_group_emails(){
	if( isset($_POST['group']) && isset($_POST['text'])  && isset($_POST['sub']) )
	{
		if(empty($_POST['group']) || empty($_POST['text'])  || empty($_POST['sub']) )
		{
			echo __("Empty value", "learndash");
			exit;
		}
		require_once(ABSPATH . 'wp-includes/pluggable.php');
		$status = "";
		$er = learndash_get_groups_user_ids($_POST['group']);
		$sent = '';
		$notsent = '';
		foreach($er as $k=>$v){
			$user = get_userdata( $er[$k] );
			$email = $user-> user_email;
			$message = nl2br(stripcslashes($_POST['text']));
			$sub = $_POST['sub'];
			
			$headers  = 'MIME-Version: 1.0' . "\r\n";
		 	$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
		// old format	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers .= "From: ".$user->display_name." <".$user->user_email.">" ;
	
			$state = wp_mail($email, $sub, $message,$headers);	
			
			 if($state) 
				$sent .=   empty($sent)? $user->user_email:", ".$user->user_email;
			 else 
				$notsent .=   empty($notsent)? $user->user_email:", ".$user->user_email;
				
		}
		
		if(!empty($notsent))
			 echo "\n".__('Email could not be sent to: ','learndash') . $notsent;

		if(!empty($sent))
			 echo "\n".__('Email sent to: ','learndash') . $sent;
			 
		exit;
	}
}
	add_action("init", "learndash_group_emails");
	
	function learndash_groups_post_content() {
		$labels = array(
			'name'               => __( 'LearnDash Groups', 'learndash' ),
			'singular_name'      => __( 'LearnDash Group', 'learndash' ),
			'add_new'            => __( 'Add New', 'learndash' ),
			'add_new_item'       => __( 'Add New LearnDash Group' , 'learndash'),
			'edit_item'          => __( 'Edit LearnDash Group' , 'learndash'),
			'new_item'           => __( 'New LearnDash Group', 'learndash' ),
			'all_items'          => __( 'LearnDash Groups', 'learndash' ),
			'updated'          => __( 'LearnDash Group Updated.', 'learndash' ),
			'view_item'          => __( 'View LearnDash Group', 'learndash' ),
			'search_items'       => __( 'Search LearnDash Group' , 'learndash'),
			'not_found'          => __( 'No LearnDash Group found' , 'learndash'),
			'not_found_in_trash' => __( 'No LearnDash Group found in the Trash' , 'learndash'), 
			'parent_item_colon'  => '',
			'menu_name'          => __( 'LearnDash Groups', 'learndash' )
		);
		$capabilities = array(
					            'read_post' => 'read_group',
					            'publish_posts' => 'publish_groups',
					            'edit_posts' => 'edit_groups',
					            'edit_others_posts' => 'edit_others_groups',
					            'delete_posts' => 'delete_groups',
					            'delete_others_posts' => 'delete_others_groups',
					            'read_private_posts' => 'read_private_groups',
					            'delete_post' => 'delete_group',
					            'edit_published_posts'	=> 'edit_published_groups',
					            'delete_published_posts'	=> 'delete_published_groups',
					        );
		if(is_admin()) {
			$admin = get_role('administrator');
			if(!$admin->has_cap('edit_groups')) {
				foreach ($capabilities as $key => $cap) {
					$admin->add_cap($cap);
				}
			}
		}
		$args = array(
			'labels'        => $labels,
			'description'   => __('Holds LearnDash user Groups', 'learndash'),
			'public'        => false,
			'menu_position' => 10,
			'show_in_menu'	=> true,
			'supports'      => array( 'title', 'editor'), //, 'custom-fields', 'author'
			'has_archive'   => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'show_ui' => true,
            'capabilities' => $capabilities,
            'map_meta_cap' => true
		);
		$args = apply_filters("learndash_post_args_groups", $args);
		register_post_type( 'groups', $args );	
	}
	
	add_action("init", "learndash_groups_post_content");
	
	function learndash_group_updated_messages( $messages ) {
  global $post, $post_ID;

  $messages['groups'] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('LearnDash Group updated.', 'learndash'), esc_url( get_permalink($post_ID) ) ),
    2 => __('Custom field updated.', 'learndash'),
    3 => __('Custom field deleted.', 'learndash'),
    4 => __('LearnDash Group updated.', 'learndash'),
    /* translators: %s: date and time of the revision */
    5 => isset($_GET['revision']) ? sprintf( __('LearnDash Group restored to revision from %s', 'learndash'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
    6 => sprintf( __('Group published.', 'learndash'), esc_url( get_permalink($post_ID) ) ),
    7 => __('LearnDash Group saved.', 'learndash'),
    8 => sprintf( __('LearnDash Group submitted. ', 'learndash'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    9 => sprintf( __('LearnDash Group scheduled for: <strong>%1$s</strong>. ', 'learndash'),
      // translators: Publish box date format, see http://php.net/date
      date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
    10 => sprintf( __('Group draft updated.', 'learndash'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
  );

  return $messages;
}
add_filter( 'post_updated_messages', 'learndash_group_updated_messages' );
function learndash_add_group_admin_role() {
	$group_leader = get_role("group_leader");
	
	if(is_null($group_leader)) {
		$group_leader = add_role("group_leader", "Group Leader", array('read' => true));
	}
}

add_action("init", "learndash_add_group_admin_role");

add_action( 'add_meta_boxes', 'learndash_groups_add_custom_box' );

function learndash_groups_add_custom_box() {
    
	add_meta_box(
            'learndash_groups',
            __( 'LearnDash Group', 'learndash' ), //Change "LearnDash Group Users" to "LearnDash Group"
            'learndash_groups_page_box',
            'groups'
        );
}

function learndash_group_leaders() {
	$all_users = get_users("orderby=display_name&order=ASC");
	$all_group_leaders = array();
	foreach ($all_users as $user) {
		if(is_group_leader($user))
			$all_group_leaders[] = $user;
	}
	return $all_group_leaders;
}

/* Prints the box content */
function learndash_groups_page_box( $post ) {
	global $wpdb;
	$post_id = $post->ID;

	// Use nonce for verification
	wp_nonce_field( plugin_basename( __FILE__ ), 'learndash_groups_nonce' );

	// The actual fields for data entry
	// Use get_post_meta to retrieve an existing value from the database and use the value for the form
	$data = get_post_meta( $post->ID, $key = '_learndash_groups', $single = true );
	//$users = get_users("orderby=display_name&order=ASC");
	$sql = "SELECT * FROM $wpdb->users"; //Exclude Admins and Group Leaders
	$sql .= ' ORDER BY display_name ASC';
	$users = $wpdb->get_results( $sql );
	$all_group_leaders = learndash_group_leaders();// get_users("role=group_leader&orderby=display_name&order=ASC");

	$group_users = learndash_get_groups_user_ids($post_id);
	$group_leaders = learndash_get_groups_administrator_ids($post_id);
	$group_enrolled_courses = learndash_group_enrolled_courses($post_id);
	$ld_course_list = ld_course_list(array('array' => true));
	?>
		<script language="Javascript">
	function SelectAddRows(SS1,SS2)
	{
		var SelID='';
		var SelText='';
		// Move rows from SS1 to SS2 from bottom to top
		for (i=SS1.options.length - 1; i>=0; i--)
		{
			if (SS1.options[i].selected == true)
			{
				SelID=SS1.options[i].value;
				SelText=SS1.options[i].text;
				var newRow = new Option(SelText,SelID);
				SS2.options[SS2.length]=newRow;
			}
		}
	}
	function SelectRemoveRows(SS1)
	{
		var SelID='';
		var SelText='';
		// Move rows from SS1 to SS2 from bottom to top
		for (i=SS1.options.length - 1; i>=0; i--)
		{
			if (SS1.options[i].selected == true)
			{
				SS1.options[i]=null;
			}
		}
	}	
	function SelectAll(ID) {
		for (i=ID.options.length - 1; i>=0; i--)
		{
			ID.options[i].selected = true;
		}
	}
	function group_user_search() {
		SS1 = document.getElementById('learndash_group_users_search');
		SS_View = document.getElementById('learndash_group_users_view');
		searchfor = document.getElementById('search_group').value.toLowerCase();
		SS_View.options.length = 0;
		length = 0;
		for (i = 0; i < SS1.options.length; i++)
		{
			SelText=SS1.options[i].text;
			if(SelText.toLowerCase().search(searchfor) < 0 && searchfor.length > 0)
			{
				SS1.options[i].disabled = true;
			}
			else
			{
				SS1.options[i].disabled = false;
				length++;
				SS_View.options.length = length;
				SS_View.options[length-1].value = SS1.options[i].value;
				SS_View.options[length-1].text = SS1.options[i].text;
				SS_View.options[length-1].title = SS1.options[i].title;
				
			}
		}
	}
	</script>	
	<div id="learndash_groups_page_box" class="learndash_groups_page_box">
	<h2><?php _e("Enrolled Courses", 'learndash' ); ?> </h2> 
		<ol>
		<?php 
		foreach($group_enrolled_courses as $course_id) {
			$course = get_post($course_id);
			echo "<li ><a href='".get_permalink($course->ID)."'>".$course->post_title."</a> (<a href='".get_edit_post_link($course->ID)."'>".__("Edit")."</a>)</li>";
		}
		?>
		</ol>
	<h2><?php _e("Enroll a Course", 'learndash' ); ?> 
		 : <select name="learndash_group_enroll_course">
			<option value=''> -- Select a Course --</option>
			<?php foreach($ld_course_list as $course) {
			if(!in_array($course->ID, $group_enrolled_courses))
			echo "<option value='".$course->ID."'>".$course->post_title."</option>";
			} ?>
		</select>
	</h2>
	<h2><?php _e("Unenroll a Course", 'learndash' ); ?> 
		 : <select name="learndash_group_unenroll_course">
			<option value=''> -- Select a Course --</option>
			<?php 
			foreach($group_enrolled_courses as $course_id) {
			$course = get_post($course_id);
			echo "<option value='".$course->ID."'>".$course->post_title."</option>";
			} ?>
		</select>
	</h2> 

	<h2><?php _e("Group Leaders", 'learndash' ); ?> </h2> 
		<?php	if(!empty($all_group_leaders)) { ?>
		<table>
		<tr>
			<td  class="td_learndash_group_users_search">
			<br>
			<select multiple="multiple" id="learndash_group_leaders_search" class="learndash_group_leaders_search_multiple">
				<?php 
						foreach($all_group_leaders as $user) {
						$name = $user->display_name.' ('.$user->user_login.')';
						$val = $user->ID;
						?>
						<option value="<?php echo $val; ?>"  title="<?php echo $name; ?>"><?php echo $name; ?></option>
						<?php 
					}	?>
			<select>
			</td>
			<td align="center">
				<a href="#" onClick="SelectAddRows(document.getElementById('learndash_group_leaders_search'), document.getElementById('learndash_group_leaders')); SelectAll(document.getElementById('learndash_group_leaders')); return false;"><img src="<?php echo plugins_url("images/arrow_right.png", __FILE__); ?>" /></a><br>
				<a href="#" onClick="SelectRemoveRows(document.getElementById('learndash_group_leaders')); SelectAll(document.getElementById('learndash_group_leaders')); return false;"><img src="<?php echo plugins_url("images/arrow_left.png", __FILE__); ?>" /></a>
			</td>
			<td class="td_learndash_group_users_search">		
			<b><?php _e("Selected:", "learndash"); ?></b><br>
			<select multiple="multiple" id="learndash_group_leaders" name="learndash_group_leaders[]"  class="learndash_group_leaders">
				<?php 
						foreach($all_group_leaders as $user) {
						$name = $user->display_name.' ('.$user->user_login.')';
						$val = $user->ID;
						$selected = in_array($user->ID, $group_leaders)? 'SELECTED="SELECTED"':'';
							if($selected == 'SELECTED="SELECTED"'){ 
							?>
							<option value="<?php echo $val; ?>"<?php echo $selected; ?> title="<?php echo $name; ?>"><?php echo $name; ?></option>
							<?php 
							}
						}	?>
			<select>
			</td>
		</tr>
		</table>
		<?php 	}
				else
				_e("Please add some users with Group Leader role", "learndash");
		?>
		<br><br>
	<h2><?php _e("Assign Users", 'learndash' ); ?> </h2>
	
	
	<label for="search_group"><?php _e("Search Users:", "learndash"); ?>
		<input type="text" id="search_group" onChange="group_user_search();" onKeyUp="group_user_search();" onKeyPress="if(event.keyCode == 13) return false;" />
	</label>

	<br/><br/>
	<?php _e("<b>Instructions: </b> Hold CNTRL to select muliple users for this group, <br>and click the arrow to move to selected list of users.", "learndash"); ?>
	
	<br/>
		<table>
		<tr>
			<td class="td_learndash_group_users_search" ><br>
				<select multiple="multiple" id="learndash_group_users_search" class="learndash_group_users_search">
					<?php foreach($users as $user) { 
						$name = $user->display_name.' ('.$user->user_login.')';
						$val = $user->ID;
							?>
							<option value="<?php echo $val; ?>" title="<?php echo $name; ?>"><?php echo $name; ?></option>
							<?php 
					}?>
				<select>
				<select multiple="multiple" id="learndash_group_users_view" class="learndash_group_users_view">
					<?php foreach($users as $user) { 
						$name = $user->display_name.' ('.$user->user_login.')';
						$val = $user->ID;
						?>
						<option value="<?php echo $val; ?>" title="<?php echo $name; ?>"><?php echo $name; ?></option>
						<?php 
					}?>
				<select>				
			</td>
			<td align="center">
				<a href="#" onClick="SelectAddRows(document.getElementById('learndash_group_users_view'), document.getElementById('learndash_group_users')); SelectAll(document.getElementById('learndash_group_users')); return false;"><img src="<?php echo plugins_url("images/arrow_right.png", __FILE__); ?>" /></a><br>
				<a href="#" onClick="SelectRemoveRows(document.getElementById('learndash_group_users')); SelectAll(document.getElementById('learndash_group_users')); return false;"><img src="<?php echo plugins_url("images/arrow_left.png", __FILE__); ?>" /></a>
			</td>
			<td class="td_learndash_group_users_search">
				<b><?php _e("Selected:", "learndash"); ?></b><br>
				<select multiple="multiple" id="learndash_group_users" name="learndash_group_users[]" class="learndash_group_users">
					<?php foreach($users as $user) { 
						$name = $user->display_name.'('.$user->user_login.')';
						$val = $user->ID;
						$selected = in_array($user->ID, $group_users)? 'SELECTED="SELECTED"':'';
								if($selected == 'SELECTED="SELECTED"'){ 
								?>
								<option value="<?php echo $val; ?>"<?php echo $selected; ?> title="<?php echo $name; ?>"><?php echo $name; ?></option>
								<?php 
								}
						} ?>
				<select>
			</td>
		</tr>
		</table>
		
	</div>
	<?php 
}

add_action( 'save_post', 'learndash_groups_save_postdata' );

/* When the post is saved, saves our custom data */
function learndash_groups_save_postdata( $post_id ) {

  // verify if this is an auto save routine. 
  // If it is our form has not been submitted, so we dont want to do anything
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return;

  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times

  if ( !isset( $_POST['learndash_groups_nonce'] ) || !wp_verify_nonce( $_POST['learndash_groups_nonce'], plugin_basename( __FILE__ ) ) )
      return;

  
  // Check permissions
  if ( 'page' == $_POST['post_type'] ) 
  {
    if ( !current_user_can( 'edit_page', $post_id ) )
        return;
  }
  else
  {
    if ( !current_user_can( 'edit_post', $post_id ) )
        return;
  }
  
  if ( 'groups' != $_POST['post_type'] )
  	return;
  // OK, we're authenticated: we need to find and save the data

	global $wpdb;
	$group_users = learndash_get_groups_user_ids($post_id);
	$group_leaders = learndash_get_groups_administrator_ids($post_id);
	
	$learndash_group_leaders = isset($_POST['learndash_group_leaders'])? $_POST['learndash_group_leaders']:array();
	$learndash_group_users = isset($_POST['learndash_group_users'])? $_POST['learndash_group_users']:array();
	$learndash_group_enroll_course = isset($_POST['learndash_group_enroll_course'])? $_POST['learndash_group_enroll_course']:array();
	$learndash_group_unenroll_course = isset($_POST['learndash_group_unenroll_course'])? $_POST['learndash_group_unenroll_course']:array();
	if(is_numeric($learndash_group_enroll_course)) $learndash_group_enroll_course = array($learndash_group_enroll_course);
	if(is_numeric($learndash_group_unenroll_course)) $learndash_group_unenroll_course = array($learndash_group_unenroll_course);

	foreach($learndash_group_enroll_course as $course_id) {
		update_post_meta($course_id, "learndash_group_enrolled_".$post_id, time());
	}
	foreach($learndash_group_unenroll_course as $course_id) {
		delete_post_meta($course_id, "learndash_group_enrolled_".$post_id);
	}

	foreach($learndash_group_leaders as $ga) {
		if(!in_array($ga, $group_leaders)) {
			update_user_meta($ga, "learndash_group_leaders_".$post_id, $post_id);
		}
		else
		{
			foreach (array_keys($group_leaders, $ga, true) as $key) {
				unset($group_leaders[$key]);
			}
		}
	}
	foreach($group_leaders as $ga) {
		delete_user_meta($ga, "learndash_group_leaders_".$post_id, null);
	}
	
	
	foreach($learndash_group_users as $ga) {
		if(!in_array($ga, $group_users)) {
			update_user_meta($ga, "learndash_group_users_".$post_id, $post_id);
		}
		else
		{
			foreach (array_keys($group_users, $ga, true) as $key) {
				unset($group_users[$key]);
			}
		}
	}
	foreach($group_users as $ga) {
		delete_user_meta($ga, "learndash_group_users_".$post_id, null);
	}
	
}


function learndash_group_user_list($group_id) {
	$current_user = wp_get_current_user();
	if(empty($current_user->ID) || !$current_user->has_cap("group_leader"))
		return __("Please login as a Group Administrator", "learndash");

	global $wpdb;
	$users = learndash_get_groups_user_ids($group_id);
	
	if(!empty($users))
	{
		$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE `ID` IN (".implode(",",$users).")");
		if(!empty($users)) {
			?>
			<table cellspacing="0" class="wp-list-table widefat fixed groups_user_table">
			<thead>
			<tr>	
				<th class="manage-column column-sno " id="sno" scope="col" ><?php _e("S. No.", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Name", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Username", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Email", 'learndash'); ?></th>
				<th class="manage-column column-action" id="action" scope="col"><?php _e("Action", 'learndash'); ?></span></th>
			</tr>
			</thead>
			<tfoot>
			<tr>	
				<th class="manage-column column-sno " id="sno" scope="col" ><?php _e("S. No.", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Name", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Username", 'learndash'); ?></th>
				<th class="manage-column column-name " id="group" scope="col"><?php _e("Email", 'learndash'); ?></th>
				<th class="manage-column column-action" id="action" scope="col"><?php _e("Action", 'learndash'); ?></span></th>
			</tr>
			</tfoot>
			<tbody>
				<?php $sn = 1;
				foreach($users as $user) {
					$name = isset($user->display_name)? $user->display_name:$user->user_nicename;
				?>
				<tr>
					<td><?php echo $sn++; ?></td>
					<td><?php echo $name; ?></td>
					<td><?php echo $user->user_login; ?></td>
					<td><?php echo $user->user_email; ?></td>
					<td><a href="<?php echo admin_url("edit.php?post_type=sfwd-courses&page=group_admin_page&group_id=".$group_id."&user_id=".$user->ID); ?>"><?php _e("Report", "learndash"); ?></a></td>
				</tr>
				<?php
				}
				?>
			</table>
		<?php
		}
	}
	else
		return __("No users.", "learndash");
	
	
}
function learndash_group_enrolled_courses($group_id) {
	global $wpdb;
	if(is_numeric($group_id)) {
		$col =  $wpdb->get_col(" SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'learndash_group_enrolled_".$group_id."'");
		$ids = implode(",", $col);
		if(!empty($ids)) {
			$col = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND ID IN (".implode(",", $col).")");
			return $col;	
		}
	}

	return array();
}
function learndash_group_has_course($group_id, $course_id) {
	return get_post_meta($course_id, "learndash_group_enrolled_". $group_id, true) > 0;
}
function learndash_group_course_access_from($group_id, $course_id) {
	return get_post_meta($course_id, "learndash_group_enrolled_". $group_id, true);
}
function learndash_user_group_enrolled_to_course($user_id, $course_id) {
	$group_ids = learndash_get_users_group_ids($user_id);
	foreach($group_ids as $group_id) {
		if(learndash_group_has_course($group_id, $course_id))
			return true;
	}
	return false;
}
function learndash_user_group_enrolled_to_course_from($user_id, $course_id) {
	$group_ids = learndash_get_users_group_ids($user_id);
	$enrolled_from = time() + 10000;
	foreach($group_ids as $group_id) {
		$enrolled_from_temp = learndash_group_course_access_from($group_id, $course_id);
		if(!empty($enrolled_from_temp) && $enrolled_from_temp < $enrolled_from)
			$enrolled_from = $enrolled_from_temp;
	}
	if($enrolled_from <= time())
	{
		$user = get_userdata( $user_id );
		$user_registered = strtotime($user->user_registered);
		if($user_registered > $enrolled_from)
			return $user_registered;
		else
			return $enrolled_from;	
	}
	else
	return null;
}
function learndash_get_administrators_group_ids($user_id) {
	global $wpdb;
	if(is_numeric($user_id)) {
		if(user_can($user_id, "manage_options")) {
			return learndash_get_groups(true);
		}
		
		$col = $wpdb->get_col("SELECT meta_value FROM $wpdb->usermeta WHERE meta_key LIKE 'learndash_group_leaders_%' AND user_id = '$user_id'");
		$ids = implode(",", $col);
		if(!empty($col) && !empty($col[0])) {
			$col = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND ID IN (".implode(",", $col).")");
			return $col;	
		}
	}
	
	return array();
}
function learndash_get_groups($id_only = false) {
	$groups = get_posts("post_type=groups&posts_per_page=-1");
	if($id_only) {
		$group_ids = array();
		foreach ($groups as $group) {
			$group_ids[$group->ID] = $group->ID;
		}
		return $group_ids;
	}
	else
		return $groups;
}
function learndash_get_users_group_ids($user_id) {
	global $wpdb;
	if(is_numeric($user_id)) {
		$col = $wpdb->get_col("SELECT meta_value FROM $wpdb->usermeta WHERE meta_key LIKE 'learndash_group_users_%' AND user_id = '$user_id'");
		$ids = implode(",", $col);
		if(!empty($col) && !empty($col[0])) {
			$col = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND ID IN (".implode(",", $col).")");
			return $col;	
		}
	}
	
	return array();
}
function learndash_get_groups_user_ids($group_id) {
	global $wpdb;
	if(is_numeric($group_id))
	return $wpdb->get_col(" SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'learndash_group_users_".$group_id."' AND meta_value = '".$group_id."'");
	else 
	return array();
}
function learndash_get_groups_administrator_ids($group_id) {
	global $wpdb;
	if(is_numeric($group_id))
	return $wpdb->get_col(" SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'learndash_group_leaders_".$group_id."' AND meta_value = '".$group_id."'");
	else 
	return array();
}
add_shortcode("learndash_group_user_list","learndash_group_user_list");


add_action('admin_menu', 'learndash_group_admin_menu', 1);
function learndash_group_admin_menu() {
	add_submenu_page("admin.php", __("Group Administration","learndash"), __("Group Administration","learndash") ,'group_leader','group_admin_page', 'learndash_group_admin_menu_page');
}

function learndash_group_admin_menu_page()
{

	$current_user = wp_get_current_user();
	if(empty($current_user->ID) || !$current_user->has_cap("group_leader"))
		die(__("Please login as a Group Administrator", "learndash"));

	global $wpdb;
	$group_ids = learndash_get_administrators_group_ids($current_user->ID);
	
	if(!isset($_GET['group_id']) || !in_array($_GET['group_id'], $group_ids)) {
	?>
	<div class="wrap">
	<h2><?php _e("Group Administration", "learndash"); ?></h2>
	
	<table cellspacing="0" class="wp-list-table widefat fixed groups_table">
	<thead>
	<tr>	
		<th class="manage-column column-sno " id="sno" scope="col"><?php _e("S. No.", 'learndash'); ?></th>
		
		<th class="manage-column column-group " id="group" scope="col"><?php _e("Group", 'learndash'); ?></th>
		
		<th class="manage-column column-action" id="action" scope="col"><?php _e("Action", 'learndash'); ?></span><span class="sorting-indicator"></span></th>
	</tr>
	</thead>
	<tfoot>
	<tr>	
		<th class="manage-column column-sno " id="sno" scope="col"><?php _e("S. No.", 'learndash'); ?></th>
		
		<th class="manage-column column-group " id="group" scope="col"><?php _e("Group", 'learndash'); ?></th>
		
		<th class="manage-column column-action" id="action" scope="col"><?php _e("Action", 'learndash'); ?></span><span class="sorting-indicator"></span></th>
	</tr>
	</tfoot>
	<tbody>
		<?php $sn = 1;
		foreach($group_ids as $group_id) {
			$group = get_post($group_id);
		?>
		<tr>
			<td><?php echo $sn++; ?></td>
			<td><?php echo $group->post_title; ?></td>
			<td><a href="<?php echo admin_url("edit.php?post_type=sfwd-courses&page=group_admin_page&group_id=".$group_id); ?>"><?php _e("List Users", "learndash"); ?></a> | <a href="<?php echo admin_url('edit.php?post_type=sfwd-courses&page=group_admin_page&action=sfp_update_module&nonce-sfwd='.wp_create_nonce('sfwd-nonce').'&page_options=sfp_home_description&courses_export_submit=Export&group_id='.$group_id); ?>"><?php _e("Export Progress", "learndash"); ?></a> | <a href="<?php echo admin_url('edit.php?post_type=sfwd-courses&page=group_admin_page&action=sfp_update_module&nonce-sfwd='.wp_create_nonce('sfwd-nonce').'&page_options=sfp_home_description&quiz_export_submit=Export&group_id='.$group_id); ?>"><?php _e("Export Results", "learndash"); ?></a><?php do_action("learndash_group_admin_page_actions", $group_id); ?></td>
		</tr>
		<?php
		}
		?>
	<tbody>
	</table>
	</div>
	<?php
	}
	else
	{
		if(!isset($_GET['user_id'])) {
		$group_id = $_GET['group_id'];
		$group = get_post($group_id);
		?>
		<div class="wrap">
		<h2><?php echo __("Group Administration", "learndash").": ".$group->post_title; ?> <small>| <a href="<?php echo admin_url("edit.php?post_type=sfwd-courses&page=group_admin_page"); ?>"><?php echo __("Back", "learndash"); ?></a></small></h2>
		<p>
			<?php echo $group->post_content; ?>
		</p>
		<?php
		echo learndash_group_user_list($group_id);
		?>
		</div>
			<!-- Email Group feature below the Group Table (on the Group Leader page) -->
			<div id="learndash_groups_page_box">
			<br><br>
			<h2><?php _e("Email Users", "learndash"); ?></h2>
			<br/>
			<label for="email"><b><?php _e('Email Subject:', 'learndash'); ?></b><br/>
			
			<input id="group_email_sub" rows="5" class="group_email_sub"/>
			
			</label>
			<br/><br/>
			<label for="text"><b><b><?php _e('Email Message:', 'learndash'); ?></b><br/>
			<div class="groupemailtext" ><?php wp_editor('','groupemailtext',array('media_buttons' => true, 'wpautop' => true)); ?></div>
			</label>
			<br/>
			<button id="email_group" type="button"><?php _e('Send', 'learndash'); ?></button>
			<br/><br/><br/><br/><br/>
			</div>
			<script>
			jQuery(function($){
				var sending = 0;
				$("#email_group").click(function(){
					tinyMCE.triggerSave();
					
					$("#email_group").html("<?php _e('Sending...', 'learndash'); ?>");
					if(sending == 1) {
						alert("<?php _e('Please Wait', 'learndash'); ?>");
						return;
					}
					
					sending = 1;
					var gid = <?php echo $group_id ?>;
					var txt = $('#groupemailtext').val();
					var sub = $('#group_email_sub').val();

					$.post( 
								 "",
								{ group: gid,
						  text: txt,
						  sub: sub
						},
								function(data) {
									alert(data);
									$("#email_group").html("<?php _e('Send', 'learndash'); ?>");
									sending = 0;
									tinyMCE.get('groupemailtext').setContent('');
									$('#group_email_sub').val('');
								}

						);
				});
			});
			</script>
		
		<?php
		}
		else
		{
			$user_id = $_GET['user_id'];
			$group_id = $_GET['group_id'];
			$group_user_ids = learndash_get_groups_user_ids($group_id);
			$user = get_user_by("id",$user_id);
			
			?>
			<div class="wrap">
			<h2><?php echo __("Group Administration", "learndash").": ".$user->display_name; ?> <small>| <a href="<?php echo admin_url("edit.php?post_type=sfwd-courses&page=group_admin_page&group_id=".$group_id); ?>"><?php echo __("Back", "learndash"); ?></a></small></h2>
			<?php
			if(in_array($user_id, $group_user_ids)) {
				echo learndash_course_info_shortcode(array('user_id' => $user_id));
			}
			?>
			</div>
			<?php
		}
	}
	
}
function is_group_leader($user) {
    if(is_numeric($user))
    $user_id = $user;
    else
    $user_id = @$user->ID;
    
     return is_numeric($user_id) && user_can($user_id, "group_leader") && !user_can($user_id, "manage_options");
}
function learndash_is_group_leader_of_user($group_leader_id, $user_id) {
	$admin_groups = learndash_get_administrators_group_ids($group_leader_id);
	$has_admin_groups = !empty($admin_groups) && is_array($admin_groups) && !empty($admin_groups[0]);
	foreach ($admin_groups as $group_id) {	
		$learndash_is_user_in_group = learndash_is_user_in_group($user_id, $group_id);
		if($learndash_is_user_in_group)
			return true;
	}
	return false;
}
function learndash_is_user_in_group($user_id, $group_id) {
	return get_user_meta($user_id, "learndash_group_users_".$group_id, true);
}
function learndash_user_groups($attr) {
	$shortcode_atts = shortcode_atts ( array(
		'user_id' => ''
					), $attr);
	extract($shortcode_atts);
		
	if(empty($user_id))
		$user_id = get_current_user_id();
	
	if(empty($user_id))
		return '';
		
	$admin_groups = learndash_get_administrators_group_ids($user_id);
	$user_groups = learndash_get_users_group_ids($user_id);
	$has_admin_groups = !empty($admin_groups) && is_array($admin_groups) && !empty($admin_groups[0]);
	$has_user_groups = !empty($user_groups) && is_array($user_groups) && !empty($user_groups[0]);

	return SFWD_LMS::get_template('user_groups_shortcode', array(
				'admin_groups' => $admin_groups,
				'user_groups' => $user_groups,
				'has_admin_groups' => $has_admin_groups,
				'has_user_groups' => $has_user_groups
			));
}
add_shortcode("user_groups", "learndash_user_groups");

add_action('delete_post', 'learndash_delete_group', 10);
function learndash_delete_group($pid) {
  global $wpdb;
  if (!empty($pid) && is_numeric($pid)) {
    $wpdb->delete($wpdb->usermeta, array("meta_key" => "learndash_group_users_".$pid, "meta_value" => $pid));
	$wpdb->delete($wpdb->usermeta, array("meta_key" => "learndash_group_leaders_".$pid, "meta_value" => $pid));
  }
  return true;
}

function ld_update_group_access($user_id, $group_id, $remove = false) {
	if($remove) {
			delete_user_meta($user_id, "learndash_group_users_".$group_id);
			do_action("ld_removed_group_access", $user_id, $group_id);
	}
	else
	{
			update_user_meta($user_id, "learndash_group_users_".$group_id, $group_id);		
			do_action("ld_added_group_access", $user_id, $group_id);
	}
}
