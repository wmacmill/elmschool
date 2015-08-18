<?php

class LearnDash_Course_Info_Widget extends WP_Widget {

	function LearnDash_Course_Info_Widget() {
		$widget_ops = array('classname' => 'widget_ldcourseinfo', 'description' => __('LearnDash - Course attempt and score information of users. Visible only to users logged in.', 'learndash'));
		$control_ops = array();//'width' => 400, 'height' => 350);
		$this->WP_Widget('ldcourseinfo', __('Course Information', 'learndash'), $widget_ops, $control_ops);
	}

	function widget( $args, $instance ) {

		extract($args);
		$title = apply_filters( 'widget_title', empty($instance['title']) ? '' : $instance['title'], $instance );

		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return;
		
			$user_id = $current_user->ID;
		}	
		
		$courseinfo = learndash_course_info($user_id);
		
		if(empty($courseinfo))
		return;
		
		echo $before_widget;
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; } 
		
		echo $courseinfo;
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = strip_tags($instance['title']);
		//$text = format_to_edit($instance['text']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'learndash'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<?php
	}
}

class LearnDash_Course_Navigation_Widget extends WP_Widget {

	function LearnDash_Course_Navigation_Widget() {
		$widget_ops = array('classname' => 'widget_ldcoursenavigation', 'description' => __('LearnDash - Course Navigation. Shows lessons and topics on the current course.', 'learndash'));
		$control_ops = array();//'width' => 400, 'height' => 350);
		$this->WP_Widget('widget_ldcoursenavigation', __('Course Navigation', 'learndash'), $widget_ops, $control_ops);
	}

	function widget( $args, $instance ) {
		global $post;
		
		if(empty($post->ID) || !is_single())
		return;
		
		$course_id = learndash_get_course_id($post->ID);
		if(empty($course_id))
		return;
		
		extract($args);
		$title = apply_filters( 'widget_title', empty($instance['title']) ? '' : $instance['title'], $instance );

				
		echo $before_widget;
		
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; } 
		
		learndash_course_navigation($course_id);
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = strip_tags($instance['title']);
		//$text = format_to_edit($instance['text']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'learndash'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<?php
	}
}
function learndash_course_navigation($course_id) {
	$course = get_post($course_id);
	
	if(empty($course->ID) || $course_id != $course->ID)
	return;
	
	$course = get_post($course_id);
	if(empty($course->ID) || $course->post_type != "sfwd-courses")
		return;

	$course_settings = learndash_get_setting($course);
	$lessons = learndash_get_course_lessons_list($course);

	include(SFWD_LMS::get_template('course_navigation_widget', array(
				'course_id' => $course_id,
				'course' => $course,
				'lessons' => $lessons,
			), null, true)); 
}
add_action('widgets_init', create_function('', 'return register_widget("LearnDash_Course_Navigation_Widget");'));

function learndash_course_navigation_admin($course_id) {
	$course = get_post($course_id);
	
	if(empty($course->ID) || $course_id != $course->ID)
	return;
	
	$course = get_post($course_id);
	if(empty($course->ID) || $course->post_type != "sfwd-courses")
		return;

	$course_settings = learndash_get_setting($course);
	$lessons = learndash_get_course_lessons_list($course);

	include(SFWD_LMS::get_template('course_navigation_admin', array(
				'course_id' => $course_id,
				'course' => $course,
				'lessons' => $lessons,
			), null, true)); 
}
add_action( 'add_meta_boxes', 'learndash_course_navigation_admin_box');
function learndash_course_navigation_admin_box() {
	$post_types = array("sfwd-courses", "sfwd-lessons", "sfwd-quiz", "sfwd-topic");
	foreach ($post_types as $post_type) {
		add_meta_box( 
			'learndash_course_navigation_admin_meta',
			__( 'Associated Content', 'learndash' ),
			'learndash_course_navigation_admin_box_content',
			$post_type,
			'side',
			'high'
		);
	}
}
function learndash_course_navigation_admin_box_content() {
	$course_id = learndash_get_course_id(@$_GET['post']);

	if(empty($course_id))
	return;
	learndash_course_navigation_admin($course_id);
}
function learndash_course_info($user_id){
	return SFWD_LMS::get_course_info($user_id);
}
add_action('widgets_init', create_function('', 'return register_widget("LearnDash_Course_Info_Widget");'));

function learndash_course_info_shortcode($atts){
	
	if(isset($atts['user_id']))
	$user_id = $atts['user_id'];
	else
	{
	$current_user = wp_get_current_user();

	if(empty($current_user->ID))
	return;
	
	$user_id = $current_user->ID;
	}	
	return SFWD_LMS::get_course_info($user_id);
}
add_shortcode('ld_course_info', 'learndash_course_info_shortcode');

function learndash_profile($atts){
	
	if(isset($atts['user_id']))
	$user_id = $atts['user_id'];
	else
	{
	$current_user = wp_get_current_user();

	if(empty($current_user->ID))
	return;
	
	$user_id = $current_user->ID;
	}
	$user_courses = ld_get_mycourses($user_id);
	if(empty($current_user))
	$current_user = get_user_by("id", $user_id);
	$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
	$quiz_attempts_meta = empty($usermeta) ?  false : $usermeta;
	$quiz_attempts  = array();
	if(!empty($quiz_attempts_meta))
	foreach($quiz_attempts_meta as $quiz_attempt) {
		$c = learndash_certificate_details($quiz_attempt['quiz'], $user_id);
		$quiz_attempt['post'] = get_post( $quiz_attempt['quiz'] );
		$quiz_attempt["percentage"]  = !empty($quiz_attempt["percentage"])? $quiz_attempt["percentage"]:(!empty($quiz_attempt["count"])? $quiz_attempt["score"]*100/$quiz_attempt["count"]:0  );

		if($user_id == get_current_user_id() && !empty($c["certificateLink"]) && ((isset($quiz_attempt['percentage']) && $quiz_attempt['percentage'] >= $c["certificate_threshold"] * 100)))
		$quiz_attempt['certificate'] = $c; 
		$quiz_attempts[learndash_get_course_id($quiz_attempt['quiz'])][] = $quiz_attempt;
	}
	return SFWD_LMS::get_template("profile", array(
								"user_id" => $user_id,
								"quiz_attempts" => $quiz_attempts,
								"current_user" => $current_user,
								"user_courses" => $user_courses

								));
}
add_shortcode('ld_profile', 'learndash_profile');