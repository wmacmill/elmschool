<?php

add_action("init", "learndash_mce_init");

function learndash_mce_init() {
	global $post;
	if(empty($post->ID) && isset($_GET['post']))
	{
		$post = get_post($_GET['post']);
	}
	if(is_admin() && !empty($post->ID) && @$post->post_type == "sfwd-certificates") {
		add_filter( 'tiny_mce_before_init', 'wpLD_tiny_mce_before_init' );	
		add_filter( 'mce_css', 'filter_mce_css' );
	}
}

function filter_mce_css( $mce_css ) {
		$mce_css = plugins_url( 'assets/sfwd_editor.css', __FILE__ );
		return $mce_css;
	}

function wpLD_tiny_mce_before_init( $initArray )
{	
	if(isset($_GET['post']))	$post_id = $_GET['post'];
	else $post_id = get_the_id();
	
	$img_path = learndash_get_thumb_url($post_id);
    $initArray['setup'] = <<<JS
[function(ed) {
    ed.onInit.add(function(ed, e) {
		var w = jQuery("#content_ifr").width();
		var editorId = ed.getParam("fullscreen_editor_id") || ed.id;
		jQuery("#content_ifr").contents().find("#tinymce").css
		({"background-image":"url($img_path)"
		});
		
		if(editorId == 'wp_mce_fullscreen'){
		jQuery("#wp_mce_fullscreen_ifr").contents().find("#tinymce").css
		({"background-image":"url($img_path)"
		});
		}
    });

}][0]
JS;
    return $initArray;
}

function learndash_get_thumb_url($post_id){
	$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
	 if ($thumbnail_id){
		$img_path = get_post_meta( $thumbnail_id, '_wp_attached_file', true );
		$upload_url = wp_upload_dir();
		$img_full_path = $upload_url['baseurl'].'/'.$img_path;
		return $img_full_path;
	 }
}

?>