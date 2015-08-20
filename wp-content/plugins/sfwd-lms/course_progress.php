<?php

function learndash_mark_complete($post) { 
	$current_user = wp_get_current_user(); 
	$userid = $current_user->ID;
	if(isset($_POST['sfwd_mark_complete']) && isset($_POST['post']) && $post->ID == $_POST['post'])
	{	
		return "";	
	}
	
	if($post->post_type == "sfwd-lessons") {
		$progress = learndash_get_course_progress(null, $post->ID);
		if(!empty($progress['this']->completed))
			return '';
			
		if(!empty($progress['prev']) && empty($progress['prev']->completed) && learndash_lesson_progression_enabled())
		{
			return '';
		}
		
		if(!learndash_lesson_topics_completed($post->ID))
			return '';
	}
	if($post->post_type == "sfwd-topic") {
		$progress = learndash_get_course_progress(null, $post->ID);
		if(!empty($progress['this']->completed))
			return '';
			
		if(!empty($progress['prev']) && empty($progress['prev']->completed) && learndash_lesson_progression_enabled())
		{
			return '';
		}
		if(learndash_lesson_progression_enabled()) {
			$lesson_id = learndash_get_setting($post, "lesson");
			$lesson = get_post($lesson_id);
			if(!is_previous_complete($lesson))
				return ''; 
		}
	}
	
		$timeval = learndash_forced_lesson_time();
		
		if(!empty($timeval)) {
			$time_sections = explode(" ", $timeval);
			$h = $m = $s = 0;
			foreach($time_sections as $k => $v) {
				$value = trim($v);
				
				if(strpos($value, "h"))
				$h = intVal($value);
				else if(strpos($value, "m"))
				$m = intVal($value);
				else if(strpos($value, "s"))
				$s = intVal($value);
			}
			$time = $h * 60 * 60 + $m * 60 + $s;
			
			if($time == 0)
			$time = (int) $timeval;
		}
		if(lesson_hasassignments($post))
		{
				$ret = "
					<table>
					<tr> <u>".__("Upload Assignment", "learndash")."</u></tr>
					<tr>
					<td>
					<form name='uploadfile' id='uploadfile_form' method='POST' enctype='multipart/form-data' action='' accept-charset='utf-8' >
					<input type='file' name='uploadfiles[]' id='uploadfiles' size='35' class='uploadfiles' />
					<input type='hidden' value='".$post->ID."' name='post'/>
					<input class='button-primary' type='submit' name='uploadfile' id='uploadfile_btn' value='".__("Upload", "learndash")."'  />
					</form>
					</td>
					</tr>
					</table>
					";
				return $ret;
		}	
		else
		if(empty($time)){

			$return = "
					<form id='sfwd-mark-complete' method='post' action=''>
					<input type='hidden' value='".$post->ID."' name='post'/>
					<input type='submit' value='" . __('Mark Complete', 'learndash') . "' name='sfwd_mark_complete'/>
					</form>
					";
		}
		else
		{ //Forced Timer
		$return = '<script>
					var learndash_forced_lesson_time = '.$time.' ;
					var learndash_timer_var = setInterval(function(){learndash_timer()},1000);
					</script>
					<style>
					 input#learndash_mark_complete_button[disabled] {color: #aaa;}
					 </style>
					';
		$return .= "<form id='sfwd-mark-complete' method='post' action=''>
						<input type='hidden' value='".$post->ID."' name='post'/>
						<input id='learndash_mark_complete_button' type='submit' value='" . __('Mark Complete', 'learndash') . "' name='sfwd_mark_complete' DISABLED/>
					</form>
					<span id='learndash_timer'></span>
					";
		}
		return apply_filters("learndash_mark_complete", $return, $post);
}
function learndash_lesson_topics_completed($lesson_id, $mark_lesson_complete = false) {
	$topics = learndash_get_topic_list($lesson_id);
	
	if(empty($topics[0]->ID))
		return true;
	
	$progress = learndash_get_course_progress(null, $topics[0]->ID);
	
	if(empty($progress['posts']) || !is_array($progress['posts']))
		return false;
		
	foreach($progress['posts'] as $topic)
	{
		if(empty($topic->completed))
			return false;
	}
	
	if($mark_lesson_complete) {
		$user_id = get_current_user_id();
		learndash_process_mark_complete(null, $lesson_id);	
	}
	//learndash_get_next_lesson_redirect();
	return true;
}
function learndash_mark_complete_process($post = null) {
	
	if(empty($post)) {
		global $post;
	}

		
	if(isset($_POST['sfwd_mark_complete']) && isset($_POST['post']))
	{
		if(empty($post) || empty($post->ID)) {
			$post = get_post();
			if(empty($post) || empty($post->ID)) {
				return;
			}
			
		}
		if(isset($_POST['userid'])){
			$userid = $_POST['userid'];
		}
		else{
			$userid = null;
		}
		//$post = get_post($_POST['post']);
		$return = learndash_process_mark_complete($userid, $_POST['post']);
		/*if($post->post_type == "sfwd-topic") {
			$lesson_id = learndash_get_setting($post, "lesson");
			learndash_lesson_topics_completed($lesson_id, true);
		}*/
		if($return)
		$nextlessonredirect = learndash_get_next_lesson_redirect();
		else
		$nextlessonredirect = get_permalink();
		if(!empty($nextlessonredirect))
		{
			$nextlessonredirect = apply_filters("learndash_completion_redirect", $nextlessonredirect, $_POST['post']);
			wp_redirect($nextlessonredirect);
			exit;
		}
	}
}
add_action('wp', 'learndash_mark_complete_process');
function learndash_get_course_url($id = null) {
	if(empty($id))
	$id = learndash_get_course_id();

	return get_permalink($id);
}
function learndash_get_next_lesson_redirect($post = null) {
	if(empty($post->ID))
	global $post;
	$next = learndash_next_post_link('', true, $post);

	if(!empty($next))
		$link = $next;
	else {
		if($post->post_type == "sfwd-topic")
		{
			$lesson_id = learndash_get_setting($post, "lesson");
			$link = get_permalink($lesson_id);
		}
		else {
			$course_id = learndash_get_course_id($post);
			$link = learndash_next_global_quiz(true, null, $course_id);
		}
	}
	if(!empty($link))
	{
		//echo '<meta HTTP-EQUIV="REFRESH" content="0; url='.$link.'">';
		$link = apply_filters("learndash_completion_redirect", $link, @$post->ID);
		wp_redirect($link);
		exit;
	}
	else
	{
		return "";
	}
}
function learndash_quiz_redirect() {
	global $post;
	$current_user = wp_get_current_user();
	$user_id = $current_user->ID;

	if(!empty($_GET['quiz_redirect']) && !empty($_GET['quiz_id']) &&  !empty($_GET['quiz_type'])  &&  !empty($_GET['course_id']) && $_GET['quiz_type'] == 'global')
	{
		$quiz_id = $_GET['quiz_id'];
		$can_attempt_again = learndash_can_attempt_again($user_id, $quiz_id);
		if($can_attempt_again)
		$link = learndash_next_global_quiz();
		else
		$link = learndash_next_global_quiz(true, null, null, array($quiz_id));
		
		learndash_update_completion($user_id);			

		$link = apply_filters("learndash_completion_redirect", $link, $quiz_id);
		wp_redirect($link);
		exit;
	}
	else
	if(!empty($_GET['quiz_redirect']) && !empty($_GET['quiz_id']) &&  !empty($_GET['quiz_type'])  &&  !empty($_GET['lesson_id']) && $_GET['quiz_type'] == 'lesson')
	{
		$quiz_id = $_GET['quiz_id'];
		$lesson_id = $_GET['lesson_id'];
		$can_attempt_again = learndash_can_attempt_again($user_id, $quiz_id);

		if($can_attempt_again)
		{
			$link = learndash_next_lesson_quiz(true, $user_id, $lesson_id, null);
		}
		else
		$link = learndash_next_lesson_quiz(true, $user_id, $lesson_id, array($quiz_id ));	
		
		if(empty($link))
		$link = learndash_next_post_link('', true);
		
		if(empty($link))
		{
			$post = get_post($lesson_id);
			if($post->post_type == "sfwd-topic")
			{	
				$lesson = learndash_get_setting($post, "lesson");
				$link = get_permalink($lesson);
			}
			else
			$link = learndash_next_global_quiz();	
		}
		
		learndash_update_completion($user_id);
		if(!empty($link))
		{
			$link = apply_filters("learndash_completion_redirect", $link, $quiz_id);
			wp_redirect($link);
			exit;
		}
	}
}
add_action('wp', 'learndash_quiz_redirect');
function learndash_can_attempt_again($user_id, $quiz_id) {
	$quizmeta = get_post_meta( $quiz_id, '_sfwd-quiz' , true);

	$repeats = $quizmeta['sfwd-quiz_repeats'];
	$repeats = apply_filters("learndash_allowed_repeats", $repeats, $user_id, $quiz_id);

	if(empty($repeats))
	return true;
	
	$quiz_results = get_user_meta($user_id, '_sfwd-quizzes', true);

	$count = 0;
	if(!empty($quiz_results))
	foreach($quiz_results as $quiz)
	{
		if($quiz['quiz'] == $quiz_id)
		$count++;
	}

	if($repeats > $count - 1)
	return true;
	else 
	return false;
}		
function is_previous_complete($post) {
	$progress = learndash_get_course_progress(null, $post->ID);

	if(empty($progress))
	return 1;
	
	if(!empty($progress['prev']) && empty($progress['prev']->completed))
	return 0;
	else
	return 1;
}
function learndash_process_mark_complete($user_id = null, $postid = null, $onlycalculate = false) {
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}
		else
		$current_user = get_user_by("id", $user_id);
		$post = get_post($postid);
		
		if(!$onlycalculate) {
			$process_completion = apply_filters("learndash_process_mark_complete", true, $post, $current_user);
			if(!$process_completion)
				return false;
		}
		
		if($post->post_type == "sfwd-topic")
		{
			$lesson_id = learndash_get_setting($post, "lesson");
			//$lesson_topics = learndash_get_topic_list($lesson_id);
		}
		$lessons = learndash_get_lesson_list($postid);
		$course_id = learndash_get_course_id($postid);

		if(empty($course_id))
		return false;
		
		if(has_global_quizzes($postid))
		$globalquiz = 1;
		else
		$globalquiz = 0;
		
		if($globalquiz && is_all_global_quizzes_complete($user_id, $postid))
			$globalquizcompleted = 1;
		else
			$globalquizcompleted = 0;
		
		$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
		if(empty($course_progress[$course_id]))
			$course_progress[$course_id]= array('lessons' => array(),'topics' => array());
		if(empty($course_progress[$course_id]['lessons']))
			$course_progress[$course_id]['lessons'] = array();
		if(empty($course_progress[$course_id]['topics']))
			$course_progress[$course_id]['topics'] = array();
		if($post->post_type == "sfwd-topic" && empty($course_progress[$course_id]['topics'][$lesson_id]))
			$course_progress[$course_id]['topics'][$lesson_id] = array();
		
		if(!$onlycalculate && $post->post_type == "sfwd-lessons" && empty($course_progress[$course_id]['lessons'][$postid])) {
			$course_progress[$course_id]['lessons'][$postid] = 1;
			$lesson_completed = true;
		}
		if(!$onlycalculate && $post->post_type == "sfwd-topic" && empty($course_progress[$course_id]['topics'][$lesson_id][$postid])) {
			$course_progress[$course_id]['topics'][$lesson_id][$postid] = 1;
			$topic_completed = true;
		}
		$completed_old = isset($course_progress[$course_id]['completed'])? $course_progress[$course_id]['completed']:0;
		$course_progress[$course_id]['completed'] = count($course_progress[$course_id]['lessons']) + $globalquizcompleted;
		$course_progress[$course_id]['total'] = count($lessons) + $globalquiz;

		update_user_meta( $user_id, '_sfwd-course_progress', $course_progress);		
		if(!empty($topic_completed)) {
			// Topic Completed
			do_action('learndash_topic_completed', array( 'user' => $current_user, 'course' => get_post($course_id), 'lesson' => get_post($lesson_id), 'topic' => $post,  'progress' => $course_progress)); 
		}
		
		if(!empty($lesson_completed)) {
			// Lesson Completed
			do_action('learndash_lesson_completed', array( 'user' => $current_user, 'course' => get_post($course_id), 'lesson' => $post,  'progress' => $course_progress)); 
		}
		if($course_progress[$course_id]['completed'] > $completed_old && $course_progress[$course_id]['total'] == $course_progress[$course_id]['completed'])
		{ // Course Completed
			do_action('learndash_course_completed', array( 'user' => $current_user, 'course' => get_post($course_id), 'progress' => $course_progress)); 
		}
		
		return true;
}
function learndash_update_completion($user_id = null, $postid = null) {
		if(empty($postid))
		{
			global $post;
			$postid = $post->ID;
		}
		learndash_process_mark_complete($user_id, $postid, true);
}
function learndash_is_quiz_notcomplete($user_id = null, $quizes = null) {
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}
		$quiz_results = get_user_meta($user_id, '_sfwd-quizzes', true); 

		if(!empty($quiz_results))
		foreach($quiz_results as $quiz) {

			if(!empty($quizes[$quiz['quiz']]))
			{
				if(isset($quiz['pass']))
				{
					$pass = ($quiz['pass'] == 1)? 1:0;
				}
				else
				{
					$quizmeta = get_post_meta($quiz['quiz'], '_sfwd-quiz', true);
					$passingpercentage = intVal($quizmeta['sfwd-quiz_passingpercentage']);
					$pass = (!empty($quiz["count"]) && $quiz["score"]*100/$quiz["count"] >= $passingpercentage)? 1:0;		
				}
					if($pass)
					unset($quizes[$quiz['quiz']]);
			}
		}

		if(empty($quizes))
		return 0;
		else
		return 1;
}
function learndash_get_course_progress($user_id = null, $postid = null) {
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return null;
			
			$user_id = $current_user->ID;
		}
		$course_id = learndash_get_course_id($postid);
		$lessons = learndash_get_lesson_list($course_id);
		$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
		$this_post = get_post($postid);
		if(empty($course_progress))
		{
			learndash_update_completion($user_id, $postid);
			$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
		}
		if($this_post->post_type == "sfwd-lessons") {
			$posts = learndash_get_lesson_list($postid);
			if(empty($course_progress) || empty($course_progress[$course_id]['lessons']))
			$completed_posts = array();
			else
			$completed_posts = $course_progress[$course_id]['lessons'];
		}
		else if($this_post->post_type == "sfwd-topic")
		{
			$lesson_id = learndash_get_setting($this_post, "lesson");
			$posts = learndash_get_topic_list($lesson_id);
			if(empty($course_progress) || empty($course_progress[$course_id]['topics'][$lesson_id]))
			$completed_posts = array();
			else
			$completed_posts = $course_progress[$course_id]['topics'][$lesson_id];
		}
		
		$temp = $prev_p = $next_p = $this_p = '';

		if(!empty($posts))
		foreach($posts as $k=>$post)
		{
			if(!empty($completed_posts[$post->ID]))
			$posts[$k]->completed = 1;
			else
			$posts[$k]->completed = 0;
			
			if($post->ID == $postid)
			{
				$this_p = $post;
				$prev_p = $temp;
			}
			
			if(!empty($temp->ID) && $temp->ID == $postid)
			$next_p = $post;
			
			$temp = $post;
		}
		return array('posts' => $posts, 'this' => $this_p, 'prev' => $prev_p, 'next' => $next_p);
}
function learndash_is_lesson_notcomplete($user_id = null, $lessons)
{		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}



		$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 

		if(!empty($lessons))
		foreach($lessons as $lesson=>$v)
		{
			$course_id = learndash_get_course_id($lesson);
			
			/*$course_options = get_post_meta($course_id, "_sfwd-courses", true);
			$course_disable_lesson_progression = !empty($course_options["sfwd-courses_course_disable_lesson_progression"]);
			if(!empty($course_disable_lesson_progression))
			unset($lessons[$lesson]);	*/

			if(!empty($course_progress[$course_id]) && !empty($course_progress[$course_id]['lessons']) && !empty($course_progress[$course_id]['lessons'][$lesson]))
			unset($lessons[$lesson]);			
		}
		if(empty($lessons))
		return 0;
		else
		return 1;
}

function learndash_course_status($id, $user_id = null)
{
	if(empty($user_id))
	{
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
	}

	$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
	
	$has_completed_topic = false;
	if(!empty($course_progress[$id]) && !empty($course_progress[$id]['topics']) && is_array($course_progress[$id]['topics']))
	foreach($course_progress[$id]['topics'] as $lesson_topics) {
		if(!empty($lesson_topics) && is_array($lesson_topics)) {
			foreach($lesson_topics as $topic) {
				if(!empty($topic)) {
					$has_completed_topic = true;
					break;
				}
			}
		}
		if($has_completed_topic)
		break;
	}

	$quiz_notstarted = true;
	$quizzes = learndash_get_global_quiz_list($id);
	if(!empty($quizzes))
	foreach($quizzes as $quiz)
	{
		if(!learndash_is_quiz_notcomplete($user_id, array($quiz->ID => 1 )))
		$quiz_notstarted = false;
	}
	
	if((empty($course_progress[$id]) || empty($course_progress[$id]['lessons']) && !$has_completed_topic) && $quiz_notstarted)
	return __("Not Started", 'learndash');
	else if(empty($course_progress[$id]) || @$course_progress[$id]['completed'] < @$course_progress[$id]['total'])
	return __("In Progress", 'learndash');
	else
	return __("Completed", 'learndash');
}
function learndash_course_progress($atts){
	extract(shortcode_atts(array(
	'course_id' => 0,
	'user_id' => 0,
	'array'	=> false
	), $atts));
	
	if(empty($user_id))
	{
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
	}	
	
	if(empty($course_id))
	$course_id = learndash_get_course_id();
	
	if(empty($course_id))
	return "";
	
	$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
	
	$percentage = 0;
	$message = '';
	if(!empty($course_progress) && !empty($course_progress[$course_id]) && !empty($course_progress[$course_id]['total']))
	{
		$completed = intVal($course_progress[$course_id]['completed']);
		$total = intVal($course_progress[$course_id]['total']);
		
		if($completed == $total - 1)
		{
			learndash_update_completion($user_id);
			$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
			$completed = intVal($course_progress[$course_id]['completed']);
			$total = intVal($course_progress[$course_id]['total']);
		}
		
		$percentage = intVal($completed*100/$total);
		$percentage = ($percentage > 100)? 100:$percentage;
		$message = $completed." out of ".$total." steps completed";
	}

	if($array) {
		return array("percentage" => @$percentage, "completed" => @$completed, "total" => @$total);
	}
	
	return SFWD_LMS::get_template('course_progress_widget', array(
				'message' => @$message,
				'percentage' => @$percentage,
				'completed'	=> @$completed,
				'total'		=> @$total
			));
}

function is_quiz_accessable($user_id = null, $post = null)
{
	if(empty($user_id))
	{
		$current_user = wp_get_current_user();
		if(empty($current_user->ID))
		return 1;
		$user_id = $current_user->ID;
	}	
	
	$quiz_lesson = learndash_get_setting($post, "lesson");
	
	if(!empty($quiz_lesson)) {
		$quiz_lesson_post = get_post($quiz_lesson);
		if($quiz_lesson_post->post_type == "sfwd-topic")
			return 1;
		else if(learndash_lesson_topics_completed($quiz_lesson))
		return 1;
		else
		return 0;
	}
	else
	{
		$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
		$course_id = learndash_get_course_id($post->ID);
		
		if(!empty($course_progress) && !empty($course_progress[$course_id]) && !empty($course_progress[$course_id]['total']))
		{
			$completed = intVal($course_progress[$course_id]['completed']);
			$total = intVal($course_progress[$course_id]['total']);
			if($completed >= $total -1)
			return 1;
			
		}
		
		$lessons = learndash_get_lesson_list($course_id);
		
		if(empty($lessons)) {
			return 1;
		}
	
		return 0;	
	}		
}

function is_all_global_quizzes_complete($user_id = null, $id = null) {
		$quizzes = learndash_get_global_quiz_list($id);
		$return = true;
		
		if(!empty($quizzes))
		foreach($quizzes as $quiz)
		{
		if(learndash_is_quiz_notcomplete($user_id, array($quiz->ID => 1 )))
			$return = false;
		}
		return $return;
}
function learndash_next_global_quiz($url = true, $user_id = null, $id = null, $exclude = array()) {
		if(empty($id))
		$id = learndash_get_course_id();

		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}
	
		$quizzes = learndash_get_global_quiz_list($id);
		$return = get_permalink($id);

		if(!empty($quizzes))
		foreach($quizzes as $quiz)
		{
			if(!in_array($quiz->ID, $exclude) && learndash_is_quiz_notcomplete($user_id, array($quiz->ID => 1 )) && learndash_can_attempt_again($user_id, $quiz->ID))
			{
				if($url)
				return get_permalink($quiz->ID);
				else
				return $quiz->ID;
			}
		}
		$return = apply_filters("learndash_course_completion_url", $return, $id);
		return $return;
}
function learndash_next_lesson_quiz($url = true, $user_id = null,$lesson_id = null, $exclude = array()) {
		global $post;
		
		if(empty($lesson_id))
		$lesson_id = $post->ID;
		
		if(empty($exclude))
		$exclude = array();
		
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}
	
		
		$posts = get_posts( Array( 'post_type' => 'sfwd-quiz' , 'numberposts' => -1) );

		$quizzes = '';
		if ( !empty( $posts ) )  {
			foreach( $posts as $p ) {
				$meta = get_post_meta( $p->ID, '_sfwd-quiz' );
				if ( is_array( $meta ) && !empty( $meta ) ) 
				{
					$meta = $meta[0];
					if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_lesson'] ) ) ) {
						if ( $meta['sfwd-quiz_lesson'] == $lesson_id ) {
							$quizids[$p->ID] = $p->ID;
							if(!in_array($p->ID, $exclude) && learndash_is_quiz_notcomplete(null, array($p->ID => 1 )) && learndash_can_attempt_again($user_id, $p->ID))
							{
								$return = ($url)? get_permalink($p->ID):$p->ID ;
								break;
							}
						}
					}
				}
			}
		}
		if(empty($return))
		{
			learndash_process_mark_complete($user_id, $lesson_id);
		}
		else
		return $return;
}
function has_global_quizzes($id = null) {
	$quizzes = learndash_get_global_quiz_list($id);
	return !empty($quizzes);
}





class LearnDash_Course_Progress_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_ldcourseprogress', 'description' => __('LearnDash course progress bar', 'learndash'));
		$control_ops = array();//'width' => 400, 'height' => 350);
		parent::__construct('ldcourseprogress', __('Course Progress Bar', 'learndash'), $widget_ops, $control_ops);
	}

	function widget( $args, $instance ) {

		extract($args);
		$title = apply_filters( 'widget_title', empty($instance['title']) ? '' : $instance['title'], $instance );

		if(!is_singular())
		return;
		
		$progressbar = learndash_course_progress($args);
		
		if(empty($progressbar))
		return;
		
		echo $before_widget;
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; } 
		
		echo $progressbar;
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

add_action('widgets_init', create_function('', 'return register_widget("LearnDash_Course_Progress_Widget");'));
function learndash_course_progress_widget($atts){
	echo learndash_course_progress($atts);
}
add_shortcode('learndash_course_progress', 'learndash_course_progress');

function learndash_lesson_progression_enabled() {
	$id = learndash_get_course_id();
	$meta = get_post_meta( $id, '_sfwd-courses' );
	return empty($meta[0]['sfwd-courses_course_disable_lesson_progression']);
}
function learndash_forced_lesson_time() {
	global $post;
	if(empty($post->ID)) return 0;
	$meta = get_post_meta($post->ID, '_'.$post->post_type );

	if(!empty($meta[0][$post->post_type.'_forced_lesson_time']))
	return $meta[0][$post->post_type.'_forced_lesson_time'];
	else 
	return 0;
}
