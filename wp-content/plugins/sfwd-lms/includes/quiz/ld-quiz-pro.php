<?php
/**
 * Extends WP Pro Quiz functionality to meet needs of LearnDash
 *
 * @since 2.1.0
 *
 * @package LearnDash\Quiz
 */



/**
 * Include WP Pro Quiz Plugin
 */

require_once dirname( dirname( __FILE__ ) ) . '/vendor/wp-pro-quiz/wp-pro-quiz.php';



/**
 * LearnDash QuizPro class
 */
class LD_QuizPro {

	public $debug = false;

	/**
	 * LD_QuizPro constructor
	 */
	function __construct() {

		add_action( 'wp_head', array( $this, 'certificate_details' ) );
		add_action( 'wp_pro_quiz_completed_quiz', array( $this, 'wp_pro_quiz_completed' ) );
		add_action( 'save_post', array( $this, 'edit_process' ), 2000 );
		add_action( 'plugins_loaded', array( $this, 'quiz_edit_redirect' ), 1 );

		add_filter( 'ldadvquiz_the_content', 'wptexturize' );
		add_filter( 'ldadvquiz_the_content', 'convert_smilies' );
		add_filter( 'ldadvquiz_the_content', 'convert_chars' );
		add_filter( 'ldadvquiz_the_content', 'wpautop' );
		add_filter( 'ldadvquiz_the_content', 'shortcode_unautop' );
		add_filter( 'ldadvquiz_the_content', 'prepend_attachment' );

		//add_action("the_content", array( $this, 'certificate_link' ));
		if ( ! empty( $_GET['ld_fix_permissions'] ) ) {
			$role = get_role( 'administrator' );
			$role->add_cap( 'wpProQuiz_show' );
			$role->add_cap( 'wpProQuiz_add_quiz' );
			$role->add_cap( 'wpProQuiz_edit_quiz' );
			$role->add_cap( 'wpProQuiz_delete_quiz' );
			$role->add_cap( 'wpProQuiz_show_statistics' );
			$role->add_cap( 'wpProQuiz_reset_statistics' );
			$role->add_cap( 'wpProQuiz_import' );
			$role->add_cap( 'wpProQuiz_export' );
			$role->add_cap( 'wpProQuiz_change_settings' );
			$role->add_cap( 'wpProQuiz_toplist_edit' );
			$role->add_cap( 'wpProQuiz_toplist_edit' );
		}

		add_action( 'wp_ajax_ld_adv_quiz_pro_ajax', array( $this, 'ld_adv_quiz_pro_ajax' ) );
		add_action( 'wp_ajax_nopriv_ld_adv_quiz_pro_ajax', array( $this, 'ld_adv_quiz_pro_ajax' ) );
	}



	/**
	 * Submit quiz and echo JSON representation of the checked quiz answers
	 *
	 * @since 2.1.0
	 *
	 */
	function ld_adv_quiz_pro_ajax() {
		$func = isset( $_REQUEST['func'] ) ? $_REQUEST['func'] : '';
		$data = isset( $_REQUEST['data'] ) ? (array)$_REQUEST['data'] : null;

		switch ( $func ) {
			case 'checkAnswers':
				echo $this->checkAnswers( $data );
				break;
		}

		exit;
	}

	/**
	 * Check answers for submitted quiz
	 *
	 * @since 2.1.0
	 *
	 * @param  array $data Quiz information and answers to be checked
	 * @return string  JSON representation of checked answers
	 */
	function checkAnswers( $data ) {

		$id = @$data['quizId'];
		$view       = new WpProQuiz_View_FrontQuiz();
		$quizMapper = new WpProQuiz_Model_QuizMapper();
		$quiz       = $quizMapper->fetch( $id );
		$questionMapper = new WpProQuiz_Model_QuestionMapper();
		$categoryMapper = new WpProQuiz_Model_CategoryMapper();
		$formMapper     = new WpProQuiz_Model_FormMapper();

		$questionModels = $questionMapper->fetchAll( $id );

		$view->quiz     = $quiz;
		$view->question = $questionModels;
		$view->category = $categoryMapper->fetchByQuiz( $quiz->getId() );

		$question_count = count( $questionModels );
		ob_start();
		$quizData = $view->showQuizBox( $question_count );
		ob_get_clean();

		$json    = $quizData['json'];
		$results = array();
		$question_index = 0;

		foreach ( $data['responses'] as $question_id => $info ) {
			$userResponse = $info['response'];
			
			if ( is_array( $userResponse ) ) {
				foreach ( $userResponse as $key => $value ) {
					if ( $value == 'false' ) {
						$userResponse[ $key ] = false;
					} else if ( $value == 'true' ) {
						$userResponse[ $key ] = true;
					}
				}
			}

			$questionData           = $json[ $question_id ];
			$correct                = false;
			$points                 = 0;
			$statisticsData         = new stdClass();
			$extra                  = array();
			$extra['type']          = $questionData['type'];
			$questionData['points'] = isset( $questionData['points'] ) ? $questionData['points'] : $questionData['globalPoints'];

			foreach ( $questionModels as $questionModel ) {
				if ( $questionModel->getId() == $question_id ) {
					break;
				}
			}

			$question_index++;
			$answer_pointed_activated = $questionModel->isAnswerPointsActivated();

			switch ( $questionData['type'] ) {
				case 'free_answer':
					//$correct = (strtolower( $userResponse ) == strtolower( $questionData['correct'][0] ));
					
					$correct = false;
					foreach($questionData['correct'] as $questionData_correct) {
						if (stripslashes(strtolower( $userResponse )) == stripslashes(strtolower( $questionData_correct ))) {
							
							$correct = true;
							break;
						}
					}
					$points  = ( $correct) ? $questionData['points'] : 0;

					if ( ! $quiz->isDisabledAnswerMark() && empty( $questionData['disCorrect'] ) ) {
						$extra['r'] = $userResponse;
						$extra['c'] = $questionData['correct'];
					}

					break;

				case 'multiple':
					$correct = true;
					$r       = array();

					foreach ( $questionData['correct'] as $answerIndex => $correctAnswer ) {

						$checked = $questionData['correct'][ $userResponse[ $answerIndex ] ];


						if ( $answer_pointed_activated ){

							/**
							 * Points are calculated per answer, add up all the points the user marked correctly
							 */
							if ( ! empty( $correctAnswer ) && ! empty( $userResponse[ $answerIndex ] ) ) {
								$r[ $answerIndex ] = true;
								$points += $questionData['points'][ $answerIndex ];
							} else {
								$r[ $answerIndex ] = false;
							}

							if ( $userResponse != $questionData['correct'] ) {
								$correct = false;
							}

							$points = apply_filters( 'learndash_ques_multiple_answer_pts_each', $points, $questionData, $answerIndex, $correctAnswer, $userResponse );
							$correct = apply_filters( 'learndash_ques_multiple_answer_correct_each', $correct, $questionData, $answerIndex, $correctAnswer, $userResponse );

						} else {

							/**
							 * Points are allocated for the entire question if the user selects all the correct answers and none of
							 * the incorrect answers
							 *
							 * if the user selects an answer that is marked as correct, mark the question true and let the
							 * foreach loop check the next answer
							 *
							 * if they select an incorrect answer, or fail to select a correct answer, mark it false and break
							 * the foreach
							 *
							 * we don't want to break the foreach if the user did not select an incorrect answer
							 */
							if ( ! empty( $correctAnswer ) && ! empty( $userResponse[ $answerIndex ] ) ) {
								$correct = true;
								$r[ $answerIndex ] = true;
								$points = $questionData['points'];
							} elseif ( empty( $correctAnswer ) && ! empty( $userResponse[ $answerIndex ] ) ) {
								$correct = false;
								$r[ $answerIndex ] = false;
								$points = 0;
								break;
							} elseif ( ! empty( $correctAnswer ) && empty( $userResponse[ $answerIndex ] ) ) {
								$correct = false;
								$r[ $answerIndex ] = false;
								$points = 0;
								break;
							}

							$points = apply_filters( 'learndash_ques_multiple_answer_pts_whole', $points, $questionData, $answerIndex, $correctAnswer, $userResponse );
							$correct = apply_filters( 'learndash_ques_multiple_answer_correct_whole', $correct, $questionData, $answerIndex, $correctAnswer, $userResponse );

						}


					}

					if ( ! $quiz->isDisabledAnswerMark() ) {
						$extra['r'] = $userResponse;
						$extra['c'] = $questionData['correct'];
					}

					break;

				case 'single':
					foreach ( $questionData['correct'] as $answerIndex => $correctAnswer ) {
						if ( empty( $userResponse[ $answerIndex ] ) ) {
							continue;
						}

						if ( ! empty( $questionData['diffMode'] ) || ! empty( $correctAnswer ) ) {
							//DiffMode or Correct
							if ( is_array( $questionData['points'] ) ) {
								$points = $questionData['points'][ $answerIndex ];
							} else {
								$points = $questionData['points'];
							}
						}

						if ( ! empty( $correctAnswer) || ! empty( $questionData['disCorrect'] ) ) {
							//Correct
							$correct = true;
						}
					}

					if ( ! $quiz->isDisabledAnswerMark() && empty( $questionData['disCorrect'] ) ) {
						$extra['r'] = $userResponse;
						$extra['c'] = $questionData['correct'];
					}

					break;

				case 'sort_answer':
				case 'matrix_sort_answer':
					$correct                 = true;
					$questionData['correct'] = LD_QuizPro::datapos_array( $question_id, count( $questionData['correct'] ) );

					foreach ( $questionData['correct'] as $answerIndex => $answer ) {
						if ( ! isset( $userResponse[ $answerIndex ] ) || $userResponse[ $answerIndex ] != $answer ) {
							$correct = false;
						} else {
							if ( is_array( $questionData['points'] ) ) {
								$points += $questionData['points'][ $answerIndex ];
							}
						}

						$statisticsData->{$answerIndex} = @$userResponse[ $answerIndex ];
					}

					if ( $correct ) {
						if ( ! is_array( $questionData['points'] ) ) {
							$points = $questionData['points'];
						}
					} else {
						$statisticsData = new stdClass();
					}

					if ( ! $quiz->isDisabledAnswerMark() && empty( $questionData['disCorrect'] ) ) {
						$extra['c'] = $questionData['correct'];
						$extra['r'] = $userResponse;
					} else {
						$statisticsData = new stdClass();
					}

					break;

				case 'cloze_answer':
					$correct = true;
					
					foreach ( $questionData['correct'] as $answerIndex => $correctArray ) {
						if ( ! isset( $userResponse[ $answerIndex ] ) || ! in_array( stripslashes($userResponse[ $answerIndex ]), $correctArray ) ) {
							$correct = false;
							if ( ! $quiz->isDisabledAnswerMark() ) {
								$statisticsData->{$answerIndex} = false;
							}
						} else {
							if ( is_array( $questionData['points'] ) ) {
								$points += $questionData['points'][ $answerIndex ];
							} else {
								$points = $questionData['points'];
							}

							if ( ! $quiz->isDisabledAnswerMark() ) {
								$statisticsData->{$answerIndex} = true;
							}
						}
					}

					if ( ! $quiz->isDisabledAnswerMark() && empty( $questionData['disCorrect'] ) ) {
						$extra['r'] = $userResponse;
						$extra['c'] = $questionData['correct'];
					}

					break;

				case 'assessment_answer':
					$correct = true;
					$points  = intVal( $userResponse );

					break;

				default:
					break;
			}

			if ( ! $quiz->isHideAnswerMessageBox() ) {
				foreach ( $questionModels as $key => $value ) {
					if ( $value->getId() == $question_id ) {
						if ( $correct || $value->isCorrectSameText() ) {
							$extra['AnswerMessage'] = do_shortcode( apply_filters( 'comment_text', $value->getCorrectMsg() ) );
						} else {
							$extra['AnswerMessage'] = do_shortcode( apply_filters( 'comment_text', $value->getIncorrectMsg() ) );
						}

						break;
					}
				}
			}

			$results[ $question_id ] = array(
				'c' => $correct,
				'p' => $points,
				's' => $statisticsData,
				'e' => $extra
			);
		}

		return json_encode( $results );
	}



	/**
	 * Redirect from the Advanced Quiz edit or add link to the Quiz edit or add link
	 *
	 * @since 2.1.0
	 */
	function quiz_edit_redirect() {

		if ( ! empty( $_GET['page'] ) && $_GET['page'] == 'ldAdvQuiz' && empty( $_GET['module'] ) && ! empty( $_GET['action'] ) && $_GET['action'] == 'addEdit' ) {

			if ( ! empty( $_GET['post_id'] ) ) {
				header( 'Location: ' . admin_url( 'post.php?action=edit&post=' . $_GET['post_id'] ) );
				exit;
			} else if ( ! empty( $_GET['quizId'] ) ) {
				$post_id = learndash_get_quiz_id_by_pro_quiz_id( $_GET['quizId'] );

				if ( ! empty( $post_id ) ) {
					header( 'Location: ' . admin_url( 'post.php?action=edit&post=' . $post_id ) );
				} else {
					header( 'Location: ' . admin_url( 'edit.php?post_type=sfwd-quiz' ) );
				}

				exit;
			}

			header( 'Location: ' . admin_url( 'post-new.php?post_type=sfwd-quiz' ) );
			exit;
		}
	}



	/**
	 * Echoes quiz content
	 *
	 * @since 2.1.0
	 *
	 * @param  int $pro_quiz_id
	 */
	static function showQuizContent( $pro_quiz_id ) {
		global $post;

		if ( empty( $post) || $post->post_type == 'sfwd-quiz' ) {
			return '';
		}

		echo LD_QuizPro::get_description( $pro_quiz_id );
	}



	/**
	 * Returns the HTML representation of the quiz description
	 *
	 * @since 2.1.0
	 *
	 * @param  int $pro_quiz_id
	 * @return string HTML representation of quiz description
	 */
	static function get_description( $pro_quiz_id ) {
		$post_id = learndash_get_quiz_id_by_pro_quiz_id( $pro_quiz_id );

		if ( empty( $post_id ) ) {
			return '';
		}

		$quiz = get_post( $post_id );

		if ( empty( $quiz->post_content) ) {
			return '';
		}

		/**
		 * Filter the description of the quiz
		 *
		 * @param  string $quiz->post_content
		 */
		$content = apply_filters( 'ldadvquiz_the_content', $quiz->post_content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		return "<div class='wpProQuiz_description'>" . $content . '</div>';
	}



	/**
	 * Outputs the debugging message to the error log file
	 *
	 * @since 2.1.0
	 *
	 * @param  string $msg Debugging message
	 */
	function debug( $msg ) {
		$original_log_errors = ini_get( 'log_errors' );
		$original_error_log  = ini_get( 'error_log' );
		ini_set( 'log_errors', true );
		ini_set( 'error_log', dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'debug.log' );

		global $processing_id;

		if ( empty( $processing_id ) ) {
			$processing_id = time();
		}

		if ( isset( $_GET['debug'] ) || ! empty( $this->debug ) ) {
			error_log( "[$processing_id] " . print_r( $msg, true ) );
		}
		//Comment This line to stop logging debug messages.

		ini_set( 'log_errors', $original_log_errors );
		ini_set( 'error_log', $original_error_log );
	}



	/**
	 * This function runs when a quiz is completed, and does the action 'wp_pro_quiz_completed_quiz'
	 *
	 * @since 2.1.0
	 */
	function wp_pro_quiz_completed() {
		$this->debug( $_REQUEST );
		$this->debug( $_SERVER );

		if ( ! isset( $_REQUEST['quizId'] ) || ! isset( $_REQUEST['results']['comp']['points'] ) ) {
			return;
		}

		$quiz_id   = $_REQUEST['quizId'];
		$score     = $_REQUEST['results']['comp']['correctQuestions'];
		$points    = $_REQUEST['results']['comp']['points'];
		$result    = $_REQUEST['results']['comp']['result'];
		$timespent = isset( $_POST['timespent'] ) ? $_POST['timespent'] : null;

		$question  = new WpProQuiz_Model_QuestionMapper();
		$questions = $question->fetchAll( $quiz_id );
		$this->debug( $questions );

		if ( empty( $result) ) {
			$total_points = 0;

			foreach ( $questions as $q ) {
				$total_points += $q->getPoints();
			}
		} else {
			$total_points = round( $points * 100 / $result );
		}

		$count = count( $_REQUEST['results'] ) - 1;

		if ( empty( $user_id) ) {
			$current_user = wp_get_current_user();

			if ( empty( $current_user->ID) ) {
				return null;
			}

			$user_id = $current_user->ID;
		}

		$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		$usermeta = maybe_unserialize( $usermeta );

		if ( ! is_array( $usermeta ) ) {
			$usermeta = array();
		}

		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return;
		}

		$ld_quiz_id = @$_REQUEST['quiz']; //$this->get_ld_quiz_id( $quiz_id);

		if ( empty( $ld_quiz_id ) ) {
			return;
		}

		$quiz = get_post_meta( $ld_quiz_id, '_sfwd-quiz', true );
		$passingpercentage = intVal( $quiz['sfwd-quiz_passingpercentage'] );
		$pass = ( $result >= $passingpercentage) ? 1 : 0;
		$quiz = get_post( $ld_quiz_id );

		$this->debug(
			array(
				'quiz' => $ld_quiz_id,
				'quiz_title' => $quiz->post_title,
				'score' => $score,
				'count' => $total_points,
				'pass' => $pass,
				'rank' => '-',
				'time' => time(),
				'pro_quizid' => $quiz_id,
			)
		);

		$quizdata = array(
			'quiz' => $ld_quiz_id,
			'score' => $score,
			'count' => $count,
			'pass' => $pass,
			'rank' => '-',
			'time' => time(),
			'pro_quizid' => $quiz_id,
			'points' => $points,
			'total_points' => $total_points,
			'percentage' => $result,
			'timespent' => $timespent,
		);

		$usermeta[] = $quizdata;

		$quizdata['quiz'] = $quiz;
		$courseid = learndash_get_course_id( $ld_quiz_id );
		$quizdata['course'] = get_post( $courseid );
		$quizdata['questions'] = $questions;

		update_user_meta( $user_id, '_sfwd-quizzes', $usermeta );

		if ( ! empty( $courseid ) ) {
			learndash_ajax_mark_complete( $ld_quiz_id,$quiz_id );
		}

		/**
		 * Does the action 'learndash_quiz_completed'
		 *
		 * @since 2.1.0
		 *
		 * @param  array  	$quizdata
		 * @param  object  $current_user
		 */
		do_action( 'learndash_quiz_completed', $quizdata, $current_user ); //Hook for completed quiz
	}



	/**
	 * Returns the Quiz ID when submitting the Pro Quiz ID
	 *
	 * @since 2.1.0
	 *
	 * @param  int 	$pro_quizid
	 * @return int  quiz ID
	 */
	function get_ld_quiz_id( $pro_quizid ) {
		$quizzes = SFWD_SlickQuiz::get_all_quizzes();
		//$this->debug( $quizzes);

		foreach ( $quizzes as $quiz ) {
			$quizmeta = get_post_meta( $quiz->ID, '_sfwd-quiz', true );
			if ( ! empty( $quizmeta['sfwd-quiz_quiz_pro'] ) && $quizmeta['sfwd-quiz_quiz_pro'] == $pro_quizid ) {
				return $quiz->ID;
			}
		}
	}



	/**
	 * Returns an array of quizes in the string format of "$quiz_id - $quiz_name"
	 *
	 * @since 2.1.0
	 *
	 * @return array  $list  String of $q->getId() . ' - ' . $q->getName()
	 */
	static function get_quiz_list() {
		global $pagenow;

		if ( ! is_admin() || ( $pagenow != 'post.php' && $pagenow != 'post-new.php' ) ) {
			return array();
		}

		$quiz    = new WpProQuiz_Model_QuizMapper();
		$quizzes = $quiz->fetchAll();
		$list    = array();

		if ( ! empty( $quizzes ) ) {
			foreach ( $quizzes as $q ) {
				$list[ $q->getId() ] = $q->getId() . ' - ' . $q->getName();
			}
		}

		return $list;
	}



	/**
	 * Echoes the HTML with inline javascript that contains the JSON representation of the certificate details and continue link details
	 *
	 * @since 2.1.0
	 */
	function certificate_details() {
		global $post;

		if ( empty( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) {
			return;
		}

		$certificate_details = learndash_certificate_details( $post->ID );
		$continue_link = learndash_quiz_continue_link( $post->ID );
		if ( $post->post_type == 'sfwd-quiz' ) {
			echo '<script>';
			echo 'var certificate_details = ' . json_encode( learndash_certificate_details( $post->ID ) ) . ';';
			echo '</script>';

			/** Continue link will appear through javascript **/
			echo '<script>';
			echo "var continue_details ='" . $continue_link . "';";
			echo '</script>';
		}
	}



	/**
	 * Returns the certificate link appended to input HTML content if the Post ID is set, else it only returns the input HTML content
	 *
	 * @since 2.1.0
	 *
	 * @param  string $content HTML
	 * @return string HTML $content or $content concatenated with the certificate link
	 */
	static function certificate_link( $content ) {
		global $post;

		if ( empty( $post->ID ) ) {
			return $content;
		}

		$cd  = learndash_certificate_details( $post->ID );
		$ret = "<a class='btn-blue' href='" . $cd['certificateLink'] . "' target='_blank'>" . __( 'PRINT YOUR CERTIFICATE!', 'learndash' ) . '</a>';
		$ret = $content . $ret;
		return $ret;
	}



	/**
	 * Returns the HTML of the add or edit page for the current quiz.  If advanced quizes are disabled, it returns an empty string.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	static function edithtml() {
		global $pagenow, $post;
		$_post = array( '1' );

		if ( ! empty( $_GET['templateLoadId'] ) ) {
			$_post = $_GET;
		}

		if ( $pagenow == 'post-new.php' && @$_GET['post_type'] == 'sfwd-quiz' || $pagenow == 'post.php' && ! empty( $_GET['post'] ) && @get_post( $_GET['post'] )->post_type == 'sfwd-quiz' ) {
                       //To fix issues with plugins using get_current_screen
                       $screen_file = ABSPATH . '/wp-admin/includes/screen.php';
                       require_once( $screen_file );
                       //To fix issues with plugins using get_current_screen

			$quizId = 0;

			if ( ! empty( $_GET['post'] ) ) {
				$quizId = intval( learndash_get_setting( $_GET['post'], 'quiz_pro', true ) );

				/**
				 * Filter whether advance quiz is disabled or not
				 *
				 * @param  bool
				 */
				if ( apply_filters( 'learndash_disable_advance_quiz', false, get_post( $_GET['post'] ) ) ) {
					return '';
				}
			}

			$pro_quiz = new WpProQuiz_Controller_Quiz();

			ob_start();
			$pro_quiz->route( array(
				'action' => 'addEdit',
				'quizId' => $quizId,
				'post_id' => @$_GET['post'] ),
				$_post
			);
			$return = ob_get_clean();

			return $return;
		}
	}



	/**
	 * Routes to the WpProQuiz_Controller_Quiz controller to output the add or edit page for quizes if not autosaving, post id is set,
	 *   and the current user has permissions to add or edit quizes.  If there is an available template to load, wordpress redirects to
	 *   the proper URL.
	 *
	 * @since 2.1.0
	 *
	 * @param  int $post_id
	 */
	static function edit_process( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $post_id) || empty( $_POST['post_type'] ) ) {
			return '';
		}

		// Check permissions
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		$post = get_post( $post_id );

		/**
		 * Filter whether advance quiz is disabled or not
		 *
		 * @param  bool
		 */
		if ( 'sfwd-quiz' != $post->post_type || empty( $_POST['form'] ) || ! empty( $_POST['disable_advance_quiz_save'] ) || apply_filters( 'learndash_disable_advance_quiz', false, $post ) ) {
			return;
		}

		$quizId   = intval( learndash_get_setting( $post_id, 'quiz_pro', true ) );
		$pro_quiz = new WpProQuiz_Controller_Quiz();
		ob_start();
		$pro_quiz->route( array( 'action' => 'addEdit', 'quizId' => $quizId, 'post_id' => $post_id) );
		ob_get_clean();

		if ( ! empty( $_POST['templateLoad'] ) && ! empty( $_POST['templateLoadId'] ) ) {
			$url = admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . '&templateLoad=' . rawurlencode( $_POST['templateLoad'] ) . '&templateLoadId=' . $_POST['templateLoadId'];
			wp_redirect( $url );
			exit;
		}
	}



	/**
	 * Returns a MD5 checksum on a concatenated string comprised of user id, question id, and pos
	 *
	 * @since 2.1.0
	 *
	 * @param  int 		$question_id
	 * @param  int 		$pos
	 * @return string 	MD5 Checksum
	 */
	static function datapos( $question_id, $pos ) {
		$pos = intval( $pos );;
		return md5( get_current_user_id() . $question_id . $pos );
	}



	/**
	 * Returns an array of MD5 Checksums on a concatenated string comprised of user id, question id, and i, where the array size is count and i is incremented from 0 for each array element
	 *
	 * @since 2.1.0
	 *
	 * @param  int 		$question_id
	 * @param  int 		$count
	 * @return array  	Array of MD5 checksum strings
	 */
	static function datapos_array( $question_id, $count ) {
		$datapos_array = array();
		$user_id       = get_current_user_id();

		for ( $i = 0; $i < $count; $i++ ) {
			$datapos_array[ $i] = md5( $user_id . $question_id . $i );
		}

		return $datapos_array;
	}

}

new LD_QuizPro();
