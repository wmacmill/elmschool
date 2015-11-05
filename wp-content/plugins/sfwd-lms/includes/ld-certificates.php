<?php
/**
 * Certificate functions
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Certificates
 */



/**
 * Get certificate details
 *
 * Return a link to certificate and certificate threshold
 *
 * @since 2.1.0
 * 
 * @param  int  	$post_id
 * @param  int  	$user_id
 * @return array    certificate details
 */
function learndash_certificate_details( $post_id, $user_id = null ) {
	$user_id = ! empty( $user_id ) ? $user_id : get_current_user_id();

	$certificateLink = '';
	$post = get_post( $post_id );
	$meta = get_post_meta( $post_id, '_sfwd-quiz' );
	$cert_post = '';
	$certificate_threshold = '0.8';

	if ( is_array( $meta ) && ! empty( $meta ) ) {
		$meta = $meta[0];

		if ( is_array( $meta ) && ( ! empty( $meta['sfwd-quiz_certificate'] ) ) ) {
			$certificate_post = $meta['sfwd-quiz_certificate'];
		}

		if ( is_array( $meta ) && ( ! empty( $meta['sfwd-quiz_threshold'] ) ) ) {
			$certificate_threshold = $meta['sfwd-quiz_threshold'];
		}
	}

	if ( ! empty( $certificate_post ) ) {
		$certificateLink = get_permalink( $certificate_post );
	}

	if ( ! empty( $certificateLink ) ) {
		$certificateLink .= ( strpos( 'a'.$certificateLink,'?' ) ) ? '&' : '?';
		$certificateLink .= "quiz={$post->ID}&print=" . wp_create_nonce( $post->ID . $user_id );
	}

	return array( 'certificateLink' => $certificateLink, 'certificate_threshold' => $certificate_threshold );
}



/**
 * Shortcode to output course certificate link
 *
 * @since 2.1.0
 * 
 * @param  array 	$atts 	shortcode attributes
 * @return string       	output of shortcode
 */
function ld_course_certificate_shortcode( $atts ) {
	$course_id = @$atts['course_id'];

	if ( empty( $course_id ) ) {
		$course_id = learndash_get_course_id();
	}

	$user_id = get_current_user_id();
	$link = learndash_get_course_certificate_link( $course_id, $user_id );

	if ( empty( $link ) ) {
		return '';
	}

	/**
	 * Filter output of shortcode
	 * 
	 * @since 2.1.0
	 *
	 * @param  string  markout of course certificate short code
	 */
	return apply_filters( 'ld_course_certificate', "<div id='learndash_course_certificate'><a href='".$link."' class='btn-blue' target='_blank'>".__( 'PRINT YOUR CERTIFICATE!', 'learndash' ).'</a></div>', $link, $course_id, $user_id );
}

add_shortcode( 'ld_course_certificate', 'ld_course_certificate_shortcode' );



/**
 * Get course certificate link for user
 *
 * @since 2.1.0
 * 
 * @param  int 		 $course_id
 * @param  int 		 $user_id
 * @return string
 */
function learndash_get_course_certificate_link( $course_id, $user_id = null ) {
	$user_id = get_current_user_id();
	if ( empty( $course_id ) || empty( $user_id ) || ! sfwd_lms_has_access( $course_id, $user_id ) ) {
		return '';
	}

	$certificate_id = learndash_get_setting( $course_id, 'certificate' );

	if ( empty( $certificate_id ) ) {
		return '';
	}

	$course_status = learndash_course_status( $course_id, $user_id );

	if ( $course_status != __( 'Completed', 'learndash' ) ) {
		return '';
	}

	$url = get_permalink( $certificate_id );
	$url = ( strpos( '?', $url ) === false ) ? $url.'?' : $url.'&';
	$url = $url.'course_id='.$course_id.'&user_id='.$user_id;

	return $url;
}



/**
 * Get certificate link if certificate exists and quizzes are completed
 *
 * @todo  consider for deprecation, not being used in plugin
 *
 * @since 2.1.0
 * 
 * @param  int 		 $quiz_id
 * @param  int 		 $user_id
 * @return string
 */
function learndash_get_certificate_link( $quiz_id, $user_id = null ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( empty( $user_id ) || empty( $quiz_id ) ) {
		return '';
	}

	$c = learndash_certificate_details( $quiz_id, $user_id );

	if ( empty( $c['certificateLink'] ) ) {
		return '';
	}

	$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
	$usermeta = maybe_unserialize( $usermeta );

	if ( ! is_array( $usermeta ) ) { 
		$usermeta = array();
	}

	foreach ( $usermeta as $quizdata ) {
		if ( ! empty( $quizdata['quiz'] ) && $quizdata['quiz'] == $quiz_id ) {
			if ( $c['certificate_threshold'] <= $quizdata['percentage'] / 100 ) {
				return '<a target="_blank" href="'.$c['certificateLink'].'">'.__( 'PRINT YOUR CERTIFICATE!', 'learndash' ).'</a>';
			}
		}
	}

	return '';
}



/**
 * Show text tab by default on certificate edit screen
 * User should not be able to use visual editor tab
 *
 * @since 2.1.0
 * 
 * @param  array $return 	An array of editors. Accepts 'tinymce', 'html', 'test'.
 * @return array $return 	html
 */
function learndash_disable_editor_on_certificate( $return ) {
	global $post;

	if ( is_admin() && ! empty( $post->post_type ) && $post->post_type == 'sfwd-certificates' ) {
		return 'html';
	}

	return $return;
}

add_filter( 'wp_default_editor', 'learndash_disable_editor_on_certificate',1, 1 );



/**
 * Disable being able to click the visual editor on certificates
 * User should not be able to use visual editor tab
 *
 * @since 2.1.0
 */
function learndash_disable_editor_on_certificate_js() {
	global $post;
	if ( is_admin() && ! empty( $post->post_type) && $post->post_type == 'sfwd-certificates' ) {
		?>
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

add_filter( 'admin_footer', 'learndash_disable_editor_on_certificate_js', 99 );