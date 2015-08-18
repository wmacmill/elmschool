<div id="ld_course_info">
<?php

/*
Available:
	$user_id
	$courses_registered: course
	$course_progress: Progress in courses
	$quizzes
 */


/*
Course registered
 */
echo '<!-- Course info shortcode -->';
if($courses_registered):
?>
<div id="ld_course_info_mycourses_list">
<h4><?php _e('You are registered for the following courses', 'learndash') ?></h4>
<?php foreach($courses_registered as $c) { ?>
	<div class="ld-course-info-my-courses"><?php 	echo get_the_post_thumbnail($c); ?>
	<?php	echo '<h2 class="ld-entry-title entry-title"><a href="' . get_permalink($c) . '"  rel="bookmark">'.get_the_title($c).'</a></h2>'; ?>
	</div>
<?php } ?>
<br/>
</div>
<?php
endif;


/*
Course progress
 */
if($course_progress):
?>
<div id="course_progress_details">
<h4><?php _e('Course progress details:', 'learndash') ?></h4>
<?php foreach($course_progress as $course_id => $coursep) { $course = get_post($course_id); if(empty($course->post_title)) continue; ?>
	<strong><?php echo $course->post_title ?></strong>: <?php _e('Completed', 'learndash') ?> <strong><?php echo $coursep['completed'] ?></strong> <?php _e('out of', 'learndash') ?> <strong> <?php echo $coursep['total'] ?> </strong> <?php _e('steps', 'learndash') ?><br/>
<?php } ?>
</div>
<br>
<?php
endif;



/*
Quizzes
 */
if($quizzes):
?>
<h4><?php _e('You have taken the following quizzes:', 'learndash') ?></h4>
<?php 
foreach( $quizzes as $k => $v ) {
	$quiz = get_post( $v['quiz'] );
	$passstatus = isset($v['pass'])? (($v['pass'] == 1)? "green":"red"):"";
	$c = learndash_certificate_details($v['quiz'], $user_id);
	$certificateLink = $c['certificateLink'];
	$certificate_threshold = $c['certificate_threshold'];
	$quiz_title = !empty($quiz->post_title)? $quiz->post_title:@$v['quiz_title'];

	if(!empty($quiz_title)) {
	?>
	<p>
		<span style="color:<?php echo $passstatus ?>"><strong><?php echo __('Quiz', 'learndash') ?></strong>: <?php echo $quiz_title ?></span> 
		<?php echo isset($v['percentage']) ? " - {$v['percentage']}%" : '' ?>
		<?php if($user_id == get_current_user_id() && !empty($certificateLink) && ((isset($v['percentage']) && $v['percentage'] >= $certificate_threshold * 100) || (isset($v['count']) && $v['score']/$v['count'] >= $certificate_threshold))): ?>
			- <a href="<?php echo $certificateLink ?>&time=<?php echo $v['time'] ?>" target="_blank"><?php echo __('Print Certificate', 'learndash') ?></a>
		<?php endif; ?>
		<br/>
		
		<?php if(isset($v['rank']) && is_numeric($v['rank'])): ?>
			<?php echo __('Rank: ', 'learndash') ?> <?php echo $v['rank'] ?>, 
		<?php endif; ?>
		<?php echo __('Score ', 'learndash') ?><?php echo $v['score'] ?> <?php echo __(' out of ', 'learndash') ?> <?php echo $v['count'] ?> <?php echo __(' question(s)', 'learndash') ?>;
		<?php if(isset($v['points']) && isset($v['total_points'])): ?>
			<?php echo __(' . Points: ', 'learndash') ?> <?php echo $v['points'] ?>/<?php echo $v['total_points'] ?>
		<?php endif; ?>
		<?php echo __(' on ', 'learndash') ?> <?php echo date( DATE_RSS, $v['time'] ) ?>
		<?php echo apply_filters("course_info_shortcode_after_item", "", $quiz, $v, $user_id); ?>
	</p>
	<?php
	}
}
endif;


echo '<!-- End Course info shortcode -->';
?>
</div>
