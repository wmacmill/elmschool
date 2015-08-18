<?php
    function learndash_show_enrolled_courses( $user ) {
		$courses = get_pages("post_type=sfwd-courses");
		$enrolled = array();
		$notenrolled = array();
    ?>
        <table class="form-table">
            <tr>
                <th> <h3><?php _e('Enrolled Courses', 'learndash'); ?></h3></th>
                <td>
					<ol>
					<?php 
						foreach($courses as $course) { 
								if(sfwd_lms_has_access($course->ID,  $user->ID)) { 
									$since = ld_course_access_from($course->ID,  $user->ID);
									$since = empty($since)? "":"Since: ".date("m/d/Y H:i:s", $since);
									if(empty($since)) {
										$since = learndash_user_group_enrolled_to_course_from($user->ID, $course->ID);
										$since = empty($since)? "":"Since: ".date("m/d/Y H:i:s", $since)." (Group Access)";
									}
									echo "<li><a href='".get_permalink($course->ID)."'>".$course->post_title."</a> ".$since."</li>";
									$enrolled[] = $course;
								}
								else
									$notenrolled[] = $course;
						}
					?>
					</ol>
				</td>
			</tr>
			<?php 
			if(current_user_can('manage_options')) { 
					?>
			<tr>			
				<th> <h3><?php _e("Enroll a Course", "learndash"); ?></h3></th>
				<td>
						<select name="enroll_course">
							<option value=''> -- Select a Course --</option>
							<?php foreach($notenrolled as $course) {
							echo "<option value='".$course->ID."'>".$course->post_title."</option>";
							} ?>
						</select>
				</td>
			</tr>
			<tr>			
				<th> <h3><?php _e("Unenroll a Course", "learndash"); ?></h3></th>
				<td>
						<select name="unenroll_course">
							<option value=''> -- Select a Course --</option>
							<?php foreach($enrolled as $course) {
							echo "<option value='".$course->ID."'>".$course->post_title."</option>";
							} ?>
						</select>
				</td>
			</tr>			
			<?php } ?>
        </table>
    <?php }
     
    function learndash_save_enrolled_courses( $user_id ) {
	
        if ( !current_user_can('manage_options'))
            return FALSE;
		
		$enroll_course = $_POST['enroll_course'];
		$unenroll_course = $_POST['unenroll_course'];
		
		if(!empty($enroll_course)) {
			$meta = ld_update_course_access($user_id, $enroll_course);
		}
		if(!empty($unenroll_course)) {
			$meta = ld_update_course_access($user_id, $unenroll_course, $remove = true);
		}
    }

    add_action( 'show_user_profile', 'learndash_show_enrolled_courses' );
    add_action( 'edit_user_profile', 'learndash_show_enrolled_courses' );
     
    add_action( 'personal_options_update', 'learndash_save_enrolled_courses' );
    add_action( 'edit_user_profile_update', 'learndash_save_enrolled_courses' );