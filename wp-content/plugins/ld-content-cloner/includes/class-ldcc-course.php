<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 *
 * @package    Ld_Content_Cloner
 * @subpackage Ld_Content_Cloner/includes
 */

/**
 * The LD course plugin class.
 *
 * @since      1.0.0
 * @package    Ld_Content_Cloner
 * @subpackage Ld_Content_Cloner/includes
 * @author     WisdmLabs <info@wisdmlabs.com>
 */

class Ldcc_Course {

	protected static $course_id=0;
	
	protected static $new_course_id=0;

	/**
	 * 
	 * @since    1.0.0
	 */

	public function __construct( ) {
		
	}

	public static function create_duplicate_course( ) {

		$course_id = filter_input( INPUT_POST, 'course_id', FILTER_VALIDATE_INT );
		$course_nonce = filter_input( INPUT_POST, 'course' );
		$nonce_check = wp_verify_nonce( $course_nonce, 'dup_course_' . $course_id );

		if( $nonce_check === false ){
			echo json_encode( array( "error" => __( "Security check failed.", "ld-content-cloner" ) ) );
			die();
		}

		if( ( !isset( $course_id ) ) || !( get_post_type( $course_id ) == 'sfwd-courses' ) ){
			echo json_encode( array( "error" => __( "The current post is not a Course and hence could not be cloned.", "ld-content-cloner" ) ) );
			die();
		}

		$course_post = get_post( $course_id, ARRAY_A );

		/*$topics = learndash_get_topic_list( $course_id );
		echo "<pre>".print_r($topics,1)."</pre>";
		die();*/

		$course_post = self::strip_post_data( $course_post );
		
		$new_course_id = wp_insert_post( $course_post, true );

		if( ! is_wp_error( $new_course_id ) ) {
			//self::$new_course_id = $new_course_id;
			self::set_meta( "course", $course_id, $new_course_id );
			$lessons = learndash_get_course_lessons_list( $course_id );
			$quizzes = learndash_get_global_quiz_list( $course_id );

			$c_data = array( 'lesson' => array(), 'quiz' => array() );
			
			if( !empty( $lessons ) ){
				foreach ($lessons as $key => $lesson) {
					$c_data['lesson'][] = array( $lesson['post']->ID, $lesson['post']->post_title );
				}
			}

			if( !empty( $quizzes ) ){
				foreach ($quizzes as $key => $quiz) {
					$c_data['quiz'][] = array( $quiz->ID, $quiz->post_title );
				}
			}

			$send_result = array( "success" => array( "new_course_id" => $new_course_id, "c_data" => $c_data, ) );
			echo json_encode( $send_result );
		} else {
			echo json_encode( array( "error" => __( "Some error occurred. The Course could not be cloned.", "ld-content-cloner" ) ) );
		}

		die();
	}

	public static function create_duplicate_lesson(){

		$lesson_id = filter_input( INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT );
		if( ( !isset( $lesson_id ) ) || !( get_post_type( $lesson_id ) == 'sfwd-lessons' ) ){
			echo json_encode( array( "error" => __( "The current post is not a Lesson and hence could not be cloned.", "ld-content-cloner" ) ) );
			die();
		}

		$course_id = filter_input( INPUT_POST, 'course_id', FILTER_VALIDATE_INT );
		if( ( !isset( $course_id ) ) || !( get_post_type( $course_id ) == 'sfwd-courses' ) ){
			echo json_encode( array( "error" => __( "The course ID provided with is incorrect for the lesson.", "ld-content-cloner" ) ) );
			die();
		}

		$lesson_post = get_post( $lesson_id, ARRAY_A );
		
		$lesson_post = self::strip_post_data( $lesson_post );

		$new_lesson_id = wp_insert_post( $lesson_post, true );

		if( ! is_wp_error( $new_lesson_id ) ) {

			$meta_result = self::set_meta( 
									'lesson',
									$lesson_id,
									$new_lesson_id,
									array(
										"course_id" => $course_id
									)
								);
			$topics = learndash_get_topic_list( $lesson_id );
			
			foreach ( $topics as $sin_topic_obj ) {
				self::duplicate_unit( $sin_topic_obj->ID, $new_lesson_id, $course_id );
			}
			
			$quizzes = learndash_get_lesson_quiz_list( $lesson_id );
			foreach ($quizzes as $quiz) {
				self::duplicate_quiz( $quiz['post']->ID, $new_lesson_id, $course_id );
			}

			$send_result = array( "success" => array( ) );
		} else{
			$send_result = array( "error" => __( "Some error occurred. The Lesson was not fully cloned.", "ld-content-cloner" ) );
		}
		echo json_encode( $send_result );
		die();
	}

	public static function duplicate_unit( $unit_id, $lesson_id, $course_id ){
		
		$unit_post = get_post( $unit_id, ARRAY_A );

		$unit_post = self::strip_post_data( $unit_post );

		$new_unit_id = wp_insert_post( $unit_post, true );

		if( ! is_wp_error( $new_unit_id ) ) {
			$meta_result = self::set_meta( 
									'unit',
									$unit_id,
									$new_unit_id,
									array(
										"lesson_id" => $lesson_id,
										"course_id" => $course_id
									)
								);
			$quizzes = learndash_get_lesson_quiz_list( $unit_id );
			foreach ($quizzes as $quiz) {
				self::duplicate_quiz( $quiz['post']->ID, $new_unit_id, $course_id );
			}
		}

	}

	public static function duplicate_quiz( $quiz_id=0, $lesson_id=0, $course_id=0 ){
		// duplicate quiz post
		$send_response = false;
		if( $quiz_id == 0 ){
			$quiz_id = filter_input( INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT );
			$course_id = filter_input( INPUT_POST, 'course_id', FILTER_VALIDATE_INT );
			$send_response = true;
		}
		$quiz_post = get_post( $quiz_id, ARRAY_A );

		$quiz_post = self::strip_post_data( $quiz_post );

		$new_quiz_id = wp_insert_post( $quiz_post, true );

		if( ! is_wp_error( $new_quiz_id ) ) {
			$meta_result = self::set_meta( 
									'quiz',
									$quiz_id,
									$new_quiz_id,
									array(
										"lesson_id" => $lesson_id,
										"course_id" => $course_id
									)
								);
			$ld_quiz_data = get_post_meta( $new_quiz_id, '_sfwd-quiz', true );
			$pro_quiz_id = $ld_quiz_data['sfwd-quiz_quiz_pro'];
			global $wpdb;
			$_prefix = $wpdb->prefix.'wp_pro_quiz_';
			
			$_tableQuestion = $_prefix.'question';
			$_tableMaster = $_prefix.'master';
			$_tablePrerequisite = $_prefix.'prerequisite';

			// fetch and create in top quiz master table ( wp_pro_quiz_master )
			$pq_query = "SELECT * FROM $_tableMaster WHERE id = %d;";

			
			
			$pro_quiz = $wpdb->get_row( $wpdb->prepare( $pq_query, $pro_quiz_id ), ARRAY_A );

			unset( $pro_quiz['id'] );
			$pro_quiz['name'] .= " Copy";

		    $format = array( '%s','%s','%s','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%s','%d','%d','%d','%d','%d','%d','%d','%d','%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' );

			$ins_result = $wpdb->insert( $_tableMaster, $pro_quiz, $format );
			
			$wp_pro_quiz_id = 0;

			if ( $ins_result !== false ){
				$wp_pro_quiz_id = $wpdb->insert_id;
				$ld_quiz_data['sfwd-quiz_quiz_pro'] = $wp_pro_quiz_id;
				update_post_meta( $new_quiz_id, '_sfwd-quiz', $ld_quiz_data );

				// fetch and create in pre-requisites table ( wp_pro_quiz_prerequisite )
				
				$pqr_query = "SELECT * FROM $_tablePrerequisite WHERE prerequisite_quiz_id = %d;";
				$pror_quizzes = $wpdb->get_results( $wpdb->prepare( $pqr_query, $pro_quiz_id ), ARRAY_A );
				if( !empty( $pror_quizzes ) ){
					foreach ($pror_quizzes as $pror_quiz) {
						$pror_quiz['prerequisite_quiz_id'] = $wp_pro_quiz_id;
						$ins_result = $wpdb->insert( $_tablePrerequisite, $pror_quiz, array( '%s','%s', ) );
					}
				}

				// copy pro quiz questions ( wp_pro_quiz_question )
				$questionArr = self::getQuestions( $pro_quiz_id );
				
				if(!empty($questionArr)){
					self::copyQuestions( $wp_pro_quiz_id, $questionArr );
				}

			}
			$send_result = array( "success" => array( ) );
		} else{
			$send_result = array( "error" => __( "Some error occurred. The Quiz was not fully cloned.", "ld-content-cloner" ) );
		}

		if( $send_response ){
			echo json_encode( $send_result );
			die();
		}
	}

	public static function getQuestions( $quizId ) {
		
		$quizMapper = new WpProQuiz_Model_QuizMapper();
		$questionMapper = new WpProQuiz_Model_QuestionMapper();
		$data = array();
		
		$quiz = $quizMapper->fetch( $quizId );

		$questions = $questionMapper->fetchAll($quiz->getId());

		$questionArray = array();
		
		foreach($questions as $qu) {

			$questionArray[] = $qu->getId();
		}

		return $questionArray;
	}

	public static function copyQuestions( $quizId, $questionArray ) {

		$questionMapper = new WpProQuiz_Model_QuestionMapper();

		$questions = $questionMapper->fetchById( $questionArray );

		foreach($questions as $question) {
			$question->setId(0);
			$question->setQuizId($quizId);
			$questionMapper->save($question);
		}

	}

	public static function strip_post_data( $post_array ){
		$exclude_remove = array( 'post_content', 'post_title', 'post_status', 'post_type', 'post_category', 'tags_input' );
		foreach ($post_array as $key => $value) {
			if( !in_array( $key, $exclude_remove ) ) {
				unset( $post_array[ $key ] );
			}
		}
		
		$post_array['post_status'] = "draft";
		$post_array['post_title'] .= " Copy";
		return $post_array;
	}

	public static function set_meta( $post_type, $old_post_id, $new_post_id, $other_data = array() ) {
		if( !empty( $old_post_id ) && !empty( $new_post_id ) ){
			if( $post_type == 'course' ) {
				$ld_data = get_post_meta( $old_post_id, '_sfwd-courses', true );
				$thumbnail = get_post_meta( $old_post_id, '_thumbnail_id', true );
				update_post_meta( $new_post_id, '_sfwd-courses', $ld_data );
				update_post_meta( $new_post_id, '_thumbnail_id', $thumbnail );
			} elseif( $post_type == 'lesson' ){
				$sent_c_id = $other_data['course_id'];
				$ld_data = get_post_meta( $old_post_id, '_sfwd-lessons', true );
				$lesson_course_id = $sent_c_id;
				
				$ld_data['sfwd-lessons_course'] = $lesson_course_id;

				$thumbnail = get_post_meta( $old_post_id, '_thumbnail_id', true );

				update_post_meta( $new_post_id, '_sfwd-lessons', $ld_data );
				update_post_meta( $new_post_id, 'course_id', $lesson_course_id );
				update_post_meta( $new_post_id, '_thumbnail_id', $thumbnail );
			} elseif( $post_type == 'unit' ){
				$unit_course_id = $other_data['course_id'];
				$unit_lesson_id = $other_data['lesson_id'];
				$ld_data = get_post_meta( $old_post_id, '_sfwd-topic', true );
				
				$ld_data['sfwd-topic_course'] = $unit_course_id;
				$ld_data['sfwd-topic_lesson'] = $unit_lesson_id;

				$thumbnail = get_post_meta( $old_post_id, '_thumbnail_id', true );

				update_post_meta( $new_post_id, '_sfwd-topic', $ld_data );
				update_post_meta( $new_post_id, 'course_id', $unit_course_id );
				update_post_meta( $new_post_id, 'lesson_id', $unit_lesson_id );
				update_post_meta( $new_post_id, '_thumbnail_id', $thumbnail );
			} elseif( $post_type == 'quiz' ){
				$unit_course_id = $other_data['course_id'];
				$unit_lesson_id = $other_data['lesson_id'];
				$ld_data = get_post_meta( $old_post_id, '_sfwd-quiz', true );
				
				$ld_data['sfwd-quiz_course'] = $unit_course_id;
				$ld_data['sfwd-quiz_lesson'] = $unit_lesson_id;

				$thumbnail = get_post_meta( $old_post_id, '_thumbnail_id', true );

				update_post_meta( $new_post_id, '_sfwd-quiz', $ld_data );
				update_post_meta( $new_post_id, 'course_id', $unit_course_id );
				update_post_meta( $new_post_id, 'lesson_id', $unit_lesson_id );
				update_post_meta( $new_post_id, '_thumbnail_id', $thumbnail );
			}
			return true;
		}
		return false;
	}

	public function add_course_row_actions( $actions, $post_data ) {
		if( get_post_type( $post_data->ID ) === 'sfwd-courses' ){
	    	$actions = array_merge( $actions, 
	    				array(
    						'clone_course' => '<a href="#" title="Clone this course" class="ldcc-clone-course" data-course-id="' . $post_data->ID . '" data-course="' . wp_create_nonce( 'dup_course_' . $post_data->ID ) . '">' . __('Clone Course') . '</a>'
	    				)
	    			);
		}
	    return $actions;
	}

	public function add_lesson_row_actions( $actions, $post_data ) {
		if( get_post_type( $post_data->ID ) === 'sfwd-lessons' ){
	    	$actions = array_merge( $actions, 
	    				array(
    						'clone_lesson' => '<a href="#" title="Clone this lesson" class="ldcc-clone-lesson" data-lesson-id="' . $post_data->ID . '" >' . __('Clone Lesson') . '</a>'
	    				)
	    			);
		} elseif( get_post_type( $post_data->ID ) === 'sfwd-quiz' ){
	    	$actions = array_merge( $actions, 
	    				array(
    						'clone_quiz' => '<a href="#" title="Clone quiz" class="ldcc-clone-quiz" data-quiz-id="' . $post_data->ID . '" data-course-id="'.get_post_meta( $post_data->ID, 'course_id', true ).'">' . __('Clone Quiz') . '</a>'
	    				)
	    			);
		}
	    return $actions;
	}

	public function add_modal_structure( ) {
		global $current_screen;
		
		if( isset( $current_screen ) && in_array( $current_screen->post_type, array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-quiz' ) ) ){
		?>
			<div id="ldcc-dialog" title="<?php _e( "Course Cloning", "ld-content-cloner" ); ?>">
				
				<div class="ldcc-success">
					<div>
						<?php echo sprintf( __( "Click %s to edit the cloned Course", "ld-content-cloner"), "<a class='ldcc-course-link' href='#'>".__( "here", "ld-content-cloner" ) . "</a>" ); ?>
					</div>
					<div>
						<?php echo sprintf( __( "Click %s to rename the cloned Course content", "ld-content-cloner"), "<a class='ldcc-course-rename-link' href='#'>".__( "here", "ld-content-cloner" ) . "</a>" ); ?>
					</div>
				</div>

				<div class="ldcc-notice"><?php _e( "Note: Remember to change the Title and Slugs for all the cloned Posts.", "ld-content-cloner"); ?></div>

			</div>
		<?php
		}
	}

}