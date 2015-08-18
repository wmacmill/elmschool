<?php
require_once(dirname(__FILE__).'/wp-pro-quiz/wp-pro-quiz.php');

class LD_QuizPro {
	public $debug = false;
	function __construct() {
		add_action("wp_head", array($this, 'certificate_details'));
		add_action("wp_pro_quiz_completed_quiz", array($this, 'wp_pro_quiz_completed'));
		add_action( 'save_post',array($this, 'edit_process'), 2000);
		add_action( 'plugins_loaded',array($this, 'quiz_edit_redirect'), 1);

		add_filter( 'ldadvquiz_the_content', 'wptexturize'        );
		add_filter( 'ldadvquiz_the_content', 'convert_smilies'    );
		add_filter( 'ldadvquiz_the_content', 'convert_chars'      );
		add_filter( 'ldadvquiz_the_content', 'wpautop'            );
		add_filter( 'ldadvquiz_the_content', 'shortcode_unautop'  );
		add_filter( 'ldadvquiz_the_content', 'prepend_attachment' );

		//add_action("the_content", array($this, 'certificate_link'));
		if(!empty($_GET["ld_fix_permissions"])) {
			$role = get_role('administrator');
		
			$role->add_cap('wpProQuiz_show');
			$role->add_cap('wpProQuiz_add_quiz');
			$role->add_cap('wpProQuiz_edit_quiz');
			$role->add_cap('wpProQuiz_delete_quiz');
			$role->add_cap('wpProQuiz_show_statistics');
			$role->add_cap('wpProQuiz_reset_statistics');
			$role->add_cap('wpProQuiz_import');
			$role->add_cap('wpProQuiz_export');
			$role->add_cap('wpProQuiz_change_settings');
			$role->add_cap('wpProQuiz_toplist_edit');
			$role->add_cap('wpProQuiz_toplist_edit');
		}

	}
	function quiz_edit_redirect() {
		//Redirection from Advanced Quiz Edit or Add link to Quiz Edit or Add link
		if(!empty($_GET["page"]) && $_GET["page"] == "ldAdvQuiz" && empty($_GET["module"]) && !empty($_GET["action"]) && $_GET["action"] == "addEdit") {
			if(!empty($_GET["post_id"])) {
				header("Location: ".admin_url("post.php?action=edit&post=".$_GET["post_id"]));
				exit;
			}
			else if(!empty($_GET["quizId"]))
			{
				$post_id = learndash_get_quiz_id_by_pro_quiz_id($_GET["quizId"]);

				if(!empty($post_id))
				header("Location: ".admin_url("post.php?action=edit&post=".$post_id));
				else
				header("Location: ".admin_url("edit.php?post_type=sfwd-quiz"));

				exit;
			}

			header("Location: ".admin_url("post-new.php?post_type=sfwd-quiz"));
			exit;
		}		
	}
	static function showQuizContent($pro_quiz_id) {
		global $post;
		if(empty($post) || $post->post_type == "sfwd-quiz")
			return "";
		echo LD_QuizPro::get_description($pro_quiz_id);
	}

	static function get_description($pro_quiz_id) {
		$post_id = learndash_get_quiz_id_by_pro_quiz_id($pro_quiz_id);
		if(empty($post_id))
			return "";

		$quiz = get_post($post_id);
		if(empty($quiz->post_content))
			return "";
		$content = apply_filters('ldadvquiz_the_content', $quiz->post_content);
		$content = str_replace(']]>', ']]&gt;', $content);
		return "<div class='wpProQuiz_description'>".$content."</div>";
	}
	function debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if( isset($_GET['debug']) || !empty($this->debug))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
	function wp_pro_quiz_completed() {
		$this->debug($_REQUEST);
		$this->debug($_SERVER);

		if(!isset($_REQUEST['quizId']) || !isset($_REQUEST['results']['comp']['points']))
			return;
		
		$quiz_id = $_REQUEST['quizId'];
		$score = $_REQUEST['results']['comp']['correctQuestions'];
		$points = $_REQUEST['results']['comp']['points'];
		$result = $_REQUEST['results']['comp']['result'];
		$timespent = isset($_POST['timespent'])? $_POST['timespent']:null;

		
		$question = new WpProQuiz_Model_QuestionMapper();
		$questions = $question->fetchAll($quiz_id);
		$this->debug($questions);

		if(empty($result)) {
			$total_points = 0;
			foreach($questions as $q) {
				$total_points += $q->getPoints();
			}
		}
		else
		{
			$total_points = round($points*100/$result);
		}
		$count = count($_REQUEST['results']) - 1;
		
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return null;
			
			$user_id = $current_user->ID;
		}
		$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		$usermeta = maybe_unserialize( $usermeta );
		if ( !is_array( $usermeta ) ) $usermeta = Array();
		
		if(empty($_SERVER['HTTP_REFERER']))
			return;
			
		$ld_quiz_id = @$_REQUEST['quiz']; //$this->get_ld_quiz_id($quiz_id);
		
		if(empty($ld_quiz_id))
		return;
		
		$quiz = get_post_meta($ld_quiz_id, '_sfwd-quiz', true);
		$passingpercentage = intVal($quiz['sfwd-quiz_passingpercentage']);
		$pass = ($result >= $passingpercentage)? 1:0;
		$quiz = get_post($ld_quiz_id);
		$this->debug(array( "quiz" => $ld_quiz_id, "quiz_title" => $quiz->post_title, "score" => $score, "count" => $total_points, "pass" => $pass, "rank" => '-', "time" => time() , 'pro_quizid' => $quiz_id));
		$quizdata = array( "quiz" => $ld_quiz_id, "score" => $score, "count" => $count, "pass" => $pass, "rank" => '-', "time" => time(), 'pro_quizid' => $quiz_id, 'points' => $points, 'total_points' => $total_points, 'percentage' => $result, 'timespent' => $timespent);
		$usermeta[] = $quizdata;

		$quizdata['quiz'] = $quiz;
		$courseid = learndash_get_course_id($ld_quiz_id);
		$quizdata['course'] = get_post($courseid);		
		$quizdata['questions'] = $questions;

		update_user_meta( $user_id, '_sfwd-quizzes', $usermeta );				
		do_action("learndash_quiz_completed", $quizdata, $current_user); //Hook for completed quiz
	}
	function get_ld_quiz_id($pro_quizid) {
		$quizzes = SFWD_SlickQuiz::get_all_quizzes();
		//$this->debug($quizzes);
		foreach($quizzes as $quiz) {
			$quizmeta = get_post_meta( $quiz->ID, '_sfwd-quiz' , true);
			if(!empty($quizmeta['sfwd-quiz_quiz_pro']) && $quizmeta['sfwd-quiz_quiz_pro'] == $pro_quizid)
				return $quiz->ID;
		}
	}
	static function get_quiz_list(){
		global $pagenow;
		if(!is_admin() || ($pagenow != "post.php" && $pagenow != "post-new.php"))
		return array();

		$quiz = new WpProQuiz_Model_QuizMapper();
		$quizzes = $quiz->fetchAll();
		$list = array();
		if(!empty($quizzes))
		foreach($quizzes as $q) {
			$list[$q->getId()] = $q->getId()." - ".$q->getName();
		}
		return $list;
	}
	function certificate_details(){
		global $post;
		if(empty($post) || empty($post->ID) || empty($post->post_type) )
		return;
		
		$certificate_details = learndash_certificate_details($post->ID);
		$continue_link  = learndash_quiz_continue_link($post->ID);
		if($post->post_type == 'sfwd-quiz') {
		echo "<script>";
		echo "var certificate_details = ".json_encode(learndash_certificate_details($post->ID)).";";
		echo "</script>";
		
		/** Continue link will appear threw javascript **/
		echo "<script>";
		echo "var continue_details ='" . $continue_link ."';";
		echo "</script>";
		}
	}
	static function certificate_link($content){
		global $post;
		if(empty($post->ID))
		return $content;

		$cd  = learndash_certificate_details($post->ID);
		$ret = "<a href='".$cd['certificateLink']."' target='_blank'>".__('PRINT YOUR CERTIFICATE!','learndash')."</a>";
			$ret = $content.$ret;
			return $ret;
		}

	static function edithtml() { 
			global $pagenow, $post;
			$_post = array('1');
			if(!empty($_GET["templateLoadId"]))
				$_post = $_GET;

			if($pagenow == "post-new.php" && @$_GET["post_type"] == "sfwd-quiz"  || $pagenow == "post.php" && !empty($_GET["post"]) && @get_post($_GET["post"])->post_type == "sfwd-quiz") {
				$quizId = 0;
				if(!empty($_GET["post"])) {
					$quizId = intval(learndash_get_setting($_GET['post'], "quiz_pro", true));
					if(apply_filters("learndash_disable_advance_quiz", false, get_post($_GET["post"])))
						return '';
				}
				$pro_quiz = new WpProQuiz_Controller_Quiz();
				ob_start();
				$pro_quiz->route(array("action" => "addEdit", "quizId" => $quizId, "post_id" => @$_GET['post']), $_post);
				$return = ob_get_clean();
				//file_put_contents(dirname(__FILE__)."/test.txt", $return);
				return $return;
			}
		}

	static function edit_process($post_id) {//echo "<pre>";print_r($_POST);exit;

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
				return;

			if(empty($post_id) || empty($_POST['post_type']))
				return "";

			// Check permissions
			if ( 'page' == $_POST['post_type'] ) 
			{
				if ( !current_user_can( 'edit_page', $post_id ) )
					return;
			}
			else
			{
				if ( !current_user_can( 'edit_post', $post_id ) )
					return;
			}
			//echo "<pre>";print_r($_POST);exit;
			$post = get_post($post_id);
			if('sfwd-quiz' != $post->post_type || empty($_POST["form"]) || !empty($_POST["disable_advance_quiz_save"]) || apply_filters("learndash_disable_advance_quiz", false, $post))
				return;

			$quizId = intval(learndash_get_setting($post_id, "quiz_pro", true));
			$pro_quiz = new WpProQuiz_Controller_Quiz();
			ob_start();
			$pro_quiz->route(array("action" => "addEdit", "quizId" => $quizId, "post_id" => $post_id));
			ob_get_clean();

			if(!empty($_POST["templateLoad"]) && !empty($_POST['templateLoadId'])) {
				$url = admin_url("post.php?post=".$post_id."&action=edit")."&templateLoad=".rawurlencode($_POST["templateLoad"])."&templateLoadId=".$_POST["templateLoadId"];
				//echo $url;exit;
				wp_redirect($url);
				exit;
			}
		}

	}


new LD_QuizPro();
