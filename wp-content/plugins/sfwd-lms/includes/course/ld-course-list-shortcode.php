<?php
/**
 * Shortcodes and helper functions for listing
 * courses, lessons, quizzes, and topics
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Shortcodes
 */




/**
 * Shortcode to list courses
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 	shortcode attributes
 * @return string   		shortcode output
 */
function ld_course_list( $attr ) {
	
	$shortcode_atts = shortcode_atts( 
		array(
			'num' => '-1', 
			'post_type' => 'sfwd-courses', 
			'post_status' => 'publish', 
			'order' => 'DESC', 
			'orderby' => 'ID', 
			'mycourses' => false, 'meta_key' => '', 
			'meta_value' => '', 
			'meta_compare' => '',
			'post__in'	=> null,
			'tag' => '', 
			'tag_id' => 0, 'tag__and' => '', 
			'tag__in' => '', 
			'tag__not_in' => '', 
			'tag_slug__and' => '', 
			'tag_slug__in' => '', 
			'cat' => '', 
			'category_name' => 0, 'category__and' => '', 
			'category__in' => '', 
			'category__not_in' => '', 
			'categoryselector' => '', 
			'author__in' => '', 
			'col' => '', 
			'array' => false,
		), 
		$attr 
	);
	
	extract( $shortcode_atts );
	global $post;
	
	$filter = array(
		'post_type' => $post_type, 
		'post_status' => $post_status, 
		'posts_per_page' => $num, 
		'order' => $order, 
		'orderby' => $orderby
	);
	
	if ( ! empty( $author__in ) ) {
		$filter['author__in'] = $author__in;
	}
	
	if ( ! empty( $tag ) ) {
		$filter['tag'] = $tag;
	}
	
	if ( ! empty( $tag_id ) ) {
		$filter['tag_id'] = $tag;
	}
	
	if ( ! empty( $meta_key ) ) {
		$filter['meta_key'] = $meta_key;
	}
	
	if ( ! empty( $meta_value ) ) {
		$filter['meta_value'] = $meta_value;
	}
	
	if ( ! empty( $meta_compare ) ) {
		$filter['meta_compare'] = $meta_compare;
	}

	if ( ! empty( $post__in ) ) {
		$filter['post__in'] = $post__in;
	}	
	
	if ( ! empty( $tag__and ) ) {
		$filter['tag__and'] = explode( ',', $tag__and );
	}
	
	if ( ! empty( $tag__in ) ) {
		$filter['tag__in'] = explode( ',', $tag__in );
	}
	
	if ( ! empty( $tag__not_in ) ) {
		$filter['tag__not_in'] = explode( ',', $tag__not_in );
	}
	
	if ( ! empty( $tag_slug__and ) ) {
		$filter['tag_slug__and'] = explode( ',', $tag_slug__and );
	}
	
	if ( ! empty( $tag_slug__in ) ) {
		$filter['tag_slug__in'] = explode( ',', $tag_slug__in );
	}
	
	if ( ! empty( $cat ) ) {
		$filter['cat'] = $cat;
	}
	
	if ( ! empty( $cat ) ) {
		$filter['cat'] = $cat;
	}
	
	if ( ! empty( $category_name ) ) {
		$filter['category_name'] = $category_name;
	}
	
	if ( ! empty( $category__and ) ) {
		$filter['category__and'] = explode( ',', $category__and );
	}
	
	if ( ! empty( $category__in ) ) {
		$filter['category__in'] = explode( ',', $category__in );
	}
	
	if ( ! empty( $category__not_in ) ) {
		$filter['category__not_in'] = explode( ',', $category__not_in );
	}
	
	if ( $array ) {
		return get_posts( $filter );
	}
	
	if ( @$post->post_type == $post_type ) {
		$filter['post__not_in'] = array( $post->ID );
	}
	
	$loop = new WP_Query( $filter );
	
	$level = ob_get_level();
	ob_start();
	$ld_categorydropdown = '';

	if ( trim( $categoryselector ) == 'true' ) {
		$cats = array();
		$posts = get_posts( $filter );

		foreach( $posts as $post ) {
			$post_categories = wp_get_post_categories( $post->ID );

			foreach( $post_categories as $c ) {

				if ( empty( $cats[ $c ] ) ) {
					$cat = get_category( $c );
					$cats[ $c ] = array('id' => $cat->cat_ID, 'name' => $cat->name, 'slug' => $cat->slug, 'parent' => $cat->parent, 'count' => 0, 'posts' => array()); //stdClass Object ( [term_id] => 39 [name] => Category 2 [slug] => category-2 [term_group] => 0 [term_taxonomy_id] => 41 [taxonomy] => category [description] => [parent] => 0 [count] => 3 [object_id] => 656 [filter] => raw [cat_ID] => 39 [category_count] => 3 [category_description] => [cat_name] => Category 2 [category_nicename] => category-2 [category_parent] => 0 )
					
				}

				$cats[ $c ]['count']++;
				$cats[ $c ]['posts'][] = $post->ID;
			}

		}

		$categorydropdown = "<div id='ld_categorydropdown'><span>" . __( 'Categories', 'learndash' ) . '</span>';
		$categorydropdown.= "<form method='get'><select name='catid' onChange='jQuery(\"#ld_categorydropdown form\").submit()'>";
		$categorydropdown.= "<option value=''>" . __( 'Select category', 'learndash' ) . '</option>';

		foreach( $cats as $cat ) {
			$selected =( empty( $_GET['catid'] ) || $_GET['catid'] != $cat['id'] ) ? '' : 'selected="selected"';
			$categorydropdown.= "<option value='" . $cat['id'] . "' " . $selected . '>' . $cat['name'] . ' (' . $cat['count'] . ')</option>';
		}

		$categorydropdown.= "</select><input type='submit' style='display:none'></form></div>";

		/**
		 * Filter HTML output of category dropdown
		 * 
		 * @since 2.1.0
		 * 
		 * @param  string  $categorydropdown
		 */
		echo apply_filters( 'ld_categorydropdown', $categorydropdown, $shortcode_atts, $filter );
	}
	
	while ( $loop->have_posts() ) {
		$loop->the_post();
		if ( trim( $categoryselector ) == 'true' && ! empty( $_GET['catid'] ) && !in_array( get_the_ID(), (array)@$cats[ $_GET['catid']]['posts'] ) ) {
			continue;
		}
		
		if ( !$mycourses || sfwd_lms_has_access( get_the_ID() ) ) {
			echo SFWD_LMS::get_template( 'course_list_template', array('shortcode_atts' => $shortcode_atts) );
		}
	}

	$output = learndash_ob_get_clean( $level );
	wp_reset_query();

	/**
	 * Filter HTML output of category dropdown
	 * 
	 * @since 2.1.0
	 * 
	 * @param  string $output
	 */
	return apply_filters( 'ld_course_list', $output, $shortcode_atts, $filter );
}

add_shortcode( 'ld_course_list', 'ld_course_list' );



/**
 * Shortcode to list lessons
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 	shortcode attributes
 * @return string   		shortcode output
 */
function ld_lesson_list( $attr ) {
	$attr['post_type'] = 'sfwd-lessons';
	$attr['mycourses'] = false;
	return ld_course_list( $attr );
}

add_shortcode( 'ld_lesson_list', 'ld_lesson_list' );



/**
 * Shortcode to list quizzes
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 	shortcode attributes
 * @return string   		shortcode output
 */
function ld_quiz_list( $attr ) {
	$attr['post_type'] = 'sfwd-quiz';
	$attr['mycourses'] = false;
	return ld_course_list( $attr );
}

add_shortcode( 'ld_quiz_list', 'ld_quiz_list' );



/**
 * Shortcode to list topics
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 	shortcode attributes
 * @return string   		shortcode output
 */
function ld_topic_list( $attr ) {
	$attr['post_type'] = 'sfwd-topic';
	$attr['mycourses'] = false;
	return ld_course_list( $attr );
}

add_shortcode( 'ld_topic_list', 'ld_topic_list' );



/**
 * Check if user has access
 *
 * @todo  duplicate function, exists in other places
 *        check it's use and consolidate
 * 
 * @since 2.1.0
 * 
 * @param  int $course_id
 * @param  int $user_id
 * @return bool
 */
function ld_course_check_user_access( $course_id, $user_id = null ) {
	return sfwd_lms_has_access( $course_id, $user_id );
}



/**
 * Shortcode to display content to users that have access to current course id
 *
 * @todo  function is duplicate of learndash_student_check_shortcode()
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @return string   			shortcode output
 */
function learndash_visitor_check_shortcode( $atts, $content = null ) {
	if ( ! is_singular() || is_null( $content ) ) {
		return '';
	}
	
	$course_id = learndash_get_course_id();
	
	if ( ! sfwd_lms_has_access( $course_id ) ) {
		return do_shortcode( $content );
	}
	
	return '';
}

add_shortcode( 'visitor', 'learndash_visitor_check_shortcode' );



/**
 * Shortcode to display content to users that have access to current course id
 *
 * @todo  function is duplicate of learndash_visitor_check_shortcode()
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @return string   			shortcode output
 */
function learndash_student_check_shortcode( $atts, $content = null ) {
	if ( ! is_singular() || is_null( $content ) ) {
		return '';
	}
	
	$course_id = learndash_get_course_id();
	
	if ( sfwd_lms_has_access( $course_id ) ) {
		return do_shortcode( $content );
	}
	
	return '';
}

add_shortcode( 'student', 'learndash_student_check_shortcode' );



/**
 * Generates output for course status shortcodes
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @param  string 	$status  	status of course
 * @return string 				shortcode output
 */
function learndash_course_status_content_shortcode( $atts, $content, $status ) {
	if ( ! is_singular() ) {
		return '';
	}
	
	$user_id = empty( $atts['user_id'] ) ? get_current_user_id() : $atts['user_id'];
	$course_id = empty( $atts['course_id'] ) ? learndash_get_course_id() : $atts['course_id'];
	
	if ( empty( $course_id ) || empty( $user_id ) ) {
		return '';
	}
	
	if ( learndash_course_status( $course_id, $user_id ) == __( $status, 'learndash' ) ) {
		return do_shortcode( $content );
	} else {
		return '';
	}
}



/**
 * Shortcode that shows the content if the user has completed the course. 
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @return string   			shortcode output
 */
function learndash_course_complete_shortcode( $atts, $content ) {
	return learndash_course_status_content_shortcode( $atts, $content, 'Completed' );
}

add_shortcode( 'course_complete', 'learndash_course_complete_shortcode' );



/**
 * Shortcode that shows the content if the user is in progress on the course.
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @return string   			shortcode output
 */
function learndash_course_inprogress_shortcode( $atts, $content ) {
	return learndash_course_status_content_shortcode( $atts, $content, 'In Progress' );
}

add_shortcode( 'course_inprogress', 'learndash_course_inprogress_shortcode' );



/**
 * Shortcode that shows the content if the user has mnot started the course
 * 
 * @since 2.1.0
 * 
 * @param  array 	$attr 		shortcode attributes
 * @param  string 	$content 	content of shortcode
 * @return string   			shortcode output
 */
function learndash_course_notstarted_shortcode( $atts, $content ) {
	if ( ! is_singular() ) {
		return '';
	}
	
	$user_id = empty( $atts['user_id'] ) ? get_current_user_id() : $atts['user_id'];
	$course_id = empty( $atts['course_id'] ) ? learndash_get_course_id() : $atts['course_id'];
	
	if ( empty( $course_id ) || empty( $user_id ) ) {
		return '';
	}
	
	if ( sfwd_lms_has_access( $user_id, $course_id ) ) {
		return learndash_course_status_content_shortcode( $atts, $content, 'Not Started' );
	} else {
		return '';
	}
}

add_shortcode( 'course_notstarted', 'learndash_course_notstarted_shortcode' );
