<?php

require_once('sfwd_module_class.php');

if ( !class_exists( 'SFWD_CPT' ) ) {
	abstract class SFWD_CPT extends Semper_Fi_Module {
		protected $post_name;
		protected $post_type;
		protected $post_options;
		protected $tax_options;
		protected $slug_name;
		protected $taxonomies = null;

		function __construct() {
			parent::__construct();
			$this->post_options = Array (	'label' 				=> $this->post_name,
											'labels'				=> Array(	'name' => $this->post_name,
																				'singular_name'		=> $this->post_name,
																				'add_new'			=> __( 'Add New', 'learndash' ),
																				'all_items'			=> $this->post_name,
																				'add_new_item'		=> sprintf( __( 'Add New %s', 'learndash' ), $this->post_name ),
																				'edit_item'			=> sprintf( __( 'Edit %s', 'learndash' ), $this->post_name ),
																				'new_item'			=> sprintf( __( 'New %s', 'learndash' ), $this->post_name ),
																				'view_item'			=> sprintf( __( 'View %s', 'learndash' ), $this->post_name ),
																				'search_items'		=> sprintf( __( 'Search %s', 'learndash' ), $this->post_name ),
																				'not_found'			=> sprintf( __( 'No %s found', 'learndash' ), $this->post_name ),
																				'not_found_in_trash'=> sprintf( __( 'No %s found in Trash', 'learndash' ), $this->post_name ),
																				'parent_item_colon'	=> sprintf( __( 'Parent %s', 'learndash' ), $this->post_name ),
																				'menu_name'			=> $this->post_name
																			),
											'public' 				=> true, 
											'rewrite'				=> Array( 'slug' => $this->slug_name, 'with_front' => false ),
											'show_ui' 				=> true,
											'has_archive'			=> true,
											'show_in_nav_menus'		=> true,
											'supports' 				=> Array(	'title',
																				'editor' )
							);
			$this->tax_options = Array( 'public' => true, 'hierarchical' => true );
		}

		function activate() {
			remove_action( 'init', Array( $this, 'add_post_type' ) );
			$this->add_post_type();
		}

		function deactivate() {
			remove_action( 'init', Array( $this, 'add_post_type' ) );
		}

		function admin_menu() {
			$this->add_menu("edit.php?post_type={$this->post_type}");
		}

		function add_post_type() {
			$this->post_options = apply_filters( 'sfwd_cpt_options', $this->post_options, $this->post_type );
			register_post_type( $this->post_type, $this->post_options );
			add_filter( 'sfwd_cpt_register_tax', Array( $this, 'register_tax' ), 10 );
			$flush = is_admin() && $this->post_type == "sfwd-certificates";
           		$learndash_flush_rewrite_rules = apply_filters("learndash_flush_rewrite_rules", $flush, $this->post_options);
			if($learndash_flush_rewrite_rules)
				flush_rewrite_rules(false);
		}
		
		function register_tax( $tax_data ) {
			if ( !is_array( $tax_data ) ) $tax_data = Array();
			if ( is_array( $this->taxonomies ) )
				foreach( $this->taxonomies as $k => $t ) {
					$this->tax_options['label'] = $t;
					$this->tax_options = apply_filters( 'sfwd_cpt_tax', $this->tax_options, $this->post_type, $k );
					if ( empty( $tax_data[$k] ) || !is_array( $tax_data[$k] ) ) $tax_data[$k] = Array();
					$tax_data[$k][] = Array( $this->post_type, $this->tax_options );
				}
			return $tax_data;
		}
		
		static function loop_shortcode( $atts, $content = null ) {
				$args = array(
		        	"pagination"		=> '',
					"posts_per_page"	=> '',
		        	"query"				=> '',
		        	"category"			=> '',
		        	"post_type"			=> '',
		        	"order"				=> '',
		        	"orderby"			=> '',
		        	"meta_key"			=> '',
		        	"meta_value"		=> '',
					"taxonomy"			=> '',
					"tax_field"			=> '',
					"tax_terms"			=> '',
					"topic_list_type"	=> "",
					"return"			=> "text", /* text or array */
		        );

				if ( !empty( $atts ) )
					foreach( $atts as $k => $v )
						if ( $v === '' ) unset( $atts[$k] );
				$filter = shortcode_atts( $args, $atts);
		        extract( shortcode_atts( $args, $atts) );		
		        global $paged;

		        $posts = new WP_Query();
				
		        if( $pagination == 'true' ) $query .= '&paged=' . $paged;
		        if( !empty( $category   ) ) $query .= '&category_name=' . $category;

				foreach ( Array('post_type', 'order', 'orderby', 'meta_key','meta_value', 'query') as $field)
					if ( !empty( $$field ) ) $query .= "&$field=" . $$field;
				
				$query = wp_parse_args( $query, $filter );
				if ( !empty( $taxonomy ) && !empty( $tax_field ) && !empty( $tax_terms ) ) {
					$query['tax_query'] = Array(
						Array( 'taxonomy' => $taxonomy, 'field' => $tax_field, 'terms' => explode(",",$tax_terms) )
					);

				}
				$posts = get_posts($query);
		    	$buf = '';
				$sno = 1;
		        foreach ( $posts as $post ) {
		        			//	$posts->the_post();	// run shortcodes in loop               
		                        $id = $post->ID;              			// allow use of id variable in template 
								$class = '';
								$status = '';
								$sample = '';
								$sub_title = '';
								$ld_lesson_access_from = '';
								if($post->post_type == 'sfwd-quiz')
								{
									$sample = (learndash_is_sample($post))? 'is_sample': 'is_not_sample';
									$id .= ' class="'.$sample.'"';
									$status = (learndash_is_quiz_notcomplete(null, array($post->ID => 1 )))? 'notcompleted':'completed';
								}
								else if($post->post_type == 'sfwd-lessons')
								{
									$sample = (learndash_is_sample($post))? 'is_sample': 'is_not_sample';
									$id .= ' class="'.$sample.'"';									
									
									if(!learndash_is_lesson_notcomplete(null, array($post->ID => 1 ))) {
									$status = 'completed';
									}
									else
									{
										$ld_lesson_access_from = ld_lesson_access_from($post->ID, get_current_user_id());
										if(empty($ld_lesson_access_from))
										$status = 'notcompleted';
										else
										{
											$status = 'notavailable';
											$sub_title = "<small class='notavailable_message'>".sprintf(__(' Available on: %s ', "learndash"), date("d-M-Y", $ld_lesson_access_from))."</small>";
										}
									}
									
									if(empty($sub_title) && !empty($topic_list_type))
									$sub_title .= learndash_topic_dots($post->ID, false, $topic_list_type);
								}
								if(isset($_GET['test']))
								echo "<br>".$post_type.":".$post->post_type.":".$post->ID.":".$status;
							
								if($meta_key != "course_id")
								$show_content = true;
								else
								$show_content = SFWD_CPT::show_content($post);

								if($show_content) {
									if($return == "array") {
				                        $buf[$sno] = array(
				                        		'sno'		=> $sno,
				                        		'post'		=> $post,
				                        		'permalink'	=> get_permalink($post->ID),
				                        		'sub_title'	=> $sub_title,
				                        		'status' 	=> $status,
				                        		'sample'	=> $sample,
				                        		'lesson_access_from'	=> $ld_lesson_access_from
				                        		);
			                        }
									else	
									{
										$show_content = str_replace("{learndash_completed_class}", 'class="'.$status.'"', $content );
										$show_content = str_replace("{the_title}", $post->post_title, $show_content );
										$show_content = str_replace("{the_permalink}", get_permalink($post->ID), $show_content );
										$show_content = str_replace("{sub_title}", $sub_title, $show_content );
										$show_content = str_replace( '$id', "$id", $show_content );
										$show_content = str_replace( '{sno}', $sno, $show_content );
				                        $buf .= do_shortcode ( $show_content );
									}
								}
								if(!empty($show_content)) 
								$sno++;
		        }
		        if ( $pagination == 'true' )
					$buf .= '<div class="navigation">
			          <div class="alignleft">' . get_previous_posts_link('« Previous') . '</div>
			          <div class="alignright">' . get_next_posts_link('More »') . '</div>
			        </div>';
		        wp_reset_query();
		        return $buf;
		}
		static function show_content($post) {
			if($post->post_type == "sfwd-quiz")
			{
				$lesson_id = learndash_get_setting($post, "lesson") ;								
				return empty($lesson_id);
			}
			else
			return true;					
		}
		function shortcode( $atts, $content = null, $code ) {
			extract( shortcode_atts( array(
				'post_type' => $code,
				'posts_per_page' => -1,
				'taxonomy' => '',
				'tax_field' => '',
				'tax_terms' => '',
				'meta_key'	=> '',
				'meta_value'	=> '',
				'order' => 'DESC',
				'orderby' => 'date',
				'wrapper' => 'div',
				'title' => 'h4',
				'topic_list_type' => 'dots',
			), $atts ) );

			global $shortcode_tags;
			$save_tags = $shortcode_tags;

			add_shortcode( 'loop', Array( $this, 'loop_shortcode' ) );
			//add_shortcode( 'the_title', 'get_the_title' );
			//add_shortcode( 'the_permalink', 'get_permalink' );
			//add_shortcode( 'the_excerpt', 'get_the_excerpt' );
			//add_shortcode( 'the_content', 'get_the_content' );		
										
			$template = "[loop post_type='$post_type' posts_per_page='$posts_per_page' meta_key='{$meta_key}' meta_value='{$meta_value}' order='$order' orderby='$orderby' taxonomy='$taxonomy' tax_field='$tax_field' tax_terms='$tax_terms' topic_list_type='".$topic_list_type."']"
								  . "<$wrapper id=post-\$id><$title><a {learndash_completed_class} href='{the_permalink}'>{the_title}</a>{sub_title}</$title>"
								  . "</$wrapper>[/loop]";
			// <div class='entry-content'>[the_content]</div>
			$template = apply_filters( 'sfwd_cpt_template', $template );
			$buf = do_shortcode( $template );

			$shortcode_tags = $save_tags;
			return $buf;
		}
		
		function get_settings_values( $location = null ) {
			$settings = $this->setting_options( $location );
			$values = $this->get_current_options( Array(), $location );
			foreach ( $settings as $k => $v )
				$settings[$k]['value'] = $values[$k];
			return $settings;
		}
		
		function display_settings_values( $location = null ) {
			$meta = $this->get_settings_values( $location );
			if ( !empty( $meta ) ) {
			?>
			<ul class='post-meta'>
			<?php
			foreach ( $meta as $m )
				echo "<li><span class='post-meta-key'>{$m['name']}</span> {$m['value']}</li>\n";
			?>
			</ul>
			<?php
			}
		}
	}
}

/* Adds widget for displaying posts */
if ( !class_exists( 'SFWD_CPT_Widget' ) ) {
	class SFWD_CPT_Widget extends WP_Widget {
		protected $post_type;
		protected $post_name;
		protected $post_args;
		public function __construct( $post_type, $post_name, $args = Array() ) {
			$this->post_type = $post_type;
			$this->post_name = $post_name;
			
			if ( !is_array( $args ) ) $args = Array();
			
			if($post_type == "sfwd-lessons")
			{
				$args['description'] = __( "Displays a list of lessons for a course and tracks lesson progress.", 'learndash' );
			}
			
			if ( empty( $args['description'] ) )
				$args['description'] = sprintf( __( "Displays a list of %s", 'learndash' ), $post_name );
			
			if ( empty( $this->post_args ) )
				$this->post_args = Array( 'post_type' => $this->post_type, 'numberposts' => -1, 'order' => 'DESC', 'orderby' => 'date' );
				
			parent::__construct( "{$post_type}-widget", $post_name, $args );
		}

		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			/* Before Widget content */
			$buf = $before_widget;

			/* Get user defined widget title */
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
			
			if ( !empty( $title ) ) $buf .= $before_title . $title . $after_title;
			$buf .= '<ul>';

			/* Display Widget Data */
			
			if($this->post_type == "sfwd-lessons") {
				$course_id = learndash_get_course_id();
				if(empty($course_id) || !is_single())
					return "";
				$course_lessons_list = $this->course_lessons_list($course_id);	
				$stripped_course_lessons_list = strip_tags($course_lessons_list);
				
				if(empty($stripped_course_lessons_list))
				return "";
				
				$buf .= $course_lessons_list;
			
			} else {
			
			$args = $this->post_args;

			$args['posts_per_page'] = $args['numberposts'];
			$args['wrapper'] = 'li';
			global $shortcode_tags, $post;
			if ( !empty( $shortcode_tags[ $this->post_type ] ) )
				$buf .= call_user_func( $shortcode_tags[ $this->post_type ], $args, null, $this->post_type );
			}
			/* After Widget content */
			$buf .= '</ul>' . $after_widget;
			
			echo $buf;
			
		}
		function course_lessons_list($course_id) {
			$course = get_post($course_id);
			
			if(empty($course->ID) || $course_id != $course->ID)
			return "";
			
			$html = '';
			$course_lesson_orderby = learndash_get_setting($course_id, 'course_lesson_orderby');
			$course_lesson_order = learndash_get_setting($course_id, 'course_lesson_order');
			$lessons = sfwd_lms_get_post_options( 'sfwd-lessons' );							
			$orderby = (empty($course_lesson_orderby))? $lessons['orderby']:$course_lesson_orderby;
			$order = (empty($course_lesson_order))? $lessons['order']:$course_lesson_order;
			$lessons = wptexturize(do_shortcode("[sfwd-lessons meta_key='course_id' meta_value='{$course_id}' order='{$order}' orderby='{$orderby}' posts_per_page='{$lessons['posts_per_page']}' wrapper='li']"));
			$html .= $lessons;
			return $html;
		}
		public function update( $new_instance, $old_instance ) {

			/* Updates widget title value */
			$instance = $old_instance;
			$instance['title'] = strip_tags( $new_instance['title'] );
			return $instance;

		}

		public function form( $instance ) {
			if ( $instance )
				$title = esc_attr( $instance[ 'title' ] );
			else
				$title = $this->post_name;
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'learndash' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" 
			name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
			</p>
			<?php 
		}
	}
}
