<?php


function quizinfo($attr) {
	        
			
	 $shortcode_atts = shortcode_atts ( array(
			'show' => '', //[score], [count], [pass], [rank], [timestamp], [pro_quizid], [points], [total_points], [percentage], [timespent] 
			'user_id' => '',			
			'quiz' => '',			
			'time' => '',	
			'format' => "F j, Y, g:i a"
			), $attr);
	extract($shortcode_atts);

	$time = (empty($time) && isset($_REQUEST['time']))? $_REQUEST['time']:$time;
	$show = (empty($show) && isset($_REQUEST['show']))? $_REQUEST['show']:$show;
	$user_id = (empty($user_id) && isset($_REQUEST['user_id']))? $_REQUEST['user_id']:$user_id;
	$quiz = (empty($quiz) && isset($_REQUEST['quiz']))? $_REQUEST['quiz']:$quiz;
	
	if(empty($user_id))
		$user_id = get_current_user_id();
		
	if(empty($quiz) || empty($user_id) || empty($show))
		return "";
	
	$quizinfo = get_user_meta($user_id, "_sfwd-quizzes", true);
	//return print_r($quizinfo,true);	
	foreach($quizinfo as $quiz_i) {
		if(isset($quiz_i['time']) && $quiz_i['time'] == $time && $quiz_i['quiz'] == $quiz) {
			$selected_quizinfo = $quiz_i;
			break;
		}
		if($quiz_i['quiz'] == $quiz)
		$selected_quizinfo2 = $quiz_i;
	}
	$selected_quizinfo = empty($selected_quizinfo)? $selected_quizinfo2:$selected_quizinfo;
	
	switch($show) {
		case "timestamp":
			$selected_quizinfo['timestamp'] = date($format, $selected_quizinfo['time']);
			break;
		case "percentage":
			if(empty($selected_quizinfo['percentage']))
			$selected_quizinfo['percentage'] = empty($selected_quizinfo['count'])? 0:$selected_quizinfo['score']*100/$selected_quizinfo['count'];
			break;
		case "pass":
			$selected_quizinfo['pass'] = !empty($selected_quizinfo['pass'])? __("Yes","learndash"):__("No","learndash");
			break;

		case "quiz_title":
			$quiz_post = get_post($quiz);
			if(!empty($quiz_post->post_title))
			$selected_quizinfo['quiz_title'] = $quiz_post->post_title;
			break;
		
		case "course_title":
			$course_id = learndash_get_setting($quiz, "course");
			$course = get_post($course_id);
			if(!empty($course->post_title))
			$selected_quizinfo['course_title'] = $course->post_title;
			break;
			
		case "timespent":
			$selected_quizinfo['timespent'] = isset($selected_quizinfo['timespent'])? learndash_seconds_to_time($selected_quizinfo['timespent']):"";
			break;	
			
	}

	if(isset($selected_quizinfo[$show]))
		return $selected_quizinfo[$show];
	else
		return "";
}
add_shortcode("quizinfo", "quizinfo");
?>