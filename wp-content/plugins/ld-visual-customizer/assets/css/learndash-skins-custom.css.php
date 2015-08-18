<?php
/*** For getting function to work ******/

require_once('../../../../../wp-config.php'); 
 

/*********/ 

header("Content-type: text/css; charset: UTF-8");

/*** Getting styles from DB ******/

$lds_heading_bg = get_option('lds_heading_bg');
$lds_heading_txt = get_option('lds_heading_txt');
$lds_row_bg = get_option('lds_row_bg');
$lds_row_bg_alt = get_option('lds_row_bg_alt');
$lds_row_txt = get_option('lds_row_txt');

$lds_sub_row_bg = get_option('lds_sub_row_bg');
$lds_sub_row_bg_alt = get_option('lds_sub_row_bg_alt');
$lds_sub_row_txt = get_option('lds_sub_row_txt');

$lds_button_bg = get_option('lds_button_bg');
$lds_button_txt = get_option('lds_button_txt');
$lds_complete_button_bg = get_option('lds_complete_button_bg');
$lds_complete_button_txt = get_option('lds_complete_button_txt');

$lds_progress = get_option('lds_progress');
$lds_links = get_option('lds_links');
$lds_complete = get_option('lds_complete');

$lds_widget_bg = get_option('lds_widget_bg');
$lds_widget_header_bg = get_option('lds_widget_header_bg');
$lds_widget_header_txt = get_option('lds_widget_header_txt');
$lds_widget_txt = get_option('lds_widget_txt');

$lds_checkbox_incomplete = get_option('lds_checkbox_incomplete');
$lds_checkbox_complete = get_option('lds_checkbox_complete');

$lds_arrow_incomplete = get_option('lds_arrow_incomplete');
$lds_arrow_complete = get_option('lds_arrow_complete');

$lds_open_css = get_option('lds_open_css');

 ?>

/* 
 * Table Heading Backgrounds 
 *
 */

#lesson_heading,
#quiz_heading,
#learndash_lessons #lesson_heading,
#learndash_profile .learndash_profile_heading,
#learndash_quizzes #quiz_heading,
#learndash_lesson_topics_list div > strong {
	background-color:<?php echo $lds_heading_bg; ?> !important;
}

/* 
 * Heading Text
 * 
 */ 

#lesson_heading span,
#quiz_heading span,
#learndash_lesson_topics_list div > strong,
.learndash_profile_heading span,
.learndash_profile_heading {
	color: <?php echo $lds_heading_txt; ?> !important ;	
}

/* 
 * Table Cells
 *
 */
#learndash_profile .profile_info,
#lessons_list > div,
#quiz_list > div,
#learndash_profile .course_progress,
#learndash_profile #course_list > div,
#learndash_lesson_topics_list ul > li {
    background: <?php echo $lds_row_bg; ?>;
	color: <?php echo $lds_row_txt; ?>
}

#lessons_list > div:nth-child(odd),
#quiz_list > div:nth-child(odd),
#learndash_lesson_topics_list ul > li.nth-of-type-odd 
{
    background: <?php echo $lds_row_bg_alt; ?>;
	color: <?php echo $lds_row_txt; ?>
}

#lessons_list h4 a,
#quiz_list h4 a {
    color: <?php echo $lds_row_txt; ?>
}

/* 
 * Buttons 
 * 
 */ 

/* Complete Button */ 

#sfwd-mark-complete input.button,
#sfwd-mark-complete input[type="submit"],
#learndash_mark_complete_button {
	background-color: <?php echo $lds_complete_button_bg; ?> !important;
	color: <?php echo $lds_complete_button_txt; ?> !important;
}

/* Standard Button */

.btn-join, #btn-join, a#quiz_continue_link, .wpProQuiz_button { 
	background-color: <?php echo $lds_button_bg; ?> !important;
	color: <?php echo $lds_complete_button_txt; ?> !important;
}


/*
 * Visual Elements
 * 
 */ 

dd.course_progress div.course_progress_blue {
	background-color: <?php echo $lds_progress; ?> !important;
}


/* 
 * Links
 *
 */

#learndash_profile .profile_info a, #learndash_profile #course_list a, #learndash_profile #course_list a span,
#learndash_lessons a, #learndash_quizzes a, .learndash_topic_dots a, .learndash_topic_dots a > span, #learndash_lesson_topics_list span a {
	color: <?php echo $lds_links; ?> !important;
}


/* 
 * Widgets
 *
 */

.widget_ldcourseprogress,
#sfwd-certificates-widget-2,
#sfwd-courses-widget-2,
#ldcourseinfo-2,
.widget_sfwd-lessons-widget,
.widget_ldcoursenavigation {
	background-color: <?php echo $lds_widget_bg; ?> !important;
	color: <?php echo $lds_widget_txt; ?> !important;
}

#learndash_course_content .learndash_topic_dots ul > li:nth-of-type(2n+1) { 
	background: <?php echo $lds_sub_row_bg; ?>;
}

#learndash_course_content .learndash_topic_dots ul > li {
	background: <?php echo $lds_sub_row_bg_alt; ?>;
}

#learndash_course_content .learndash_topic_dots ul > li a span { 
	color: <?php echo $lds_sub_row_txt; ?> !important;
}

#learndash_course_content .learndash_topic_dots ul > li:hover { 
	background: <?php echo $lds_sub_row_bg; ?>
}

#learndash_course_content .learndash_topic_dots ul > li:nth-of-type(2n+1) { 
	background: <?php echo $lds_sub_row_bg_alt; ?>;
}

.widget_ldcourseprogress .widget-title,
.widget_sfwd-lessons-widget .widget-title,
.widget_ldcoursenavigation .widget-title {
	background-color: <?php echo $lds_widget_header_bg; ?> !important;
}

.widget_ldcourseprogress .widget-title,
.widget_sfwd-lessons-widget .widget-title,
.widget_ldcoursenavigation .widget-title {
	color: <?php echo $lds_widget_header_txt; ?> !important;
}

#course_navigation a,
.widget_sfwd-lessons-widget ul li a { 
	color: <?php echo $lds_links; ?> !important;
}

.learndash_profile_quizzes .passed_icon:before,
.learndash .completed:after,
#learndash_profile .completed:after,
.learndash .topic-completed span:after,
.learndash_nevigation_lesson_topics_list .list_arrow.collapse.lesson_completed:before,
.learndash a.completed::after, #learndash_profile a.completed:after,
#learndash_profile .list_arrow.collapse:before,
#learndash_profile .list_arrow.expand:before
 {
	color: <?php echo $lds_checkbox_complete; ?>
}

.list_arrow.expand.lesson_completed:before,
.learndash_nevigation_lesson_topics_list .topic-completed:before,
.list_arrow.expand.lesson_completed:before {
	color: <?php echo $lds_arrow_complete; ?>
}


.learndash .notcompleted:after,
#learndash_profile .notcompleted:after,
{ 
	color: <?php echo $lds_checkbox_incomplete; ?>
}

.learndash_nevigation_lesson_topics_list .list_arrow.collapse:before,
.lesson_incomplete.list_arrow.expand::before
{
	color: <?php echo $lds_arrow_incomplete; ?>
}

/*
 .IconColor{color:<?php echo $IconColor; ?>;}
 .CompleteColor{color:<?php echo $CompleteColor; ?>;}
*/

#learndash_profile .profile_info .profile_avatar img {
	border-color: <?php echo $lds_button_bg; ?>
}

<?php echo $lds_open_css; ?>


