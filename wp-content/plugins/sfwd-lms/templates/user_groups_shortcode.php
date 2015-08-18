<?php
	if($has_admin_groups) {
		?>
		<b><?php _e("Group Leader in : ", "learndash"); ?></b><br>
		<?php 
		foreach($admin_groups as $group_id) {
			if(!empty($group_id)) {
				$group = get_post($group_id);
				?>
				<b><?php echo $group->post_title; ?></b>
				<p><?php echo trim($group->post_content); ?></p>
				<?php 
			}
		}
		?><br><br><?php 
	}
	
	if($has_user_groups) {
		?>
		<b><?php _e("Assigned Group(s) : ", "learndash"); ?></b><br>
		<?php
		foreach($user_groups as $group_id) {
			if(!empty($group_id)) {
				$group = get_post($group_id);
				?>
				<b><?php echo $group->post_title; ?></b>
				<p><?php echo trim($group->post_content); ?></p>
				<?php 
			}
		}
		?><br><br><?php 
	}