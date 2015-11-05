<?php
/**
 * SFWD_CPT_Instance
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\CPT
 */


if ( ! class_exists( 'SFWD_CPT_Instance' ) ) {

	/**
	 * Extends functionality of SWFD_CPT instance
	 *
	 * @todo  consider whether these methods can just be included in SWFD_CPT
	 *        unclear as to why it's separate
	 */
	class SFWD_CPT_Instance extends SFWD_CPT {

		public static $instances = array();



		/**
		 * Sets up properties for CPT to be used in plugins
		 *
		 * @since 2.1.0
		 * 
		 * @param array $args  parameters for setting up the CPT instance
		 */
		function __construct( $args ) {
			extract( $args );

			if ( empty( $plugin_name ) ) {	
				$plugin_name = 'SFWD CPT Instance';
			}

			if ( empty( $post_name ) ) {
				$post_name = $plugin_name;
			}

			if ( empty( $slug_name ) ) {
				$slug_name = sanitize_file_name( strtolower( strtr( $post_name, ' ', '_' ) ) );
			}

			if ( empty( $post_type ) ) {
				$post_type = sanitize_file_name( strtolower( strtr( $slug_name, ' ', '_' ) ) );
			}

			SFWD_CPT_Instance::$instances[ $post_type ] =& $this;

			if ( empty( $name ) ) {
				$name = ! empty( $options_page_title)? $options_page_title:$post_name.__( ' Options', 'learndash' );
			}

			if ( empty( $prefix ) ) {
				$prefix = sanitize_file_name( $post_type ) . '_';
			}

			if ( ! empty( $taxonomies ) ) {	
				$this->taxonomies = $taxonomies;
			}

			$this->file = __FILE__ . "?post_type={$post_type}";
			$this->plugin_name	= $plugin_name;
			$this->post_name	= $post_name;
			$this->slug_name	= $slug_name;
			$this->post_type	= $post_type;
			$this->name			= $name;
			$this->prefix		= $prefix;
			$posts_per_page = get_option( 'posts_per_page' );

			if ( empty( $posts_per_page ) ) { 
				$posts_per_page = 5;
			}

			if ( empty( $default_options ) ) {

				$this->default_options = array(
					'orderby' => array(
						'name' => __( 'Sort By', 'learndash' ),
						'type' => __( 'select', 'learndash' ),
						'initial_options' => array(	
							''		=> __( 'Select a choice...', 'learndash' ),
							'title'	=> __( 'Title', 'learndash' ),
							'date'	=> __( 'Date', 'learndash' ),
							'menu_order' => __( 'Menu Order', 'learndash' )
						),
						'default' => 'date',
						'help_text' => __( 'Choose the sort order.', 'learndash' )
					),
					'order' => array(
						'name' => __( 'Sort Direction', 'learndash' ),
						'type' => 'select',
						'initial_options' => array(	
							''		=> __( 'Select a choice...', 'learndash' ),
							'ASC'	=> __( 'Ascending', 'learndash' ),
							'DESC'	=> __( 'Descending', 'learndash' )
						),
						'default' => 'DESC',
						'help_text' => __( 'Choose the sort order.', 'learndash' )
					),
					'posts_per_page' => array(
						'name' => __( 'Posts Per Page', 'learndash' ),
						'type' => 'text',
						'help_text' => __( 'Enter the number of posts to display per page.', 'learndash' ),
						'default' => $posts_per_page
					),
				);

			} else {				
				$this->default_options = $default_options;
			}

			if ( ! empty( $fields ) ) {
				$this->locations = array(
					'default' => array( 
						'name' => $this->name, 
						'prefix' => $this->prefix, 
						'type' => 'settings', 
						'options' => null 
					),
					$this->post_type => array( 
						'name' => $this->plugin_name, 
						'type' => 'metabox', 'prefix' => '',
						'options' => array_keys( $fields ),
						'default_options' => $fields,
						'display' => array( $this->post_type ) 
					)
				);
			}

			parent::__construct();

			if ( ! empty( $description ) ) { 
				$this->post_options['description'] = wp_kses_post( $description );			
			}

			if ( ! empty( $menu_icon ) ) { 
				$this->post_options['menu_icon'] = esc_url( $menu_icon );
			}

			if ( ! empty( $cpt_options ) ) { 
				$this->post_options = wp_parse_args( $cpt_options, $this->post_options );
			}

			add_action( 'admin_menu', array( &$this, 'admin_menu') );
			add_shortcode( $this->post_type, array( $this, 'shortcode' ) );
			add_action( 'init', array( $this, 'add_post_type' ) );

			$this->update_options();

			if ( ! is_admin() ) {
				add_action( 'pre_get_posts', array( $this, 'pre_posts' ) );

				if ( isset( $template_redirect ) && ( $template_redirect === true ) ) {
					/*if ( !empty( $this->options[ $this->prefix . 'template_redirect'] ) ) {
						add_action("template_redirect", array( $this, 'template_redirect' ) );
					} else*/ {
					add_action( 'template_redirect', array( $this, 'template_redirect_access' ) );
					add_filter( 'the_content', array( $this, 'template_content' ), 1000 );
					}
				}

			}

		} // end __construct()



		/**
		 * Get Archive content
		 *
		 * @todo Consider reworking, function returns content of a post.
		 *       Not archive.
		 *       
		 * @since 2.1.0
		 * 
		 * @param  string $content
		 * @return string $content
		 */
		function get_archive_content( $content ) {
			global $post;
			if ( sfwd_lms_has_access( $post->ID ) ) {
				return $content;
			} else {
				return get_the_excerpt();
			}
		} // end get_archive_content()



		/**
		 * Generate output for courses, lessons, topics, quizzes
		 * Filter callback for 'the_content' (wp core filter)
		 * 
		 * Determines what the user is currently looking at, sets up data,
		 * passes to template, and returns output.
		 *
		 * @since 2.1.0
		 * 
		 * @param  string $content content of post
		 * @return string $content content of post
		 */
		function template_content( $content ) {
			global $wp;
			$post = get_post( get_the_id() );
			$current_user = wp_get_current_user();
			$post_type = '';

			if ( get_query_var( 'post_type' ) ) {
				$post_type = get_query_var( 'post_type' );
			}

			if ( ( ! is_singular() ) || ( $post_type != $this->post_type ) || ( $post_type != $post->post_type ) ) {
				return $content;
			}

			$user_id = get_current_user_id();
			$logged_in = ! empty( $user_id );
			$course_id = learndash_get_course_id();
			$lesson_progression_enabled = false;
			$has_access = '';

			if ( ! empty( $course_id ) ) {
				$course = get_post( $course_id );
				$course_settings = learndash_get_setting( $course );
				$lesson_progression_enabled  = learndash_lesson_progression_enabled();
				$courses_options = learndash_get_option( 'sfwd-courses' );
				$lessons_options = learndash_get_option( 'sfwd-lessons' );
				$quizzes_options = learndash_get_option( 'sfwd-quiz' );
				$course_status = learndash_course_status( $course_id, null );
				$course_certficate_link = learndash_get_course_certificate_link( $course_id, $user_id );
				$has_access = sfwd_lms_has_access( $course_id, $user_id );
			}

			if ( ! empty( $wp->query_vars['name'] ) ) {
				// single
				if ( is_course_prerequities_completed( $post->ID ) ) {
					if ( $this->post_type == 'sfwd-courses' ) {

						$courses_prefix = $this->get_prefix();
						$prefix_len = strlen( $courses_prefix );

						if ( ! empty( $course_settings['course_materials'] ) ) {
							$materials = wp_kses_post( wp_specialchars_decode( $course_settings['course_materials'], ENT_QUOTES ) );
						}

						$lessons = learndash_get_course_lessons_list( $course );
						$quizzes = learndash_get_course_quiz_list( $course );

						$has_course_content = ( ! empty( $lessons) || ! empty( $quizzes ) );

						$lesson_topics = array();

						$has_topics = false;

						if ( ! empty( $lessons ) ) {
							foreach ( $lessons as $lesson ) {
								$lesson_topics[ $lesson['post']->ID ] = learndash_topic_dots( $lesson['post']->ID, false, 'array' );
								if ( ! empty( $lesson_topics[ $lesson['post']->ID ] ) ) {
									$has_topics = true;
								}
							}
						}

						include_once( 'vendor/paypal/enhanced-paypal-shortcodes.php' );
						$level = ob_get_level();
						ob_start();
						include( SFWD_LMS::get_template( 'course', null, null, true ) );
						$content = learndash_ob_get_clean( $level );

					} elseif ( $this->post_type == 'sfwd-quiz' ) {

						$quiz_settings = learndash_get_setting( $post );
						$meta = @$this->get_settings_values( 'sfwd-quiz' );
						$show_content = ! ( ! empty( $lesson_progression_enabled) && ! is_quiz_accessable( null, $post ) );
						$attempts_count = 0;
						$repeats = trim( @$quiz_settings['repeats'] );

						if ( $repeats != '' ) {
							$user_id = get_current_user_id();

							if ( $user_id ) {
								$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
								$usermeta = maybe_unserialize( $usermeta );

								if ( ! is_array( $usermeta ) ) { 
									$usermeta = array();
								}

								if ( ! empty( $usermeta ) )	{
									foreach ( $usermeta as $k => $v ) {
										if ( $v['quiz'] == $post->ID ) { 
											$attempts_count++;
										}
									}
								}

							}
						}

						$attempts_left = ( $repeats == '' || $repeats >= $attempts_count );

						if ( ! empty( $lesson_progression_enabled) && ! is_quiz_accessable( null, $post ) ) {
							add_filter( 'comments_array', 'learndash_remove_comments', 1, 2 );
						}

						 /**
						 * Filter for content access
						 *
						 * If not null, will display instead of quiz content
						 * 
						 * @since 2.1.0
						 * 
						 * @param  string
						 */
						$access_message = apply_filters( 'learndash_content_access', null, $post );

						if ( ! is_null( $access_message ) ) {
							$quiz_content = $access_message;
						} else {							
							if ( ! empty( $quiz_settings['quiz_pro'] ) ) {
								$quiz_content = wptexturize( do_shortcode( '[LDAdvQuiz '.$quiz_settings['quiz_pro'].']' ) );
							}

							 /**
							 * Filter quiz content
							 * 
							 * @since 2.1.0
							 * 
							 * @param  string  $quiz_content
							 */
							$quiz_content = apply_filters( 'learndash_quiz_content', $quiz_content, $post );
						}

						$level = ob_get_level();
						ob_start();
						include( SFWD_LMS::get_template( 'quiz', null, null, true ) );
						$content = learndash_ob_get_clean( $level );

					} elseif ( $this->post_type == 'sfwd-lessons' ) {

						$previous_lesson_completed = is_previous_complete( $post );
						$show_content = ( ! $lesson_progression_enabled || $previous_lesson_completed );
						$lesson_settings = learndash_get_setting( $post );
						$quizzes = learndash_get_lesson_quiz_list( $post );

						if ( ! empty( $quizzes) ) {
							foreach ( $quizzes as $quiz ) {
								$quizids[ $quiz['post']->ID ] = $quiz['post']->ID;
							}
						}

						if ( $lesson_progression_enabled && ! $previous_lesson_completed ) {
							add_filter( 'comments_array', 'learndash_remove_comments', 1,2 );
						}

						$topics = learndash_topic_dots( $post->ID, false, 'array' );

						if ( ! empty( $quizids ) ) {
							$all_quizzes_completed = ! learndash_is_quiz_notcomplete( null, $quizids );
						} else {
							$all_quizzes_completed = true;
						}

						$level = ob_get_level();
						ob_start();
						include( SFWD_LMS::get_template( 'lesson', null, null, true ) );
						$content = learndash_ob_get_clean( $level );

					}  elseif ( $this->post_type == 'sfwd-topic' ) {
						
						$lesson_id = learndash_get_setting( $post, 'lesson' );
						$lesson_post = get_post( $lesson_id );
						$previous_topic_completed = is_previous_complete( $post );
						$previous_lesson_completed = is_previous_complete( $lesson_post );
						$show_content = (empty( $lesson_progression_enabled) || $previous_topic_completed && $previous_lesson_completed );
						$quizzes = learndash_get_lesson_quiz_list( $post );

						if ( ! empty( $quizzes) ) {
							foreach ( $quizzes as $quiz ) {
								$quizids[ $quiz['post']->ID ] = $quiz['post']->ID;
							}
						}

						if ( $lesson_progression_enabled && ( ! $previous_topic_completed || ! $previous_lesson_completed ) ) {
							add_filter( 'comments_array', 'learndash_remove_comments', 1, 2 );
						}

						if ( ! empty( $quizids ) ) {
							$all_quizzes_completed = ! learndash_is_quiz_notcomplete( null, $quizids );
						} else {
							$all_quizzes_completed = true;
						}

						$topics = learndash_topic_dots( $lesson_id, false, 'array' );
						$level = ob_get_level();
						ob_start();
						include( SFWD_LMS::get_template( 'topic', null, null, true ) );
						$content = learndash_ob_get_clean( $level );

					} else {
						// archive
						$content = $this->get_archive_content( $content );
					}
				} else {

					if ( $this->post_type == 'sfwd-courses' ) { 
						$content_type = 'course';
					} elseif ( $this->post_type == 'sfwd-lessons' ) {
						$content_type = 'lesson';
					} elseif ( $this->post_type == 'sfwd-quiz' ) {
						$content_type = 'quiz';
					}

					$course_pre = isset( $course_settings['course_prerequisite'] ) ? $course_settings['course_prerequisite'] : 0;
					$course_title = get_the_title( $course_pre );
					$course_link = get_permalink( $course_pre );
					$content = "<div id='learndash_complete_prerequisites'>".sprintf( __( 'To take this %s, you need to complete the following course first:%s', 'learndash' ), __( $content_type, 'learndash' ),'<br><a href="'.$course_link.'">'.$course_title.'</a>' ).'</div>';
				}
			}

			$content = str_replace( array( "\n", "\r" ), ' ', $content );
			$user_has_access = $has_access? 'user_has_access':'user_has_no_access';

			 /**
			 * Filter content to be return inside div
			 * 
			 * @since 2.1.0
			 * 
			 * @param  string  $content 
			 */
			return '<div class="learndash '.$user_has_access.'"  id="learndash_post_'.$post->ID.'">'.apply_filters( 'learndash_content', $content, $post ).'</div>';
		} // end template_content()



		/**
		 * Show course completion/quiz completion
		 * Action callback from 'template_redirect' (wp core action)
		 *
		 * @since 2.1.0
		 */
		function template_redirect_access() {
			global $wp;
			global $post;

			if ( get_query_var( 'post_type' ) ) {
				$post_type = get_query_var( 'post_type' );
			} else {
				if ( ! empty( $post ) ) {
					$post_type = $post->post_type;
				}
			}

			if ( empty( $post_type ) ) { 
				return;
			}

			if ( $post_type == $this->post_type ) {
				if ( is_robots() ) {
					/**
					 * Display the robots.txt file content. (wp core action)				
					 * 
					 * @since 2.1.0
					 *
					 * @link https://codex.wordpress.org/Function_Reference/do_robots
					 */
					do_action( 'do_robots' );
				} elseif ( is_feed() ) {
					do_feed();
				} elseif ( is_trackback() ) {
				   include( ABSPATH . 'wp-trackback.php' );
				} elseif ( ! empty( $wp->query_vars['name'] ) ) {
					// single
					if ( ( $post_type == 'sfwd-quiz' ) || ( $post_type == 'sfwd-lessons' )  || ( $post_type == 'sfwd-topic' ) ) {
						global $post;
						sfwd_lms_access_redirect( $post->ID );
					}
				}
				// archive
			}

			if ( $post_type == 'sfwd-certificates' ) {
				if ( ! empty( $_GET['course_id'] ) && ! empty( $_GET['user_id'] ) && sfwd_lms_has_access( $_GET['course_id'], $_GET['user_id'] ) ) {
					if ( current_user_can( 'manage_options' ) || get_current_user_id() == $_GET['user_id'] ) {
						$course_status = learndash_course_status( $_GET['course_id'], $_GET['user_id'] );
						if ( $course_status == __( 'Completed', 'learndash' ) ) {
							
							/**
							 * Include library to generate PDF
							 */
							require_once( 'ld-convert-post-pdf.php' );							
							post2pdf_conv_post_to_pdf();
							die();
						}
					}
					die();
				}
			}

			if ( ( $this->post_type == 'sfwd-quiz' ) && ( $post_type == 'sfwd-certificates' ) ) {
				global $post;
				$id = $post->ID;

				if ( ! empty( $_GET ) && ! empty( $_GET['quiz'] ) ) { 
					$id = $_GET['quiz'];
				}

				$meta = get_post_meta( $id, '_sfwd-quiz' );

				if ( ! empty( $post ) && is_single() ) {
					$print_cert = false;
					$cert_post = '';

					if ( is_array( $meta ) && ! empty( $meta ) ) {
						$meta = $meta[0];
						if ( is_array( $meta ) && ( ! empty( $meta['sfwd-quiz_certificate'] ) ) ) {
							$cert_post = $meta['sfwd-quiz_certificate'];
						}
					}

					if ( empty( $cert_post ) && ! empty( $this->options["{$this->prefix}certificate_post"] ) ) {
						$cert_post = $this->options["{$this->prefix}certificate_post"];
					}

					$user_id = get_current_user_id();
					$quiz = $_GET['quiz'];

					if ( ! empty( $cert_post ) && ( $cert_post == $post->ID ) ) {

						if ( ( ! empty( $_GET ) ) && ( ! empty( $_GET['print'] ) && ( wp_verify_nonce( $_GET['print'], $id . $user_id ) ) ) ) {

							$time = isset( $_GET['time'] )? $_GET['time']: -1;
							$quizinfo = get_user_meta( $user_id, '_sfwd-quizzes', true );
							$selected_quizinfo = $selected_quizinfo2 = null;

							if ( ! empty( $quizinfo) ) {
								foreach ( $quizinfo as $quiz_i ) {

									if ( isset( $quiz_i['time'] ) && $quiz_i['time'] == $time && $quiz_i['quiz'] == $quiz ) {
										$selected_quizinfo = $quiz_i;
										break;
									}

									if ( $quiz_i['quiz'] == $quiz ) {
										$selected_quizinfo2 = $quiz_i;
									}

								}
							}

							$selected_quizinfo = empty( $selected_quizinfo ) ? $selected_quizinfo2 : $selected_quizinfo;
							$certificate_threshold = learndash_get_setting( $post, 'threshold' );

							if ( ! empty( $selected_quizinfo ) ) {
								if ( (isset( $selected_quizinfo['percentage'] ) && $selected_quizinfo['percentage'] >= $certificate_threshold * 100) || (isset( $selected_quizinfo['count'] ) && $selected_quizinfo['score'] / $selected_quizinfo['count'] >= $certificate_threshold) ) {
									$print_cert = true;
								}
							}

						}

					}

					if ( $print_cert ) {
						/**
						 * Include library to generate PDF
						 */
						require_once( 'ld-convert-post-pdf.php' );
						post2pdf_conv_post_to_pdf();
						die();
					} else {
						if ( ! current_user_can( 'level_8' ) ) {
							echo __( 'Access to certificate page is disallowed.', 'learndash' );
							die();
						}
					}

				}
			}
		} // end template_redirect_access()



		/**
		 * Amend $wp_query based on what content user is viewing
		 * 
		 * If archive for post type of this instance, set order and posts per page
		 * If post archive, don't display certificates
		 *
		 * @since 2.1.0
		 */
		function pre_posts() {
			global $wp_query;

			if ( is_post_type_archive( $this->post_type ) ) {

				foreach ( array( 'orderby', 'order', 'posts_per_page' ) as $field ) {
					if ( $this->option_isset( $field ) ) {
						$wp_query->set( $field, $this->options[ $this->prefix . $field ] );
					}
				}

			} elseif ( ( $this->post_type == 'sfwd-quiz' ) && ( is_post_type_archive( 'post' ) || is_home() ) && ! empty( $this->options["{$this->prefix}certificate_post"] ) ) {
				
				$post_not_in = $wp_query->get( 'post__not_in' );

				if ( ! is_array( $post_not_in ) ) { 
					$post_not_in = array();
				}

				$post_not_in = array_merge( $post_not_in, array( $this->options["{$this->prefix}certificate_post"] ) );
				$wp_query->set( 'post__not_in', $post_not_in );

			}
		} // end pre_posts()
	}
}
