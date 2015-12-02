<?php
/**
 * SFWD_LMS
 * 
 * @since 2.1.0
 * 
 * @package LearnDash
 */


if ( ! class_exists( 'SFWD_LMS' ) ) {

	class SFWD_LMS extends Semper_Fi_Module  {

		public $post_types = array();
		public $cache_key = '';
		public $quiz_json = '';
		public $count = null;


		/**
		 * Set up properties and hooks for this class 
		 */
		function __construct() {
			self::$instance =& $this;
			$this->file = __FILE__;
			$this->name = 'LMS';
			$this->plugin_name = 'SFWD LMS';
			$this->name = 'LMS Options';
			$this->prefix = 'sfwd_lms_';
			$this->parent_option = 'sfwd_lms_options';
			parent::__construct();
			register_activation_hook( $this->plugin_path['basename'], array( $this, 'activate' ) );
			add_action( 'init', array( $this, 'add_post_types' ), 1 );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
			add_action( 'parse_request', array( $this, 'parse_ipn_request' ) );
			add_action( 'generate_rewrite_rules', array( $this, 'paypal_rewrite_rules' ) );
			add_filter( 'sfwd_cpt_loop', array( $this, 'cpt_loop_filter' ) );
			add_filter( 'edit_term_count', array( $this, 'tax_term_count' ), 10, 3 );
			add_action( 'init', array( $this, 'add_tag_init' ) ); //Initialise the tagging capability here
			add_action( 'plugins_loaded', array( $this, 'i18nize') );	//Add internationalization support
			add_shortcode( 'usermeta', array( $this, 'usermeta_shortcode' ) );

			if ( is_admin() && get_transient( 'sfwd_lms_rewrite_flush' ) ) {
				add_action( 'admin_init', 'flush_rewrite_rules' );
				set_transient( 'sfwd_lms_rewrite_flush', false );
			}

			add_action( 'init', array( $this, 'load_template_functions') );

			if (is_admin()) {
				require_once( LEARNDASH_LMS_PLUGIN_DIR .'includes/admin/class-learndash-admin-groups-edit.php' );
				$this->ld_admin_groups_edit = new Learndash_Admin_Groups_Edit;
			}

			add_action( 'wp_ajax_select_a_lesson', array( $this, 'select_a_lesson_ajax' ) );
			add_action( 'wp_ajax_select_a_lesson_or_topic', array( $this, 'select_a_lesson_or_topic_ajax' ) );
		}


		/**
		 * Load functions used for templates
		 *
		 * @since 2.1.0
		 */
		function load_template_functions() {
			$this->get_template( 'learndash_template_functions', array(), true );
		}


		/**
		 * Register Courses, Lessons, Quiz CPT's and set up their admin columns on post list view
		 */
		function add_tag_init()	{
			$tag_args = array( 'taxonomies' => array( 'post_tag', 'category' ) );
			register_post_type( 'sfwd-courses', $tag_args ); //Tag arguments for $post_type='sfwd-courses'
			register_post_type( 'sfwd-lessons', $tag_args ); //Tag arguments for $post_type='sfwd-courses'
			register_post_type( 'sfwd-quiz', $tag_args ); //Tag arguments for $post_type='sfwd-courses'

			add_filter( 'manage_edit-sfwd-lessons_columns', 'add_course_data_columns' );
			add_filter( 'manage_edit-sfwd-quiz_columns', 'add_shortcode_data_columns' );
			add_filter( 'manage_edit-sfwd-quiz_columns', 'add_course_data_columns' );
			add_filter( 'manage_edit-sfwd-topic_columns', 'add_lesson_data_columns' );
			add_filter( 'manage_edit-sfwd-assignment_columns', 'add_lesson_data_columns' );
			add_filter( 'manage_edit-sfwd-assignment_columns', 'add_assignment_data_columns' );
			add_filter( 'manage_edit-sfwd-quiz_columns', 'remove_tags_column' );
			add_filter( 'manage_edit-sfwd-quiz_columns', 'remove_categories_column' );

			add_action( 'manage_sfwd-lessons_posts_custom_column', 'manage_asigned_course_columns', 10, 3 );
			add_action( 'manage_sfwd-quiz_posts_custom_column', 'manage_asigned_course_columns', 10, 3 );
			add_action( 'manage_sfwd-topic_posts_custom_column', 'manage_asigned_course_columns', 10, 3 );
			add_action( 'manage_sfwd-assignment_posts_custom_column', 'manage_asigned_course_columns', 10, 3 );
			add_action( 'manage_sfwd-assignment_posts_custom_column', 'manage_asigned_assignment_columns', 10, 3 );

			add_action( 'restrict_manage_posts', 'restrict_listings_by_course' );
			add_filter( 'parse_query', 'course_table_filter' );
		}



		/**
		 * Loads the plugin's translated strings
		 *
		 * @since 2.1.0
		 */
		function i18nize() {
			if ((defined('LD_LANG_DIR')) && (LD_LANG_DIR)) {
				load_plugin_textdomain( 'learndash', false, LD_LANG_DIR );
			} else {
				load_plugin_textdomain( 'learndash', false, dirname( plugin_basename( dirname( __FILE__ ) ) ) . '/languages/' );
			}
		}



		/**
		 * Update count of posts with a term
		 * 
		 * Callback for add_filter 'edit_term_count'
		 * There is no apply_filters or php call to execute this function
		 *
		 * @todo  consider for deprecation, other docblock tags removed
		 *
		 * @since 2.1.0
		 */
		function tax_term_count( $columns, $id, $tax ) {
			if ( empty( $tax ) || ( $tax != 'courses' ) ) { 
				return $columns;
			}

			if ( ! empty( $_GET ) && ! empty( $_GET['post_type'] ) ) {
				$post_type = $_GET['post_type'];
				$wpq = array(		
					'tax_query' => array( 
						array( 
							'taxonomy' => $tax, 
							'field' => 'id', 
							'terms' => $id 
						)
					),
					'post_type' => $post_type,
					'post_status' => 'publish',
					'posts_per_page' => -1
				);
				$q = new WP_Query( $wpq );
				$this->count = $q->found_posts;
				add_filter( 'number_format_i18n', array( $this, 'column_term_number' ) );
			}

			return $columns;			
		}


		/**
		 * Set column term number
		 * 
		 * This function is called by the 'tax_term_count' method and is no longer being ran
		 * See tax_term_count()
		 *
		 * @todo  consider for deprecation, other docblock tags removed
		 *
		 * @since 2.1.0
		 */
		function column_term_number( $number ) {
			remove_filter( 'number_format_i18n', array( $this, 'column_term_number' ) );
			if ( $this->count !== null ) {
				$number = $this->count;
				$this->count = null;
			}
			return $number;
		}



		/**
		 * [usermeta] shortcode
		 * 
		 * This shortcode takes a parameter named field, which is the name of the user meta data field to be displayed.
		 * Example: [usermeta field="display_name"] would display the user's Display Name.
		 *
		 * @since 2.1.0
		 * 
		 * @param  array 	$attr    shortcode attributes
		 * @param  string 	$content content of shortcode
		 * @return string          	 output of shortcode
		 */
		function usermeta_shortcode( $attr, $content = null ) {
			extract( shortcode_atts( array( 'field' => null ), $attr ) );
			global $user_info, $user_ID;
			get_currentuserinfo();
			$user_info = get_userdata( $user_ID );
			if ( is_user_logged_in() ) {
				return $user_info->$field;
			}
			return '';
		}



		/**
		 * Callback for add_filter 'sfwd_cpt_loop'
		 * There is no apply_filters or php call to execute this function
		 *
		 * @since 2.1.0
		 * 
		 * @todo  consider for deprecation, other docblock tags removed
		 */
		function cpt_loop_filter( $content ) {
			global $post;
			if ( $post->post_type == 'sfwd-quiz' ) {
				$meta = get_post_meta( $post->ID, '_sfwd-quiz' );
				if ( is_array( $meta ) && ! empty( $meta ) ) {
					$meta = $meta[0];
					if ( is_array( $meta ) && ( ! empty( $meta['sfwd-quiz_lesson'] ) ) ) {
						$content = '';
					}
				}
			}
			return $content;
		}



		/**
		 * Fire on plugin activation
		 * 
		 * Currently sets 'sfwd_lms_rewrite_flush' to true
		 *
		 * @todo   consider if needed, transient is not being used anywhere else in LearnDash
		 * 
		 * @since 2.1.0
		 */
		function activate() {
			set_transient( 'sfwd_lms_rewrite_flush', true );
		}



		/**
		 * Add 'sfwd-lms' to query vars
		 * Fired on filter 'query_vars'
		 * 
		 * @since 2.1.0
		 * 
		 * @param  array  	$vars  query vars
		 * @return array 	$vars  query vars
		 */
		function add_query_vars( $vars ) {
			return array_merge( array( 'sfwd-lms' ), $vars );
		}



		/**
		 * Include PayPal IPN if request is for PayPal IPN
		 * Fired on action 'parse_request'
		 * 
		 * @since 2.1.0
		 * 
		 * @param  object 	$wp  wp query
		 */
		function parse_ipn_request( $wp ) {
			if ( array_key_exists( 'sfwd-lms', $wp->query_vars )
					&& $wp->query_vars['sfwd-lms'] == 'paypal' ) {
				
				/**
				 * include PayPal IPN
				 */
				require_once( 'vendor/paypal/ipn.php' );
			}
		}



		/**
		 * Adds paypal to already generated rewrite rules
		 * Fired on action 'generate_rewrite_rules'
		 *
		 * @since 2.1.0
		 * 
		 * @param  object  $wp_rewrite
		 */
		function paypal_rewrite_rules( $wp_rewrite ) {
			$wp_rewrite->rules = array_merge( array( 'sfwd-lms/paypal' => 'index.php?sfwd-lms=paypal' ), $wp_rewrite->rules );
		}



		/**
		 * Sets up CPT's and creates a 'new SFWD_CPT_Instance()' of each
		 * 
		 * @since 2.1.0
		 */
		function add_post_types() {
			$post = 0;

			if ( is_admin() && ! empty( $_GET ) && ( isset( $_GET['post'] ) ) ) {
				$post_id = $_GET['post'];
			}

			if ( ! empty( $post_id ) ) {
				$this->quiz_json = get_post_meta( $post_id, '_quizdata', true );
				if ( ! empty( $this->quiz_json ) ) {
					$this->quiz_json = $this->quiz_json['workingJson'];
				}
			}

			$options = get_option( 'sfwd_cpt_options' );

			$level1 = $level2 = $level3 = $level4 = $level5 = '';

			if ( ! empty( $options['modules'] ) ) {
				$options = $options['modules'];
				if ( ! empty( $options['sfwd-quiz_options'] ) ) {
					$options = $options['sfwd-quiz_options'];
					foreach ( array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) {
						$$level = '';
						if ( ! empty( $options["sfwd-quiz_{$level}"] ) ) {
							$$level = $options["sfwd-quiz_{$level}"];
						}
					}
				}
			}

			if ( empty( $this->quiz_json ) ) { 
				$this->quiz_json = '{"info":{"name":"","main":"","results":"","level1":"' . $level1 . '","level2":"' . $level2 . '","level3":"' . $level3 . '","level4":"' . $level4 . '","level5":"' . $level5 . '"}}';
			}
			
			$posts_per_page = get_option( 'posts_per_page' );

			$course_capabilities = array(
				'read_post' => 'read_course',
				'publish_posts' => 'publish_courses',
				'edit_posts' => 'edit_courses',
				'edit_others_posts' => 'edit_others_courses',
				'delete_posts' => 'delete_courses',
				'delete_others_posts' => 'delete_others_courses',
				'read_private_posts' => 'read_private_courses',
				'edit_private_posts' => 'edit_private_courses',
				'delete_private_posts' => 'delete_private_courses',
				'delete_post' => 'delete_course',
				'edit_published_posts'	=> 'edit_published_courses',
				'delete_published_posts'	=> 'delete_published_courses',
			);

			if ( is_admin() ) {
				$admin = get_role( 'administrator' );
				if ( ! $admin->has_cap( 'delete_private_courses' ) ) {
					foreach ( $course_capabilities as $key => $cap ) {
						if ( ! $admin->has_cap( $cap ) ) {
							$admin->add_cap( $cap );
						}
					}
				}
				if ( ! $admin->has_cap( 'enroll_users' ) ) {
					$admin->add_cap( 'enroll_users' );
				}
			}

			$lesson_topic_labels = array(
				'name' => __( 'Topics', 'learndash' ),
				'singular_name' => __( 'Topic', 'learndash' ),
				'add_new' => __( 'Add New', 'learndash' ),
				'add_new_item' => __( 'Add New Topic', 'learndash' ),
				'edit_item' => __( 'Edit Topic', 'learndash' ),
				'new_item' => __( 'New Topic', 'learndash' ),
				'all_items' => __( 'Topics', 'learndash' ),
				'view_item' => __( 'View Topic', 'learndash' ),
				'search_items' => __( 'Search Topics', 'learndash' ),
				'not_found' => __( 'No Topics found', 'learndash' ),
				'not_found_in_trash' => __( 'No Topics found in Trash', 'learndash' ),
				'parent_item_colon' => '',
				'menu_name' => __( 'Topics', 'learndash' )
			);

			$quiz_labels = array(
				'name' => __( 'Quizzes', 'learndash' ),
				'singular_name' => __( 'Quiz', 'learndash' ),
				'add_new' => __( 'Add New', 'learndash' ),
				'add_new_item' => __( 'Add New Quiz', 'learndash' ),
				'edit_item' => __( 'Edit Quiz', 'learndash' ),
				'new_item' => __( 'New Quiz', 'learndash' ),
				'all_items' => __( 'Quizzes', 'learndash' ),
				'view_item' => __( 'View Quiz', 'learndash' ),
				'search_items' => __( 'Search Quizzes', 'learndash' ),
				'not_found' => __( 'No Quizzes found', 'learndash' ),
				'not_found_in_trash' => __( 'No Quizzes found in Trash', 'learndash' ),
				'parent_item_colon' => '',
			);

			$lesson_labels = array(
				'name' => __( 'Lessons', 'learndash' ),
				'singular_name' => __( 'Lesson', 'learndash' ),
				'add_new' => __( 'Add New', 'learndash' ),
				'add_new_item' => __( 'Add New Lesson', 'learndash' ),
				'edit_item' => __( 'Edit Lesson', 'learndash' ),
				'new_item' => __( 'New Lesson', 'learndash' ),
				'all_items' => __( 'Lessons', 'learndash' ),
				'view_item' => __( 'View Lesson', 'learndash' ),
				'search_items' => __( 'Search Lessons', 'learndash' ),
				'not_found' => __( 'No Lessons found', 'learndash' ),
				'not_found_in_trash' => __( 'No Lessons found in Trash', 'learndash' ),
				'parent_item_colon' => '',
			);

			$course_labels = array(
				'name' => __( 'Courses', 'learndash' ),
				'singular_name' => __( 'Course', 'learndash' ),
				'add_new' => __( 'Add New', 'learndash' ),
				'add_new_item' => __( 'Add New Course', 'learndash' ),
				'edit_item' => __( 'Edit Course', 'learndash' ),
				'new_item' => __( 'New Course', 'learndash' ),
				'all_items' => __( 'Courses', 'learndash' ),
				'view_item' => __( 'View Course', 'learndash' ),
				'search_items' => __( 'Search Courses', 'learndash' ),
				'not_found' => __( 'No Courses found', 'learndash' ),
				'not_found_in_trash' => __( 'No Courses found in Trash', 'learndash' ),
				'parent_item_colon' => '',
			);

			if ( empty( $posts_per_page ) ) { 
				$posts_per_page = 5;
			}

			$post_args = array(
				array(
					'plugin_name' => __( 'Course', 'learndash' ),
					'slug_name' => 'courses',
					'post_type' => 'sfwd-courses',
					'template_redirect' => true,
					// 'taxonomies' => array( 'courses' => __( 'Manage Course Associations', 'learndash' ) ),
					'cpt_options' => array( 
						'hierarchical' => 'false', 
						'supports' => array( 'title', 'editor', 'thumbnail' , 'author', 'comments', 'revisions' ),
						'labels' => $course_labels,
						'capability_type' => 'course',
						'capabilities' => $course_capabilities,
						'map_meta_cap' => true
					),
					'options_page_title' => __( 'PayPal Settings', 'learndash' ),
					'fields' => array( 
						'course_materials' => array(
							'name' => __( 'Course Materials', 'learndash' ),
							'type' => 'textarea',
							'help_text' => __( 'Options for course materials', 'learndash' ),
						),
						'course_price_type' => array(
							'name' => __( 'Course Price Type', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	
								'open' => __( 'Open', 'learndash' ),
								'closed' => __( 'Closed', 'learndash' ),
								'free' => __( 'Free', 'learndash' ),
								'paynow' => __( 'Buy Now', 'learndash' ),
								'subscribe'	=> __( 'Recurring', 'learndash' ),
							),
							'default' => 'buynow',
							'help_text' => __( 'Is it open to all, free join, one time purchase, or a recurring subscription?', 'learndash' ),
						),
						'custom_button_url' => array(
							'name' => __( 'Custom Button URL', 'learndash' ),
							'type' => 'text',
							'placeholder'	=> __( 'Optional', 'learndash' ),
							'help_text' => __( 'Entering a URL in this field will enable the "Take This Course" button. The button will not display if this field is left empty.', 'learndash' ),
						),
						'course_price' => array(
							'name' => __( 'Course Price', 'learndash' ),
							'type' => 'text',
							'help_text' => __( 'Enter course price here. Leave empty if the course is free.', 'learndash' ),
						),
						'course_price_billing_cycle' => array(
							'name' => __( 'Billing Cycle', 'learndash' ),
							'type' => 'html',
							'default' => $this->learndash_course_price_billing_cycle_html(),
							'help_text' => __( 'Billing Cycle for the recurring payments in case of a subscription.', 'learndash' ),
						),
						'course_access_list' => array(
							'name' => __( 'Course Access List', 'learndash' ),
							'type' => 'textarea',
							'help_text' => __( 'This field is auto-populated with the UserIDs of those who have access to this course.', 'learndash' ),
						),
						'course_lesson_orderby' => array(
							'name' => __( 'Sort Lesson By', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	
								''		=> __( 'Use Default', 'learndash' ),
								'title'	=> __( 'Title', 'learndash' ),
								'date'	=> __( 'Date', 'learndash' ),
								'menu_order' => __( 'Menu Order', 'learndash' ),
							),
							'default' => '',
							'help_text' => __( 'Choose the sort order of lessons in this course.', 'learndash' ),
						),
						'course_lesson_order' => array(
							'name' => __( 'Sort Lesson Direction', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	
								''		=> __( 'Use Default', 'learndash' ),
								'ASC'	=> __( 'Ascending', 'learndash' ),
								'DESC'	=> __( 'Descending', 'learndash' ),
							),
							'default' => '',
							'help_text' => __( 'Choose the sort order of lessons in this course.', 'learndash' ),
						),
						'course_prerequisite' => array( 
							'name' => __( 'Course prerequisites', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Select a course as prerequisites to view this course', 'learndash' ), 
							'initial_options' => '', 
							'default' => '',
						),
						'course_disable_lesson_progression' => array(
							'name' => __( 'Disable Lesson Progression', 'learndash' ),
							'type' => 'checkbox',
							'default' => 0,
							'help_text' => __( 'Disable the feature that allows attempting lessons only in allowed order.', 'learndash' )
						),
						'expire_access' => array(
							'name' => __( 'Expire Access', 'learndash' ),
							'type' => 'checkbox',
							'help_text' => __( 'Leave this field unchecked if access never expires.', 'learndash' ),
						),
						'expire_access_days' => array(
							'name' => __( 'Expire Access After (days)', 'learndash' ),
							'type' => 'text',
							'help_text' => __( 'Enter the number of days a user has access to this course.', 'learndash' ),
						),
						'expire_access_delete_progress' => array(
							'name' => __( 'Delete Course and Quiz Data After Expiration', 'learndash' ),
							'type' => 'checkbox',
							'help_text' => __( 'Select this option if you want the user\'s course progress to be deleted when their access expires.', 'learndash' ),
						),
						'certificate' => array( 
							'name' => __( 'Associated Certificate', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Select a certificate to be awarded upon course completion (optional).', 'learndash' ), 
							'default' => '' 
						),
					),
					'default_options' => array(
						'paypal_email' => array( 
							'name' => __( 'PayPal Email', 'learndash' ), 
							'help_text' => __( 'Enter your PayPal email here.', 'learndash' ), 
							'type' => 'text',
						),
						'paypal_currency' => array( 
							'name' => __( 'PayPal Currency', 'learndash' ), 
							'help_text' => __( 'Enter the currency code for transactions.', 'learndash' ), 
							'type' => 'text', 
							'default' => 'USD',
						),
						'paypal_country' => array( 
							'name' => __( 'PayPal Country', 'learndash' ), 
							'help_text' => __( 'Enter your country code here.', 'learndash' ), 
							'type' => 'text', 
							'default' => 'US',
						),
						'paypal_cancelurl' => array( 
							'name' => __( 'PayPal Cancel URL', 'learndash' ), 
							'help_text' => __( 'Enter the URL used for purchase cancellations.', 'learndash' ), 
							'type' => 'text', 
							'default' => get_home_url(),
						),
						'paypal_returnurl' => array( 
							'name' => __( 'PayPal Return URL', 'learndash' ), 
							'help_text' => __( 'Enter the URL used for completed purchases (typically a thank you page).', 'learndash' ), 
							'type' => 'text', 
							'default' => get_home_url(),
						),
						'paypal_notifyurl' => array( 
							'name' => __( 'PayPal Notify URL', 'learndash' ), 
							'help_text' => __( 'Enter the URL used for IPN notifications.', 'learndash' ), 
							'type' => 'text', 
							'default' => get_home_url() . '/sfwd-lms/paypal',
						),
						'paypal_sandbox' => array( 
							'name' => __( 'Use PayPal Sandbox', 'learndash' ), 
							'help_text' => __( 'Check to enable the PayPal sandbox.', 'learndash' ),
						),
					),
				),
				array(
					'plugin_name' => __( 'Lesson', 'learndash' ),
					'slug_name' => 'lessons',
					'post_type' => 'sfwd-lessons',
					'template_redirect' => true,
					// 'taxonomies' => array( 'courses' => __( 'Manage Course Associations', 'learndash' ) ),
					'cpt_options' => array( 
						'has_archive' => false, 
						'supports' => array( 'title', 'thumbnail', 'editor', 'page-attributes' , 'author', 'comments', 'revisions'), 
						'labels' => $lesson_labels , 
						'capability_type' => 'course', 
						'capabilities' => $course_capabilities, 
						'map_meta_cap' => true,
					),
					'options_page_title' => __( 'Lesson Options', 'learndash' ),
					'fields' => array(
						'course' => array( 
							'name' => __( 'Associated Course', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Associate with a course.', 'learndash' ), 
							'default' => '' , 
							'initial_options' => $this->select_a_course( 'sfwd-lessons' ),
						),
						'forced_lesson_time' => array( 
							'name' => __( 'Forced Lesson Timer', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Minimum time a user has to spend on Lesson page before it can be marked complete. Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec', 'learndash' ), 
							'default' => '',
						),
						'lesson_assignment_upload' => array( 
							'name' => __( 'Upload Assignment', 'learndash' ), 
							'type' => 'checkbox', 
							'help_text' => __( 'Check this if you want to make it mandatory to upload assignment', 'learndash' ), 
							'default' => 0,
						),
						'auto_approve_assignment' => array( 
							'name' => __( 'Auto Approve Assignment', 'learndash' ), 
							'type' => 'checkbox', 
							'help_text' => __( 'Check this if you want to auto-approve the uploaded assignment', 'learndash' ), 
							'default' => 0,
						),
						'sample_lesson' => array( 
							'name' => __( 'Sample Lesson', 'learndash' ), 
							'type' => 'checkbox', 
							'help_text' => __( 'Check this if you want this lesson and all its topics to be available for free.', 'learndash' ), 
							'default' => 0,
						),
						'visible_after' => array( 
							'name' => __( 'Make lesson visible X days after sign-up', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Make lesson visible ____ days after sign-up', 'learndash' ), 
							'default' => 0,
						),
						'visible_after_specific_date' => array( 
							'name' => __( 'Make lesson visible on specific date', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Set the date that you would like this lesson to become available.', 'learndash' ),
						),
					),
					'default_options' => array(
						'orderby' => array(
							'name' => __( 'Sort By', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	
								''		=> __( 'Select a choice...', 'learndash' ),
								'title'	=> __( 'Title', 'learndash' ),
								'date'	=> __( 'Date', 'learndash' ),
								'menu_order' => __( 'Menu Order', 'learndash' ),
							),
							'default' => 'date',
							'help_text' => __( 'Choose the sort order.', 'learndash' ),
						),
						'order' => array(
							'name' => __( 'Sort Direction', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	
								''		=> __( 'Select a choice...', 'learndash' ),
								'ASC'	=> __( 'Ascending', 'learndash' ),
								'DESC'	=> __( 'Descending', 'learndash' ),
							),
							'default' => 'DESC',
							'help_text' => __( 'Choose the sort order.', 'learndash' ),
						),
						'posts_per_page' => array(
							'name' => __( 'Posts Per Page', 'learndash' ),
							'type' => 'text',
							'help_text' => __( 'Enter the number of posts to display per page.', 'learndash' ),
							'default' => $posts_per_page,
						),
					)
				),
				array(
					'plugin_name' => __( 'Quiz', 'learndash' ),
					'slug_name' => 'quizzes',
					'post_type' => 'sfwd-quiz',
					'template_redirect' => true,
					// 'taxonomies' => array( 'courses' => __( 'Manage Course Associations', 'learndash' ) ),
					'cpt_options' => array(	
						'hierarchical' => false, 
						'supports' => array( 'title', 'thumbnail', 'editor' , 'author', 'page-attributes' ,'comments', 'revisions' ), 
						'labels' => $quiz_labels, 
						'capability_type' => 'course', 
						'capabilities' => $course_capabilities, 
						'map_meta_cap' => true
					),
					'options_page_title' => __( 'Quiz Settings', 'learndash' ),
					'fields' => array(
						'repeats' => array( 
							'name' => __( 'Repeats', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Number of repeats allowed for quiz', 'learndash' ), 
							'default' => '',
						),
						'threshold' => array( 
							'name' => __( 'Certificate Threshold', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Minimum score required to award a certificate, between 0 and 1 where 1 = 100%.', 'learndash' ), 
							'default' => '0.8',
						),
						'passingpercentage' => array( 
							'name' => __( 'Passing Percentage', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Passing percentage required to pass the quiz (number only). e.g. 80 for 80%.', 'learndash' ), 
							'default' => '80',
						),
						'course' => array( 
							'name' => __( 'Associated Course', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Associate with a course.', 'learndash' ), 
							'default' => '', 'initial_options' => $this->select_a_course( 'sfwd-quiz' ),
						),
						'lesson' => array( 
							'name' => __( 'Associated Lesson', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Optionally associate a quiz with a lesson.', 'learndash' ), 
							'default' => '', 
						),
						'certificate' => array( 
							'name' => __( 'Associated Certificate', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Optionally associate a quiz with a certificate.', 'learndash' ), 
							'default' => '',
						),
						'quiz_pro' => array( 
							'name' => __( 'Associated Settings', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'If you imported a quiz, use this field to select it. Otherwise, create new settings below. After saving or publishing, you will be able to add questions.', 'learndash' ). '<a style="display:none" id="advanced_quiz_preview" class="wpProQuiz_prview" href="#">'.__( 'Preview', 'learndash' ).'</a>',
							'initial_options' => ( array( 0 => __( '-- Select Settings --', 'learndash' ) ) + LD_QuizPro::get_quiz_list() ), 
							'default' => '',
						),
						'quiz_pro_html' => array(
							'name' => __( 'Quiz Options', 'learndash' ),
							'type' => 'html',
							'help_text' => '',
							'label' => 'none',
							'save' => false,
							'default' => LD_QuizPro::edithtml()
						),
					),
					'default_options' => array()
				),
				array(
					'plugin_name' => __( 'Lesson Topic', 'learndash' ),
					'slug_name' => 'topic',
					'post_type' => 'sfwd-topic',
					'template_redirect' => true,
					//'taxonomies' => array( 'courses' => __( 'Manage Course Associations', 'learndash' ) ),
					'cpt_options' => array( 
						'supports' => array( 'title', 'thumbnail', 'editor', 'page-attributes' , 'author', 'comments', 'revisions'),
						'has_archive' => false, 
						'labels' => $lesson_topic_labels, 
						'capability_type' => 'course', 
						'capabilities' => $course_capabilities, 
						'map_meta_cap' => true,
						'taxonomies' => array( 'post_tag'),
					),
					'options_page_title' => __( 'Lesson Topis Options', 'learndash' ),
					'fields' => array(
						'course' => array( 
							'name' => __( 'Associated Course', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Associate with a course.', 'learndash' ), 
							'default' => '', 
							'initial_options' => $this->select_a_course( 'sfwd-topic' ),
						),
						'lesson' => array( 
							'name' => __( 'Associated Lesson', 'learndash' ), 
							'type' => 'select', 
							'help_text' => __( 'Optionally associate a quiz with a lesson.', 'learndash' ), 
							'default' => '' , 
							'initial_options' => $this->select_a_lesson(),
						),
						'forced_lesson_time' => array( 
							'name' => __( 'Forced Topic Timer', 'learndash' ), 
							'type' => 'text', 
							'help_text' => __( 'Minimum time a user has to spend on Topic page before it can be marked complete. Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec', 'learndash' ), 
							'default' => '',
						),
						'lesson_assignment_upload' => array( 
							'name' => __( 'Upload Assignment', 'learndash' ), 
							'type' => 'checkbox', 
							'help_text' => __( 'Check this if you want to make it mandatory to upload assignment', 'learndash' ), 
							'default' => 0,
						),
						'auto_approve_assignment' => array( 
							'name' => __( 'Auto Approve Assignment', 'learndash' ), 
							'type' => 'checkbox', 
							'help_text' => __( 'Check this if you want to auto-approve the uploaded assignment', 'learndash' ), 
							'default' => 0 
						),
						// 'visible_after' => array( 
						// 	'name' => __( 'Make lesson visible X days after sign-up', 'learndash' ), 
						// 	'type' => 'text', 
						// 	'help_text' => __( 'Make lesson visible ____ days after sign-up', 'learndash' ), 
						// 	'default' => 0,
						// ),
					),
					'default_options' => array(
						'orderby' => array(
							'name' => __( 'Sort By', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	''		=> __( 'Select a choice...', 'learndash' ),
								'title'	=> __( 'Title', 'learndash' ),
								'date'	=> __( 'Date', 'learndash' ),
								'menu_order' => __( 'Menu Order', 'learndash' ),
							),
							'default' => 'date',
							'help_text' => __( 'Choose the sort order.', 'learndash' ),
							),
						'order' => array(
							'name' => __( 'Sort Direction', 'learndash' ),
							'type' => 'select',
							'initial_options' => array(	''		=> __( 'Select a choice...', 'learndash' ),
									'ASC'	=> __( 'Ascending', 'learndash' ),
									'DESC'	=> __( 'Descending', 'learndash' ),
							),
							'default' => 'DESC',
							'help_text' => __( 'Choose the sort order.', 'learndash' ),
						),
					),
				),
				/*	array(
					  'plugin_name' => __( 'Assignment', 'learndash' ),
					  'slug_name' => 'assignment',
					  'post_type' => 'sfwd-assignment',
					  'template_redirect' => true,
					  'cpt_options' => array( 'supports' => array ( 'title', 'comments', 'author' ), 'exclude_from_search' => true, 'publicly_queryable' => true, 'show_in_nav_menus' => false , 'show_in_menu'	=> true, 'has_archive' => false),
					  'fields' => array(),
				),*/			
			);

			$cert_defaults = array(
				'shortcode_options' => array(
					'name' => 'Shortcode Options',
					'type' => 'html',
					'default' => '',
					'save' => false,
					'label' => 'none',
				),
			);

			$post_args[] = array(
				'plugin_name' => __( 'Certificates', 'learndash' ),
				'slug_name' => 'certificates',
				'post_type' => 'sfwd-certificates',
				'template_redirect' => false,
				'fields' => array(),
				'options_page_title' => __( 'Certificates Options', 'learndash' ),
				'default_options' => $cert_defaults,
				'cpt_options' => array( 
					'exclude_from_search' => true, 
					'has_archive' => false, 
					'hierarchical' => 'false', 
					'supports' => array( 'title', 'editor', 'thumbnail' , 'author',  'revisions'), 
					'capability_type' => 'course', 
					'capabilities' => $course_capabilities, 
					'map_meta_cap' => true,
				)
			);

			if ( current_user_can( 'manage_options' ) ) {
				$post_args[] = array(
					'plugin_name' => __( 'Transactions', 'learndash' ),
					'slug_name' => 'transactions',
					'post_type' => 'sfwd-transactions',
					'template_redirect' => false,
					'options_page_title' => __( 'Transactions Options', 'learndash' ),
					'cpt_options' => array( 
						'supports' => array ( 'title', 'custom-fields' ), 
						'exclude_from_search' => true, 
						'publicly_queryable' => false, 
						'show_in_nav_menus' => false , 
						'show_in_menu'	=> 'edit.php?post_type=sfwd-courses'
					),
					'fields' => array(),
					'default_options' => array( 
						null => array( 
							'type' => 'html', 
							'save' => false, 
							'default' => __( 'Click the Export button below to export the transaction list.', 'learndash' ),
						)
					)
				);

				add_action( 'admin_init', array( $this, 'trans_export_init' ) );
			}

			 /**
			 * Filter $post_args used to create the custom post types and everything
			 * associated with them.
			 * 
			 * @since 2.1.0
			 * 
			 * @param  array  $post_args       
			 */
			$post_args = apply_filters( 'learndash_post_args', $post_args );

			add_action( 'admin_init', array( $this, 'quiz_export_init' ) );
			add_action( 'admin_init', array( $this, 'course_export_init' ) );
			add_action( 'show_user_profile', array( $this, 'show_course_info' ) );
			add_action( 'edit_user_profile', array( $this, 'show_course_info' ) );

			foreach ( $post_args as $p ) {				
				$this->post_types[ $p['post_type'] ] = new SFWD_CPT_Instance( $p );
			}

			//add_action( 'publish_sfwd-courses', array( $this, 'add_course_tax_entry' ), 10, 2 );
			add_action( 'init', array( $this, 'tax_registration' ), 11 );
			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter( "{$quiz_prefix}display_settings", array( $this, 'quiz_display_settings' ), 10, 3 );
			$sfwd_courses = $this->post_types['sfwd-courses'];
			$courses_prefix = $sfwd_courses->get_prefix();
			add_filter( "{$courses_prefix}display_settings", array( $this, 'course_display_settings' ), 10, 3 );

		}



		/**
		 * Displays users course information at bottom of profile
		 * Fires on action 'show_user_profile'
		 * Fires on action 'edit_user_profile'
		 * 
		 * @since 2.1.0
		 * 
		 * @param  object $user  wp user object
		 */
		function show_course_info( $user ) {
			$user_id = $user->ID;
			echo '<h3>' . __( 'Course Info', 'learndash' ) . '</h3>';
			echo $this->get_course_info( $user_id );
		}



		/**
		 * Returns output of users course information for bottom of profile
		 *
		 * @since 2.1.0
		 * 
		 * @param  int 		$user_id 	user id
		 * @return string          		output of course information
		 */
		static function get_course_info( $user_id ) {
			$courses_registered = ld_get_mycourses( $user_id );

			$usermeta = get_user_meta( $user_id, '_sfwd-course_progress', true );
			$course_progress = empty( $usermeta ) ? false : $usermeta;

			$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
			$quizzes = empty( $usermeta ) ? false : $usermeta;

			return SFWD_LMS::get_template('course_info_shortcode', array(
					'user_id' => $user_id,
					'courses_registered' => $courses_registered,
					'course_progress' => $course_progress,
					'quizzes' => $quizzes
				)
			);
		}



		/**
		 * Updates course price billy cycle on save
		 * Fires on action 'save_post'
		 *
		 * @since 2.1.0
		 * 
		 * @param  int 		 $post_id 	 
		 */
		function learndash_course_price_billing_cycle_save( $post_id ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( empty( $post_id ) || empty( $_POST['post_type'] ) ) {
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

			if ( isset( $_POST['course_price_billing_p3'] ) ) {
				update_post_meta( $post_id, 'course_price_billing_p3', $_POST['course_price_billing_p3'] );
			}

			if ( isset( $_POST['course_price_billing_t3'] ) ) {
				update_post_meta( $post_id, 'course_price_billing_t3', $_POST['course_price_billing_t3'] );
			}
		}



		/**
		 * Billing Cycle field html output for courses
		 * 
		 * @since 2.1.0
		 * 
		 * @return string
		 */
		function learndash_course_price_billing_cycle_html() {
			global $pagenow;
			add_action( 'save_post', array( $this, 'learndash_course_price_billing_cycle_save' ) );

			if ( $pagenow == 'post.php' && ! empty( $_GET['post'] ) ) {
				$post_id = $_GET['post'];
				$post = get_post( $post_id );

				if ( $post->post_type != 'sfwd-courses' ) {
					return;
				}

				$course_price_billing_p3 = get_post_meta( $post_id, 'course_price_billing_p3',  true );
				$course_price_billing_t3 = get_post_meta( $post_id, 'course_price_billing_t3',  true );
				$settings = learndash_get_setting( $post_id );

				if ( ! empty( $settings ) && $settings['course_price_type'] == 'paynow' && empty( $settings['course_price'] ) ) {
					if ( empty( $settings['course_join'] ) ) {
						learndash_update_setting( $post_id, 'course_price_type', 'open' );
					} else {
						learndash_update_setting( $post_id, 'course_price_type', 'free' );
					}
				}

			} else {

				if ( $pagenow == 'post-new.php' && ! empty( $_GET['post_type'] ) && $_GET['post_type'] == 'sfwd-courses' ) {
					$post_id = 0;
					$course_price_billing_p3 = $course_price_billing_t3 = '';
				} else {
					return;
				}

			}
			

			$selected_D = $selected_W = $selected_M = $selected_Y = '';
			${'selected_'.$course_price_billing_t3} = 'selected="selected"';
			return '<input name="course_price_billing_p3" type="text" value="'.$course_price_billing_p3.'" size="2"/> 
					<select class="select_course_price_billing_p3" name="course_price_billing_t3">
						<option value="D" '.$selected_D.'>'.__( 'day(s)', 'learndash' ).'</option>
						<option value="W" '.$selected_W.'>'.__( 'week(s)', 'learndash' ).'</option>
						<option value="M" '.$selected_M.'>'.__( 'month(s)', 'learndash' ).'</option>
						<option value="Y" '.$selected_Y.'>'.__( 'year(s)', 'learndash' ).'</option>
					</select>';
		}



		/**
		 * Set up course progress data for group leaders and admins
		 *
		 * @since 2.1.0
		 * 
		 * @param  int  	$course_id 	Optional.
		 * @return array 	course progress data	            
		 */
		static function course_progress_data( $course_id = null ) {
			set_time_limit( 0 );
			global $wpdb;

			$current_user = wp_get_current_user();
			if ( empty( $current_user) || ! current_user_can( 'manage_options' ) && ! is_group_leader( $current_user->ID ) ) {
				return;
			}
			
			$group_id = 0;
			if ( isset( $_GET['group_id'] ) ) {
				$group_id = $_GET['group_id'];
			}

			if ( is_group_leader( $current_user->ID ) ) {

				$users_group_ids = learndash_get_administrators_group_ids( $current_user->ID );
				if ( ! count( $users_group_ids ) ) {
					return array();
				}
				
				if ( !empty( $group_id ) ) {
					if ( ! in_array( $group_id, $users_group_ids ) ) {
						return;
					}
					$users_group_ids = array( $group_id );
				} 

				$all_user_ids = array();
				// First get the user_ids for each group...
				foreach($users_group_ids as $users_group_id) {
					$user_ids = learndash_get_groups_user_ids( $users_group_id );
					if (!empty($user_ids)) {
						if (!empty($all_user_ids)) {
							$all_user_ids = array_merge($all_user_ids, $user_ids);
						} else {
							$all_user_ids = $user_ids;
						}
					}
				}
				
				// Then once we have all the groups user_id run a last query for the complete user ids
				if (!empty($all_user_ids)) {
					$user_query_args = array(
						'include' 	=> 	$all_user_ids,
						'orderby' 	=>	'display_name',
						'order'	 	=>	'ASC',
					);
	
					$user_query = new WP_User_Query( $user_query_args );
	
					if ( isset( $user_query->results ) ) {
						$users = $user_query->results;
					}
				}
				
			} else if ( current_user_can( 'manage_options' ) ) {
				if ( ! empty( $group_id ) ) {
					$users = learndash_get_groups_users( $group_id );
				} else {
					$users = get_users( 'orderby=display_name&order=ASC' );
				}

			} else {
				return array();
			}
			
			if ( empty( $users ) ) return array();

			$course_access_list = array();

			$course_progress_data = array();
			set_time_limit( 0 );

			$quiz_titles = array();
			$lessons = array();

			if ( ! empty( $course_id ) ) {
				$courses = array( get_post( $course_id ) );
			} elseif ( ! empty( $group_id ) ){
				$courses = learndash_group_enrolled_courses( $group_id );
				$courses = array_map( 'intval', $courses );
				$courses = ld_course_list( array( 'post__in' => $courses, 'array' => true ) );
			} else {
				$courses = ld_course_list( array( 'array' => true ) );
			}

			if ( ! empty( $users ) ) {

				foreach ( $users as $u ) {

					$user_id = $u->ID;
					$usermeta = get_user_meta( $user_id, '_sfwd-course_progress', true );
					if ( ! empty( $usermeta ) ) {
						$usermeta = maybe_unserialize( $usermeta );
					}

					if ( ! empty( $courses[0] ) ) {

						foreach ( $courses as $course ) {
							$c = $course->ID;

							if ( empty( $course->post_title) || ! sfwd_lms_has_access( $c, $user_id ) ) {
								continue;
							}

							$cv = ! empty( $usermeta[ $c] ) ? $usermeta[ $c ] : array( 'completed' => '', 'total' => '' );

							$course_completed_meta = get_user_meta( $user_id, 'course_completed_'.$course->ID, true );
							( empty( $course_completed_meta ) ) ? $course_completed_date = '' : $course_completed_date = date_i18n( 'F j, Y H:i:s', $course_completed_meta );

							$row = array( 'user_id' => $user_id,
								'name' => $u->display_name,
								'email' => $u->user_email,
								'course_id' => $c,
								'course_title' => $course->post_title,
								'total_steps' => $cv['total'],
								'completed_steps' => $cv['completed'], 
								'course_completed' => ( ! empty( $cv['total'] ) && $cv['completed'] >= $cv['total'] ) ? 'YES' : 'NO' , 
								'course_completed_on' => $course_completed_date
							);

							$i = 1;
							if ( ! empty( $cv['lessons'] ) ) {
								foreach ( $cv['lessons'] as $lesson_id => $completed ) {
									if ( ! empty( $completed ) ) {
										if ( empty( $lessons[ $lesson_id ] ) ) {
											$lesson = $lessons[ $lesson_id ] = get_post( $lesson_id );
										}
										else {
											$lesson = $lessons[ $lesson_id ];
										}

										$row['lesson_completed_'.$i] = $lesson->post_title;
										$i++;
									}
								}
							}

							$course_progress_data[] = $row;

						} // end foreach

					} // end if 

				} // end foreach

			} else {
				$course_progress_data[] = array( 
					'user_id' => $user_id, 
					'name' => $u->display_name, 
					'email' => $u->user_email, 
					'status' => __( 'No attempts', 'learndash' ),
				);
			}

			 /**
			 * Filter course progress data to be displayed
			 * 
			 * @since 2.1.0
			 * 
			 * @param  array  $course_progress_data
			 */
			$course_progress_data = apply_filters( 'course_progress_data', $course_progress_data, $users, @$group_id );

			return $course_progress_data;
		}



		/**
		 * Exports course progress data to CSV file
		 *
		 * @since 2.1.0
		 */
		function course_export_init() {
			error_reporting( 0 );
			set_time_limit( 0 );

			if ( ! empty( $_REQUEST['courses_export_submit'] ) && ! empty( $_REQUEST['nonce-sfwd'] ) ) {
                                date_default_timezone_set( get_option( 'timezone_string' ) );

				$nonce = $_REQUEST['nonce-sfwd'];

				if ( ! wp_verify_nonce( $nonce, 'sfwd-nonce' ) ) { 
					die( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				}

				$content = SFWD_LMS::course_progress_data();

				if ( empty( $content ) ) {
					$content[] = array( 'status' => __( 'No attempts', 'learndash' ) );
				}

				/**
				 * include parseCSV to write csv file
				 */
				require_once( dirname( __FILE__ ) . '/vendor/parsecsv.lib.php' );

				$csv = new lmsParseCSV();

				 /**
				 * Filter the content will print onto the exported CSV
				 * 
				 * @since 2.1.0
				 * 
				 * @param  array  $content
				 */
				$content = apply_filters( 'course_export_data', $content );

				$csv->output( true, 'courses.csv', $content, array_keys( reset( $content ) ) );
				die();
			}
		}



		/**
		 * Course Export Button submit data
		 * 
		 * apply_filters ran in display_settings_page() in sfwd_module_class.php
		 *
		 * @todo  currently no add_filter using this callback
		 *        consider for deprecation or implement add_filter
		 *
		 * @since 2.1.0
		 * 
		 * @param  array $submit
		 * @return array $submit
		 */
		function courses_filter_submit( $submit ) {
			$submit['courses_export_submit'] = array( 
				'type' => 'submit',
				'class' => 'button-primary',
				'value' => __( 'Export User Course Data &raquo;', 'learndash' ) 
			);
			return $submit;
		}



		/**
		 * Export quiz data to CSV
		 * 
		 * @since 2.1.0
		 */
		function quiz_export_init() {
			error_reporting( 0 );
			set_time_limit( 0 );
			global $wpdb;
			$current_user = wp_get_current_user();

			if ( empty( $current_user) || ! current_user_can( 'manage_options' ) && ! is_group_leader( $current_user->ID ) ) {
				return;
			}

			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter( $quiz_prefix . 'submit_options', array( $this, 'quiz_filter_submit' ) );

			if ( ! empty( $_REQUEST['quiz_export_submit'] ) && ! empty( $_REQUEST['nonce-sfwd'] ) ) {
                                date_default_timezone_set( get_option( 'timezone_string' ) );

				$nonce = $_REQUEST['nonce-sfwd'];

				if ( ! wp_verify_nonce( $nonce, 'sfwd-nonce' ) ) { 
					die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				}

				/**
				 * include parseCSV to write csv file
				 */
				require_once( 'vendor/parsecsv.lib.php' );

				$content = array();
				set_time_limit( 0 );
				//Need ability to export quiz results for group to CSV

				if ( isset( $_GET['group_id'] ) ) {
					$group_id = $_GET['group_id'];
				}

				if ( is_group_leader( $current_user->ID ) ) {

					$users_group_ids = learndash_get_administrators_group_ids( $current_user->ID );
					if ( ! count( $users_group_ids ) ) {
						return array();
					}

					if ( isset( $group_id ) ) {
						if ( ! in_array( $group_id, $users_group_ids ) ) {
							return;
						}
						$users_group_ids = array( $group_id );
					} 
					
					$all_user_ids = array();
					// First get the user_ids for each group...
					foreach($users_group_ids as $users_group_id) {
						$user_ids = learndash_get_groups_user_ids( $users_group_id );
						if (!empty($user_ids)) {
							if (!empty($all_user_ids)) {
								$all_user_ids = array_merge($all_user_ids, $user_ids);
							} else {
								$all_user_ids = $user_ids;
							}
						}
					}
				
					// Then once we have all the groups user_id run a last query for the complete user ids
					if (!empty($all_user_ids)) {
						$user_query_args = array(
							'include' => $all_user_ids,
							'orderby' => 'display_name',
							'order'	 =>	'ASC',
							'meta_query' => array(
								array(
									'key'     	=> 	'_sfwd-quizzes',
									'compare' 	=> 	'EXISTS',
								),
							)
						);
						
						$user_query = new WP_User_Query( $user_query_args );
	
						if ( isset( $user_query->results ) ) {
							$users = $user_query->results;
						} 
					}
				} else if ( current_user_can( 'manage_options' ) ) {
					if ( ! empty( $group_id ) ) {
						$user_ids = learndash_get_groups_user_ids( $group_id );
						if (!empty($user_ids)) {
							$user_query_args = array(
								'include' => $user_ids,
								'orderby' => 'display_name',
								'order'	 =>	'ASC',
								'meta_query' => array(
									array(
										'key'     	=> 	'_sfwd-quizzes',
										'compare' 	=> 	'EXISTS',
									),
								)
							);
		
							$user_query = new WP_User_Query( $user_query_args );
							if (isset($user_query->results)) {
								$users = $user_query->results;
							} else {
								$users = array();
							}
						}
						
					}
					else {
						
						$user_query_args = array(
							'orderby' => 'display_name',
							'order'	 =>	'ASC',
							'meta_query' => array(
								array(
									'key'     	=> 	'_sfwd-quizzes',
									'compare' 	=> 	'EXISTS',
								),
							)
						);
	
						$user_query = new WP_User_Query( $user_query_args );
						if (isset($user_query->results)) {
							$users = $user_query->results;
						} else {
							$users = array();
						}
					}

				} else {
					return array();
				}
				
				$quiz_titles = array();

				if ( ! empty( $users ) ) {

					foreach ( $users as $u ) {

						$user_id = $u->ID;
						$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );

						if ( ! empty( $usermeta ) ) {

							foreach ( $usermeta as $k => $v ) {

								if ( ! empty( $group_id ) ) {
									$course_id = learndash_get_course_id( intval( $v['quiz'] ) );
									if ( ! learndash_group_has_course( $group_id, $course_id ) ) {
										continue;
									}
								}								

								if ( empty( $quiz_titles[ $v['quiz']] ) ) {

									if ( ! empty( $v['quiz'] ) ) {
										$quiz = get_post( $v['quiz'] );

										if ( empty( $quiz) ) {
											continue;
										}

										$quiz_titles[ $v['quiz']] = $quiz->post_title;

									} else if ( ! empty( $v['pro_quizid'] ) ) {

										$quiz = get_post( $v['pro_quizid'] );

										if ( empty( $quiz) ) {
											continue;
										}

										$quiz_titles[ $v['quiz']] = $quiz->post_title;

									} else {
										$quiz_titles[ $v['quiz']] = '';
									}
								}

								$content[] = array( 
									'user_id' => $user_id,
									'name' => $u->display_name,
									'email' => $u->user_email,
									'quiz_id' => $v['quiz'],
									'quiz_title' => $quiz_titles[ $v['quiz'] ],
									'rank' => $v['rank'],
									'score' => $v['score'],
									'total' => $v['count'],
									'date' => date_i18n( DATE_RSS, $v['time'] ) ,
								);

							}

						} else {

							//	$content[] = array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'status' => __( 'No attempts', 'learndash' ) );
							$content[] = array( 
								'user_id' => $user_id,
								'name' => $u->display_name,
								'email' => $u->user_email,
								'quiz_id' => __( 'No attempts',
								'learndash' ),
								'quiz_title' => '',
								'rank' => '',
								'score' => '',
								'total' => '',
								'date' => '' 
							 );

						} // end if

					} // end foreach 

				} // end if

				if ( empty( $content ) ) {
					$content[] = array( 'status' => __( 'No attempts', 'learndash' ) );
				}

				 /**
				 * Filter quiz data that will print to CSV
				 * 
				 * @since 2.1.0
				 * 
				 * @param  array  $content
				 */
				$content = apply_filters( 'quiz_export_data', $content, $users, @$group_id );

				$csv = new lmsParseCSV();
				$csv->output( true, 'quizzes.csv', $content, array_keys( reset( $content ) ) );
				die();

			}
		}



		/**
		 * Quiz Export Button submit data
		 * 
		 * Filter callback for $quiz_prefix . 'submit_options'
		 * apply_filters ran in display_settings_page() in sfwd_module_class.php
		 * 
		 * @since 2.1.0
		 * 
		 * @param  array $submit
		 * @return array
		 */
		function quiz_filter_submit( $submit ) {			
			$submit['quiz_export_submit'] = array( 
				'type' => 'submit',
				'class' => 'button-primary',
				'value' => __( 'Export Quiz Data &raquo;', 'learndash' ) 
			);
			return $submit;
		}



		/**
		 * Export transcations to CSV file
		 * 
		 * Not currently being used in plugin
		 *
		 * @todo consider for deprecation or implement in plugin
		 *
		 * @since 2.1.0
		 */
		function trans_export_init() {
			$sfwd_trans = $this->post_types['sfwd-transactions'];
			$trans_prefix = $sfwd_trans->get_prefix();
			add_filter( $trans_prefix . 'submit_options', array( $this, 'trans_filter_submit' ) );

			if ( ! empty( $_REQUEST['export_submit'] ) && ! empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];

				if ( ! wp_verify_nonce( $nonce, 'sfwd-nonce' ) ) { 
					die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				}

				/**
				 * Include parseCSV to write csv file
				 */
				require_once( 'vendor/parsecsv.lib.php' );

				$content = array();
				set_time_limit( 0 );

				$locations = query_posts( 
					array( 
						'post_status' => 'publish', 
						'post_type' => 'sfwd-transactions', 
						'posts_per_page' => -1 
					) 
				);

				foreach ( $locations as $key => $location ) {
					$location_data = get_post_custom( $location->ID );
					foreach ( $location_data as $k => $v ) {
						if ( $k[0] == '_' ) {
							unset( $location_data[ $k ] );
						}
						else {
							$location_data[ $k] = $v[0];
						}
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



		/**
		 * Transaction Export Button submit data
		 *
		 * Filter callback for $trans_prefix . 'submit_options'
		 * apply_filters ran in display_settings_page() in sfwd_module_class.php
		 * 
		 * @since 2.1.0
		 * 
		 * @param  array $submit
		 * @return array
		 */
		function trans_filter_submit( $submit ) {
			unset( $submit['Submit'] );
			unset( $submit['Submit_Default'] );

			$submit['export_submit'] = array( 
				'type' => 'submit',
				'class' => 'button-primary',
				'value' => __( 'Export &raquo;', 'learndash' ) 
			);

			return $submit;
		}



		/**
		 * Set up quiz display settings
		 * 
		 * Filter callback for '{$quiz_prefix}display_settings'
		 * apply_filters in display_options() in swfd_module_class.php
		 *
		 * @since 2.1.0
		 * 
		 * @param  array  $settings        quiz settings
		 * @param  string $location        where these settings are being displayed
		 * @param  array  $current_options current options stored for a given location
		 * @return array                   quiz settings
		 */
		function quiz_display_settings( $settings, $location, $current_options ) {
			global $sfwd_lms;
			$sfwd_quiz = $sfwd_lms->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			$prefix_len = strlen( $quiz_prefix );
			$quiz_options = $sfwd_quiz->get_current_options();

			if ( $location == null ) {

				foreach ( $quiz_options as $k => $v ) {
					if ( strpos( $k, $quiz_prefix ) === 0 ) {
						$quiz_options[ substr( $k, $prefix_len ) ] = $v;
						unset( $quiz_options[ $k ] );
					}
				}

				foreach ( array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) {
					$quiz['info'][ $level ] = $quiz_options[ $level ];
				}

				$quiz['info']['name'] = $quiz['info']['main'] = $quiz['info']['results'] = '';
				$quiz_json = json_encode( $quiz );
				$settings['sfwd-quiz_quiz']['default'] = '<div class="quizFormWrapper"></div><script type="text/javascript">var quizJSON = ' . $quiz_json . ';</script>';
				
				if ( $location == null ) { 
					unset( $settings["{$quiz_prefix}quiz"] );
				}

				if ( ! empty( $settings["{$quiz_prefix}certificate_post"] ) ) {
					$posts = get_posts( array( 'post_type' => 'sfwd-certificates' , 'numberposts' => -1 ) );
					$post_array = array( '0' => __( '-- Select a Certificate --', 'learndash' ) );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $p ) {
							$post_array[ $p->ID ] = $p->post_title;
						}
					}

					$settings["{$quiz_prefix}certificate_post"]['initial_options'] = $post_array;
				}

			} else {

				if ( ! empty( $settings["{$quiz_prefix}lesson"] ) ) {
					$post_array = $this->select_a_lesson_or_topic();
					$settings["{$quiz_prefix}lesson"]['initial_options'] = $post_array;
				}

				if ( ! empty( $settings["{$quiz_prefix}certificate"] ) ) {					
					$posts = get_posts( array( 'post_type' => 'sfwd-certificates'  , 'numberposts' => -1 ) );
					$post_array = array( '0' => __( '-- Select a Certificate --', 'learndash' ) );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $p ) {
							$post_array[ $p->ID ] = $p->post_title;
						}
					}

					$settings["{$quiz_prefix}certificate"]['initial_options'] = $post_array;
				}
			}

			return $settings;
		}



		/**
		 * Sets up Associated Course dropdown for lessons, quizzes, and topics
		 *
		 * @since 2.1.0
		 * 
		 * @param  string $current_post_type
		 * @return array of courses
		 * 
		 */
		function select_a_course( $current_post_type = null ) {
			global $pagenow;

			if ( ! is_admin() || ( $pagenow != 'post.php' && $pagenow != 'post-new.php') ) {
				return array();
			}

			if ( $pagenow == 'post.php' && empty( $_POST['_wpnonce'] ) && ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
				$post_id = $_GET['post'];
				$post = get_post( $post_id );
				if ( ! empty( $post->ID ) && $current_post_type == $post->post_type ) {
					if ( in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-quiz', 'sfwd-topic') ) ) {
						$course_id = learndash_get_course_id( $post );
						learndash_update_setting( $post, 'course', $course_id );
					}
				}
			}

			$options = array( 
				'array' => true, 
				'post_status' => 'any',  
				'orderby' => 'title', 
				'order' => 'ASC' 
			);

			 /**
			 * Filter options for querying course list
			 * 
			 * @since 2.1.0
			 * 
			 * @param  array  $options
			 */
			$options = apply_filters( 'learndash_select_a_course', $options );
			$posts = ld_course_list( $options );

			$post_array = array( '0' => __( '-- Select a Course --', 'learndash' ) );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $p ){
					$post_array[ $p->ID ] = $p->post_title;
				}
			}

			return $post_array;
		}



		/**
		 * Retrieves lessons or topics for a course to populate dropdown on edit screen
		 * 
		 * Ajax action callback for wp_ajax_select_a_lesson_or_topic
		 *
		 * @since 2.1.0
		 */
		function select_a_lesson_or_topic_ajax() {
			$post_array = $this->select_a_lesson_or_topic( @$_REQUEST['course_id'] );
			$i = 0;
			foreach ( $post_array as $key => $value ) {
				$opt[ $i ]['key'] = $key;
				$opt[ $i ]['value'] = $value;
				$i++;
			}
			$data['opt'] = $opt;
			echo json_encode( $data );
			exit;
		}



		/**
		 * Makes wp_query to retrieve lessons or topics for a course
		 *
		 * @since 2.1.0
		 * 
		 * @param  int 		$course_id 
		 * @return array 	array of lessons or topics
		 */
		function select_a_lesson_or_topic( $course_id = null ) {
			if ( ! is_admin() ) {
				return array();
			}

			$opt = array( 
				'post_type' => 'sfwd-lessons',
				'post_status' => 'any',  'numberposts' => -1,
				'orderby' => learndash_get_option( 'sfwd-lessons', 'orderby' ),
				'order' => learndash_get_option( 'sfwd-lessons', 'order' ),
			);

			if ( empty( $course_id ) ) {
				$course_id = learndash_get_course_id( @$_GET['post'] );
			}

			if ( ! empty( $course_id ) ) {
				$opt['meta_key'] = 'course_id';
				$opt['meta_value'] = $course_id;
			}

			$posts = get_posts( $opt );
			$topics_array = learndash_get_topic_list();

			$post_array = array( '0' => __( '-- Select a Lesson or Topic --', 'learndash' ) );
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $p ){
					$post_array[ $p->ID ] = $p->post_title;
					if ( ! empty( $topics_array[ $p->ID ] ) ) {
						foreach ( $topics_array[ $p->ID ] as $id => $topic ) {
							$post_array[ $topic->ID ] = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $topic->post_title;
						}
					}
				}
			}
			return $post_array;
		}



		/**
		 * Retrieves lessons for a course to populate dropdown on edit screen
		 * 
		 * Ajax action callback for wp_ajax_select_a_lesson
		 *
		 * @since 2.1.0
		 */
		function select_a_lesson_ajax() {
			$post_array = $this->select_a_lesson( @$_REQUEST['course_id'] );
			echo json_encode( $post_array );
			exit;
		}



		/**
		 * Makes wp_query to retrieve lessons a course
		 *
		 * @since 2.1.0
		 * 
		 * @param  int 		$course_id 
		 * @return array 	array of lessons
		 */
		function select_a_lesson( $course_id = null ) {			
			if ( ! is_admin() ) {
				return array();
			}

			if ( ! empty( $_REQUEST['ld_action'] ) || ! empty( $_GET['post'] ) && is_array( $_GET['post'] ) ) {
				return array();
			}

			$opt = array( 
				'post_type' => 'sfwd-lessons', 
				'post_status' => 'any',  
				'numberposts' => -1 , 
				'orderby' => learndash_get_option( 'sfwd-lessons', 'orderby' ), 
				'order' => learndash_get_option( 'sfwd-lessons', 'order' ),
			);

			if ( empty( $course_id ) ) {
				if ( empty( $_GET['post'] ) ) {
					$course_id = learndash_get_course_id();
				} else {
					$course_id = learndash_get_course_id( $_GET['post'] );
				}
			}

			if ( ! empty( $course_id ) ) {
				$opt['meta_key'] = 'course_id';
				$opt['meta_value'] = $course_id;
			}

			$posts = get_posts( $opt );
			$post_array = array( '0' => __( '-- Select a Lesson --', 'learndash' ) );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $p ) {
					$post_array[ $p->ID ] = $p->post_title;
				}
			}

			return $post_array;
		}


		
		/**
		 * Set up course display settings
		 * 
		 * Filter callback for '{$courses_prefix}display_settings'
		 * apply_filters in display_options() in swfd_module_class.php
		 *
		 * @since 2.1.0
		 * 
		 * @param  array  $settings        quiz settings
		 * @return array                   quiz settings
		 */
		function course_display_settings( $settings ) {

			global $sfwd_lms;
			$sfwd_courses = $sfwd_lms->post_types['sfwd-courses'];
			$courses_prefix = $sfwd_courses->get_prefix();

			if ( ! empty( $settings["{$courses_prefix}course_prerequisite"] ) ) {
				$options = array( 
					'post_type' => 'sfwd-courses', 
					'post_status' => 'any',  
					'numberposts' => -1
				);

				 /**
				 * Filter course prerequisites
				 * 
				 * @since 2.1.0
				 * 
				 * @param  array  $options 
				 */
				$options = apply_filters( 'learndash_course_prerequisite_post_options', $options );

				$posts = get_posts( $options );
				$post_array = array( '0' => __( '-- Select a Course --', 'learndash' ) );

				if ( ! empty( $posts ) ) {
					foreach ( $posts as $p ) {
						if ( $p->ID == get_the_id() ){
							//Skip for current post id as current course can not be prerequities of itself
						} else { 
							$post_array[ $p->ID ] = $p->post_title;
						}
					}
				}

				$settings["{$courses_prefix}course_prerequisite"]['initial_options'] = $post_array;
			}

			if ( ! empty( $settings["{$courses_prefix}certificate"] ) ) {
				$posts = get_posts( array( 'post_type' => 'sfwd-certificates'  , 'numberposts' => -1) );
				$post_array = array( '0' => __( '-- Select a Certificate --', 'learndash' ) );

				if ( ! empty( $posts ) ) {
					foreach ( $posts as $p ) {
						$post_array[ $p->ID ] = $p->post_title;
					}
				}

				$settings["{$courses_prefix}certificate"]['initial_options'] = $post_array;
			}

			return $settings;

		}



		/**
		 * Insert course name as a term on course publish
		 * 
		 * Action callback for 'publish_sfwd-courses' (wp core filter action)
		 *
		 * @todo  consider for deprecation, action is commented 
		 *
		 * @since 2.1.0
		 * 
		 * @param int 		$post_id
		 * @param object 	$post
		 */
		function add_course_tax_entry( $post_id, $post ) {
			$term = get_term_by( 'slug', $post->post_name, 'courses' );
			$term_id = isset( $term->term_id ) ? $term->term_id : 0;

			if ( ! $term_id ) {
				$term = wp_insert_term( $post->post_title, 'courses', array( 'slug' => $post->post_name ) );
				$term_id = $term['term_id'];
			}

			wp_set_object_terms( (int)$post_id, (int)$term_id, 'courses', true );
		}



		/**
		 * Register taxonomies for each custom post type
		 * 
		 * Action callback for 'init'
		 *
		 * @since 2.1.0
		 */
		function tax_registration() {

			/**
			 * Filter that gathers taxonomies that need to be registered
			 * add_filters are currently added during the add_post_type() method in swfd_cpt.php
			 *
			 * @since 2.1.0
			 * 
			 * @param  array
			 */
			$taxes = apply_filters( 'sfwd_cpt_register_tax', array() );

			if ( ! empty( $taxes ) ) {
				$post_types = array();
				$tax_options = null;

				foreach ( $taxes as $k => $v ) {

					if ( ! empty( $v ) ) {

						foreach ( $v as $tax ) {

							if ( ! is_array( $tax[0] ) ) { 
								$tax[0] = array( $tax[0] );
							}

							$post_types = array_merge( $post_types, $tax[0] );

							if ( empty( $tax_options ) ) {
								$tax_options = $tax[1];
							} else {
								foreach ( $tax[1] as $l => $w ) {
									$tax_options[ $l] = $w;
								}
							}

						} // end foreach

					} // endif

				}// end foreach

				register_taxonomy( $k, $post_types, $tax_options );				
			} // endif

		}



		/**
		 * Get LearnDash template and pass data to be used in template
		 *
		 * Checks to see if user has a 'learndash' directory in their current theme
		 * and uses the template if it exists.
		 *
		 * @since 2.1.0
		 * 
		 * @param  string  	$name             template name
		 * @param  array  	$args             data for template
		 * @param  boolean 	$echo             echo or return
		 * @param  boolean 	return_file_path  return just file path instead of output
		 */
		static function get_template( $name, $args, $echo = false, $return_file_path = false ){
			$filename = substr( $name, -4 ) == '.php' ? $name : $name . '.php';
			$filepath = locate_template( array( 'learndash/'.$filename) );

			if ( ! $filepath ) {
				$filepath = locate_template( $filename );
			}

			if ( ! $filepath ){
				$filepath = dirname( dirname( __FILE__ ) ) . '/templates/' . $filename;
				if ( ! file_exists( $filepath ) ) {
					return false;
				}				
			}

			/**
			 * Filter filepath for learndash template being called
			 * 
			 * @since 2.1.0
			 * 
			 * @param  string  $filepath
			 */
			$filepath = apply_filters( 'learndash_template', $filepath, $name, $args, $echo, $return_file_path );

			if ( $return_file_path ) {
				return $filepath;
			}

			extract( $args );
			$level = ob_get_level();
			ob_start();
			include( $filepath );
			$contents = learndash_ob_get_clean( $level );

			if ( ! $echo ) {
				return $contents;
			}

			echo $contents;

		}

	}

}

$sfwd_lms = new SFWD_LMS();
