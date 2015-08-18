<?php
	/*
		Available Variables:
		$course_id 		: (int) ID of the course
		$course 		: (object) Post object of the course
		$course_settings : (array) Settings specific to current course
		
		$courses_options : Options/Settings as configured on Course Options page
		$lessons_options : Options/Settings as configured on Lessons Options page
		$quizzes_options : Options/Settings as configured on Quiz Options page

		$user_id 		: Current User ID
		$logged_in 		: User is logged in
		$current_user 	: (object) Currently logged in user object

		$course_status 	: Course Status
		$has_access 	: User has access to course or is enrolled.
		$has_course_content		: Course has course content
		$lessons 		: Lessons Array
		$quizzes 		: Quizzes Array
		$lesson_progression_enabled 	: (true/false)

	*/

	/* $course_settings Array
		(
		    [course_materials] => material text
		    [course_price] => 1
		    [course_price_type] => paynow
		    [course_access_list] => 1,2,3,5,3,9,4
		    [course_lesson_orderby] => 
		    [course_lesson_order] => ASC
		    [course_prerequisite] => 
		)
	*/

	/* Show Course Status */


	if ( $has_course_content ) 
	{
		?>
		<div id='learndash_course_content'>
			<h4 id='learndash_course_content_title'><?php _e('Course Content', 'learndash'); ?></h4>

		<?php


		/* Show Lesson List */
		if(!empty($lessons)) {
		?>
		<?php if($has_topics) { ?>
		<div class="expand_collapse"><a href="#" onClick="jQuery('#learndash_post_<?php echo $course_id; ?> .learndash_topic_dots').slideDown(); return false;"><?php _e('Expand All', 'learndash'); ?></a> | <a href="#" onClick="jQuery('#learndash_post_<?php echo $course_id; ?> .learndash_topic_dots').slideUp(); return false;"><?php _e('Collapse All','learndash'); ?></a></div>
		<?php } ?>
		<div id='learndash_lessons'>
			<div id="lesson_heading">
					<span><?php _e('Lessons', 'learndash') ?></span>
					<span class="right"><?php _e('Status', 'learndash') ?></span>
			</div>
			<div id="lessons_list">
				<?php foreach($lessons as $lesson) { ?>
				<div id="post-<?php echo $lesson["post"]->ID; ?>" class="<?php echo $lesson["sample"];?>">
					<div class="list-count"><?php echo $lesson["sno"]; ?></div>
					<h4>
						<a class="<?php echo $lesson["status"]; ?>" href="<?php echo $lesson["permalink"]?>"><?php echo $lesson["post"]->post_title; ?></a>
						<?php 
						/* Not available message for drip feeding lessons */
						if(!empty($lesson["lesson_access_from"])) { ?>
						<small class='notavailable_message'>
							<?php echo sprintf(__(' Available on: %s ', "learndash"), date("d-M-Y", $lesson["lesson_access_from"])); ?>
						</small>
						<?php } 
						
						/* Lesson Topis */
						$topics = @$lesson_topics[$lesson["post"]->ID];
						/*
						Topics Array Format
							(
							    [0] => WP_Post Object
							        (
							            [ID] => 584
							            [post_author] => 1
							            [post_date] => 2014-02-05 22:24:06
							            [post_date_gmt] => 2014-02-05 22:24:06
							            [post_content] => 
							            [post_title] => Lesson Topic 
							            [post_excerpt] => 
							            [post_status] => publish
							            [comment_status] => open
							            [ping_status] => open
							            [post_password] => 
							            [post_name] => lesson-topic
							            [to_ping] => 
							            [pinged] => 
							            [post_modified] => 2014-02-05 22:24:06
							            [post_modified_gmt] => 2014-02-05 22:24:06
							            [post_content_filtered] => 
							            [post_parent] => 0
							            [guid] => http://domain.com/?post_type=sfwd-topic&p=584
							            [menu_order] => 0
							            [post_type] => sfwd-topic
							            [post_mime_type] => 
							            [comment_count] => 0
							            [filter] => raw
							            [completed] => 0
							        )

							)
						*/
						//echo "<pre>";
						//print_r($topics);
						if(!empty($topics)) {
						?>
						<div id="learndash_topic_dots-<?php echo $lesson["post"]->ID; ?>" class="learndash_topic_dots type-list">
							<ul>
								<?php
								$odd_class = "";
								foreach ($topics as $key => $topic) { 
									$odd_class = empty($odd_class)? "nth-of-type-odd":"";
									$completed_class = empty($topic->completed)? "topic-notcompleted":"topic-completed";
									?>
									<li class="<?php echo $odd_class; ?>">
										<span class="topic_item">
											<a class="<?php echo $completed_class; ?>" href="<?php echo get_permalink($topic->ID); ?>" title="<?php echo $topic->post_title; ?>">
												<span><?php echo $topic->post_title; ?></span>
											</a>
										</span>
									</li>
								<?php } ?>
							</ul>
						</div>
						<?php } ?>
					</h4>
				</div>
				<?php } ?>
			</div>
		</div>
		<?php
		}


		/* Show Quiz List */		
		if(!empty($quizzes)) {
		?>
		<div id='learndash_quizzes'>
			<div id="quiz_heading"><span><?php _e('Quizzes', 'learndash') ?></span><span class="right"><?php _e('Status', 'learndash') ?></span></div>
			<div id="quiz_list">
			<?php foreach($quizzes as $quiz) { ?>
				<div id="post-<?php echo $quiz["post"]->ID; ?>" class="<?php echo $quiz["sample"];?>">
					<div class="list-count"><?php echo $quiz["sno"]; ?></div>
					<h4>
						<a class="<?php echo $quiz["status"]; ?>" href="<?php echo $quiz["permalink"]?>"><?php echo $quiz["post"]->post_title; ?></a>
					</h4>
				</div>
			<?php } ?>
			</div>
		</div>
		<?php
		}
		?>	
		</div>
		<?php
	}