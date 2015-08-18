<div id='course_navigation'>
	<div class='learndash_nevigation_lesson_topics_list'>

	<?php
	global $post;
		if($post->post_type == "sfwd-topic" || $post->post_type == "sfwd-quiz") {
			$lesson_id = learndash_get_setting($post, "lesson");
		}
		else
			$lesson_id = $post->ID;

		if(!empty($lessons))
		foreach($lessons as $course_lesson)
		{
			$current_topic_ids = "";
			$topics =  learndash_topic_dots($course_lesson["post"]->ID, false, 'array');
			$is_current_lesson = ($lesson_id== $course_lesson["post"]->ID);
			$lesson_list_class = ($is_current_lesson)? 'active':'inactive';
			$lesson_lesson_completed = ($course_lesson["status"]=='completed')?'lesson_completed':'lesson_incomplete';
			$list_arrow_class = ($is_current_lesson && !empty($topics))? 'expand':'collapse';
			if(!empty($topics))
				$list_arrow_class .= " flippable";
			?>
			
			<div class="<?php echo $lesson_list_class ?>" id="lesson_list-<?php echo $course_lesson["post"]->ID; ?>">
				<div class="list_arrow <?php echo $list_arrow_class; ?> <?php echo $lesson_lesson_completed; ?>" onClick="return flip_expand_collapse('#lesson_list', <?php echo $course_lesson["post"]->ID; ?>);" >
				</div>
				<div class="list_lessons">
					<div class="lesson" >
						<a href="<?php echo get_permalink($course_lesson["post"]->ID);?>"><?php echo $course_lesson["post"]->post_title ?></a>
					</div> 

					<?php
						if(!empty($topics)) {
						?>
						<div id="learndash_topic_dots-<?php echo $course_lesson["post"]->ID; ?>" class="flip learndash_topic_widget_list"  style="<?php echo (strpos($list_arrow_class,"collapse") !== false)? "display:none":"" ?>">
							<ul>
								<?php
								$odd_class = "";
								foreach ($topics as $key => $topic) { 
								//	$odd_class = empty($odd_class)? "nth-of-type-odd":"";
									$completed_class = empty($topic->completed)? "topic-notcompleted":"topic-completed";
									?>
									<li>
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
					</div>
				</div> 
	<?php } ?>
	</div> <!-- Closing <div class='learndash_nevigation_lesson_topics_list'> -->
	<?php if($post->ID != $course->ID) { ?> 
	<div class="widget_course_return">
		<?php _e("Return to", "learndash"); ?> <a href="<?php echo get_permalink($course_id); ?>">
			<?php echo $course->post_title;?>
		</a>
	</div>
	<?php } ?>
</div> <!-- Closing <div id='course_navigation'> -->
