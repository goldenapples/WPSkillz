<?php
/*
 * Display filters for quizzes
 *
 * If single-quiz.php template is present, will use that. Otherwise defaults to single.php
 * display and uses these filters to fudge the correct display.
 *
 */

add_filter( 'the_title', 'wpskillz_hide_title' );

function wpskillz_hide_title( $title ) {
	global $post;
	if ( $post->post_type == 'quiz' )
		$title = '';
	return $title;
}

/**
 * By default, filters the_content to add the vote box above the content 
 * (the link description). If you would like to add the vote box in a different 
 * location, you can remove this filter and include the template tag
 * reclinks_votebox() in your theme files.
 *
 */
add_filter( 'the_content', 'wpskillz_show_quiz_answers' );

function wpskillz_show_votelinks( $content ) {
	if ( is_admin() )
		return $content;

	global $post;
	if ( $post->post_type !== 'reclink' )
		return $content;

	$content = reclinks_votebox( false ) . $content;
	return $content;
}
