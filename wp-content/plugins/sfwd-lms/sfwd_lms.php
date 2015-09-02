<?php
/**
 * @package LearnDash
 * @version 2.0.6.6 0
 */
/*
Plugin Name: LearnDash LMS
Plugin URI: http://www.learndash.com
Description: LearnDash LMS Plugin - Turn your WordPress site into a learning management system.
Version: 2.0.6.8
Author: LearnDash
Author URI: http://www.learndash.com
*/
define("LEARNDASH_VERSION", "2.0.6.8");
require_once(dirname(__FILE__).'/sfwd_cpt.php');
require_once(dirname(__FILE__).'/course_progress.php');
require_once(dirname(__FILE__).'/course_list_shortcode.php');
require_once(dirname(__FILE__).'/course_info_widget.php');
require_once(dirname(__FILE__).'/quiz_pro.php');
require_once(dirname(__FILE__).'/sfwd_editor.php');
require_once(dirname(__FILE__).'/assignment_uploads.php');
require_once(dirname(__FILE__).'/groups.php');
require_once(dirname(__FILE__).'/quizinfo_shortcode.php');
require_once(dirname(__FILE__).'/enroll_users.php');
require_once(dirname(__FILE__).'/quiz_migration.php');


function learndash_enqueueAssets()  {
	// wp_enqueue_script('date-picker-js', plugins_url('assets/jquery-ui.js', __FILE__), array('jquery'), '', true);
	wp_enqueue_style( 'date-picker-css', plugins_url('assets/jquery-ui.css', __FILE__));
	//wp_enqueue_script('date-picker-custom-js', plugins_url('assets/datepicker.js', __FILE__), array('jquery'), '', true);    
}
// to add datepicker scripts
add_action('init', 'learndash_enqueueAssets');
 
function learndash_load_resources() {
	global $pagenow, $post;

	if($pagenow == "post.php" && $post->post_type == "sfwd-quiz" || $pagenow == "post-new.php" && @$_GET["post_type"] == "sfwd-quiz")
    wp_enqueue_script( 'proquiz_admin_js', plugins_url( 'wp-pro-quiz/js/wpProQuiz_admin.min.js', __FILE__ ) );
}
add_action("admin_enqueue_scripts", "learndash_load_resources");
function sfwd_lms_has_access( $post_id, $user_id = null ) {

	if(empty($user_id))
	$user_id = get_current_user_id();
	
	if ( user_can( $user_id, 'manage_options' ) ) 
		return true;
	
	$course_id = learndash_get_course_id($post_id);
	
	if(empty($course_id)) 
		return true;

	if(!empty($post_id) && learndash_is_sample($post_id)) {
		return true;
	}

	$meta = get_post_meta( $course_id, '_sfwd-courses', true );

	if(@$meta['sfwd-courses_course_price_type'] == "open" || @$meta['sfwd-courses_course_price_type'] == "paynow" && empty($meta['sfwd-courses_course_join']) && empty($meta['sfwd-courses_course_price']))
		return true;
	
	
	if(empty($user_id))
		return false;

	if ( !empty( $meta['sfwd-courses_course_access_list'] ) ) 
		$course_access_list = explode( ',', $meta['sfwd-courses_course_access_list'] );
	else 
		$course_access_list = array();
		
	if(in_array( $user_id, $course_access_list )  || learndash_user_group_enrolled_to_course($user_id, $course_id)) {
		$expired = ld_course_access_expired($course_id, $user_id);
		return !$expired; //True if not expired.
	}
	else
		return false;
}

function sfwd_lms_access_redirect( $post_id ) {
	$access = sfwd_lms_has_access( $post_id );
	if ( $access === true ) 
		return true;
	
	$link = get_permalink( learndash_get_course_id($post_id) );
	wp_redirect( $link );
	exit();
}

if ( !class_exists( 'SFWD_LMS' ) ) {
	class SFWD_LMS extends Semper_Fi_Module  {
		public $post_types = Array();
		public $cache_key = '';
		public $quiz_json = '';
		public $count = null;
		
		function __construct() {
			self::$instance =& $this;
			$this->file = __FILE__;
			$this->name = "LMS";
			$this->plugin_name = "SFWD LMS";
			$this->name = 'LMS Options';
			$this->prefix = 'sfwd_lms_';
			$this->parent_option = 'sfwd_lms_options';
			parent::__construct();
			register_activation_hook(   $this->plugin_path['basename'], Array ( $this, 'activate' ) );
			add_action( 'init', Array( $this, 'add_post_types' ), 1 );
			add_filter( 'query_vars', Array( $this, 'add_query_vars' ) );
			add_action( 'parse_request', Array( $this, 'parse_ipn_request' ) );
			add_action( 'generate_rewrite_rules', Array( $this, 'paypal_rewrite_rules' ) );
			add_filter( 'sfwd_cpt_loop', Array( $this, 'cpt_loop_filter' ) );
			add_filter( 'edit_term_count', Array( $this, 'tax_term_count' ), 10, 3 );
			add_action( 'init', Array( $this, 'add_tag_init' ) ); //Initialise the tagging capability here
			add_action( 'plugins_loaded', Array($this, 'i18nize') );	//Add internationalization support
			add_shortcode( 'usermeta', Array( $this, 'usermeta_shortcode' ) );
			if ( is_admin() && get_transient( 'sfwd_lms_rewrite_flush' ) ) {
				add_action( 'admin_init', 'flush_rewrite_rules' );
				set_transient( 'sfwd_cpt_rewrite_flush', false );
			}
			add_action('init', array($this, 'load_template_functions'));

			add_action( 'wp_ajax_select_a_lesson', array($this, 'select_a_lesson_ajax') );
			add_action( 'wp_ajax_select_a_lesson_or_topic', array($this, 'select_a_lesson_or_topic_ajax') );
		}
		function load_template_functions() {
			$this->get_template('learndash_template_functions', array(), true);
		}
 		function add_tag_init()
 		{
				$tag_args = array(
 					'taxonomies' => array('post_tag', 'category')
 					);
				register_post_type('sfwd-courses',$tag_args); //Tag arguments for $post_type='sfwd-courses'
				register_post_type('sfwd-lessons',$tag_args); //Tag arguments for $post_type='sfwd-courses'
				register_post_type('sfwd-quiz',$tag_args); //Tag arguments for $post_type='sfwd-courses'
				
				add_filter('manage_edit-sfwd-lessons_columns', 'add_course_data_columns');
				add_filter('manage_edit-sfwd-quiz_columns', 'add_shortcode_data_columns');
				add_filter('manage_edit-sfwd-quiz_columns', 'add_course_data_columns');
				add_filter('manage_edit-sfwd-topic_columns', 'add_lesson_data_columns');
				add_filter('manage_edit-sfwd-assignment_columns', 'add_lesson_data_columns');
				add_filter('manage_edit-sfwd-assignment_columns', 'add_assignment_data_columns');
				add_filter('manage_edit-sfwd-quiz_columns', 'remove_tags_column');
				add_filter('manage_edit-sfwd-quiz_columns', 'remove_categories_column');

				add_action('manage_sfwd-lessons_posts_custom_column', 'manage_asigned_course_columns', 10, 3);
				add_action('manage_sfwd-quiz_posts_custom_column', 'manage_asigned_course_columns', 10, 3);
				add_action('manage_sfwd-topic_posts_custom_column', 'manage_asigned_course_columns', 10, 3);
				add_action('manage_sfwd-assignment_posts_custom_column', 'manage_asigned_course_columns', 10, 3);
				add_action('manage_sfwd-assignment_posts_custom_column', 'manage_asigned_assignment_columns', 10, 3);
				
				add_action('restrict_manage_posts','restrict_listings_by_course');
				add_filter( 'parse_query','course_table_filter' );
 		}
 		function i18nize(){
			load_plugin_textdomain( 'learndash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 			
 		}
		
		function tax_term_count( $columns, $id, $tax ) {
			if ( empty( $tax ) || ( $tax != 'courses' ) ) return $columns;
			if ( !empty( $_GET ) && !empty( $_GET['post_type'] ) ) {
				$post_type = $_GET['post_type'];
				$wpq = array (		'tax_query' => Array( Array( 'taxonomy' => $tax, 'field' => 'id', 'terms' => $id ) ),
									'post_type' => $post_type,
									'post_status' => 'publish',
									'posts_per_page' => -1
								);
				$q = new WP_Query ($wpq);
				$this->count = $q->found_posts;
				add_filter( 'number_format_i18n', Array( $this, 'column_term_number' ) );
			}
			return $columns;
		}
		
		function column_term_number( $number ) {
			remove_filter( 'number_format_i18n', Array( $this, 'column_term_number' ) );
			if ( $this->count !== null ) {
				$number = $this->count;
				$this->count = null;
			}
			return $number;
		}
		
		function usermeta_shortcode( $attr, $content = null ) {
			extract(shortcode_atts( array( "field" => null ), $attr ) );	
		    global $user_info, $user_ID;
		    get_currentuserinfo();
		    $user_info = get_userdata( $user_ID );
			if ( is_user_logged_in() )
				return $user_info->$field;
			return "";
		}
		
		function cpt_loop_filter( $content ) {
			global $post;
			if ( $post->post_type == 'sfwd-quiz' ) {
				$meta = get_post_meta( $post->ID, '_sfwd-quiz' );
				if ( is_array( $meta ) && !empty( $meta ) ) {
					$meta = $meta[0];
					if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_lesson'] ) ) )
						$content = '';
				}
			}
			return $content;
		}
		
		function activate() {
			set_transient( 'sfwd_lms_rewrite_flush', true );
		}
		
		function add_query_vars($vars) {
			return array_merge( array( 'sfwd-lms' ), $vars );
		}
		
		function parse_ipn_request( $wp ) {
		    if (array_key_exists('sfwd-lms', $wp->query_vars) 
		            && $wp->query_vars['sfwd-lms'] == 'paypal') {
				require_once( 'ipn.php' );
		    }
		}
		
		function paypal_rewrite_rules( $wp_rewrite ) {
			$wp_rewrite->rules = array_merge( array( 'sfwd-lms/paypal' => 'index.php?sfwd-lms=paypal' ), $wp_rewrite->rules );
		}

		function add_post_types() {
			$post = 0;
			if ( is_admin() && !empty( $_GET ) && ( isset( $_GET['post'] ) ) )
				$post_id = $_GET['post'];
			if ( !empty( $post_id ) ) {
				$this->quiz_json = get_post_meta( $post_id, '_quizdata', true );
				if ( !empty( $this->quiz_json ) )
					$this->quiz_json = $this->quiz_json['workingJson'];
			}
			$options = get_option('sfwd_cpt_options');
			
			$level1 = $level2 = $level3 = $level4 = $level5 = ''; 
			if ( !empty( $options['modules'] ) ) {
				$options = $options['modules'];
				if ( !empty( $options['sfwd-quiz_options'] ) ) {
					$options = $options['sfwd-quiz_options'];
					foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) {
						$$level = '';
						if ( !empty( $options["sfwd-quiz_{$level}"] ) )
							$$level = $options["sfwd-quiz_{$level}"];
					}
				}
			}

			if ( empty( $this->quiz_json ) ) $this->quiz_json = '{"info":{"name":"","main":"","results":"","level1":"' . $level1 . '","level2":"' . $level2 . '","level3":"' . $level3 . '","level4":"' . $level4 . '","level5":"' . $level5 . '"}}';
			$posts_per_page = get_option( 'posts_per_page' );

			$course_capabilities = array(
								            'read_post' => 'read_course',
								            'publish_posts' => 'publish_courses',
								            'edit_posts' => 'edit_courses',
								            'edit_others_posts' => 'edit_others_courses',
								            'delete_posts' => 'delete_courses',
								            'delete_others_posts' => 'delete_others_courses',
								            'read_private_posts' => 'read_private_courses',
								            'delete_post' => 'delete_course',
								            'edit_published_posts'	=> 'edit_published_courses',
								            'delete_published_posts'	=> 'delete_published_courses',
								        );
			if(is_admin()) {
				$admin = get_role('administrator');
				if(!$admin->has_cap('edit_courses')) {
					foreach ($course_capabilities as $key => $cap) {
						$admin->add_cap($cap);
					}
				}
			}
			
			  $lesson_topic_labels = array(
				'name' => __('Topics', 'learndash'),
				'singular_name' => __('Topic', 'learndash'),
				'add_new' => __('Add New', 'learndash'),
				'add_new_item' => __('Add New Topic', 'learndash'),
				'edit_item' => __('Edit Topic', 'learndash'),
				'new_item' => __('New Topic', 'learndash'),
				'all_items' => __( 'Topics', 'learndash'),
				'view_item' => __('View Topic', 'learndash'),
				'search_items' => __('Search Topics', 'learndash'),
				'not_found' =>  __('No Topics found', 'learndash'),
				'not_found_in_trash' => __('No Topics found in Trash', 'learndash'), 
				'parent_item_colon' => '',
				'menu_name' => __('Topics', 'learndash')
			  );
			 $quiz_labels = array(
			 	'name' => __('Quizzes', 'learndash'),
				'singular_name' => __('Quiz', 'learndash'),
				'add_new' => __('Add New', 'learndash'),
				'add_new_item' => __('Add New Quiz', 'learndash'),
				'edit_item' => __('Edit Quiz', 'learndash'),
				'new_item' => __('New Quiz', 'learndash'),
				'all_items' => __( 'Quizzes', 'learndash'),
				'view_item' => __('View Quiz', 'learndash'),
				'search_items' => __('Search Quizzes', 'learndash'),
				'not_found' =>  __('No Quizzes found', 'learndash'),
				'not_found_in_trash' => __('No Quizzes found in Trash', 'learndash'), 
				'parent_item_colon' => '',
			 	);
			 $lesson_labels = array(
			 	'name' => __('Lessons', 'learndash'),
				'singular_name' => __('Lesson', 'learndash'),
				'add_new' => __('Add New', 'learndash'),
				'add_new_item' => __('Add New Lesson', 'learndash'),
				'edit_item' => __('Edit Lesson', 'learndash'),
				'new_item' => __('New Lesson', 'learndash'),
				'all_items' => __( 'Lessons', 'learndash'),
				'view_item' => __('View Lesson', 'learndash'),
				'search_items' => __('Search Lessons', 'learndash'),
				'not_found' =>  __('No Lessons found', 'learndash'),
				'not_found_in_trash' => __('No Lessons found in Trash', 'learndash'), 
				'parent_item_colon' => '',
			 	);
			 $course_labels = array(
			 	'name' => __('Courses', 'learndash'),
				'singular_name' => __('Course', 'learndash'),
				'add_new' => __('Add New', 'learndash'),
				'add_new_item' => __('Add New Course', 'learndash'),
				'edit_item' => __('Edit Course', 'learndash'),
				'new_item' => __('New Course', 'learndash'),
				'all_items' => __( 'Courses', 'learndash'),
				'view_item' => __('View Course', 'learndash'),
				'search_items' => __('Search Courses', 'learndash'),
				'not_found' =>  __('No Courses found', 'learndash'),
				'not_found_in_trash' => __('No Courses found in Trash', 'learndash'), 
				'parent_item_colon' => '',
			 	);			  
			if ( empty( $posts_per_page ) ) $posts_per_page = 5;
			$post_args = Array(
					Array(
					  'plugin_name' => __('Course', 'learndash'),
					  'slug_name' => 'courses',
					  'post_type' => 'sfwd-courses',
					  'template_redirect' => true,
					//  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'hierarchical' => 'false', 'supports' => array ( 'title', 'editor', 'thumbnail' , 'author', 'comments', 'revisions') , 'labels' => $course_labels, 'capability_type' => 'course', 'capabilities' => $course_capabilities, 'map_meta_cap' => true),
					  'options_page_title' => __("PayPal Settings", "learndash"),
					  'fields' => 
					  Array( 'course_materials' => 
					    Array(
					      'name' => __('Course Materials', 'learndash'),
					      'type' => 'textarea',
					      'help_text' => __('Options for course materials', 'learndash'),
					    ),
					   'course_price_type' => 
					    Array(
					      'name' => __('Course Price Type', 'learndash'),
					      'type' => 'select',
					      'initial_options' => 
								Array(	'open' => __('Open', 'learndash'),
										'closed' => __('Closed', 'learndash'),
										'free' => __('Free', 'learndash'),
										'paynow' => __('Buy Now', 'learndash'),
										'subscribe'	=> __('Recurring', 'learndash'),
								),
						   'default' => 'buynow',
						   'help_text' => __('Is it open to all, free join, one time purchase, or a recurring subscription?', 'learndash'),
					    ),
					    'custom_button_url' => 
					    Array(
					      'name' => __('Custom Button URL', 'learndash'),
					      'type' => 'text',
					      'placeholder'	=> __('Optional', 'learndash'),
					      'help_text' => __('Entering a URL in this field will enable the "Take This Course" button. The button will not display if this field is left empty.', 'learndash'),
					    ),
						 'course_price' => 
					    Array(
					      'name' => __('Course Price', 'learndash'),
					      'type' => 'text',
					      'help_text' => __('Enter course price here. Leave empty if the course is free.', 'learndash'),
					    ),
						'course_price_billing_cycle' => 
					    Array(
					      'name' => __('Billing Cycle', 'learndash'),
					      'type' => 'html',
					       'default' => $this->learndash_course_price_billing_cycle_html(),
						   'help_text' => __('Billing Cycle for the recurring payments in case of a subscription.', 'learndash'),
					    ),
						/*'course_no_of_cycles' => 
					    Array(
					      'name' => __('No of Cycles', 'learndash'),
					      'type' => 'text',
					       'default' => 0,
						   'help_text' => __('No. of billing cycles. 0 for infinite cycles.', 'learndash'),
					    ),
						'course_remove_access_on_subscription_end' => 
					    Array(
					      'name' => __('Remove access at end of cycle?', 'learndash'),
					      'type' => 'checkbox',
					       'default' => 0,
						   'help_text' => __('Check the box if you want to remove users access when payment cycle ends or if subscription is cancelled?', 'learndash'),
					    ),*/
						/*'course_join' => 
					    Array(
					      'name' => __('Free Course?', 'learndash'),
					      'type' => 'checkbox',
						  'default' => '',
					      'help_text' => __('This is applicable only for free courses. Leave Course Price empty.', 'learndash'),
					    ),*/
						'course_access_list' => Array(
						  'name' => __('Course Access List', 'learndash'),
						  'type' => 'textarea',
						  'help_text' => __('This field is auto-populated with the UserIDs of those who have access to this course.', 'learndash')
						),
						'course_lesson_orderby' => Array( 
							'name' => __('Sort Lesson By', 'learndash'),
							'type' => 'select',
							'initial_options' => 
								Array(	''		=> __('Use Default', 'learndash'),
										'title'	=> __('Title', 'learndash'),
										'date'	=> __('Date', 'learndash'),
										'menu_order' => __('Menu Order', 'learndash')
								),
							'default' => '',
							'help_text' => __('Choose the sort order of lessons in this course.', 'learndash')
							),
						'course_lesson_order' => Array( 
							'name' => __('Sort Lesson Direction', 'learndash'),
							'type' => 'select',
							'initial_options' => 
								Array(	''		=> __('Use Default', 'learndash'),
										'ASC'	=> __('Ascending', 'learndash'),
										'DESC'	=> __('Descending', 'learndash')
								),
							'default' => '',
							'help_text' => __('Choose the sort order of lessons in this course.', 'learndash')
							),
						'course_prerequisite' => Array( 'name' => __('Course prerequisites', 'learndash'), 'type' => 'select', 'help_text' => __('Select a course as prerequisites to view this course', 'learndash'), 'initial_options' => '' , 'default' => ''),						
						'course_disable_lesson_progression' => Array( 
							'name' => __('Disable Lesson Progression', 'learndash'),
							'type' => 'checkbox',
							'default' => 0,
							'help_text' => __('Disable the feature that allows attempting lessons only in allowed order.', 'learndash')
							),
						'expire_access' => 
					    Array(
					      'name' => __('Expire Access', 'learndash'),
					      'type' => 'checkbox',
					      'help_text' => __('Leave this field unchecked if access never expires.', 'learndash'),
					    ),
					    'expire_access_days' => 
					    Array(
					      'name' => __('Expire Access After (days)', 'learndash'),
					      'type' => 'text',
					      'help_text' => __('Enter the number of days a user has access to this course.', 'learndash'),
					    ),
						'expire_access_delete_progress' => 
					    Array(
					      'name' => __('Delete Course and Quiz Data After Expiration', 'learndash'),
					      'type' => 'checkbox',
					      'help_text' => __('Select this option if you want the user\'s course progress to be deleted when their access expires.', 'learndash'),
					    ),					    
					  ),
					  'default_options' => Array(
						'paypal_email'		=> Array( 'name' => __('PayPal Email', 'learndash'), 'help_text' => __('Enter your PayPal email here.', 'learndash'), 'type' => 'text' ),
						'paypal_currency'	=> Array( 'name' => __('PayPal Currency', 'learndash'), 'help_text' => __('Enter the currency code for transactions.', 'learndash'), 'type' => 'text', 'default' => 'USD' ),
						'paypal_country'	=> Array( 'name' => __('PayPal Country', 'learndash'), 'help_text' => __('Enter your country code here.', 'learndash'), 'type' => 'text', 'default' => 'US' ),
						'paypal_cancelurl'	=> Array( 'name' => __('PayPal Cancel URL', 'learndash'), 'help_text' => __('Enter the URL used for purchase cancellations.', 'learndash'), 'type' => 'text', 'default' => get_home_url() ),
						'paypal_returnurl'	=> Array( 'name' => __('PayPal Return URL', 'learndash'), 'help_text' => __('Enter the URL used for completed purchases (typically a thank you page).', 'learndash'), 'type' => 'text', 'default' => get_home_url() ),
						'paypal_notifyurl'	=> Array( 'name' => __('PayPal Notify URL', 'learndash'), 'help_text' => __('Enter the URL used for IPN notifications.', 'learndash'), 'type' => 'text', 'default' => get_home_url() . "/sfwd-lms/paypal" ),
						'paypal_sandbox'	=> Array( 'name' => __('Use PayPal Sandbox', 'learndash'), 'help_text' => __('Check to enable the PayPal sandbox.', 'learndash') ),
					  ),
					),
					Array(
					  'plugin_name' => __('Lesson', 'learndash'),
					  'slug_name' => 'lessons',
					  'post_type' => 'sfwd-lessons',
					  'template_redirect' => true,
					//  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'has_archive' => false, 'supports' => array ( 'title', 'thumbnail', 'editor', 'page-attributes' , 'author', 'comments', 'revisions'), 'labels' => $lesson_labels , 'capability_type' => 'course', 'capabilities' => $course_capabilities, 'map_meta_cap' => true),
					  'fields' => Array(
							'course' => Array( 'name' => __('Associated Course', 'learndash'), 'type' => 'select', 'help_text' => __('Associate with a course.', 'learndash'), 'default' => '' , 'initial_options' => $this->select_a_course('sfwd-lessons') ),
							'forced_lesson_time' => Array( 'name' => __('Forced Lesson Timer', 'learndash'), 'type' => 'text', 'help_text' => __('Minimum time a user has to spend on Lesson page before it can be marked complete. Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec', 'learndash'), 'default' => '' ),		
							'lesson_assignment_upload' => Array( 'name' => __('Upload Assignment', 'learndash'), 'type' => 'checkbox', 'help_text' => __('Check this if you want to make it mandatory to upload assignment', 'learndash'), 'default' => 0 ),
							'auto_approve_assignment' => Array( 'name' => __('Auto Approve Assignment', 'learndash'), 'type' => 'checkbox', 'help_text' => __('Check this if you want to auto-approve the uploaded assignment', 'learndash'), 'default' => 0 ),
							'sample_lesson' => Array( 'name' => __('Sample Lesson', 'learndash'), 'type' => 'checkbox', 'help_text' => __('Check this if you want this lesson and all its topics to be available for free.', 'learndash'), 'default' => 0 ),
							'visible_after' => Array( 'name' => __('Make lesson visible X days after sign-up', 'learndash'), 'type' => 'text', 'help_text' => __('Make lesson visible ____ days after sign-up', 'learndash'), 'default' => 0 ),
                            'visible_after_specific_date' => Array( 'name' => __('Make lesson visible on specific date', 'learndash'), 'type' => 'text', 'help_text' => __('Set the date that you would like this lesson to become available.', 'learndash')),                            
							),
					  'default_options' => Array(
												'orderby' => Array( 
													'name' => __('Sort By', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'title'	=> __('Title', 'learndash'),
																'date'	=> __('Date', 'learndash'),
																'menu_order' => __('Menu Order', 'learndash')
														),
													'default' => 'date',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												'order' => Array( 
													'name' => __('Sort Direction', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'ASC'	=> __('Ascending', 'learndash'),
																'DESC'	=> __('Descending', 'learndash')
														),
													'default' => 'DESC',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												'posts_per_page' => Array(
														'name' => __('Posts Per Page', 'learndash'),
														'type' => 'text',
														'help_text' => __('Enter the number of posts to display per page.', 'learndash'),
														'default' => $posts_per_page
													),
												)
					),
					Array(
					  'plugin_name' => __('Quiz', 'learndash'),
					  'slug_name' => 'quizzes',
					  'post_type' => 'sfwd-quiz',
					  'template_redirect' => true,
					//  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array(  'hierarchical' => false, 'supports' => array ( 'title', 'thumbnail', 'editor' , 'author', 'page-attributes' ,'comments', 'revisions' ) , 'labels' => $quiz_labels, 'capability_type' => 'course', 'capabilities' => $course_capabilities, 'map_meta_cap' => true),
					  'fields' => 
					  Array( 
						'repeats' => Array( 'name' => __('Repeats', 'learndash'), 'type' => 'text', 'help_text' => __('Number of repeats allowed for quiz', 'learndash'), 'default' => '' ),
						'threshold' => Array( 'name' => __('Certificate Threshold', 'learndash'), 'type' => 'text', 'help_text' => __('Minimum score required to award a certificate, between 0 and 1 where 1 = 100%.', 'learndash'), 'default' => '0.8' ),
						'passingpercentage' => Array( 'name' => __('Passing Percentage', 'learndash'), 'type' => 'text', 'help_text' => __('Passing percentage required to pass the quiz (number only). e.g. 80 for 80%.', 'learndash'), 'default' => '80' ),
						'course' => Array( 'name' => __('Associated Course', 'learndash'), 'type' => 'select', 'help_text' => __('Associate with a course.', 'learndash'), 'default' => '', 'initial_options' => $this->select_a_course('sfwd-quiz') ),
						'lesson' => Array( 'name' => __('Associated Lesson', 'learndash'), 'type' => 'select', 'help_text' => __('Optionally associate a quiz with a lesson.', 'learndash'), 'default' => '' ),
						'certificate' => Array( 'name' => __('Associated Certificate', 'learndash'), 'type' => 'select', 'help_text' => __('Optionally associate a quiz with a certificate.', 'learndash'), 'default' => '' ),
						'quiz_pro' => Array( 'name' => __('Associated Settings', 'learndash'), 'type' => 'select', 'help_text' => __('If you imported a quiz, use this field to select it. Otherwise, create new settings below. After saving or publishing, you will be able to add questions.', 'learndash'). '<a style="display:none" id="advanced_quiz_preview" class="wpProQuiz_prview" href="#">'.__('Preview', 'learndash').'</a>', 'initial_options' => (array(0 => __('-- Select Settings --', 'learndash')) + LD_QuizPro::get_quiz_list()) , 'default' => ''),
						'quiz_pro_html' => Array(
                                              'name' => __('Quiz Options', 'learndash'),
                                              'type' => 'html',
                                              'help_text' => '',
                                                  'label' => 'none',
                                                  'save' => false,
                                                  'default' => LD_QuizPro::edithtml()
                                            ),
						),
					  'default_options' => Array(
						 )
					),
					Array(
					  'plugin_name' => __('Lesson Topic', 'learndash'),
					  'slug_name' => 'topic',
					  'post_type' => 'sfwd-topic',
					  'template_redirect' => true,
					//  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'supports' => array ( 'title', 'thumbnail', 'editor', 'page-attributes' , 'author', 'comments', 'revisions'),  'has_archive' => false, 'labels' => $lesson_topic_labels, 'capability_type' => 'course', 'capabilities' => $course_capabilities, 'map_meta_cap' => true,'taxonomies' => array('post_tag')),
					  'fields' => Array(
							'course' => Array( 'name' => __('Associated Course', 'learndash'), 'type' => 'select', 'help_text' => __('Associate with a course.', 'learndash'), 'default' => '', 'initial_options' => $this->select_a_course('sfwd-topic')  ),
							'lesson' => Array( 'name' => __('Associated Lesson', 'learndash'), 'type' => 'select', 'help_text' => __('Optionally associate a quiz with a lesson.', 'learndash'), 'default' => '' , 'initial_options' => $this->select_a_lesson()),
							'forced_lesson_time' => Array( 'name' => __('Forced Topic Timer', 'learndash'), 'type' => 'text', 'help_text' => __('Minimum time a user has to spend on Topic page before it can be marked complete. Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec', 'learndash'), 'default' => '' ),		
							'lesson_assignment_upload' => Array( 'name' => __('Upload Assignment', 'learndash'), 'type' => 'checkbox', 'help_text' => __('Check this if you want to make it mandatory to upload assignment', 'learndash'), 'default' => 0 ),
							'auto_approve_assignment' => Array( 'name' => __('Auto Approve Assignment', 'learndash'), 'type' => 'checkbox', 'help_text' => __('Check this if you want to auto-approve the uploaded assignment', 'learndash'), 'default' => 0 ),
							//'visible_after' => Array( 'name' => __('Make lesson visible X days after sign-up', 'learndash'), 'type' => 'text', 'help_text' => __('Make lesson visible ____ days after sign-up', 'learndash'), 'default' => 0 ),
							),
					  'default_options' => Array(
												'orderby' => Array( 
													'name' => __('Sort By', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'title'	=> __('Title', 'learndash'),
																'date'	=> __('Date', 'learndash'),
																'menu_order' => __('Menu Order', 'learndash')
														),
													'default' => 'date',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												'order' => Array( 
													'name' => __('Sort Direction', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'ASC'	=> __('Ascending', 'learndash'),
																'DESC'	=> __('Descending', 'learndash')
														),
													'default' => 'DESC',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												)
					),
				/*	Array(
						  'plugin_name' => __('Assignment', 'learndash'),
						  'slug_name' => 'assignment',
						  'post_type' => 'sfwd-assignment',
						  'template_redirect' => true,
						  'cpt_options' => Array( 'supports' => array ( 'title', 'comments', 'author' ), 'exclude_from_search' => true, 'publicly_queryable' => true, 'show_in_nav_menus' => false , 'show_in_menu'	=> true, 'has_archive' => false),
						  'fields' => Array(),
					),*/
					
				);
			$cert_defaults = 	Array(
										'shortcode_options' => Array(
												'name' => 'Shortcode Options',
												'type' => 'html',
												'default' => '',
												'save' => false,
												'label' => 'none'
											),
										);
			$post_args[] = 
					Array(
					  'plugin_name' => __('Certificates', 'learndash'),
					  'slug_name' => 'certificates',
					  'post_type' => 'sfwd-certificates',
					  'template_redirect' => false,
					  'fields' => Array(),
					  'default_options' => $cert_defaults,
					  'cpt_options' => Array( 'exclude_from_search' => true, 'has_archive' => false, 'hierarchical' => 'false', 'supports' => array ( 'title', 'editor', 'thumbnail' , 'author',  'revisions') , 'capability_type' => 'course', 'capabilities' => $course_capabilities, 'map_meta_cap' => true)
					);
			if ( current_user_can( 'manage_options' ) ) {
				$post_args[] = 
						Array(
						  'plugin_name' => __('Transactions', 'learndash'),
						  'slug_name' => 'transactions',
						  'post_type' => 'sfwd-transactions',
						  'template_redirect' => false,
						  'cpt_options' => Array( 'supports' => array ( 'title', 'custom-fields' ), 'exclude_from_search' => true, 'publicly_queryable' => false, 'show_in_nav_menus' => false , 'show_in_menu'	=> 'edit.php?post_type=sfwd-courses'),
						  'fields' => Array(),
						  'default_options' => Array( null => Array( 'type' => 'html', 'save' => false, 'default' => __('Click the Export button below to export the transaction list.', 'learndash') ) )
						);
				add_action( 'admin_init', Array( $this, 'trans_export_init' ) );
			}
			$post_args = apply_filters("learndash_post_args", $post_args);	
			add_action( 'admin_init', Array( $this, 'quiz_export_init' ) );
			add_action( 'admin_init', Array( $this, 'course_export_init' ) );
			add_action( 'show_user_profile', Array( $this, 'show_course_info' ) );
			add_action( 'edit_user_profile', Array( $this, 'show_course_info' ) );
			
			foreach( $post_args as $p )
				$this->post_types[$p['post_type']] = new SFWD_CPT_Instance( $p );
			//add_action( 'publish_sfwd-courses', Array( $this, 'add_course_tax_entry' ), 10, 2 );
			add_action( 'init', Array( $this, 'tax_registration' ), 11 );
			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter( "{$quiz_prefix}display_settings", Array( $this, "quiz_display_settings" ), 10, 3 );
			$sfwd_courses = $this->post_types['sfwd-courses'];
			$courses_prefix = $sfwd_courses->get_prefix();
			add_filter( "{$courses_prefix}display_settings", Array( $this, "course_display_settings" ), 10, 3 );
		}

		function show_course_info( $user ) {
			$user_id = $user->ID;
			echo "<h3>" . __('Course Info', 'learndash') . "</h3>";
			echo $this->get_course_info($user_id);
		}

		
		
		static function get_course_info($user_id) {
			$courses_registered = ld_get_mycourses($user_id);
			
			$usermeta = get_user_meta( $user_id, '_sfwd-course_progress', true );
			$course_progress = empty($usermeta) ?  false : $usermeta;

			$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
			$quizzes = empty($usermeta) ?  false : $usermeta;

			return SFWD_LMS::get_template('course_info_shortcode', array(
				'user_id' => $user_id,
				'courses_registered' => $courses_registered,
				'course_progress' => $course_progress,
				'quizzes' => $quizzes
			));
		}
		

		function learndash_course_price_billing_cycle_save($post_id) {
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
				
			if(isset($_POST['course_price_billing_p3'])) {
				update_post_meta($post_id, "course_price_billing_p3", $_POST['course_price_billing_p3']);
			}
			if(isset($_POST['course_price_billing_t3'])) {
				update_post_meta($post_id, "course_price_billing_t3", $_POST['course_price_billing_t3']);
			}
		}
		function learndash_course_price_billing_cycle_html() {
			global $pagenow;
			add_action( 'save_post', array($this, 'learndash_course_price_billing_cycle_save'));

			if($pagenow == "post.php" && !empty($_GET['post'])) {
				$post_id = $_GET['post'];
				$post = get_post($post_id);

				if($post->post_type != "sfwd-courses")
				return;			

				$course_price_billing_p3 = get_post_meta($post_id, "course_price_billing_p3",  true);
				$course_price_billing_t3 = get_post_meta($post_id, "course_price_billing_t3",  true);
				$settings = learndash_get_setting($post_id);
				if(!empty($settings) && $settings["course_price_type"] == "paynow" && empty($settings["course_price"]))
					if(empty($settings["course_join"]))
						learndash_update_setting($post_id, "course_price_type", "open");
					else
						learndash_update_setting($post_id, "course_price_type", "free");
			}
			else
			if($pagenow == "post-new.php" && !empty($_GET["post_type"]) && $_GET["post_type"] == "sfwd-courses") {
				$post_id = 0;
				$course_price_billing_p3 = $course_price_billing_t3 = '';				
			}
			else
			{
				return;
			}
			
			$selected_D = $selected_W = $selected_M = $selected_Y = "";
			${"selected_".$course_price_billing_t3} = 'selected="selected"';
			return '<input name="course_price_billing_p3" type="text" value="'.$course_price_billing_p3.'" size="2"/> 
					<select class="select_course_price_billing_p3" name="course_price_billing_t3">
						<option value="D" '.$selected_D.'>'.__("day(s)", "learndash").'</option>
						<option value="W" '.$selected_W.'>'.__("week(s)", "learndash").'</option>
						<option value="M" '.$selected_M.'>'.__("month(s)", "learndash").'</option>
						<option value="Y" '.$selected_Y.'>'.__("year(s)", "learndash").'</option>
					</select>';
		}
		static function course_progress_data($course_id = null) {
			set_time_limit(0);
			global $wpdb;

			$current_user = wp_get_current_user();
			if(empty($current_user) || !current_user_can("manage_options") && !is_group_leader($current_user->ID))
				return;
			
			if(isset($_GET['group_id']))
				$group_id = $_GET['group_id'];
				
			if(!current_user_can('manage_options') && is_group_leader($current_user->ID)) {		
				$users_group_ids = learndash_get_administrators_group_ids($current_user->ID);
				
				if(isset($group_id)) {
					if(!in_array($group_id, $users_group_ids))
					return;
					$users_group_ids = array($group_id);
				}


				$user_ids = array();
				foreach ($users_group_ids as $user_group_id) {
					$user_ids_of_group = learndash_get_groups_user_ids($user_group_id);
					foreach ($user_ids_of_group as $user_id) {
						$user_ids[$user_id] = $user_id;
					}
				}
				$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE `ID` IN (".implode(",",$user_ids).")");
			}
			else if(current_user_can('manage_options')) {
				if(!empty($group_id))
				{
					$user_ids = learndash_get_groups_user_ids($group_id);
					$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE `ID` IN (".implode(",",$user_ids).")");
				}
				else
				$users = get_users();
			}
			else
				return array();

			$course_access_list = array();

			$course_progress_data = array();
			set_time_limit( 0 );
			
			$quiz_titles = Array();
			$lessons = array();

			if(!empty($course_id))
				$courses = array(get_post($course_id));
			else
				$courses = ld_course_list(array('array' => true));


			if ( !empty( $users ) )
			foreach( $users as $u ) {
				$user_id = $u->ID;
				$usermeta = get_user_meta( $user_id, '_sfwd-course_progress', true );
				if(!empty($usermeta))
				$usermeta = maybe_unserialize($usermeta);

				if(!empty($courses[0]))
				foreach ($courses as $course) {
					$c = $course->ID;

					if(empty($course->post_title) || !sfwd_lms_has_access($c, $user_id))
						continue;

					$cv = !empty($usermeta[$c])? $usermeta[$c]:array("completed" => "", "total" => "");
                    
                    $course_completed_meta = get_user_meta($user_id, "course_completed_".$course->ID, true);
                    (empty($course_completed_meta)) ? $course_completed_date = "" : $course_completed_date = date("F j, Y H:i:s", $course_completed_meta);

					$row = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'course_id' => $c, 'course_title' => $course->post_title, 'total_steps' => $cv['total'], 'completed_steps' => $cv['completed'], 'course_completed' => (!empty($cv['total']) && $cv['completed'] >= $cv['total'])? "YES":"NO" , 'course_completed_on' => $course_completed_date);
					$i = 1;
					if(!empty($cv['lessons']))
					foreach($cv['lessons'] as $lesson_id => $completed) {
						if(!empty($completed)) {
							if(empty($lessons[$lesson_id]))
							$lesson = $lessons[$lesson_id] = get_post($lesson_id);
							else
							$lesson = $lessons[$lesson_id];
							
							$row['lesson_completed_'.$i] = $lesson->post_title;
							$i++;
						}
					}
					$course_progress_data[] = $row;
				}
			}
			else
			{
				$course_progress_data[] = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'status' => __('No attempts', 'learndash'));
			}

			return $course_progress_data;
		}
		function course_export_init() {
			error_reporting(0);
			set_time_limit(0);

			if ( !empty( $_REQUEST['courses_export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );

				$content = SFWD_LMS::course_progress_data();

				if ( empty( $content ) ) {
					$content[] = Array( 'status' => __('No attempts', 'learndash'));
				}
				require_once( dirname(__FILE__) . '/parsecsv.lib.php' );
				$csv = new lmsParseCSV();
				$csv->output( true, 'courses.csv', $content, array_keys( reset( $content ) ) );
				die();
			}
		}
		function courses_filter_submit( $submit ) {
			$submit['courses_export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export User Course Data &raquo;', 'learndash') );
			return $submit;
		}

		
		function quiz_export_init() {
			error_reporting(0);
			set_time_limit(0);
			global $wpdb;
			$current_user = wp_get_current_user();
			if(empty($current_user) || !current_user_can("manage_options") && !is_group_leader($current_user->ID))
				return;

			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter($quiz_prefix . 'submit_options', Array( $this, 'quiz_filter_submit' ) );
			if ( !empty( $_REQUEST['quiz_export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				require_once( 'parsecsv.lib.php' );
				$content = array();
				set_time_limit( 0 );
				//Need ability to export quiz results for group to CSV

				if(isset($_GET['group_id']))
					$group_id = $_GET['group_id'];
					
				if(!current_user_can('manage_options') && is_group_leader($current_user->ID)) {		
					$users_group_ids = learndash_get_administrators_group_ids($current_user->ID);
					
					if(isset($group_id)) {
						if(!in_array($group_id, $users_group_ids))
						return;
						$users_group_ids = array($group_id);
					}

					$user_ids = array();
					foreach ($users_group_ids as $user_group_id) {
						$user_ids_of_group = learndash_get_groups_user_ids($user_group_id);
						foreach ($user_ids_of_group as $user_id) {
							$user_ids[$user_id] = $user_id;
						}
					}
					$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE `ID` IN (".implode(",",$user_ids).")");
				}
				else if(current_user_can('manage_options')) {
					if(!empty($group_id))
					{
						$user_ids = learndash_get_groups_user_ids($group_id);
						$users = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE `ID` IN (".implode(",",$user_ids).")");
					}
					else
					$users = get_users( Array( 'meta_key' => '_sfwd-quizzes' ) );
				}
				
				$quiz_titles = Array();
				if ( !empty( $users ) )
					foreach( $users as $u ) {
						$user_id = $u->ID;
						$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
						if ( !empty( $usermeta ) ) {
							foreach( $usermeta as $k => $v ) {
								if(empty( $quiz_titles[$v['quiz']] )) {
									if ( !empty($v['quiz'])) {
										$quiz = get_post( $v['quiz'] );
										$quiz_titles[$v['quiz']] = $quiz->post_title;
									}
									else if(!empty($v['pro_quizid'])) {
										$quiz = get_post( $v['pro_quizid'] );
										$quiz_titles[$v['quiz']] = $quiz->post_title;
									}
									else
										$quiz_titles[$v['quiz']] = "";
								}
								$content[] = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'quiz_id' => $v['quiz'], 'quiz_title' => $quiz_titles[$v['quiz']], 'rank' => $v['rank'], 'score' => $v['score'], 'total' => $v['count'], 'date' => date( DATE_RSS, $v['time'] ) );
							}
						}
						else
						{
				 		//	$content[] = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'status' => __('No attempts', 'learndash'));
						$content[] = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'quiz_id' =>  __('No attempts', 'learndash'), 'quiz_title' => '', 'rank' => '', 'score' => '', 'total' => '', 'date' => '' );
						}					
					}
		 		if ( empty( $content ) ) {
					$content[] = Array( 'status' => __('No attempts', 'learndash'));
				}  
				
					$csv = new lmsParseCSV();
					$csv->output( true, 'quizzes.csv', $content, array_keys( reset( $content ) ) );
					die();
				
			}			
		}
		
		function quiz_filter_submit( $submit ) {
			$submit['quiz_export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export Quiz Data &raquo;', 'learndash') );
			return $submit;
		}
		
		function trans_export_init() {
			$sfwd_trans = $this->post_types['sfwd-transactions'];
			$trans_prefix = $sfwd_trans->get_prefix();
			add_filter($trans_prefix . 'submit_options', Array( $this, 'trans_filter_submit' ) );
			if ( !empty( $_REQUEST['export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				require_once( 'parsecsv.lib.php' );
				$content = array();
				set_time_limit( 0 );
				$locations = query_posts( array( 'post_status' => 'publish', 'post_type' => 'sfwd-transactions', 'posts_per_page' => -1 ) );
				foreach ( $locations as $key => $location ) {
					$location_data = get_post_custom( $location->ID );
					foreach( $location_data as $k => $v ) {
						if ( $k[0] == '_' )
							unset( $location_data[$k] );
						else
							$location_data[$k] = $v[0];
					}
					$content[] = $location_data;
				}
				if ( ! empty( $content ) ) {
					$csv = new lmsParseCSV();
					$csv->output( true, 'transactions.csv', $content, array_keys( reset( $content ) ) );
				}
				die();
			}
		}
		
		function trans_filter_submit( $submit ) {
			unset( $submit['Submit'] );
			unset( $submit['Submit_Default'] );
			$submit['export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export &raquo;', 'learndash') );
			return $submit;
		}
		
		function quiz_display_settings( $settings, $location, $current_options ) {
			global $sfwd_lms;
			$sfwd_quiz = $sfwd_lms->post_types['sfwd-quiz'];
		    $quiz_prefix = $sfwd_quiz->get_prefix();
		    $prefix_len = strlen( $quiz_prefix );
		    $quiz_options = $sfwd_quiz->get_current_options();
			if ( $location == null ) {
			    foreach( $quiz_options as $k => $v ) {
				    if ( strpos( $k, $quiz_prefix ) === 0 ) {
					    $quiz_options[ substr( $k, $prefix_len ) ] = $v;
					    unset( $quiz_options[$k] );
				    }
			    }
			    foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level )
					$quiz['info'][$level] = $quiz_options[$level];
				$quiz['info']['name'] = $quiz['info']['main'] = $quiz['info']['results'] = '';
				$quiz_json = json_encode( $quiz );
				$settings['sfwd-quiz_quiz']['default'] = '<div class="quizFormWrapper"></div><script type="text/javascript">var quizJSON = ' . $quiz_json . ';</script>';
				if ( $location == null ) unset( $settings["{$quiz_prefix}quiz"] );
				if ( !empty( $settings["{$quiz_prefix}certificate_post"] ) ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-certificates' , 'numberposts' => -1 ) );
					$post_array = Array( '0' => __('-- Select a Certificate --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							$post_array[$p->ID] = $p->post_title;
					$settings["{$quiz_prefix}certificate_post"]['initial_options'] = $post_array;
				}
			} else {
				if ( !empty( $settings["{$quiz_prefix}lesson"] ) ) {
					$post_array = $this->select_a_lesson_or_topic();
					$settings["{$quiz_prefix}lesson"]['initial_options'] = $post_array;
				}
				if ( !empty( $settings["{$quiz_prefix}certificate"] ) ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-certificates'  , 'numberposts' => -1) );
					$post_array = Array( '0' => __('-- Select a Certificate --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							$post_array[$p->ID] = $p->post_title;
					
					$settings["{$quiz_prefix}certificate"]['initial_options'] = $post_array;
				}
			}
			return $settings;
		}
		function select_a_course($current_post_type = null) {
			global $pagenow;
			//print_r($_POST);
			if(!is_admin() || ($pagenow != "post.php" && $pagenow != "post-new.php"))
			return array();
			if($pagenow == "post.php" && empty($_POST['_wpnonce']) && !empty($_GET["post"]) && !empty($_GET["action"]) && $_GET["action"] == "edit") {
				$post_id = $_GET["post"];
				$post = get_post($post_id);
				if(!empty($post->ID) && $current_post_type == $post->post_type)
				{
					if(in_array($post->post_type, array("sfwd-lessons", "sfwd-quiz", "sfwd-topic") )) {
						$course_id = learndash_get_course_id($post);
						learndash_update_setting($post, "course", $course_id);
					}
				}
			}
			$options = array('array' => true, 'post_status' => 'any',  'orderby' => 'title', 'order' => 'ASC');
			$options = apply_filters("learndash_select_a_course", $options);
			$posts = ld_course_list($options);
					
			$post_array = Array( '0' => __('-- Select a Course --', 'learndash') );
			if ( !empty( $posts ) )
			foreach( $posts as $p ){
				$post_array[$p->ID] = $p->post_title;
			}
			return $post_array;
		}
		function select_a_lesson_or_topic_ajax() {
			$post_array = $this->select_a_lesson_or_topic(@$_REQUEST["course_id"]);
			echo json_encode($post_array);
			exit;			
		}
		function select_a_lesson_or_topic($course_id = null) {
			if(!is_admin())
				return array();
			$opt = array( 'post_type' => 'sfwd-lessons' ,'post_status' => 'any',  'numberposts' => -1 , 'orderby' => learndash_get_option('sfwd-lessons', 'orderby') , 'order' => learndash_get_option('sfwd-lessons', 'order'));
					
			if(empty($course_id))
			$course_id = learndash_get_course_id(@$_GET["post"]);

			if(!empty( $course_id )) {
				$opt["meta_key"] = "course_id";
				$opt["meta_value"] = $course_id;
			}

			$posts = get_posts($opt);
			$topics_array = learndash_get_topic_list();
			
			$post_array = Array( '0' => __('-- Select a Lesson or Topic --', 'learndash') );
			if ( !empty( $posts ) )
			foreach( $posts as $p ){
				$post_array[$p->ID] = $p->post_title;
				if(!empty($topics_array[$p->ID]))
				foreach($topics_array[$p->ID] as $id => $topic) {
					$post_array[$topic->ID] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $topic->post_title;
				}
			}
			return $post_array;
		}
		function select_a_lesson_ajax() {
			$post_array = $this->select_a_lesson(@$_REQUEST["course_id"]);
			echo json_encode($post_array);
			exit;
		}
		function select_a_lesson($course_id = null) {
			if(!is_admin())
				return array();
			if(!empty($_REQUEST["ld_action"]) || !empty($_GET['post']) && is_array($_GET['post']))
				return array();

			$opt = array( 'post_type' => 'sfwd-lessons', 'post_status' => 'any',  'numberposts' => -1 , 'orderby' => learndash_get_option('sfwd-lessons', 'orderby') , 'order' => learndash_get_option('sfwd-lessons', 'order'));
			
			if(empty($course_id))
				if(empty($_GET["post"]))
					$course_id = learndash_get_course_id();
				else
					$course_id = learndash_get_course_id($_GET["post"]);


			if(!empty( $course_id )) {
				$opt["meta_key"] = "course_id";
				$opt["meta_value"] = $course_id;
			}

			$posts = get_posts($opt);
			$post_array = Array( '0' => __('-- Select a Lesson --', 'learndash') );
			if ( !empty( $posts ) )
				foreach( $posts as $p )
					$post_array[$p->ID] = $p->post_title;
			return $post_array;
		}
		function course_display_settings( $settings ) {
		/*
			@Description:
			Function to display course prerequisite list
			Select from given list of course to make it mandatory to access
			the current course
		*/
			global $sfwd_lms;
			$sfwd_courses = $sfwd_lms->post_types['sfwd-courses'];
		    $courses_prefix = $sfwd_courses->get_prefix();
			if ( !empty( $settings["{$courses_prefix}course_prerequisite"] ) ) {
					$options = Array( 'post_type' => 'sfwd-courses'  , 'post_status' => 'any',  'numberposts' => -1);
					$options = apply_filters("learndash_course_prerequisite_post_options", $options);
					$posts = get_posts( $options );
					$post_array = Array( '0' => __('-- Select a Course --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							if($p->ID == get_the_id()){ 
							//Skip for current post id as current course can not be prerequities of itself
							}
							else $post_array[$p->ID] = $p->post_title;
					
					$settings["{$courses_prefix}course_prerequisite"]['initial_options'] = $post_array;
			}
			
			return $settings;
		}

		function add_course_tax_entry( $post_id, $post ) {
		
			$term = get_term_by('slug', $post->post_name, 'courses');
			$term_id = isset($term->term_id)? $term->term_id:0;

			if ( !$term_id ) {
				$term = wp_insert_term( $post->post_title, 'courses', Array( 'slug' => $post->post_name ) );
				$term_id = $term['term_id'];
			}
			
			wp_set_object_terms( (int)$post_id, (int)$term_id, 'courses', true );
		}

		function tax_registration() {
			$taxes = apply_filters( 'sfwd_cpt_register_tax', Array() );
			if ( !empty( $taxes ) ) {
				$post_types = Array();
				$tax_options = null;
				foreach( $taxes as $k => $v ) {
					if ( !empty( $v ) )
						foreach( $v as $tax ) {
							if ( !is_Array( $tax[0] ) ) $tax[0] = Array( $tax[0] );
							$post_types = array_merge( $post_types, $tax[0] );
							if ( empty( $tax_options ) )
								$tax_options = $tax[1];
							else
								foreach( $tax[1] as $l => $w )
									$tax_options[$l] = $w;
						}
				}
				register_taxonomy( $k, $post_types, $tax_options );
			}
		}

		static function get_template($name, $args, $echo = false, $return_file_path = false){
			$filename = substr($name, -4) == '.php' ? $name : $name . '.php';
			$filepath = locate_template(array("learndash/".$filename));
			if(!$filepath)
			$filepath = locate_template($filename);
			if(!$filepath){
				$filepath = dirname(__FILE__) . '/templates/' . $filename;
				if(!file_exists($filepath))
					return false;
			}
			$filepath = apply_filters("learndash_template", $filepath, $name, $args, $echo, $return_file_path);
			if($return_file_path)
				return $filepath;

			extract ($args);
			$level = ob_get_level();
			ob_start();
			include($filepath);
			$contents = learndash_ob_get_clean($level);

			if(!$echo)
				return $contents;
			echo $contents;
		}
	}
}

if ( !class_exists( 'SFWD_CPT_Instance' ) ) {
	class SFWD_CPT_Instance extends SFWD_CPT {
		public static $instances = Array();
		function __construct( $args ) {
			extract( $args );
			if ( empty( $plugin_name ) )	$plugin_name = "SFWD CPT Instance";
			if ( empty( $post_name ) )		$post_name = $plugin_name;
			if ( empty( $slug_name ) )		$slug_name = sanitize_file_name( strtolower( strtr( $post_name, ' ', '_' ) ) );
			if ( empty( $post_type ) )		$post_type = sanitize_file_name( strtolower( strtr( $slug_name, ' ', '_' ) ) );
			SFWD_CPT_Instance::$instances[ $post_type ] =& $this;
			if ( empty( $name ) )			$name = !empty($options_page_title)? $options_page_title:$post_name.__(" Options", 'learndash');
			if ( empty( $prefix ) )			$prefix = sanitize_file_name( $post_type ) . '_';
			if ( !empty( $taxonomies ) )	$this->taxonomies = $taxonomies;
			$this->file = __FILE__ . "?post_type={$post_type}";
			$this->plugin_name	= $plugin_name;
			$this->post_name	= $post_name;
			$this->slug_name	= $slug_name;
			$this->post_type	= $post_type;
			$this->name			= $name;
			$this->prefix		= $prefix;
			$posts_per_page = get_option( 'posts_per_page' );
			if ( empty( $posts_per_page ) ) $posts_per_page = 5;
			if ( empty( $default_options ) )
				$this->default_options = Array(
										'orderby' => Array( 
											'name' => __('Sort By', 'learndash'),
											'type' => __('select', 'learndash'),
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'title'	=> __('Title', 'learndash'),
														'date'	=> __('Date', 'learndash'),
														'menu_order' => __('Menu Order', 'learndash')
												),
											'default' => 'date',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'order' => Array( 
											'name' => __('Sort Direction', 'learndash'),
											'type' => 'select',
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'ASC'	=> __('Ascending', 'learndash'),
														'DESC'	=> __('Descending', 'learndash')
												),
											'default' => 'DESC',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'posts_per_page' => Array(
												'name' => __('Posts Per Page', 'learndash'),
												'type' => 'text',
												'help_text' => __('Enter the number of posts to display per page.', 'learndash'),
												'default' => $posts_per_page
											),
										);
			else
				$this->default_options = $default_options;
			if ( !empty( $fields ) ) {
				$this->locations = Array (
						'default' => Array( 'name' => $this->name, 'prefix' => $this->prefix, 'type' => 'settings', 'options' => null ),
						$this->post_type => Array( 'name' => $this->plugin_name, 'type' => 'metabox', 'prefix' => '',
												'options' => array_keys( $fields ),
												'default_options' => $fields,
												'display' => Array( $this->post_type ) )
						);
			}
			parent::__construct();
			if ( !empty( $description ) ) $this->post_options['description'] = wp_kses_post( $description );
			if ( !empty( $menu_icon ) ) $this->post_options['menu_icon'] = esc_url ( $menu_icon );
			if ( !empty( $cpt_options ) ) $this->post_options = wp_parse_args( $cpt_options, $this->post_options );
			add_action( 'admin_menu', Array( &$this, 'admin_menu') );
			add_shortcode( $this->post_type, Array( $this, 'shortcode' ) );
			add_action( 'init', Array( $this, 'add_post_type' ) );

			$this->update_options();
			if ( !is_admin() ) {
				add_action( 'pre_get_posts', Array( $this, 'pre_posts' ) );
				if ( isset( $template_redirect ) && ( $template_redirect === true ) ) {
					/*if ( !empty( $this->options[ $this->prefix . 'template_redirect'] ) ) {
						add_action("template_redirect", Array( $this, 'template_redirect' ) );
					} else*/ {
						add_action("template_redirect", Array( $this, 'template_redirect_access' ) );
						add_filter( "the_content", Array( $this, "template_content" ), 1000 );
					}
				}
			}
		}
		
		function get_archive_content( $content ) {
			global $post;
			if ( sfwd_lms_has_access( $post->ID ) ) {
				return $content;
			} else {
				return get_the_excerpt();
			}
		}
		
		
		function template_content( $content ) { 
			global $wp;
			$post = get_post(get_the_id());
			$current_user = wp_get_current_user();
			$post_type = '';

			if ( get_query_var('post_type') )
		        $post_type = get_query_var( 'post_type' );
			
			if ( ( !is_singular() ) || ( $post_type != $this->post_type ) || ( $post_type != $post->post_type ) )
				return $content;
			
			$user_id = get_current_user_id();
			$logged_in = !empty($user_id);
			$course_id = learndash_get_course_id();
			$lesson_progression_enabled = false;
			$has_access = '';
			if(!empty($course_id)) {
			$course = get_post($course_id);
			$course_settings = learndash_get_setting($course);
			$lesson_progression_enabled  = learndash_lesson_progression_enabled();
			$courses_options = learndash_get_option( 'sfwd-courses' );
			$lessons_options = learndash_get_option( 'sfwd-lessons' );
			$quizzes_options = learndash_get_option( 'sfwd-quiz' );
			$course_status = learndash_course_status($course_id, null);
			$has_access = sfwd_lms_has_access($course_id, $user_id);

			}
			
			if ( !empty( $wp->query_vars["name"]) ) {
				// single
				if(is_course_prerequities_completed($post->ID)) {
					if ( $this->post_type == 'sfwd-courses' ) {

						$courses_prefix = $this->get_prefix();
						$prefix_len = strlen( $courses_prefix );


						if ( !empty( $course_settings['course_materials'] ) ) 
						{
							$materials = wp_kses_post( wp_specialchars_decode( $course_settings['course_materials'], ENT_QUOTES ) );
						}
						
						$lessons = learndash_get_course_lessons_list($course);
						$quizzes = learndash_get_course_quiz_list($course);	
						
						$has_course_content = (!empty($lessons) || !empty($quizzes));
						
						$lesson_topics = array();

						$has_topics = false;
						if(!empty($lessons))
						foreach ($lessons as $lesson) {
							$lesson_topics[$lesson["post"]->ID] = learndash_topic_dots($lesson["post"]->ID, false, 'array'); 
							if(!empty($lesson_topics[$lesson["post"]->ID]))
								$has_topics = true;
						}
						include_once('enhanced-paypal-shortcodes.php');
						$level = ob_get_level();
						ob_start();
						include(SFWD_LMS::get_template('course', null, null, true));
						$content = learndash_ob_get_clean($level);
						
					}
					 elseif ( $this->post_type == 'sfwd-quiz' ) {
							$quiz_settings = learndash_get_setting($post);
							$meta = @$this->get_settings_values( 'sfwd-quiz' );
							$show_content = !(!empty($lesson_progression_enabled) && !is_quiz_accessable(null, $post));
							$attempts_count = 0;
							$repeats = trim(@$quiz_settings['repeats']);

							if ( $repeats != "") {
								$user_id = get_current_user_id();
								if ( $user_id ) {
									$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
									$usermeta = maybe_unserialize( $usermeta );
									if ( !is_array( $usermeta ) ) $usermeta = Array();
									if ( !empty( $usermeta ) )	{
										foreach( $usermeta as $k => $v ) {
											if ( $v['quiz'] == $post->ID ) $attempts_count++;
										}
									}
								}
							}
							$attempts_left = ($repeats == "" || $repeats >= $attempts_count);
							if(!empty($lesson_progression_enabled) && !is_quiz_accessable(null, $post)) 
								add_filter('comments_array', 'learndash_remove_comments', 1,2);

							$access_message = apply_filters("learndash_content_access", null, $post);

							if(!is_null($access_message))
							$quiz_content = $access_message;
							else
							{
								if(!empty($quiz_settings['quiz_pro']))
								$quiz_content = wptexturize(do_shortcode("[LDAdvQuiz ".$quiz_settings['quiz_pro']."]"));

								$quiz_content = apply_filters("learndash_quiz_content", $quiz_content, $post);
							}
							$level = ob_get_level();
							ob_start();
							include(SFWD_LMS::get_template('quiz', null, null, true));
							$content = learndash_ob_get_clean($level);		

						} elseif ( $this->post_type == 'sfwd-lessons' ) {
						$previous_lesson_completed = is_previous_complete($post);
						$show_content = (!$lesson_progression_enabled || $previous_lesson_completed);
						$lesson_settings = learndash_get_setting($post);
						$quizzes = learndash_get_lesson_quiz_list($post);
						if(!empty($quizzes))
						foreach ($quizzes as $quiz) {
							$quizids[$quiz["post"]->ID] = $quiz["post"]->ID;
						}
						if($lesson_progression_enabled && !$previous_lesson_completed)
							add_filter('comments_array', 'learndash_remove_comments', 1,2);
					
						$topics = learndash_topic_dots($post->ID, false, 'array'); 
						
						if(!empty($quizids))
						$all_quizzes_completed = !learndash_is_quiz_notcomplete(null, $quizids);
						else
						$all_quizzes_completed = true;
						$level = ob_get_level();
						ob_start();
						include(SFWD_LMS::get_template('lesson', null, null, true));
						$content = learndash_ob_get_clean($level);
					
					}  elseif ( $this->post_type == 'sfwd-topic' ) {
						//print_r(learndash_get_lesson_quiz_list($post));
						$lesson_id = learndash_get_setting($post, "lesson");
						$lesson_post = get_post($lesson_id);
						$previous_topic_completed = is_previous_complete($post);
						$previous_lesson_completed = is_previous_complete($lesson_post);
						$show_content =  (empty($lesson_progression_enabled) || $previous_topic_completed && $previous_lesson_completed);
						$quizzes = learndash_get_lesson_quiz_list($post);
						if(!empty($quizzes))
						foreach ($quizzes as $quiz) {
							$quizids[$quiz["post"]->ID] = $quiz["post"]->ID;
						}
						if($lesson_progression_enabled && (!$previous_topic_completed || !$previous_lesson_completed))
							add_filter('comments_array', 'learndash_remove_comments', 1,2);
					
						if(!empty($quizids))
						$all_quizzes_completed = !learndash_is_quiz_notcomplete(null, $quizids);
						else
						$all_quizzes_completed = true;

						$topics = learndash_topic_dots($lesson_id, false, 'array'); 
						$level = ob_get_level();
						ob_start();
						include(SFWD_LMS::get_template('topic', null, null, true));
						$content = learndash_ob_get_clean($level);	
					}
					else {
						// archive
						$content = $this->get_archive_content( $content );
					}
				}
				else
				{
					if($this->post_type == 'sfwd-courses') $content_type = 'course';
					elseif($this->post_type == 'sfwd-lessons') $content_type = 'lesson';
					elseif($this->post_type == 'sfwd-quiz') $content_type = 'quiz';

					$course_pre = isset($course_settings['course_prerequisite'])? $course_settings['course_prerequisite']:0;
					$course_title = get_the_title($course_pre);
					$course_link = get_permalink( $course_pre );
					$content = "<div id='learndash_complete_prerequisites'>".sprintf(__('To take this %s, you need to complete the following course first:%s', 'learndash'), __($content_type, "learndash"),'<br><a href="'.$course_link.'">'.$course_title.'</a>')."</div>";	
				}
			}
			$content = str_replace(array("\n", "\r"), " ", $content);
			$user_has_access = $has_access? "user_has_access":"user_has_no_access";

			return '<div class="learndash '.$user_has_access.'"  id="learndash_post_'.$post->ID.'">'.apply_filters("learndash_content", $content, $post).'</div>';
		}
		
		function template_redirect_access() {
			global $wp;
		    global $post;
			if ( get_query_var('post_type') )
		        $post_type = get_query_var( 'post_type' );
		    else
		        if ( !empty( $post ) )
		            $post_type = $post->post_type;
			if ( empty( $post_type ) ) return;
			if ( $post_type == $this->post_type ) {
				if ( is_robots() )
			       do_action('do_robots');
				elseif ( is_feed() )
			       do_feed();
				elseif ( is_trackback() )
			       include( ABSPATH . 'wp-trackback.php' );
				elseif( !empty( $wp->query_vars["name"]) ) {
					// single
					if ( ( $post_type == 'sfwd-quiz' ) || ( $post_type == 'sfwd-lessons' )  || ( $post_type == 'sfwd-topic' ) ) {
						global $post;
						sfwd_lms_access_redirect( $post->ID );
					}
				}
					// archive
			}
			
			
			if ( ( $this->post_type == 'sfwd-quiz' ) && ( $post_type == 'sfwd-certificates' ) ) {
				global $post;
				$id = $post->ID;
				if ( !empty( $_GET ) && !empty( $_GET['quiz'] ) ) $id = $_GET['quiz'];
				$meta = get_post_meta( $id, '_sfwd-quiz' );
				if ( !empty( $post ) && is_single() ) {
					$print_cert = false;
					$cert_post = '';
					if ( is_array( $meta ) && !empty( $meta ) ) {
						$meta = $meta[0];
						if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_certificate'] ) ) )
							$cert_post = $meta['sfwd-quiz_certificate'];
					}
					
					if ( empty( $cert_post ) && !empty( $this->options["{$this->prefix}certificate_post"] ) )
						$cert_post = $this->options["{$this->prefix}certificate_post"];
					
					$user_id = get_current_user_id();
					$quiz = $_GET['quiz'];
					if ( !empty( $cert_post ) && ( $cert_post == $post->ID ) ) {
						if ( ( !empty( $_GET ) ) && ( !empty( $_GET['print'] ) 
							&& ( wp_verify_nonce( $_GET['print'], $id . $user_id ) ) ) ) {
								
								$time = isset($_GET['time'])? $_GET['time']:-1;
								$quizinfo = get_user_meta($user_id, "_sfwd-quizzes", true);
								$selected_quizinfo = $selected_quizinfo2 = null;
								if(!empty($quizinfo))
								foreach($quizinfo as $quiz_i) {
									if(isset($quiz_i['time']) && $quiz_i['time'] == $time && $quiz_i['quiz'] == $quiz) {
										$selected_quizinfo = $quiz_i;
										break;
									}
									if($quiz_i['quiz'] == $quiz)
									$selected_quizinfo2 = $quiz_i;
								}
								$selected_quizinfo = empty($selected_quizinfo)? $selected_quizinfo2:$selected_quizinfo;
								$certificate_threshold = learndash_get_setting($post, "threshold");
								if(!empty($selected_quizinfo))
								if((isset($selected_quizinfo['percentage']) && $selected_quizinfo['percentage'] >= $certificate_threshold * 100) || (isset($selected_quizinfo['count']) && $selected_quizinfo['score']/$selected_quizinfo['count'] >= $certificate_threshold))
								$print_cert = true;
						}
					}
					
					if ( $print_cert ) {
						require_once( 'conv_pdf.php' );
						post2pdf_conv_post_to_pdf();
						die();
					} else {
						if ( !current_user_can('level_8') ) {
							echo __('Access to certificate page is disallowed.', 'learndash');
							die();							
						}
					}
				}
			}
		    
		}

		function pre_posts() {
			global $wp_query;
			if ( is_post_type_archive( $this->post_type ) ) {
				foreach ( Array( 'orderby', 'order', 'posts_per_page' ) as $field )
					if ( $this->option_isset( $field ) )
						$wp_query->set( $field, $this->options[ $this->prefix . $field ] );
			} elseif ( ( $this->post_type == 'sfwd-quiz' ) && ( is_post_type_archive( 'post') || is_home() ) && !empty( $this->options["{$this->prefix}certificate_post"] ) ) {
				$post_not_in = $wp_query->get( 'post__not_in' );
				if ( !is_array( $post_not_in ) ) $post_not_in = Array();
				$post_not_in = array_merge( $post_not_in, Array( $this->options["{$this->prefix}certificate_post"] ) );
				$wp_query->set( 'post__not_in', $post_not_in );
			}
		}
	}
}

if ( !class_exists( 'SFWD_SlickQuiz' ) ) {
    class SFWD_SlickQuiz {

        var $quiz = null;
        var $status = null;
        var $pageQuizzes = array();
		var $publishedJson = '[]';
		var $options = array();
		var $debug = false;
        // Constructor
        function __construct() {

            // Add Shortcodes
//            add_shortcode( 'slickquiz', array( &$this, 'show_slickquiz_handler' ) );

            // Filter the post/page/widget content for the shortcode, load resources ONLY if present
            add_filter( 'the_content', array( &$this, 'load_resources' ) );
            add_filter( 'widget_text', array( &$this, 'load_resources' ) );
            // Add the script and style files
      /*     add_action( 'admin_enqueue_scripts', array( &$this, 'load_admin_resources' ) );

            // Make sure dynamic quiz scripts gets loaded below jQuery
            add_filter( 'wp_footer', array( &$this, 'load_quiz_script' ), 5000 );

			add_action( 'wp_ajax_create_quiz', array( &$this, 'create_quiz' ) );
            add_action( 'wp_ajax_update_quiz', array( &$this, 'update_quiz' ) );
            add_action( 'wp_ajax_revert_quiz', array( &$this, 'revert_quiz' ) );
            add_action( 'wp_ajax_publish_quiz', array( &$this, 'publish_quiz' ) );
            add_action( 'wp_ajax_unpublish_quiz', array( &$this, 'unpublish_quiz' ) );
            add_action( 'wp_ajax_delete_quiz', array( &$this, 'delete_quiz' ) );
            add_action( 'wp_ajax_finish_quiz', array( &$this, 'finish_quiz' ) );
        */
        }
		

        function create_quiz() {
            if ( isset( $_POST['json'] ) ) {
                $this->save_working_copy( $_POST['json'] );
                $quiz = $this->get_last_quiz_by_user( get_current_user_id() );
                echo $quiz->id;
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function update_quiz() {
            if ( isset( $_POST['json'] ) ) {
                $quiz      = $this->get_quiz_by_id( $_GET['post'] );
                $published = $this->get_quiz_status( $quiz ) != 'publish' ? false : true;
                $this->update_working_copy( $_POST['json'], $quiz, $published );
                echo $quiz->ID;
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function finish_quiz() {
            if ( isset( $_POST['score'] ) ) {
				$user_id = $_POST["userID"];
				$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
				$usermeta = maybe_unserialize( $usermeta );
				if ( !is_array( $usermeta ) ) $usermeta = Array();
				$user = get_user_by("id", $user_id);
				$quiz = get_post_meta($_POST["postID"], '_sfwd-quiz', true);
				$passingpercentage = intVal($quiz['sfwd-quiz_passingpercentage']);
				$quiz_percentage = $_POST["score"]*100/$_POST["questionCount"];
				$pass = ($quiz_percentage >= $passingpercentage)? 1:0;
				$quiz = get_post($_POST["postID"]);
				$quizdata = Array( "quiz" => $_POST["postID"], "quiz_title" => $quiz->post_title, "score" => $_POST["score"], "count" => $_POST["questionCount"], "pass" => $pass, "rank" => $_POST["levelRank"], "time" => time() , 'percentage' => $quiz_percentage);
				$usermeta[] = $quizdata;
				$quizdata['quiz'] = $quiz;
				$courseid = learndash_get_course_id($_POST["postID"]);
				$quizdata['course'] = get_post($courseid);
				
				do_action("learndash_completed", $quizdata); //Hook for completed quiz //Depricated
				do_action("learndash_quiz_completed", $quizdata, $user); //Hook for completed quiz
				
				update_user_meta( $user_id, '_sfwd-quizzes', $usermeta );
				echo __('Quiz data was saved!', 'learndash');
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function revert_quiz()
        {
            $quiz = $this->get_quiz_by_id( $_GET['id'] );
            $this->revert_to_published_copy( $quiz->publishedJson, $quiz->id, $quiz->publishedDate );
            die();
        }

        function publish_quiz()
        {
            $quiz = $this->get_quiz_by_id( $_GET['id'] );
            $this->update_published_copy( $quiz->workingJson, $quiz->id );
            die();
        }

        function unpublish_quiz()
        {
            $this->unpublish( $_GET['id'] );
            die();
        }

        function delete_quiz()
        {
            $this->delete( $_GET['id'] );
            die();
        }

        function get_all_quizzes() {
            return get_posts( Array( 'numberposts' => -1, 'post_type' => 'sfwd-quiz' ) );
        }

        function get_last_quiz_by_user( $user_id ) {
            return get_posts( Array( 'numberposts' => 1, 'post_type' => 'sfwd-quiz', 'author' => $user_id ) );
        }

		function insert( $set ) {
			
		}

		function update( $set, $args ) {
;
			if ( !empty( $args ) ) {
				if ( !empty( $args['id'] ) ) {
					$id = $args['id'];
					return update_post_meta( $id, '_quizdata', $set );
				}
			}
			return false;
		}

        function save_working_copy( $json, $user_id = null )
        {
            global $wpdb, $data;

            $data    = json_decode( stripcslashes( $json ) );
            $set     = array();
            $now     = date( 'Y-m-d H:i:s' );
            $user_id = $user_id ? $user_id : get_current_user_id();

            $set['createdDate']     = $now;
            $set['createdBy']       = $user_id;
            $set['lastUpdatedDate'] = $now;
            $set['lastUpdatedBy']   = $user_id;
            $set['name']            = $this->get_name();
            $set['workingQCount']   = $this->get_question_count();
            $set['workingJson']     = $json;

            $this->insert( $set );
        }

	function get_name() {
		return 'Test';
	}

	function get_question_count() {
		return 10;
	}

        function update_working_copy( $json, $quiz, $published = false ) {
			global $wpdb, $data;
			$id = $quiz->ID;
            $data = json_decode( stripcslashes( $json ) );

            $set  = array();

            if ( !$published ) {
                $set['name'] = $this->quiz->post_title;
            }


            $set['lastUpdatedDate'] = date( 'Y-m-d H:i:s' );
            $set['lastUpdatedBy']   = get_current_user_id();
            $set['workingQCount']   = count((Array)$data->questions);
            $set['workingJson']     = $json;
	
            $this->update( $set, array( 'id' => $id ) );
        }

        function update_published_copy( $json, $id )
        {
            global $wpdb, $data;

            $data    = json_decode( $json );
            $set     = array();
            $now     = date( 'Y-m-d H:i:s' );
            $user_id = get_current_user_id();

            $set['name']             = $this->get_name();
            $set['workingQCount']    = $this->get_question_count();
            $set['publishedQCount']  = $set['workingQCount'];
            $set['workingJson']      = $json;
            $set['publishedJson']    = $set['workingJson'];
            $set['hasBeenPublished'] = 1;
            $set['publishedDate']    = $now;
            $set['publishedBy']      = $user_id;
            $set['lastUpdatedDate']  = $now;
            $set['lastUpdatedBy']    = $user_id;

            $this->update( $set, array( 'id' => $id ) );
        }

        function revert_to_published_copy( $json, $id, $updatedOn )
        {
            global $wpdb, $data;

            $data = json_decode( stripcslashes( $json ) );
            $set  = array();

            $set['lastUpdatedDate'] = $updatedOn;
            $set['workingQCount']   = $this->get_question_count();
            $set['workingJson']     =  $json  ;

            $this->update( $set, array( 'id' => $id ) );
        }

        function unpublish( $id )
        {
            global $wpdb, $data;

            $set = array();

            $set['publishedQCount'] = null;
            $set['publishedJson']   = null;
            $set['lastUpdatedDate'] = date( 'Y-m-d H:i:s' );
            $set['lastUpdatedBy']   = get_current_user_id();

            $wpdb->update( $set, array( 'id' => $id ) );
        }

        function delete( $id ) {
			wp_delete_post( $id );
        }

        // Add Admin JS and styles
        function load_admin_resources( $content ) {

            // Only load resources when a shortcode is on the page

            preg_match( '/post.php/is', $_SERVER['REQUEST_URI'], $matches );
            if ( count( $matches) == 0 ) return;
			global $post;
			$quizmeta = get_post_meta( $post->ID, '_sfwd-quiz' , true);
			
			if(!empty($quizmeta['sfwd-quiz_quiz_pro']))
			{
			/*	?>
				
				<style type="text/css">
					#sfwd-quiz_passingpercentage {
						display:none;
					}
				</style>
				<?php*/
				return;
			}
				
			if(apply_filters("leandash_slickquiz_loadresources", true, $post)) {
            // Scripts
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'slickquiz_admin_js', plugins_url( 'assets/admin.js', __FILE__ ) );

            // Styles
            wp_enqueue_style( 'slickquiz_admin_css', plugins_url( 'assets/admin.css', __FILE__ ) );



            }
		}
	
	function debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if(isset($_GET['debug']) || !empty($this->debug))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
        function load_resources( $content ) {
/*		global $sfwd_lms;
	    $sfwd_quiz = $sfwd_lms->post_types['sfwd-quiz'];
	    $quiz_prefix = $sfwd_quiz->get_prefix();
	    $prefix_len = strlen( $quiz_prefix );
	    $quiz_options = $sfwd_quiz->get_current_options();

	    foreach( $quiz_options as $k => $v ) {
		    if ( strpos( $k, $quiz_prefix ) === 0 ) {
			    $quiz_options[ substr( $k, $prefix_len ) ] = $v;
			    unset( $quiz_options[$k] );
		    }
	    }
	    $this->options = $quiz_options;
*/

            // Only load resources when a shortcode is on the page
	    global $post;
		if(empty($post->post_type))
		return $content;
		
/*	    if (!$post->post_type == 'sfwd-quiz' )
		if ( preg_match( '/\[\s*slickquiz[^\]]*\]/is', $content, $matches ) )
            	    if ( !count( $matches) ) return $content;

			
		// Scripts
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'sfwd_slickquiz_js', plugins_url( 'assets/slickQuiz.js', __FILE__ ) );
		wp_localize_script('sfwd_slickquiz_js', 'LDLMS', array( 'siteurl' => get_bloginfo('wpurl'), 'lang_print_your_certificate'  => __('PRINT YOUR CERTIFICATE!', 'learndash'), 'lang_question_x_of_y' => __('Question {0} of {1}', 'learndash')  ));

		// Styles
		wp_enqueue_style( 'sfwd_slickquiz_css', plugins_url( 'assets/slickQuiz.css', __FILE__ ) );
*/		wp_enqueue_style( 'sfwd_front_css', plugins_url( 'assets/front.css', __FILE__ ) );
		$filepath = locate_template(array("learndash/learndash_template_style.css"));
		if($filepath && file_exists($filepath)) {
			wp_enqueue_style( 'sfwd_template_css', get_stylesheet_directory_uri()."/learndash/learndash_template_style.css");
		}
		else
		{
			$filepath = locate_template("learndash_template_style.css");
			if($filepath &&  file_exists($filepath)) {
				wp_enqueue_style( 'sfwd_template_css', get_stylesheet_directory_uri()."/learndash_template_style.css");
			}
			else if(file_exists(dirname(__FILE__) .'/templates/learndash_template_style.css'))
			wp_enqueue_style( 'sfwd_template_css', plugins_url( 'templates/learndash_template_style.css', __FILE__ ) );
		}
		$filepath = locate_template(array("learndash/learndash_template_script.js"));
		if($filepath && file_exists($filepath)) {
			wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri()."/learndash/learndash_template_script.js");
		}
		else
		{
			$filepath = locate_template("learndash_template_script.js");
			if($filepath &&  file_exists($filepath)) {
				wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri()."/learndash_template_script.js");
			}
			else if(file_exists(dirname(__FILE__) .'/templates/learndash_template_script.js'))
			wp_enqueue_script( 'sfwd_template_js', plugins_url( 'templates/learndash_template_script.js', __FILE__ ) );
		}
			
            return $content;
        }

	function get_admin_option( $option ) {
		return $this->options[$option];
	}

        function load_quiz_script() {
            global $pageQuizzes;
            $out = '';
	    $quiz_options = $this->options;
	    extract( $this->options );
		if ( !empty( $quiz ) ) foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) $quiz['info'][$level] = $quiz_options[$level];
            if ( count( $pageQuizzes ) ) {
                foreach ( $pageQuizzes as $id => $quizStat ) {
					$post   = $quizStat[0];
                    $status = $quizStat[1];
					$quiz['info']['name'] = $post->post_title;
					$quiz['info']['main'] = $post->post_title;
					$cf = get_post_meta( $post->ID, '_quizdata', true );
					$quiz_json = $cf['workingJson'];
					if ( empty( $quiz_json ) ) {
						$quiz_json = json_encode( $quiz );
					}
					$certificate_details = learndash_certificate_details($post->ID);
					
						$out .= '
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                $("#slickQuiz' . $post->ID . '").slickQuiz({
                                    json:                        ' . $quiz_json . ', // $quiz->publishedJson
                                    checkAnswerText:             "' . $this->get_admin_option( 'check_answer_text' ) . '",
                                    nextQuestionText:            "' . $this->get_admin_option( 'next_question_text' ) . '",
                                    backButtonText:              "' . $this->get_admin_option( 'back_button_text' ) . '",
                                    randomSortQuestions:         ' . ( $this->get_admin_option( 'random_sort_questions' ) ? 'true' : 'false' ) . ',
                                    randomSortAnswers:           ' . ( $this->get_admin_option( 'random_sort_answers' ) ? 'true' : 'false' ) . ',
                                    randomSort:                  ' . ( $this->get_admin_option( 'random_sort' ) ? 'true' : 'false' ) . ',
                                    preventUnanswered:           ' . ( $this->get_admin_option( 'disable_next' ) ? 'true' : 'false' ) . ',
                                    disableResponseMessaging:    ' . ( $this->get_admin_option( 'disable_responses' ) ? 'true' : 'false' ) . ',
                                    completionResponseMessaging: ' . ( $this->get_admin_option( 'completion_responses' ) ? 'true' : 'false' ) . ',
									certificateLink:	"' . $certificate_details['certificateLink'] . '",
									certificateThreshold:	"' . $certificate_details['certificate_threshold'] . '",
									postID:	"' . $post->ID . '",
									userID:	"' . get_current_user_id() . '"
                                });
                            });
							</script>';
                }
            }

            echo $out;
        }

        function show_slickquiz_handler( $atts )
        {
            extract( shortcode_atts( array(
                'id' => 0,
            ), $atts ) );

            $out = $this->show_slickquiz( $id );

            return $out;
        }

		function get_quiz_by_id( $id ) {
			return get_post( $id );
		}
		
		function get_quiz_status( $quiz ) {
			if ( !empty( $quiz ) && !empty( $quiz->ID ) )
				return get_post_status( $quiz->ID );
			return false;
		}
		



        function show_slickquiz( $id )
        {
            global $quiz, $status, $pageQuizzes;

            $quiz = $this->get_quiz_by_id( $id );
			$returnLink  = learndash_quiz_continue_link($id);

            if ( $quiz ) {

                $pageQuizzes[$id] = array( $quiz, null );
                    $out = '
                        <div class="slickQuizWrapper" id="slickQuiz' . $quiz->ID . '">
                            <h2 class="quizName"></h2>
                            <div class="quizArea">
                                <div class="quizHeader">
                                    <div class="buttonWrapper"><a class="button startQuiz">' . $this->get_admin_option( 'start_button_text' ) . '</a></div>
                                </div>
                            </div>

                            <div class="quizResults">
                                <div class="quizResultsCopy">
                                    <h3 class="quizScore">' . $this->get_admin_option( 'your_score_text' ) . ' <span>&nbsp;</span></h3>
                                    <h3 class="quizLevel">' . $this->get_admin_option( 'your_ranking_text' ) . ' <span>&nbsp;</span></h3>
                                </div>
                            <div class="quizReturn">
								'.$returnLink.'
							</div>								
                            </div>

                        </div>';

            } else {
                $out = "<p class='quiz-$id notFound'>" . $this->get_admin_option( 'missing_quiz_message' ) . "</p>";
            }
            return $out;
        }

    }
}

$sfwd_lms = new SFWD_LMS();
$sfwd_slickquiz = new SFWD_SlickQuiz();

function learndash_previous_post_link($prevlink='', $url = false) {
	global $post;
	
	if(!is_singular() || empty($post))
		return $prevlink;
	
	if($post->post_type == "sfwd-lessons") {
		$link_name = __('Previous Lesson', 'learndash');
		$posts = learndash_get_lesson_list();
	}
	else if($post->post_type == "sfwd-topic")
	{
		$link_name = __('Previous Topic', 'learndash');
		$lesson_id = learndash_get_setting($post, "lesson");
		$posts = learndash_get_topic_list($lesson_id);
	}
	else
		return $prevlink;
	
	foreach($posts as $k => $p) {
			if($p->ID == $post->ID)
			{
				$found_at = $k;
				break;
			}
	}
	
	if(isset($found_at) && !empty($posts[$found_at-1]))
	{
		$permalink = get_permalink( $posts[$found_at-1]->ID );
		if($url) 
		return $permalink;
		else
		{
			if(is_rtl())
				$link_name_with_arrow =  $link_name;
			else
				$link_name_with_arrow = '<span class="meta-nav">&larr;</span> ' . $link_name;
			$link = '<a href="'.$permalink.'" rel="prev">' . $link_name_with_arrow . '</a>';
			return apply_filters('learndash_previous_post_link', $link, $permalink, $link_name, $post);
		}
	}
	else
		return $prevlink;
		
}


function learndash_next_post_link($prevlink='', $url = false, $post = null) {
	if(empty($post))
	global $post;

	if(empty($post))
		return $prevlink;

	if($post->post_type == "sfwd-lessons") {
		$link_name = __('Next Lesson', 'learndash');
		$course_id = learndash_get_course_id($post);
		$posts = learndash_get_lesson_list($course_id);
	}
	else if($post->post_type == "sfwd-topic")
	{
		$link_name = __('Next Topic', 'learndash');
		$lesson_id = learndash_get_setting($post, "lesson");
		$posts = learndash_get_topic_list($lesson_id);
	}
	else
		return $prevlink;
		
	foreach($posts as $k => $p) {
			if($p->ID == $post->ID)
			{
				$found_at = $k;
				break;
			}
	}

	if(isset($found_at) && !empty($posts[$found_at+1]))
	{
		$permalink = get_permalink( $posts[$found_at+1]->ID );
		if($url) 
		return $permalink;
		else
		{
			if(is_rtl())
				$link_name_with_arrow =  $link_name ;
			else
				$link_name_with_arrow = $link_name . ' <span class="meta-nav">&rarr;</span>';

			$link = '<a href="'.$permalink.'" rel="next">' . $link_name_with_arrow.'</a>';
			return apply_filters('learndash_next_post_link', $link, $permalink, $link_name, $post);
		}
	}
	else
		return $prevlink;
		
}

function learndash_clear_prev_next_links($prevlink=''){
	global $post;
	
	if(!is_singular() || empty($post->post_type) || ($post->post_type != "sfwd-lessons" && $post->post_type != "sfwd-quiz" && $post->post_type != "sfwd-courses" && $post->post_type != "sfwd-topic" && $post->post_type != "sfwd-assignment"))
		return $prevlink;
	else
		return "";
}
add_filter('previous_post_link', 'learndash_clear_prev_next_links', 1, 2);
add_filter('next_post_link', 'learndash_clear_prev_next_links', 1, 2);
function ldp($msg) {
	echo "<pre>";
	print_r($msg);
	echo "</pre>";
}
function learndash_get_lesson_list($id = null){
	global $post;

	if(empty($id))
	$id = $post->ID;

	$course_id = learndash_get_course_id($id);
	if(empty($course_id))
		return array();
	global $wpdb;
	
	$lessons = sfwd_lms_get_post_options( 'sfwd-lessons' );
	$course_options = get_post_meta($course_id, "_sfwd-courses", true);
	$course_orderby = @$course_options["sfwd-courses_course_lesson_orderby"];
	$course_order = @$course_options["sfwd-courses_course_lesson_order"];
	
	$orderby = (empty($course_orderby))? $lessons['orderby']:$course_orderby;
	$order = (empty($course_order))? $lessons['order']:$course_order;

	switch($orderby) {
		case "title": $orderby = "title"; break;
		case "date": $orderby = "date"; break;
	}
	$lessons = ld_lesson_list(array('array' => true, 'meta_key'	=> 'course_id', 'meta_value' => $course_id,'orderby' => $orderby, 'order' => $order));
	return $lessons;	
}

function learndash_get_option($post_type, $setting = "") {
	$options = get_option( 'sfwd_cpt_options' );
	if(empty($setting) && !empty($options['modules'][$post_type."_options"])) {
		foreach($options['modules'][$post_type."_options"] as $key => $val) {
			$return[str_replace($post_type."_", "", $key)] = $val;
		}
		return $return;
	}
	if(!empty($options['modules'][$post_type."_options"][$post_type."_".$setting]))
	return $options['modules'][$post_type."_options"][$post_type."_".$setting];
	else
	return "";
}
function learndash_get_topic_list($for_lesson_id = null){
	$course_id = learndash_get_course_id($for_lesson_id);
	$lessons = sfwd_lms_get_post_options( 'sfwd-lessons' );
	$course_options = get_post_meta($course_id, "_sfwd-courses", true);
	$course_orderby = @$course_options["sfwd-courses_course_lesson_orderby"];
	$course_order = @$course_options["sfwd-courses_course_lesson_order"];
	
	$orderby = (empty($course_orderby))? $lessons['orderby']:$course_orderby;
	$order = (empty($course_order))? $lessons['order']:$course_order;

	$topics = get_posts( Array( 'post_type' => 'sfwd-topic' , 'numberposts' => -1, 'orderby' => $orderby , 'order' => $order));
	
	if (empty($topics))
	return array(); 
	
	foreach( $topics as $p ){
		$lesson_id = learndash_get_setting($p, "lesson");
		if(!empty($lesson_id))
		$topics_array[$lesson_id][] = $p;
	}
	if(empty($topics_array))
	return array();
	
	if(!empty($for_lesson_id)) {
		if(!empty($topics_array[$for_lesson_id]))
		return $topics_array[$for_lesson_id];
		else
		return array();
	}
	else
	return $topics_array;
}

function learndash_get_global_quiz_list($id = null){
	global $post;
	if(empty($id))
		if(!empty($post->ID))
		$id = $post->ID;
		else
		return array();
	//COURSEIDCHANGE
	$course_id = learndash_get_course_id($id);
	$course_settings = learndash_get_setting($course_id);
	$lessons_options = learndash_get_option( 'sfwd-lessons' );
	$orderby = (empty($course_settings['course_lesson_orderby']))? @$lessons_options['orderby']:$course_settings['course_lesson_orderby'];
	$order = (empty($course_settings['course_lesson_order']))? @$lessons_options['order']:$course_settings['course_lesson_order'];

	$quizzes = get_posts(array('post_type' => 'sfwd-quiz', 'posts_per_page' => -1, 'meta_key' => 'course_id', 'meta_value'	=> $course_id, 'meta_compare' => '=', 'orderby' => $orderby, 'order' => $order));

	$quizzes_new = array();
	foreach($quizzes as $k=>$quiz)
	{
		$quiz_lesson = learndash_get_setting($quiz, "lesson");
		if(empty($quiz_lesson))
		$quizzes_new[] = $quizzes[$k];
	}

	return $quizzes_new;	
}
function learndash_get_course_id($id = null) {
	global $post;
	if(is_object($id) && $id->ID) {
		$p = $id;
		$id = $p->ID;
	}
	else if(is_numeric($id)) {
		$p = get_post($id);
	}

	if(empty($id))
	{
		if(!is_single() || is_home())
			return false;
			
		$id = $post->ID;
		$p = $post;
	}
	if(empty($p->ID))
		return 0;

	if($p->post_type == "sfwd-courses")
		return $p->ID;

	return get_post_meta($id, "course_id", true);	
}
function learndash_get_legacy_course_id($id = null){
	global $post;
 	
	
	if(empty($id))
	{
		if(!is_single() || is_home())
			return false;
			
		$id = $post->ID;
	}
	$terms = wp_get_post_terms($id, 'courses');
	
	if(empty($terms) || empty($terms[0]) || empty($terms[0]->slug))
		return 0;
		
	$courseslug = $terms[0]->slug;
	
	global $wpdb;
	
	$term_taxonomy_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy tt, $wpdb->terms t 
		 WHERE slug = %s 
		 AND t.term_id = tt.term_id
		 AND tt.taxonomy = 'courses'
		",
	        $courseslug 
        )
	);
	
	$course_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `ID` FROM $wpdb->term_relationships, $wpdb->posts 
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-courses'
		 AND `post_status` = 'publish' 
		",
	        $term_taxonomy_id
        )
	);
	return $course_id;
}
if(!function_exists('sfwd_lms_get_post_options')) {
function sfwd_lms_get_post_options( $post_type ) {
	global $sfwd_lms;
	$cpt = $sfwd_lms->post_types[$post_type];
	$prefix = $cpt->get_prefix();
	$options = $cpt->get_current_options();
	$ret = Array( 'order' => '', 'orderby' => '', 'posts_per_page' => '' );
	foreach( $ret as $k => $v )
		if ( !empty( $options["{$prefix}{$k}"] ) )
			$ret[$k] = $options["{$prefix}{$k}"];
	return $ret;			
}
}

function ld_course_access_expired($course_id, $user_id) {
	$course_access_upto = ld_course_access_expires_on($course_id, $user_id);
	
	if(empty($course_access_upto))
		return false;
	else
	if(time() >= $course_access_upto) {
		update_user_meta($user_id, "learndash_course_expired_".$course_id, 1);
		ld_update_course_access($user_id, $course_id, $remove = true);
		$delete_course_progress = learndash_get_setting($course_id, "expire_access_delete_progress");
		if(!empty($delete_course_progress)) {
			learndash_delete_course_progress($course_id, $user_id);
		}
		return true;
	}
	else
		return false;
}
function ld_course_access_expired_alert() {
	global $post;
	if(!is_singular() || empty($post->ID) || $post->post_type != "sfwd-courses")
		return;

	$user_id = get_current_user_id();
	if(empty($user_id))
		return;

	$expired = get_user_meta($user_id, "learndash_course_expired_".$post->ID, true);

	if(empty($expired))
		return;

	$has_access = sfwd_lms_has_access($post->ID, $user_id);
	if($has_access) {
		delete_user_meta($user_id, "learndash_course_expired_".$post->ID);
		return;
	}
	else
	{
		?>
		<script>
			setTimeout(function() {
				alert("<?php _e('Your access to this course has expired.', 'learndash'); ?>")
			}, 2000);
		</script>
		<?php
	}
}
add_action("wp_head", "ld_course_access_expired_alert", 1);
function ld_course_access_expires_on($course_id, $user_id) {
	$couses_access_from = ld_course_access_from($course_id, $user_id);
	if(empty($couses_access_from))
		$couses_access_from = learndash_user_group_enrolled_to_course_from($user_id, $course_id);
		
	$expire_access = learndash_get_setting($course_id, "expire_access");
	
	if(empty($expire_access))
		return 0;

	$expire_access_days = learndash_get_setting($course_id, "expire_access_days");
	$course_access_upto = $couses_access_from + $expire_access_days * 24 * 60 * 60;
	return $course_access_upto;
}
function ld_lesson_access_from($lesson_id, $user_id) {
	$course_id = learndash_get_course_id($lesson_id);
	$couses_access_from = ld_course_access_from($course_id, $user_id);

	if(empty($couses_access_from))
		$couses_access_from = learndash_user_group_enrolled_to_course_from($user_id, $course_id);
		
	$visible_after = learndash_get_setting($lesson_id, "visible_after");

    if($visible_after > 0) {
	$lesson_access_from = $couses_access_from + $visible_after * 24 * 60 * 60;

    if(time() >= $lesson_access_from)
	   return null;
	else 
	   return $lesson_access_from;
    }else {
	 $visible_after_specific_date = learndash_get_setting($lesson_id, "visible_after_specific_date");
	 $specific_date = strtotime($visible_after_specific_date);
	 if(time() > $specific_date)
           return null;
	 else 
	   return $specific_date;
    }
}
function learndash_get_lesson_id($id = null) {
	global $post;
	if(empty($id))
	{
		if(!is_single() || is_home())
			return false;
			
		$id = $post->ID;
	}
	return get_post_meta($id, "lesson_id", true);	
}
function learndash_update_setting($post, $setting, $value) {
	if(is_numeric($post)) {
	$post = get_post($post);
	} else if(empty($post) || !is_object($post) || empty($post->ID)) {
		return null;
	}
	if(empty($setting))
		return null;

	$meta = get_post_meta($post->ID, "_".$post->post_type, true);
	$meta[$post->post_type."_".$setting] = $value;

	if($setting == "course")
		update_post_meta($post->ID, "course_id", $value);
	else
	if($setting == "lesson")
		update_post_meta($post->ID, "lesson_id", $value);
	
	return update_post_meta($post->ID, "_".$post->post_type, $meta );
}
function learndash_get_setting($post, $setting = null) {
	if(is_numeric($post)) {
	$post = get_post($post);
	} else if(empty($post) || !is_object($post) || empty($post->ID)) {
		return null;
	}
	if($setting == "lesson")
		return learndash_get_lesson_id($post->ID);
	if($setting == "course")
		return get_post_meta($post->ID, "course_id", true);

	$meta = get_post_meta($post->ID, "_".$post->post_type, true);
	if(empty($setting) && !empty($meta)){
		$settings = array();
		foreach($meta as $k => $v) {
			$settings[str_replace($post->post_type."_", "", $k)] = $v;
		}
		return $settings;
	}
	else
	if(isset($meta[$post->post_type."_".$setting]))
	return $meta[$post->post_type."_".$setting];
	else
	return '';
}
function ld_course_access_from($course_id, $user_id) {
	return get_user_meta($user_id, "course_".$course_id."_access_from", true);
}
function ld_update_course_access($user_id, $course_id, $remove = false) {
	if(empty($user_id) || empty($course_id))
		return;
		
	$meta = get_post_meta( $course_id, '_sfwd-courses', true );
	$access_list = $meta['sfwd-courses_course_access_list'];
	
	if(empty($remove)) {
		if ( empty( $access_list ) )
			$access_list = $user_id;
		else
			$access_list .= ",$user_id";
			
		update_user_meta($user_id, "course_".$course_id."_access_from", time());
	}
	else if (!empty( $access_list ))
	{
		$access_list = explode(",", $access_list);
		$new_access_list = array();
		foreach($access_list as $c) {
			if(trim($c) != $user_id)
			$new_access_list[] = trim($c);
		}
		$access_list = implode(",", $new_access_list);
		delete_user_meta($user_id, "course_".$course_id."_access_from");
	}
	$meta['sfwd-courses_course_access_list'] = $access_list;
	update_post_meta( $course_id, '_sfwd-courses', $meta );
	do_action("learndash_update_course_access", $user_id, $course_id, $access_list, $remove);
	return $meta;
}
// Load the auto-update class
add_action('init', 'nss_plugin_updater_activate_sfwd_lms');
//initiate the function if MySql version > 5
add_action('init', 'mysql_5_hack');

/* Function mysql_5_hack()
*	Refer to bug http://core.trac.wordpress.org/ticket/2115
*	Sql "Default NULL check" in version 5(strict mode)
*	Function to disable null checks
*/
function mysql_5_hack() {
	if(learndash_on_iis()) {
		global $wpdb;
		$sqlVersion = $wpdb->get_var("select @@version");
		if ( $sqlVersion{0} == 5 ) $wpdb->query('set sql_mode="";'); //set "Strict" mode off 
	}
}
function learndash_on_iis() {
    $sSoftware = strtolower( $_SERVER["SERVER_SOFTWARE"] );
    if ( strpos($sSoftware, "microsoft-iis") !== false )
        return true;
    else
        return false;
}
function is_course_prerequities_completed($id){
/*
  Returns True if prerequities is completed or does not exists, False otherwise
*/
			global $wp;
			$current_user = wp_get_current_user();
			$course_pre = learndash_get_course_prerequisite($id);
			if(!empty($course_pre)){ 
					//Now check if the prerequities course is completed by user or not
					$course_status = learndash_course_status($course_pre, null);
					if($course_status == __('Completed','learndash')) return true;
					else return false;
			}
			else{
					return true;
			}
}
function learndash_get_course_prerequisite($id) {
	$id = learndash_get_course_id($id);
	$post_options = get_post_meta( $id, '_sfwd-courses', true ); 
	$course_pre = isset($post_options['sfwd-courses_course_prerequisite'])? $post_options['sfwd-courses_course_prerequisite']:0;
	return $course_pre;			
}	
function learndash_certificate_details($post_id, $user_id = null) {
		$user_id = !empty($user_id)? $user_id:get_current_user_id();
		
		$certificateLink = '';
		$post = get_post($post_id);
		$meta = get_post_meta( $post_id, '_sfwd-quiz' );
		$cert_post = '';
		$certificate_threshold = '0.8';
		if ( is_array( $meta ) && !empty( $meta ) ) {
			$meta = $meta[0];
			if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_certificate'] ) ) )
				$certificate_post = $meta['sfwd-quiz_certificate'];
			if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_threshold'] ) ) )
				$certificate_threshold = $meta['sfwd-quiz_threshold'];
		}
		
		if ( !empty( $certificate_post ) )
			$certificateLink = get_permalink( $certificate_post );

		if ( !empty( $certificateLink ) )
		{
			$certificateLink .= (strpos("a".$certificateLink,"?"))? "&":"?";
			$certificateLink .= "quiz={$post->ID}&print=" . wp_create_nonce( $post->ID . $user_id);
		}
		return array('certificateLink' => $certificateLink, 'certificate_threshold' => $certificate_threshold);
}
function nss_plugin_updater_activate_sfwd_lms()
{
	//if(!class_exists('nss_plugin_updater'))
    require_once (dirname(__FILE__).'/wp_autoupdate.php');
	
	$nss_plugin_updater_plugin_remote_path = 'http://support.learndash.com/';
    $nss_plugin_updater_plugin_slug = plugin_basename(__FILE__);

    new nss_plugin_updater_sfwd_lms ($nss_plugin_updater_plugin_remote_path, $nss_plugin_updater_plugin_slug);
}

	/*** Function to add quiz continued link **/
	function learndash_quiz_continue_link($id) {
		global $status, $pageQuizzes;

		$quizmeta = get_post_meta( $id, '_sfwd-quiz' , true);
		if(!empty($quizmeta['sfwd-quiz_lesson']))
		$return_id = $quiz_lesson = $quizmeta['sfwd-quiz_lesson'];
		
		if(empty($quiz_lesson))
		{
			$return_id = $course_id = learndash_get_course_id($id);
			$url = get_permalink( $return_id );
			$url .= strpos("a".$url, "?")? "&":"?";
			$url .= 'quiz_type=global&quiz_redirect=1&course_id='.$course_id.'&quiz_id='.$id;
			$returnLink = '<a id="quiz_continue_link" href="'.$url.'">' . __('Click Here to Continue ', 'learndash') . '</a>';
		}
		else
		{
			$url = get_permalink( $return_id );
			$url .= strpos("a".$url, "?")? "&":"?";
			$url .= 'quiz_type=lesson&quiz_redirect=1&lesson_id='.$return_id.'&quiz_id='.$id;
			$returnLink = '<a id="quiz_continue_link" href="'.$url.'">' . __('Click Here to Continue ', 'learndash') . '</a>';
		}
		$version = get_bloginfo('version');
		if($version >= '1.5.1')
		return apply_filters('learndash_quiz_continue_link', $returnLink, $url);
		else
		return apply_filters('learndash_quiz_continue_link', $returnLink);
	}
	function learndash_topic_dots($lesson_id, $show_text = false, $type = "dots") {
		if(empty($lesson_id))
			return "";
			
		$topics = learndash_get_topic_list($lesson_id);

		if(empty($topics[0]->ID))
			return "";
		
		$topics_progress = learndash_get_course_progress(null, $topics[0]->ID);
		
		if(!empty($topics_progress['posts'][0]))
			$topics = $topics_progress['posts'];
		
		if($type == "array")
			return $topics;
		
		$html = "<div id='learndash_topic_dots-".$lesson_id. "' class='learndash_topic_dots type-".$type."'>";
		if(!empty($show_text))
		$html .= "<strong>".$show_text."</strong>";
		
		switch($type) {
			case "list":
	 			$html .= "<ul>";
				$sn = 0;
				foreach($topics as $topic) {
					$sn++;
					if($topic->completed)
						$completed = 'topic-completed';
					else
						$completed = 'topic-notcompleted';
					$html .= apply_filters("learndash_topic_dots_item", "<li><a class='".$completed."' href='".get_permalink($topic->ID)."'  title='".$topic->post_title."'><span>".$topic->post_title."</span></a></li>", $topic, $completed, $type, $sn);
				}
					$html .= "</ul>";
				break;
			case "dots": 
			default:
				$sn = 0;
				foreach($topics as $topic) {
					$sn++;
					if($topic->completed)
						$completed = 'topic-completed';
					else
						$completed = 'topic-notcompleted';
					$html .= apply_filters("learndash_topic_dots_item", '<a class="'.$completed.'" href="'.get_permalink($topic->ID).'"><SPAN TITLE="'.$topic->post_title.'"></SPAN></a>', $topic, $completed, $type, $sn);
				}
				break;
		}
		$html .= "</div>";
		return $html;
	}
	function ld_remove_lessons_and_quizzes_page($content) {
		if(is_archive() && !is_admin())  {
			$post_type = get_post_type();
			if($post_type == 'sfwd-lessons' || $post_type == 'sfwd-quiz')
			{
				wp_redirect(home_url());
				exit;
			}
		}
	}
	add_action("wp", 'ld_remove_lessons_and_quizzes_page');
		if(!function_exists('ld_debug')) {
		function ld_debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if(isset($_GET['debug']))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
	}
	
	function learndash_process_course_join(){
		if(!isset($_POST['course_join']) || !isset($_POST['course_id']))
			return;
			
		$user_id = get_current_user_id();
		if(empty($user_id)) {
			$login_url = wp_login_url();
			$login_url = apply_filters("learndash_course_join_redirect", $login_url, $_POST['course_id']);
			wp_redirect($login_url);
			exit;
		}
		
		$course_id = $_POST['course_id'];
		$meta = get_post_meta( $course_id, '_sfwd-courses', true );

		if(@$meta["sfwd-courses_course_price_type"] == "free" || @$meta["sfwd-courses_course_price_type"] == "paynow" && empty($meta["sfwd-courses_course_price"]) && !empty($meta["sfwd-courses_course_join"]) || sfwd_lms_has_access($course_id, $user_id))
			ld_update_course_access($user_id, $course_id);
		
	}
	add_action("wp", "learndash_process_course_join");
	
	function learndash_delete_user_data_link($user) {
		if(!current_user_can('manage_options'))
			return "";
		?>
		<div id="learndash_delete_user_data">
		<h2><?php _e('Permanently Delete Course Data', 'learndash'); ?></h2>
		<input type="checkbox" name="learndash_delete_user_data" value="<?php echo $user->ID; ?>"> <?php _e('Check and click update profile to permanently delete user\'s LearnDash course data. <strong>This cannot be undone.</strong>', 'learndash'); ?><br><br>
		</div>
		<?php	
	}
    add_action( 'show_user_profile', 'learndash_delete_user_data_link',1000,1 );
    add_action( 'edit_user_profile', 'learndash_delete_user_data_link',1000,1 );

	add_action("nss_license_footer","learndash_delete_user_data_link");
	function learndash_delete_user_data($user_id) {
		if(!current_user_can('manage_options'))
			return;
		$user = get_user_by("id", $user_id);

		if(!empty($user->ID) && !empty($_POST['learndash_delete_user_data']) && $user->ID == $_POST['learndash_delete_user_data']) {
			global $wpdb;
			$ref_ids = $wpdb->get_col($wpdb->prepare("SELECT statistic_ref_id FROM ".$wpdb->prefix."wp_pro_quiz_statistic_ref WHERE  user_id = '%d' ", $user->ID));

			if(!empty($ref_ids[0])) {
				$wpdb->delete($wpdb->prefix."wp_pro_quiz_statistic_ref", array('user_id' => $user->ID));
				$wpdb->query("DELETE FROM ".$wpdb->prefix."wp_pro_quiz_statistic WHERE statistic_ref_id IN (".implode(",", $ref_ids).")");
			}
			$wpdb->delete($wpdb->usermeta, array('meta_key' => '_sfwd-quizzes', 'user_id' => $user->ID));
			$wpdb->delete($wpdb->usermeta, array('meta_key' => '_sfwd-course_progress', 'user_id' => $user->ID));
			$wpdb->query("DELETE FROM ".$wpdb->usermeta." WHERE meta_key LIKE 'completed_%' AND user_id = '".$user->ID."'");
			$wpdb->delete($wpdb->prefix."wp_pro_quiz_toplist", array('user_id' => $user->ID));
		}
	}

    add_action( 'personal_options_update', 'learndash_delete_user_data' );
    add_action( 'edit_user_profile_update', 'learndash_delete_user_data' );
	
	function learndash_loadres() {
		wp_enqueue_style( 'learndash_style', plugins_url( 'assets/style.css', __FILE__ ) );
	}
	add_action("wp_enqueue_scripts", "learndash_loadres");
	add_action("admin_enqueue_scripts", "learndash_loadres");
	
	/*function learndash_query_post_type($query) {
		$post_types = get_post_types();
		if ( !empty($query->is_category) || !empty($query->is_tag)) {
			$query->set('post_type', 'any');
			return $query;
		}
	}

	add_filter('pre_get_posts', 'learndash_query_post_type');	*/
	
	function learndash_seconds_to_time($inputSeconds) {
		$secondsInAMinute = 60;
		$secondsInAnHour  = 60 * $secondsInAMinute;
		$secondsInADay    = 24 * $secondsInAnHour;

		$return = "";
		// extract days
		$days = floor($inputSeconds / $secondsInADay);
		$return .= empty($days)? "":$days."day";
		
		// extract hours
		$hourSeconds = $inputSeconds % $secondsInADay;
		$hours = floor($hourSeconds / $secondsInAnHour);
		$return .= (empty($hours) && empty($days))? "":" ".$hours."hr";
		
		// extract minutes
		$minuteSeconds = $hourSeconds % $secondsInAnHour;
		$minutes = floor($minuteSeconds / $secondsInAMinute);
		$return .= (empty($hours) && empty($days) && empty($minutes))? "":" ".$minutes."min";
		
		// extract the remaining seconds
		$remainingSeconds = $minuteSeconds % $secondsInAMinute;
		$seconds = ceil($remainingSeconds);
		$return .= " ".$seconds."sec";

		return trim($return);
	}
	
	function learndash_remove_comments($comments, $array) {
		return array();
	}
	add_filter('widget_text', 'do_shortcode');
	
	function lesson_visible_after($content, $post) {
		if(empty($post->post_type))
			return $content;
		if($post->post_type == "sfwd-lessons")
			$lesson_id = $post->ID;
		else
		if($post->post_type == "sfwd-topic" || $post->post_type == "sfwd-quiz")
		{
			$lesson_id = learndash_get_setting($post, "lesson");
			if(empty($lesson_id))
				return $content;
		}
		else
			return $content;

		$lesson_access_from = ld_lesson_access_from($lesson_id, get_current_user_id());
		if(empty($lesson_access_from))
			return $content;
		else
		{
			$content = sprintf(__(" Available on: %s ", "learndash"), date("d-M-Y", $lesson_access_from))."<br><br>";
			$course_id = learndash_get_course_id($lesson_id);
			$course_link = get_permalink($course_id);
			$content .= "<a href='".$course_link."'>". __("Return to Course Overview", "learndash") . "</a>";
		
			return "<div class='notavailable_message'>".apply_filters("leardash_lesson_available_from_text", $content, $post, $lesson_access_from)."</div>";
		}
		return $content;
	}
	add_filter("learndash_content", "lesson_visible_after", 1, 2);
	
	function learndash_lms_reports_page() {
		?>
		<div  id="learndash-reports"  class="wrap">
			<h2><?php _e("User Reports", "learndash"); ?></h2>
			<br>
			<div class="sfwd_settings_left">
				<div class=" " id="sfwd-learndash-reports_metabox">
					<div class="inside">
						<a  class="button-primary" href="<?php echo admin_url('admin.php?page=learndash-lms-reports&action=sfp_update_module&nonce-sfwd='.wp_create_nonce('sfwd-nonce').'&page_options=sfp_home_description&courses_export_submit=Export'); ?>"><?php _e("Export User Course Data", "learndash"); ?></a> <a  class="button-primary" href="<?php echo admin_url('admin.php?page=learndash-lms-reports&action=sfp_update_module&nonce-sfwd='.wp_create_nonce('sfwd-nonce').'&page_options=sfp_home_description&quiz_export_submit=Export'); ?>"><?php _e("Export Quiz Data", "learndash"); ?></a> <?php do_action("learndash_report_page_buttons"); ?>
					</div>
				</div>		
			</div>
		</div>
		<?php
	}
	function learndash_menu() {
		if(!is_admin())
			return;
	    add_menu_page(__("LearnDash LMS", "learndash"), __("LearnDash LMS", "learndash"), "read", "learndash-lms", null, null, null);
	    add_submenu_page("learndash-lms-non-existant", __("LearnDash Reports", "learndash"), __("LearnDash Reports", "learndash"), "manage_options", "learndash-lms-reports", "learndash_lms_reports_page");
	    add_submenu_page("learndash-lms-non-existant", __("Certificate Shortcodes", "learndash"), __("Certificate Shortcodes", "learndash"), "manage_options", "learndash-lms-certificate_shortcodes", "learndash_certificate_shortcodes_page");
	    add_submenu_page("learndash-lms-non-existant", __("Course Shortcodes", "learndash"), __("Course Shortcodes", "learndash"), "edit_courses", "learndash-lms-course_shortcodes", "learndash_course_shortcodes_page");

		$remove_from_submenu = array(
				"options-general.php?page=nss_plugin_license-sfwd_lms-settings" => __('LearnDash LMS License', 'learndash'),
				"admin.php?page=learndash-lms-reports" => "Reports",
				);
		$remove_from_menu = array(
				"edit.php?post_type=sfwd-courses",
				"edit.php?post_type=sfwd-lessons",
				"edit.php?post_type=sfwd-quiz",
				"edit.php?post_type=sfwd-topic",
				"edit.php?post_type=sfwd-certificates",
				"edit.php?post_type=sfwd-assignment",
				"edit.php?post_type=groups",
				);
		global $submenu;
		//echo '<pre>';
		//print_r($submenu);
		//echo '</pre>';
		$add_submenu = array(
				array(
						"name" 	=>	__("Courses", "learndash"),
						"cap"	=>	"edit_courses",
						"link"	=> "edit.php?post_type=sfwd-courses"
					),
				array(
						"name" 	=>	__("Lessons", "learndash"),
						"cap"	=>	"edit_courses",
						"link"	=> "edit.php?post_type=sfwd-lessons"
					),
				array(
						"name" 	=>	__("Topics", "learndash"),
						"cap"	=>	"edit_courses",
						"link"	=> "edit.php?post_type=sfwd-topic"
					),
				array(
						"name" 	=>	__("Quizzes", "learndash"),
						"cap"	=>	"edit_courses",
						"link"	=> "edit.php?post_type=sfwd-quiz"
					),
				array(
						"name" 	=>	__("Certificates", "learndash"),
						"cap"	=>	"edit_courses",
						"link"	=> "edit.php?post_type=sfwd-certificates"
					),
				array(
						"name" 	=>	__("Assignments", "learndash"),
						"cap"	=>	"edit_assignments",
						"link"	=> "edit.php?post_type=sfwd-assignment"
					),
				"groups" => array(
						"name" 	=>	__("Groups", "learndash"),
						"cap"	=>	"edit_groups",
						"link"	=> "edit.php?post_type=groups"
					),
				array(
						"name" 	=>	__("Reports", "learndash"),
						"cap"	=>	"manage_options",
						"link"	=> "admin.php?page=learndash-lms-reports"
					),
				array(
						"name" 	=>	__("Settings", "learndash"),
						"cap"	=>	"manage_options",
						"link"	=> "edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses"
					),
				array(
						"name" 	=>	__("Group Administration","learndash"),
						"cap"	=>	"group_leader",
						"link"	=> "admin.php?page=group_admin_page"
					),
			);

		$add_submenu = apply_filters("learndash_submenu", $add_submenu);
		$location = 500;
		foreach ($add_submenu as $key => $add_submenu_item) {
			if(current_user_can($add_submenu_item["cap"]))
  			$submenu['learndash-lms'][$location++] = array($add_submenu_item['name'], $add_submenu_item['cap'], $add_submenu_item['link']);			
		}

		foreach ($remove_from_menu as $menu) {
			if(isset($submenu[$menu]))
				remove_menu_page( $menu );
		}

		foreach($remove_from_submenu as $menu => $remove_submenu_items) {
			if(isset($submenu[$menu]) && is_array($submenu[$menu])) {
				foreach($submenu[$menu] as $key => $item) {
					if(isset($item[0]) && in_array($item[0], $remove_submenu_items)) {
						unset($submenu[$menu][$key]);
					}
				}
			}
		}

	}

	add_action('admin_menu', 'learndash_menu', 1000);

	function learndash_select_menu()
	{
		global $learndash_current_page_link;
		?>
	        <script type="text/javascript">
	        jQuery(window).load( function($) {
	        	jQuery("body").removeClass("sticky-menu");
	        	jQuery("#toplevel_page_learndash-lms").addClass('current wp-has-current-submenu wp-menu-open');
	        	<?php if(!empty($learndash_current_page_link)) { ?>
	        		jQuery("#toplevel_page_learndash-lms a[href='<?php echo $learndash_current_page_link;?>']").parent().addClass("current");
	        	<?php } ?>
	        });     
	        </script>
		<?php
	};

	function learndash_admin_tabs() {
		if(!is_admin())
			return;
		$admin_tabs = array(
							0 	=>	array(
									"link"	=>	"post-new.php?post_type=sfwd-courses",
									"name"	=>	__("Add New", "learndash"),
									"id"	=>	"sfwd-courses",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses",
									),
							10 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-courses",
									"name"	=>	__("Courses", "learndash"),
									"id"	=>	"edit-sfwd-courses",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses",
									),

							24 	=>	array(
									"link"	=>	"edit-tags.php?taxonomy=category&post_type=sfwd-courses",
									"name"	=>	__("Categories", "learndash"),
									"id"	=>	"edit-category",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses",
								),
							26 	=>	array(
									"link"	=>	"edit-tags.php?taxonomy=post_tag&post_type=sfwd-courses",
									"name"	=>	__("Tags", "learndash"),
									"id"	=>	"edit-post_tag",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses",
								),
							28 	=>	array(
									"link"	=>	"admin.php?page=learndash-lms-course_shortcodes",
									"name"	=>	__("Course Shortcodes", "learndash"),
									"id"	=>	"admin_page_learndash-lms-course_shortcodes",
									"cap"	=> "edit_courses",
									"menu_link"	=>	"edit.php?post_type=sfwd-courses",
								),


							30 	=>	array(
									"link"	=>	"post-new.php?post_type=sfwd-lessons",
									"name"	=>	__("Add New", "learndash"),
									"id"	=>	"sfwd-lessons",
									"menu_link"	=> 	"edit.php?post_type=sfwd-lessons",
									),
							40 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-lessons",
									"name"	=>	__("Lessons", "learndash"),
									"id"	=>	"edit-sfwd-lessons",
									"menu_link"	=> 	"edit.php?post_type=sfwd-lessons",
									),
							50 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-lessons&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-lessons",
									"name"	=>	__("Lesson Options", "learndash"),
									"id"	=>	"sfwd-lessons_page_sfwd-lms_sfwd_lms_post_type_sfwd-lessons",
									"menu_link"	=> 	"edit.php?post_type=sfwd-lessons",
								),


							60 	=>	array(
									"link"	=>	"post-new.php?post_type=sfwd-topic",
									"name"	=>	__("Add New", "learndash"),
									"id"	=>	"sfwd-topic",
									"menu_link"	=> 	"edit.php?post_type=sfwd-topic",
									),
							70 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-topic",
									"name"	=>	__("Topics", "learndash"),
									"id"	=>	"edit-sfwd-topic",
									"menu_link"	=> 	"edit.php?post_type=sfwd-topic",
									),


							80 	=>	array(
									"link"	=>	"post-new.php?post_type=sfwd-quiz",
									"name"	=>	__("Add New", "learndash"),
									"id"	=>	"sfwd-quiz",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
									),
							90 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-quiz",
									"name"	=>	__("Quizzes", "learndash"),
									"id"	=>	"edit-sfwd-quiz",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
									),
							


							100 	=>	array(
									"link"	=>	"admin.php?page=ldAdvQuiz&module=globalSettings",
									"name"	=>	__("Quiz Options", "learndash"),
									"id"	=>	"admin_page_ldAdvQuiz_globalSettings",
									"cap"	=> "wpProQuiz_change_settings",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
								),

							101 	=>	array(
									"link"	=>	"admin.php?page=ldAdvQuiz",
									"name"	=>	__("Import/Export", "learndash"),
									"id"	=>	"admin_page_ldAdvQuiz",
									'cap'	=> 'wpProQuiz_export',
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
								),

							95 =>	array(
									"link"	=>	"post.php?post=[post_id]&action=edit",
									"name"	=>	__("Edit Quiz", "learndash"),
									"id"	=>	"sfwd-quiz_edit",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
									),

							102 	=>	array(
									"link"	=>	"admin.php?page=ldAdvQuiz&module=question&quiz_id=[quiz_id]&post_id=[post_id]",
									"name"	=>	__("Questions", "learndash"),
									"id"	=>	"admin_page_ldAdvQuiz_question",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
								),

							104 	=>	array(
									"link"	=>	"admin.php?page=ldAdvQuiz&module=statistics&id=[quiz_id]&post_id=[post_id]",
									"name"	=>	__("Statistics", "learndash"),
									"id"	=>	"admin_page_ldAdvQuiz_statistics",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
								),

							106 	=>	array(
									"link"	=>	"admin.php?page=ldAdvQuiz&module=toplist&id=[quiz_id]&post_id=[post_id]",
									"name"	=>	__("Leaderboard", "learndash"),
									"id"	=>	"admin_page_ldAdvQuiz_toplist",
									"menu_link"	=>	"edit.php?post_type=sfwd-quiz",
								),

							110 	=>	array(
									"link"	=>	"post-new.php?post_type=sfwd-certificates",
									"name"	=>	__("Add New", "learndash"),
									"id"	=>	"sfwd-certificates",
									"menu_link"	=>	"edit.php?post_type=sfwd-certificates",
									),
							120 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-certificates",
									"name"	=>	__("Certificates", "learndash"),
									"id"	=>	"edit-sfwd-certificates",
									"menu_link"	=>	"edit.php?post_type=sfwd-certificates",
									),
							130 	=>	array(
									"link"	=>	"admin.php?page=learndash-lms-certificate_shortcodes",
									"name"	=>	__("Certificate Shortcodes", "learndash"),
									"id"	=>	"admin_page_learndash-lms-certificate_shortcodes",
									"menu_link"	=>	"edit.php?post_type=sfwd-certificates",
								),

							135 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses",
									"name"	=>	__("PayPal Settings", "learndash"),
									"id"	=>	"sfwd-courses_page_sfwd-lms_sfwd_lms_post_type_sfwd-courses",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses",
								),

							140 	=>	array(
									"link"	=>	"admin.php?page=nss_plugin_license-sfwd_lms-settings",
									"name"	=>	__("LMS License", "learndash"),
									"id"	=>	"admin_page_nss_plugin_license-sfwd_lms-settings",
									"menu_link"	=> 	"edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses",
									),

							150 	=>	array(
									"external_link"	=>	"http://support.learndash.com",
									"target"=> "_blank",
									"name"	=>	__("Support", "learndash"),
									"id"	=>	"external_link_support_learndash",
									),

							160 	=>	array(
									"link"	=>	"admin.php?page=learndash-lms-reports",
									"name"	=>	__("User Reports", "learndash"),
									"id"	=>	"admin_page_learndash-lms-reports",
									"menu_link"	=>	"admin.php?page=learndash-lms-reports",
									),
							22 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-transactions",
									"name"	=>	__("Transactions", "learndash"),
									"id"	=>	"edit-sfwd-transactions",
									"menu_link"	=>	"admin.php?page=learndash-lms-reports",
								),

							170 	=>	array(
									"link"	=>	"edit.php?post_type=groups",
									"name"	=>	__("LearnDash Groups", "learndash"),
									"id"	=>	"edit-groups",
									"menu_link"	=>	"edit.php?post_type=groups",
									),
							180 	=>	array(
									"link"	=>	"edit.php?post_type=sfwd-assignment",
									"name"	=>	__("Assignments", "learndash"),
									"id"	=>	"edit-sfwd-assignment",
									"menu_link"	=>	"edit.php?post_type=sfwd-assignment",
									),
							"group_admin_page"	=> array(
										"id"	=> "admin_page_group_admin_page",
										"name" 	=>	__("Group Administration","learndash"),
										"cap"	=>	"group_leader",
										"menu_link"	=> "admin.php?page=group_admin_page"
									),
					);

		$admin_tabs = apply_filters("learndash_admin_tabs", $admin_tabs);

		foreach ($admin_tabs as $key => $admin_tab) {
			if(!empty($admin_tab['cap'])) {
				if(!current_user_can($admin_tab['cap']))
					unset($admin_tabs[$key]);
			}	
		}
		$admin_tabs_on_page = array(
				"edit-sfwd-courses"	=> array(0,10,28, 24,26),
				"sfwd-courses"	=> array(0,10,28, 24,26),
				"admin_page_learndash-lms-course_shortcodes"	=> array(0,10,28, 24,26),

				"edit-sfwd-lessons"	=> array(30,40,50),
				"sfwd-lessons_page_sfwd-lms_sfwd_lms_post_type_sfwd-lessons"	=> array(30,40,50),
				"sfwd-lessons"	=> array(30,40,50),

				"edit-sfwd-topic"	=> array(60,70),
				"sfwd-topic"	=> array(60,70),

				"edit-sfwd-quiz"	=> array(80,90,100,101),
				"sfwd-quiz"			=> array(80,90,100,101),
				"sfwd-quiz_edit"	=> array(80,90,100, 101, 95),
				"admin_page_ldAdvQuiz"	=> array(80,90,100,101),
				"admin_page_ldAdvQuiz_globalSettings"	=> array(80,90,100,101),

				"edit-sfwd-certificates"	=> array(110,120,130),
				"admin_page_learndash-lms-certificate_shortcodes"	=> array(110,120,130),
				"sfwd-certificates"	=> array(110,120,130),

				"admin_page_learndash-lms-reports" => array(160, 22),
				"edit-sfwd-transactions"	=> array(160, 22),
				
				"sfwd-courses_page_sfwd-lms_sfwd_lms_post_type_sfwd-courses"	=> array(135, 140, 150),
				"admin_page_nss_plugin_license-sfwd_lms-settings"	=> array(135, 140, 150),
			);
		if(isset($_GET["post_type"]) && $_GET["post_type"] == "sfwd-courses") {
			$admin_tabs_on_page["edit-category"] = array(0,10,28,24,26);
			$admin_tabs_on_page["edit-post_tag"] = array(0,10,28,24,26);
		}
		$current_page_id = get_current_screen()->id;//echo $current_page_id;

		$post_id = !empty($_GET["post_id"])? $_GET["post_id"]:(empty($_GET["post"])? 0:$_GET["post"]);
		if(empty($post_id) && !empty($_GET["quiz_id"]) && $current_page_id == "admin_page_ldAdvQuiz") {
			$post_id = learndash_get_quiz_id_by_pro_quiz_id($_GET["quiz_id"]);
		}

		if($current_page_id == "sfwd-quiz" || $current_page_id == "admin_page_ldAdvQuiz") {
			if(!empty($_GET["module"])) {
				$current_page_id = $current_page_id."_".$_GET["module"];
				if(empty($admin_tabs_on_page[$current_page_id])) 
					$admin_tabs_on_page[$current_page_id] = $admin_tabs_on_page["admin_page_ldAdvQuiz"];
			}
			else if(!empty($_GET["post"]))
				$current_page_id = $current_page_id."_edit";
			
			if(!empty($post_id)) {
				$quiz_id = learndash_get_setting($post_id, "quiz_pro", true);
				if(!empty($quiz_id)) {
					$admin_tabs_on_page[$current_page_id] =  array(80,90,100,101,95,102,104,106);
					foreach ($admin_tabs_on_page[$current_page_id] as $admin_tab_id) {
						$admin_tabs[$admin_tab_id]["link"] = str_replace("[quiz_id]", $quiz_id, $admin_tabs[$admin_tab_id]["link"]);
					}
				}
			}
		}
		$admin_tabs_on_page = apply_filters("learndash_admin_tabs_on_page", $admin_tabs_on_page, $admin_tabs, $current_page_id);

		if(empty($admin_tabs_on_page[$current_page_id]))
			$admin_tabs_on_page[$current_page_id] = array();

		$admin_tabs_on_page[$current_page_id] = apply_filters("learndash_current_admin_tabs_on_page", $admin_tabs_on_page[$current_page_id], $admin_tabs, $admin_tabs_on_page, $current_page_id);

	//	echo $current_page_id;
		if(!empty($post_id)) {
			foreach ($admin_tabs_on_page[$current_page_id] as $admin_tab_id) {
				$admin_tabs[$admin_tab_id]["link"] = str_replace("[post_id]", $post_id, $admin_tabs[$admin_tab_id]["link"]);
			}
		}	

		if(!empty($admin_tabs_on_page[$current_page_id]) && count($admin_tabs_on_page[$current_page_id])) {
			echo '<h2 class="nav-tab-wrapper">';
			$tabid = 0;
			foreach ($admin_tabs_on_page[$current_page_id] as $admin_tab_id) {
				if(!empty($admin_tabs[$admin_tab_id]["id"])) {
				$class = ($admin_tabs[$admin_tab_id]["id"] == $current_page_id)? "nav-tab nav-tab-active":"nav-tab";
				$url = !empty($admin_tabs[$admin_tab_id]["external_link"])? $admin_tabs[$admin_tab_id]["external_link"]:admin_url($admin_tabs[$admin_tab_id]["link"]);
				$target = !empty($admin_tabs[$admin_tab_id]["target"])? 'target="'.$admin_tabs[$admin_tab_id]["target"].'"':'';
				echo '<a href="'.$url.'" class="'.$class.' nav-tab-'.$admin_tabs[$admin_tab_id]["id"].'"  '.$target.'>'.$admin_tabs[$admin_tab_id]["name"].'</a>';
				}
			}
			echo '</h2>';
		}

		foreach ($admin_tabs as $admin_tab) {
			//echo "<Br>".$current_page_id ." == ". trim($admin_tab["id"]);
			
			if($current_page_id == trim($admin_tab["id"])) {
				//add_action( "admin_print_scripts", Array( $this, 'enqueue_scripts' ) );
				//add_action( "admin_print_styles", Array( $this, 'enqueue_styles' ) );

				global $learndash_current_page_link;
				$learndash_current_page_link = trim(@$admin_tab["menu_link"]);
				add_action( "admin_footer", "learndash_select_menu");
				break;
			}			
		}
	}
	add_action( 'all_admin_notices', 'learndash_admin_tabs');	
	
	function learndash_admin_bar_link() {
    global $wp_admin_bar;
    global $post;
    if ( !is_super_admin() || !is_admin_bar_showing() )
        return;
    if ( is_single() && $post->post_type == "sfwd-topic")
    $wp_admin_bar->add_menu( array(
        'id' => 'edit_fixed',
        'parent' => false,
        'title' => __( 'Edit Topic', 'learndash'),
        'href' => get_edit_post_link($post->id)
    ) );
	}
	add_action( 'wp_before_admin_bar_render', 'learndash_admin_bar_link' );
	
	
/*	function change_meta_box_name($post) {
		remove_meta_box("categorydiv", "sfwd-courses", "normal");
		//remove_meta_box("categorydiv", "sfwd-lessons", "normal");
		//remove_meta_box("categorydiv", "sfwd-quiz", "normal");
		add_meta_box("categorydiv", "LearnDash Categories", "post_category_meta_box", "sfwd-courses", "advanced", "high");
	}*/
	function learndash_get_quiz_id_by_pro_quiz_id($quiz_id) {
		$opt = array(
		    'post_type' => 'sfwd-quiz',
		    'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
			'posts_per_page'	=> -1
		);
		$quizzes = get_posts($opt);
		foreach ($quizzes as $quiz) {
			$pro_quiz_id  = learndash_get_setting($quiz, "quiz_pro", true);
			if($quiz_id == $pro_quiz_id)
				return $quiz->ID;
		}
	}
	function learndash_payment_buttons($course) {
						
			if(is_numeric($course)) {
				$course_id = $course;
				$course = get_post($course_id);
			}
			else if(!empty($course->ID)) {
				$course_id = $course->ID;
			}
			else
				return "";
				
			$user_id = get_current_user_id();
			
			if($course->post_type != "sfwd-courses")
				return "";
			$meta = get_post_meta($course_id, "_sfwd-courses", true);
			$course_price_type = @$meta["sfwd-courses_course_price_type"];
			$course_price = @$meta["sfwd-courses_course_price"];
			$course_no_of_cycles = @$meta["sfwd-courses_course_no_of_cycles"];
			$course_price = @$meta["sfwd-courses_course_price"];
			$custom_button_url = @$meta["sfwd-courses_custom_button_url"];

			$courses_options = learndash_get_option("sfwd-courses");
			if(!empty($courses_options))
			extract($courses_options);
			$paypal_sandbox = empty($paypal_sandbox)? 0:1;

		if(sfwd_lms_has_access($course->ID, $user_id))
			return "";

		if(!empty($course_price_type) && $course_price_type == "closed") {
			if(empty($custom_button_url))
				$custom_button = '';
			else {
				if(!strpos($custom_button_url, "://"))
					$custom_button_url = "http://".$custom_button_url;
				$custom_button = '<a class="btn-join" href="'.$custom_button_url.'" id="btn-join">'.__("Take this Course", "learndash").'</a>';		
			}
			 $payment_params = array('custom_button_url' => $custom_button_url,
									'post' => $course);
			return 	apply_filters('learndash_payment_closed_button', $custom_button, $payment_params);
		}
		else if(!empty( $course_price )) {
			include_once('enhanced-paypal-shortcodes.php');

			$paypal_button = '';
			if ( !empty( $paypal_email ) ) {
				
				if(empty($course_price_type) || $course_price_type == "paynow")
				$paypal_button = wptexturize(do_shortcode("<div class='learndash_checkout_button learndash_paypal_button'>[paypal type='paynow' amount='{$course_price}' sandbox='{$paypal_sandbox}' email='{$paypal_email}' itemno='{$course->ID}' name='{$course->post_title}' noshipping='1' nonote='1' qty='1' currencycode='{$paypal_currency}' rm='2' notifyurl='{$paypal_notifyurl}' returnurl='{$paypal_returnurl}' scriptcode='scriptcode' imagewidth='100px' pagestyle='paypal' lc='{$paypal_country}' cbt='" . __('Complete Your Purchase', 'learndash') . "' custom='".$user_id."']</div>"));
				else if($course_price_type == "subscribe")
				{
					$course_price_billing_p3 = get_post_meta($course_id, "course_price_billing_p3",  true);
					$course_price_billing_t3 = get_post_meta($course_id, "course_price_billing_t3",  true);
					$srt = intval($course_no_of_cycles);
					$paypal_button = wptexturize(do_shortcode("<div class='learndash_checkout_button learndash_paypal_button'>[paypal type='subscribe' a3='{$course_price}' p3='{$course_price_billing_p3}' t3='{$course_price_billing_t3}' sandbox='{$paypal_sandbox}' email='{$paypal_email}' itemno='{$course->ID}' name='{$course->post_title}' noshipping='1' nonote='1' qty='1' currencycode='{$paypal_currency}' rm='2' notifyurl='{$paypal_notifyurl}' returnurl='{$paypal_returnurl}' scriptcode='scriptcode' imagewidth='100px' pagestyle='paypal' lc='{$paypal_country}' cbt='" . __('Complete Your Purchase', 'learndash') . "' custom='".$user_id."' srt='{$srt}']</div>"));
					
				}
			}
			 $payment_params = array('price' => $course_price,
									'post' => $course);


			 
			 $payment_buttons = apply_filters('learndash_payment_button', $paypal_button, $payment_params);

			//if(empty($payment_buttons))
			// $payment_buttons = __('The buyer PayPal email is empty; please configure this in the plugin or use alternative payment gateway.', 'learndash');
			 if(!empty($payment_buttons))
			 return '<div class="learndash_checkout_buttons">'.$payment_buttons.'</div>';
		}
		else
		{
			return '<div class="learndash_join_button"><form method="post">
							<input type="hidden" value="'.$course->ID.'" name="course_id">
							<input type="submit" value="'.__("Take this Course", "learndash").'" name="course_join" class="btn-join" id="btn-join">
						</form></div>';
		}
	
	}
	
	function learndash_payment_buttons_shortcode($attr) {
		 $shortcode_atts = shortcode_atts ( array(
			'course_id' => 0
			), $attr);
		extract($shortcode_atts);
		if(empty($course_id))
			return "";
		else
			return learndash_payment_buttons($course_id);
	}
	add_shortcode("learndash_payment_buttons", "learndash_payment_buttons_shortcode");
	
	
	function learndash_is_sample($post) {
		if(empty($post))
			return false;

		if(is_numeric($post)) {
			$post = get_post($post);
		}
		if(empty($post->ID))
			return false;

		if($post->post_type == "sfwd-lessons") {
			if(learndash_get_setting($post->ID, "sample_lesson"))
				return true;
		}
		if($post->post_type == "sfwd-topic") {
			$lesson_id = learndash_get_setting($post->ID, "lesson");
			if(learndash_get_setting($lesson_id, "sample_lesson"))
				return true;
		}
		if($post->post_type == "sfwd-quiz") {
			$lesson_id = learndash_get_setting($post->ID, "lesson");
			return learndash_is_sample($lesson_id);
		}
		return false;
	}
	
	//Functions for additional columns on Post types Lessons, Topics and Quizes
	function add_shortcode_data_columns($cols) {
  
	    return array_merge(
				array_slice( $cols, 0, 3 ),
				array( 	'shortcode' => __('Shortcode', 'learndash') ),
				array_slice( $cols, 3 )
			);
	}	
	function add_course_data_columns($cols) {
  
	    return array_merge(
				array_slice( $cols, 0, 3 ),
				array( 	'course' => __('Assigned Course', 'learndash') ),
				array_slice( $cols, 3 )
			);
	}
	function add_lesson_data_columns($cols) {
  
	    return array_merge(
				array_slice( $cols, 0, 3 ),
				array( 	'lesson' => __('Assigned Lesson', 'learndash'),
				 	'course' => __('Assigned Course', 'learndash')
				),
				array_slice( $cols, 3 )
			);
	}
	function add_assignment_data_columns($cols) {
  
	    return array_merge(
				array_slice( $cols, 0, 3 ),
				array( 	'approval_status' => __('Status', 'learndash'),
				),
				array_slice( $cols, 3 )
			);
	}
	function remove_tags_column($cols){
		unset($cols["tags"]);
		return $cols;
	}
	function remove_categories_column($cols){
		unset($cols["categories"]);
		return $cols;
	}
	function manage_asigned_assignment_columns($column_name, $id) {
		switch ($column_name) {
			case 'approval_status':
				if(learndash_is_assignment_approved_by_meta($id))
				{
					$url = admin_url( 'edit.php?post_type='.@$_GET['post_type'].'&approval_status=1');
					echo '<a href="'.$url.'">'.__("Approved", "learndash").'</a>';
				}
				else
				{
					$url = admin_url( 'edit.php?post_type='.@$_GET['post_type'].'&approval_status=0');
					echo '<a href="'.$url.'">'.__("Not Approved", "learndash").'</a>';
				}
				break;
		}			
	}

	function manage_asigned_course_columns($column_name, $id){
		switch ($column_name) {
		case 'shortcode':
			$quiz_pro = learndash_get_setting($id, "quiz_pro", true);
			if(!empty($quiz_pro))
				echo '[LDAdvQuiz '.$quiz_pro.']';
			else
				echo '-';
			break;
		case 'course':
			$url = admin_url( 'edit.php?post_type='.@$_GET['post_type'].'&course_id='.learndash_get_course_id($id));
			if (learndash_get_course_id($id)){
				echo '<a href="'.$url .'">'.get_the_title(learndash_get_course_id($id)).'</a>';
			}
			else{
				echo '&#8212;';
			}
			break;
			
			
			case 'lesson':
			$parent_id = learndash_get_setting($id, "lesson");
			if(!empty($parent_id)) {
			$url = admin_url( 'edit.php?post_type='.@$_GET['post_type'].'&lesson_id='.$parent_id);
			echo '<a href="'.$url.'">'.get_the_title($parent_id).'</a>';
			}
			else
				echo  '&#8212;';
			break;
			default:
			break;
		}	
	}
	
	function restrict_listings_by_course() {
       	global $pagenow;

	 	if( is_admin() AND $pagenow == 'edit.php'  AND isset($_GET['post_type']) AND ( $_GET['post_type'] == 'sfwd-lessons' OR $_GET['post_type'] == 'sfwd-topic' OR $_GET['post_type'] == 'sfwd-quiz' OR $_GET['post_type'] == 'sfwd-assignment') ) {
      	
			$filters = get_posts('post_type=sfwd-courses&posts_per_page=-1');
			echo "<select name='course_id' id='course_id' class='postform'>";
			echo "<option value=''>".__("Show All Courses", "learndash")."</option>";
			foreach ($filters as $post) {
				echo '<option value='. $post->ID, ($_GET['course_id'] == $post->ID ? ' selected="selected"' : '').'>' . $post->post_title .'</option>';		
			}
		    echo "</select>";

		    if($_GET['post_type'] == "sfwd-topic" OR $_GET['post_type'] == "sfwd-assignment") {
		    	$filters = get_posts( 'post_type=sfwd-lessons&posts_per_page=-1');
				echo "<select name='lesson_id' id='lesson_id' class='postform'>";
				echo "<option value=''>".__("Show All Lessons", 'learndash')."</option>";
				foreach ($filters as $post) {
					echo '<option value='. $post->ID, ($_GET['lesson_id'] == $post->ID ? ' selected="selected"' : '').'>' . get_the_title($post->ID) .'</option>';		
				}
			    echo "</select>";
		    }
		    if($_GET['post_type'] == "sfwd-assignment") {
		    	if(isset($_GET['approval_status'])) 
		    	if($_GET['approval_status'] == 1)
		    	{	
		    		$selected_1 = 'selected="selected"';
		    		$selected_0 = '';
		    	}
		    	else if($_GET['approval_status'] == 0)
		    	{	
		    		$selected_0 = 'selected="selected"';
		    		$selected_1 = '';
		    	}
		    	?>
				<select name='approval_status' id='approval_status' class='postform'>
					<option value='-1'><?php _e("Approval Status", 'learndash'); ?></option>
					<option value='1' <?php echo $selected_1; ?>><?php _e("Approved", 'learndash'); ?></option>	
					<option value='0' <?php echo $selected_0; ?>><?php _e("Not Approved", 'learndash'); ?></option>	
			    </select>
		    	<?php 
		    }
		}
	}

	function course_table_filter($query) {
	  global $pagenow;
	  $q_vars = &$query->query_vars;
	   if( is_admin() AND $pagenow == 'edit.php'  AND !empty($_GET['course_id']) AND ( $query->query['post_type'] == 'sfwd-lessons' OR $query->query['post_type'] == 'sfwd-topic' OR $query->query['post_type'] == 'sfwd-quiz' OR $query->query['post_type'] == 'sfwd-assignment') ) {
			$q_vars["meta_query"][] = array(
											"key" => "course_id",
											"value"	=> $_GET['course_id']
										);
	  }
	  if( is_admin() AND $pagenow == 'edit.php'  AND !empty($_GET['lesson_id']) AND ( $query->query['post_type'] == 'sfwd-topic' OR $query->query['post_type'] == 'sfwd-assignment') ) {
			$q_vars["meta_query"][] = array(
											"key" => "lesson_id",
											"value"	=> $_GET['lesson_id']
										);
	  }
	  if( is_admin() AND $pagenow == 'edit.php'  AND isset($_GET['approval_status']) AND ( $query->query['post_type'] == 'sfwd-topic' OR $query->query['post_type'] == 'sfwd-assignment') ) {
			if($_GET['approval_status'] == 1)
			$q_vars["meta_query"][] = array(
											"key" => "approval_status",
											"value"	=> 1
										);
			else if($_GET['approval_status'] == 0)
			$q_vars["meta_query"][] = array(
											"key" => "approval_status",
											"compare" => 'NOT EXISTS'
										);				
	  }
	 }
	function learndash_generate_patent_course_and_lesson_id_onetime() {
		/* This will run one time to generate lesson id's and course id's once for all existing lessons, quizzes and topics */
		if(isset($_GET['learndash_generate_patent_course_and_lesson_ids_onetime']) || get_option("learndash_generate_patent_course_and_lesson_ids_onetime", "yes") == "yes") {
			$quizzes = get_posts("post_type=sfwd-quiz&posts_per_page=-1");
			if(!empty($quizzes))
			foreach($quizzes as $quiz) {
				update_post_meta($quiz->ID, "course_id", learndash_get_course_id($quiz->ID));			
				$meta = get_post_meta($quiz->ID, "_sfwd-quiz", true);
				if(!empty($meta['sfwd-quiz_lesson']))
					update_post_meta($quiz->ID, "lesson_id", $meta['sfwd-quiz_lesson']);			
			}//exit;
			$topics = get_posts("post_type=sfwd-topic&posts_per_page=-1");
			if(!empty($topics))
			foreach($topics as $topic) {
				update_post_meta($topic->ID, "course_id", learndash_get_course_id($topic->ID));			
				$meta = get_post_meta($topic->ID, "_sfwd-topic", true);
				if(!empty($meta['sfwd-topic_lesson']))
					update_post_meta($topic->ID, "lesson_id", $meta['sfwd-topic_lesson']);			
			}
			$lessons = get_posts("post_type=sfwd-lessons&posts_per_page=-1");
			if(!empty($lessons))
			foreach($lessons as $lesson) {
				update_post_meta($lesson->ID, "course_id", learndash_get_course_id($lesson->ID));			
			}
			update_option("learndash_generate_patent_course_and_lesson_ids_onetime", "no");
		}
	}
	add_action("admin_init", "learndash_generate_patent_course_and_lesson_id_onetime");
	function learndash_patent_course_and_lesson_id_save($post_id) {

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
		if( 'sfwd-lessons' == $_POST['post_type'] || 'sfwd-quiz' == $_POST['post_type'] || 'sfwd-topic' == $_POST['post_type'] ) {
			if(isset($_POST[$_POST['post_type'].'_course']))			
				update_post_meta($post_id, "course_id", @$_POST[$_POST['post_type'].'_course']);			
		}
		if( 'sfwd-topic' == $_POST['post_type'] || 'sfwd-quiz' == $_POST['post_type'] ) {
			if(isset($_POST[$_POST['post_type'].'_lesson']))			
				update_post_meta($post_id, "lesson_id", @$_POST[$_POST['post_type'].'_lesson']);			
		}
		if('sfwd-lessons' == $_POST['post_type'] || 'sfwd-topic' == $_POST['post_type']) {
			global $wpdb;
			if(isset($_POST[$_POST['post_type'].'_course']))
			$course_id = get_post_meta($post_id, "course_id", true);
			if(!empty($course_id)) {
				$posts_with_lesson = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'lesson_id' AND meta_value = '%d'", $post_id));
				//print_r($posts_with_lesson);
				if(!empty($posts_with_lesson) && !empty($posts_with_lesson[0]))
				foreach ($posts_with_lesson as $post_with_lesson) {
					$post_course_id = learndash_get_setting($post_with_lesson, "course");
					//echo $course_id.":". $post_course_id;
					if($post_course_id != $course_id) {
						learndash_update_setting($post_with_lesson, "course", $course_id);

						$quizzes_under_lesson_topic = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'lesson_id' AND meta_value = '%d'", $posts_with_lesson));
						if(!empty($quizzes_under_lesson_topic) && !empty($quizzes_under_lesson_topic[0])) {
							foreach ($quizzes_under_lesson_topic as $quiz_post_id) {
								$quiz_course_id = learndash_get_setting($quiz_post_id, "course");
								if($course_id != $quiz_course_id)
									learndash_update_setting($quiz_course_id, "course", $course_id);
							}
						}
					}
				}
			}
		}
	}
	add_action( 'save_post', 'learndash_patent_course_and_lesson_id_save');

	function learndash_get_course_lessons_list($course = null) {
		if(empty($course)) {
			$course_id = learndash_get_course_id();
		}
		if(is_numeric($course)) {
			$course_id = $course;
			$course = get_post($course_id);
		}
		
		if(empty($course->ID))
			return array();
		

		$course_settings = learndash_get_setting($course);
		$lessons_options = learndash_get_option( 'sfwd-lessons' );

		$orderby = (empty($course_settings['course_lesson_orderby']))? @$lessons_options['orderby']:$course_settings['course_lesson_orderby'];
		$order = (empty($course_settings['course_lesson_order']))? @$lessons_options['order']:$course_settings['course_lesson_order'];

		$opt = array(
					'post_type' => 'sfwd-lessons',
					'meta_key'	=> 'course_id',
					'meta_value'=> $course->ID,
					'order' => $order,
					'orderby' => $orderby,
					'posts_per_page' => empty($lessons_options['posts_per_page'])? -1:$lessons_options['posts_per_page'],
					'return' => 'array'
				);
		$lessons = SFWD_CPT::loop_shortcode($opt);

		return $lessons;
	}

	function learndash_get_course_quiz_list($course = null) {
		if(empty($course)) {
			$course_id = learndash_get_course_id();
			$course = get_post($course_id);
		}
		if(is_numeric($course)) {
			$course_id = $course;
			$course = get_post($course_id);
		}

		if(empty($course->ID))
			return array();

		$course_settings = learndash_get_setting($course);
		$lessons_options = learndash_get_option( 'sfwd-lessons' );
		$orderby = (empty($course_settings['course_lesson_orderby']))? @$lessons_options['orderby']:$course_settings['course_lesson_orderby'];
		$order = (empty($course_settings['course_lesson_order']))? @$lessons_options['order']:$course_settings['course_lesson_order'];
		$opt = array(
				'post_type' => 'sfwd-quiz',
				'meta_key'	=> 'course_id',
				'meta_value'=> $course->ID,
				'order' => $order,
				'orderby' => $orderby,
				'posts_per_page' => empty($lessons_options['posts_per_page'])? -1:$lessons_options['posts_per_page'],
				'return' => 'array'
				);
		$quizzes = SFWD_CPT::loop_shortcode($opt);	
		return $quizzes;
	}
	function learndash_get_lesson_quiz_list($lesson) {
		if(is_numeric($lesson)) {
			$lesson_id = $lesson;
			$lesson = get_post($lesson_id);
		}
		
		if(empty($lesson->ID))
			return array();

		$course_id = learndash_get_course_id($lesson);

		$course_settings = learndash_get_setting($course_id);
		$lessons_options = learndash_get_option( 'sfwd-lessons' );
		$orderby = (empty($course_settings['course_lesson_orderby']))? @$lessons_options['orderby']:$course_settings['course_lesson_orderby'];
		$order = (empty($course_settings['course_lesson_order']))? @$lessons_options['order']:$course_settings['course_lesson_order'];
		$opt = array(
				'post_type' => 'sfwd-quiz',
				'meta_key'	=> 'lesson_id',
				'meta_value'=> $lesson->ID,
				'order' => $order,
				'orderby' => $orderby,
				'posts_per_page' => empty($lessons_options['posts_per_page'])? -1:$lessons_options['posts_per_page'],
				'return' => 'array'
				);
		$quizzes = SFWD_CPT::loop_shortcode($opt);	
		return $quizzes;
	}	
	 function learndash_get_certificate_link($quiz_id) {
		$user_id = get_current_user_id();
		if(empty($user_id) || empty($quiz_id))
			return "";

		$c = learndash_certificate_details($quiz_id, $user_id);

		if(empty($c["certificateLink"]))
			return "";

		$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		$usermeta = maybe_unserialize( $usermeta );
		if ( !is_array( $usermeta ) ) $usermeta = Array();

		foreach ($usermeta as $quizdata) {
			if(!empty($quizdata["quiz"]) && $quizdata["quiz"] == $quiz_id) {
				if($c['certificate_threshold'] <= $quizdata["percentage"]/100) {
					return '<a target="_blank" href="'.$c["certificateLink"].'">'.__("PRINT YOUR CERTIFICATE!", "learndash").'</a>';
				}
			}
		}
		return "";
	}

	function learndash_course_content_shortcode($atts) {
		if(empty($atts["course_id"]))
			return '';

		$course_id = $atts["course_id"];

		$course = $post = get_post($course_id);
		
		if(!is_singular() || $post->post_type != "sfwd-courses")
			return '';
		
		$current_user = wp_get_current_user();

		$user_id = $current_user->ID;
		$logged_in = !empty($user_id);
		$lesson_progression_enabled = false;

		$course_settings = learndash_get_setting($course);
		$lesson_progression_enabled  = learndash_lesson_progression_enabled();
		$courses_options = learndash_get_option( 'sfwd-courses' );
		$lessons_options = learndash_get_option( 'sfwd-lessons' );
		$quizzes_options = learndash_get_option( 'sfwd-quiz' );
		$course_status = learndash_course_status($course_id, null);
		$has_access = sfwd_lms_has_access($course_id, $user_id);	

		$lessons = learndash_get_course_lessons_list($course);
		$quizzes = learndash_get_course_quiz_list($course);				
		$has_course_content = (!empty($lessons) || !empty($quizzes));
		
		$has_topics = false;
		if(!empty($lessons))
		foreach ($lessons as $lesson) {
			$lesson_topics[$lesson["post"]->ID] = learndash_topic_dots($lesson["post"]->ID, false, 'array'); 
			if(!empty($lesson_topics[$lesson["post"]->ID]))
				$has_topics = true;
		}
		$level = ob_get_level();		
		ob_start();
		include(SFWD_LMS::get_template('course_content_shortcode', null, null, true));
		$content = learndash_ob_get_clean($level);
		$content = str_replace(array("\n", "\r"), " ", $content);
		$user_has_access = $has_access? "user_has_access":"user_has_no_access";
		return '<div class="learndash '.$user_has_access.'" id="learndash_post_'.$course_id.'">'.apply_filters("learndash_content", $content, $post).'</div>';
	}
	add_shortcode("course_content", "learndash_course_content_shortcode");

	function learndash_ob_get_clean($level = 0) {
		$content = '';
		$i = 1;
		while ($i <= 10 && ob_get_level() > $level) {
			$i++;
			$content = ob_get_clean();		
		}
		return $content;
	}

	function learndash_certificate_shortcodes_page() {
		?>
		<div  id="certificate-shortcodes"  class="wrap">
			<h2><?php _e("Certificate Shortcodes", "learndash"); ?></h2>
			<div class="sfwd_options_wrapper sfwd_settings_left">
				<div class="postbox " id="sfwd-certificates_metabox">
					<div class="inside">
					<?php
					echo __('<b>Shortcode Options</b><p>You may use shortcodes to customize the display of your certificates. Provided is a built-in shortcode for displaying user information.</p><p><b>[usermeta]</b><p>This shortcode takes a parameter named field, which is the name of the user meta data field to be displayed.</p><p>Example: <b>[usermeta field="display_name"]</b> would display the user\'s Display Name.</p><p>See <a href="http://codex.wordpress.org/Function_Reference/get_userdata#Notes">the full list of available fields here</a>.</p>', 'learndash').
							'<p><b>[quizinfo]</b></p><p>' . __('This shortcode displays information regarding quiz attempts on the certificate. This short code can use the following parameters:', 'learndash') . '</p> 
							
							<ul>
							<li><b>SHOW</b>: ' . __('This parameter determines the information to be shown by the shortcode. Possible values are:
								
								<ol class="cert_shortcode_parm_list">
								
									<li>score</li>
									<li>count</li>
									<li>pass</li>
									<li>timestamp</li>
									<li>points*</li>
									<li>total_points*</li>
									<li>percentage</li>
									<li>quiz_title</li>
									<li>course_title</li>
									<li>timespent*</li>
								</ol>
								
								Values marked with an asterisk (*) are only valid for the Advanced Quiz. <br>Example: <b>[quizinfo show="percentage"]%</b> shows the percentage score of the user in the quiz.', 'learndash') . '<br><br><br></li>
							<li><b>FORMAT</b>: ' . __('This can be used to change the timestamp format. Default: "F j, Y, g:i a" shows as <i>March 10, 2001, 5:16 pm</i>. <br>Example: <b>[quizinfo show="timestamp" format="Y-m-d H:i:s"]</b> will show as <i>2001-03-10 17:16:18</i>', 'learndash') . '</li>
							</ul>
							<p>' . __('See <a target="_blank" href="http://php.net/manual/en/function.date.php">the full list of available date formating strings  here.</a>', 	'learndash') . '</p>'										
					?>
					</div>
				</div>		
			</div>
		</div>
		<?php
	}
	function learndash_course_shortcodes_page() {
		?>
		<div  id="course-shortcodes"  class="wrap">
			<h2><?php _e("Course Shortcodes", "learndash"); ?></h2>
			<div class="sfwd_options_wrapper sfwd_settings_left">
				<div class="postbox " id="sfwd-course_metabox">
					<div class="inside">
					<?php
					echo '<b>' . __('Shortcode Options', 'learndash') . '</b>

											<p>' . __('You may use shortcodes to add information to any page/course/lesson/quiz. Here are built-in shortcodes for displaying relavent user information.', 'learndash') . '</p>
											<p><b>[ld_profile]</b></p><p>' . __('Displays user\'s enrolled courses, course progress, quiz scores, and achieved certificates.', 'learndash') . '</p>

											<br>
											<p><b>[ld_course_list]</b></p><p>' . __('This shortcode shows list of courses. You can use this short code on any page if you dont want to use the default /courses page. This short code can take following parameters:', 'learndash') . '</p>
											<ul>
											<li><b>num</b>: ' . __('limits the number of courses displayed. Example: <b>[ld_course_list num="10"]</b> shows 10 courses.', 'learndash') . '</li>
											<li><b>order</b>: ' . __('sets order of courses. Possible values: <b>DESC</b>, <b>ASC</b>. Example: <b>[ld_course_list order="ASC"]</b> shows courses in ascending order.', 'learndash') . '</li>
											<li><b>orderby</b>: ' . __('sets what the list of ordered by. Example: <b>[ld_course_list order="ASC" orderby="title"]</b> shows courses in ascending order by title.', 'learndash') . '</li>
											<li><b>tag</b>: ' . __('shows courses with mentioned tag. Example: <b>[ld_course_list tag="math"]</b> shows courses having tag math.', 'learndash') . '</li>
											<li><b>tag_id</b>: ' . __('shows courses with mentioned tag_id. Example: <b>[ld_course_list tag_id="30"]</b> shows courses having tag with tag_id 30.', 'learndash') . '</li>
											<li><b>cat</b>: ' . __('shows courses with mentioned category id. Example: <b>[ld_course_list cat="10"]</b> shows courses having category with category id 10.', 'learndash') . '</li>
											<li><b>category_name</b>: ' . __('shows courses with mentioned category slug. Example: <b>[ld_course_list category_name="math"]</b> shows courses having category slug math.', 'learndash') . '</li>
											<li><b>mycourses</b>: ' . __('show current user\'s courses. Example: <b>[ld_course_list mycourses="true"]</b> shows courses the current user has access to.', 'learndash') . '</li>
											<li><b>categoryselector</b>: ' . __('shows a category dropdown. Example: <b>[ld_course_list categoryselector="true"]</b>.', 'learndash') . '</li>
											<li><b>col</b>: ' . __('number of columns to show when using course grid addon. Example: <b>[ld_course_list col="2"]</b> shows 2 columns.', 'learndash') . '</li>
											</ul>
											<p>' . __('See <a target="_blank" href="https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters">the full list of available orderby options here.</a>', 'learndash') . '</p>
											<br>
											<p><b>[ld_lesson_list]</b></p><p>' . __('This shortcode shows list of lessons. You can use this short code on any page. This short code can take following parameters: num, order, orderby, tag, tag_id, cat, category_name. See [ld_course_list] above details on using the shortcode parameters.', 'learndash') . '</p>
											<br>
											<p><b>[ld_quiz_list]</b></p><p>' . __('This shortcode shows list of quizzes. You can use this short code on any page. This short code can take following parameters: num, order, orderby, tag, tag_id, cat, category_name.. See [ld_course_list] above details on using the shortcode parameters.', 'learndash') . '</p>
											<br>
											<p><b>[learndash_course_progress]</b></p><p>' . __('This shortcode displays users progress bar for the course in any course/lesson/quiz pages.', 'learndash') . '</p>
											<br>
											<p><b>[visitor]</b></p><p>' . __('This shortcode shows the content if the user is not enrolled in the course. Example usage: <strong>[visitor]</strong>Welcome Visitor!<strong>[/visitor]</strong>', 'learndash') . '</p>
											<br>
											<p><b>[student]</b></p><p>' . __('This shortcode shows the content if the user is enrolled in the course. Example usage: <strong>[student]</strong>Welcome Student!<strong>[/student]</strong>', 'learndash') . '</p>
											<br>
											<p><b>[user_groups]</b></p><p>' . __('This shortcode displays the list of groups users are assigned to as users or leaders.', 'learndash') . '</p>
											<br>
											<p><b>[learndash_payment_buttons]</b></p><p>' . __('This shortcode displays can show the payment buttons on any page. Example: <strong>[learndash_payment_buttons course_id="123"]</strong> shows the payment buttons for course with Course ID: 123', 'learndash') . '</p>
											<br>
											<p><b>[course_content]</b></p><p>' . __('This shortcode displays the Course Content table (course lessons, topics, and quizzes) when inserted on a page or post. Example: <strong>[course_content course_id="123"]</strong> shows the course content for course with Course ID: 123', 'learndash') . '</p>
											';
						?>
					</div>
				</div>		
			</div>
		</div>
		<?php
	}
	function learndash_quizzes_inline_actions($actions, $post) {
		if($post->post_type == "sfwd-quiz") {
			$pro_quiz_id = learndash_get_setting($post, "quiz_pro", true);
			if(empty($pro_quiz_id))
				return $actions;

			$statistics_link = admin_url("admin.php?page=ldAdvQuiz&module=statistics&id=".$pro_quiz_id."&post_id=".$post->ID);
			$questions_link = admin_url("admin.php?page=ldAdvQuiz&module=question&quiz_id=".$pro_quiz_id."&post_id=".$post->ID);
			$leaderboard_link = admin_url("admin.php?page=ldAdvQuiz&module=toplist&id=".$pro_quiz_id."&post_id=".$post->ID);

			$actions["questions"] = "<a href='".$questions_link."'>".__("Questions", "learndash")."</a>";
			$actions["statistics"] = "<a href='".$statistics_link."'>".__("Statistics", "learndash")."</a>";
			$actions["leaderboard"] = "<a href='".$leaderboard_link."'>".__("Leaderboard", "learndash")."</a>";
		}
		return $actions;
	}
	add_filter('post_row_actions', "learndash_quizzes_inline_actions", 10, 2);

	function learndash_add_theme_support() { 
	if(!current_theme_supports( 'post-thumbnails')) 
		add_theme_support('post-thumbnails', array( 'sfwd-certificates', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-assignment' )); 
	} 
	add_action( 'after_setup_theme', 'learndash_add_theme_support' );

	function learndash_disable_editor_on_certificate($return) {
		global $post;
		if(is_admin() && !empty($post->post_type) && $post->post_type == "sfwd-certificates")
			return "html";

		return $return;
	}
	add_filter( 'wp_default_editor', 'learndash_disable_editor_on_certificate',1, 1);
	function learndash_disable_editor_on_certificate_js() {
		global $post;
		if(is_admin() && !empty($post->post_type) && $post->post_type == "sfwd-certificates")
		{	?>
				    <style type="text/css">
					a#content-tmce, a#content-tmce:hover, #qt_content_fullscreen, #insert-media-button{
						display:none;
					}
					</style>
					<script type="text/javascript">
				 	jQuery(document).ready(function(){
						jQuery("#content-tmce").attr("onclick", null);
				 	});
				 	</script>
			<?php
		}
	}
	add_filter( 'admin_footer', 'learndash_disable_editor_on_certificate_js', 99);

	function learndash_hide_menu_when_not_required() {
		?>
		<script>
		jQuery(window).ready(function() {
			if(jQuery(".toplevel_page_learndash-lms").length && jQuery(".toplevel_page_learndash-lms").find("li").length <= 1)
				jQuery(".toplevel_page_learndash-lms").hide();
		});
		</script>
		<?php 
	}
	add_filter( 'admin_footer', 'learndash_hide_menu_when_not_required', 99);

	function learndash_delete_course_progress($course_id, $user_id) {
			global $wpdb;
			$usermeta = get_user_meta($user_id, "_sfwd-course_progress", true);

			if(isset($usermeta[$course_id]))
			{
				unset($usermeta[$course_id]);
				update_user_meta($user_id, "_sfwd-course_progress", $usermeta);
			}
			$quizzes = get_posts(array(
					"post_type" => "sfwd-quiz",
					"meta_key"	=> "course_id",
					"meta_value" => $course_id
				));
			//print_r($quizzes);
			foreach ($quizzes as $quiz) {
				//echo $quiz->ID."<br>";
				learndash_delete_quiz_progress($user_id, $quiz->ID);
			}
	}
	function learndash_delete_quiz_progress($user_id, $quiz_id) {
		global $wpdb;

		//Clear User Meta
		$usermeta = get_user_meta($user_id, "_sfwd-quizzes", true);

		if(!empty($usermeta) && is_array($usermeta)) {
			foreach ($usermeta as $key => $quizmeta) {
				if($quizmeta["quiz"] != $quiz_id)
					$usermeta_new[] = $quizmeta;
			}
			update_user_meta($user_id, "_sfwd-quizzes", $usermeta_new);
		}

		//Lesson/Topic
		/*
		$lesson_id = learndash_get_setting($quiz_id, "lesson");
		if(!empty($lesson_id)) {
			$lesson_post = get_post($lesson_id);
			if($lesson_post->post_type == "sfwd-lessons")
				learndash_specific_mark_lesson_incomplete($user_id, $lesson_id);
			else if($lesson_post->post_type == "sfwd-topic")
				learndash_specific_mark_topic_incomplete($user_id, $lesson_id);
		}*/

		//Pro Quiz Data
		$pro_quiz_id = learndash_get_setting($quiz_id, "quiz_pro");

		$ref_ids = $wpdb->get_col($wpdb->prepare("SELECT statistic_ref_id FROM ".$wpdb->prefix."wp_pro_quiz_statistic_ref WHERE  user_id = '%d' AND quiz_id = '%d' ", $user_id, $pro_quiz_id));

		if(!empty($ref_ids[0])) {
			$wpdb->delete($wpdb->prefix."wp_pro_quiz_statistic_ref", array('user_id' => $user_id, 'quiz_id' => $pro_quiz_id));
			$wpdb->query("DELETE FROM ".$wpdb->prefix."wp_pro_quiz_statistic WHERE statistic_ref_id IN (".implode(",", $ref_ids).")");
		}

		//$wpdb->query("DELETE FROM ".$wpdb->usermeta." WHERE meta_key LIKE 'completed_%' AND user_id = '".$user->ID."'");
		$wpdb->delete($wpdb->prefix."wp_pro_quiz_toplist",  array('user_id' => $user_id, 'quiz_id' => $pro_quiz_id));
	}

// add course completion date
add_action("learndash_course_completed", "learndash_course_completed_store_time", 10, 1);

function learndash_course_completed_store_time($data) {
	$user_id = $data["user"]->ID;
	$course_id = $data["course"]->ID;
	$meta_key = "course_completed_".$course_id;
	$meta_value = time();

	$course_completed = get_user_meta($user_id, $meta_key);
	if(empty($course_completed))
		update_user_meta( $user_id, $meta_key, $meta_value ); 
}