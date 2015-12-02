<?php
/**
 * Scripts & Styles
 *
 * @since 2.1.0
 *
 * @package LearnDash\Scripts
 */



/**
 * Styles for front-end
 *
 * @since 2.1.0
 */
function learndash_load_resources() {
	wp_enqueue_style( 'learndash_style', plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ) );
}

add_action( 'wp_enqueue_scripts', 'learndash_load_resources' );



/**
 * Enqueue styles/scripts whenever LearnDash content is being displayed
 *
 * @since 2.1.0
 *
 * @param  string $content post or widget content
 * @return string $content post or widget content
 */
function learndash_the_content_load_resources( $content ) {
	global $post;
	if ( empty( $post->post_type) ) {
		return $content;
	}
	
	if (is_singular('sfwd-courses')) {
		wp_enqueue_script( 'jquery-dropdown-js', LEARNDASH_LMS_PLUGIN_URL . 'assets/js/jquery.dropdown.min.js', array( 'jquery' ) );
		wp_enqueue_style( 'jquery-dropdown-css', LEARNDASH_LMS_PLUGIN_URL . 'assets/css/jquery.dropdown.min.css' );
	}

	wp_enqueue_style( 'sfwd_front_css', plugins_url( 'assets/css/front.css', dirname( __FILE__ ) ) );
	$filepath = locate_template( array( 'learndash/learndash_template_style.css' ) );

	if ( $filepath && file_exists( $filepath ) ) {
		wp_enqueue_style( 'sfwd_template_css', get_stylesheet_directory_uri().'/learndash/learndash_template_style.css' );
	} else {

		$filepath = locate_template( 'learndash_template_style.css' );
		if ( $filepath &&  file_exists( $filepath ) ) {
			wp_enqueue_style( 'sfwd_template_css', get_stylesheet_directory_uri().'/learndash_template_style.css' );
		} else if ( file_exists( dirname( dirname( __FILE__ ) ) .'/templates/learndash_template_style.css' ) ) {
			wp_enqueue_style( 'sfwd_template_css', plugins_url( 'templates/learndash_template_style.css', dirname( __FILE__ ) ) );
		}

	}

	$filepath = locate_template( array( 'learndash/learndash_template_script.js') );

	if ( $filepath && file_exists( $filepath ) ) {
		wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri().'/learndash/learndash_template_script.js', array( 'jquery' ) );
	} else	{
		$filepath = locate_template( 'learndash_template_script.js' );

		if ( $filepath &&  file_exists( $filepath ) ) {
			wp_enqueue_script( 'sfwd_template_js', get_stylesheet_directory_uri().'/learndash_template_script.js', array( 'jquery' ) );
		} else if ( file_exists( dirname( dirname( __FILE__ ) ) .'/templates/learndash_template_script.js' ) ) {
			wp_enqueue_script( 'sfwd_template_js', plugins_url( 'templates/learndash_template_script.js', dirname( __FILE__ ) ), array( 'jquery' ) );
		}
	}

	return $content;
}

add_filter( 'the_content', 'learndash_the_content_load_resources' );
add_filter( 'widget_text', 'learndash_the_content_load_resources' );