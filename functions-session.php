<?php

/*
 * Session functions for tracking logged-in or anonymous users during quiz.
 *
 *
 */

add_action( 'template_redirect', 'wpskillz_session_start' );

function wpskillz_session_start() {
	if ( !is_singular( 'quiz' ) )
		return;

	session_start();
}

function wpskillz_user_progress() {
	global $current_user;

	if ( is_user_logged_in() )
		$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
	else 
		$progress = $_SESSION['wpskillz_test'];

	$complete = ( $progress ) ? count( $progress ) : 0;
	$questions = wp_count_posts( 'quiz' )->publish;

	return array( 
		'complete' => $complete,
		'oftotal' => $questions,
		'current' => $complete + 1,
		'percent' => $complete / $questions,
		'progress' => $progress
	);
}

/*
 * Pick a next question at random.
 *
 * @return	bool|url	false if user has completed all the questions,
 * 						permlink for next question otherwise
 */
function wpskillz_next_question() {
	global $current_user;

	if ( is_user_logged_in() )
		$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
	else 
		$progress = $_SESSION['wpskillz_test'];

	$questions_done = array_keys( $progress );

	$next_question_array = get_posts(
		array(
			'numberposts' => 1,
			'post_type' => 'quiz',
			'exclude' => array_filter( $questions_done ),
			'order' => 'rand'
		)
	);

	if ( !count( $next_question_array ) )
		return false;

	$next_question = array_pop($next_question_array);
		
	return get_permalink( $next_question->ID );

}

/*
 * Echo or return a formatted HTML element with the link to the next question.
 * Should do nothing if the user has already completed all the questions.
 *
 * @uses	wpskillz_next_question()
 *
 * @return	str|null	<a> element with href of the next question, or null if 
 * 						all questions have already been completed.
 */
function wpskillz_next_question_link( $echo = true ) {
	if ( $link = wpskillz_next_question() ) {
		$link_text = '<a href="'.$link.'" title="Next question">Next question</a>';
	} else {
		$link_text = 'You have completed all the available questions.';
	}
	if ( $echo )
		echo $link_text;
	else
		return $link_text;
}

add_action( 'wp_ajax_wpskillz_answer', 'wpskillz_ajax_handle_answer' );
add_action( 'wp_ajax_nopriv_wpskillz_answer', 'wpskillz_ajax_handle_answer' );

function wpskillz_ajax_handle_answer() {
	if ( !defined( 'DOING_AJAX' ) )
		return false;

	$q = (int)$_REQUEST['question']; 
	$a = $_REQUEST['guess'];

	$response['answer_section_text'] = wpskillz_render_answer_mark( $q, $a );

	echo json_encode ( $response );

	die(-1);

}

/*
 * Check if answer is correct or not; mark answer and return an array containing 
 * 		[mark]				bool	Correct/incorrect
 * 		[correct_answer]	str		The correct answer
 *
 * Can be called through Ajax or directly.
 *
 * @param	int		Question number
 * @param	unknown	Answer provided
 *
 * @return	array 	See keys listed above
 */
function wpskillz_handle_answer( $question, $answer ) {
	global $current_user;

	if ( is_user_logged_in() )
		$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
	else 
		$progress = $_SESSION['wpskillz_test'];

	if ( empty( $progress ) )
		$progress = array();

	$answers = get_post_meta( $question, 'answers', true );

	$correct_answer = array_filter( 
		$answers,
		create_function( '$a', 'return $a["is_correct"];' )
	);

	$is_answer_correct = ( $answer == $correct_answer[0]['answer_id'] );

	$progress[$question] = array(
		'answer_given' => $answer,
		'correct' => $is_answer_correct,
		'date' => current_time( 'mysql' )
	);

	// Update progress session variable and user meta
	$_SESSION['wpskillz_test'] = $progress;
	if ( is_user_logged_in() )
		update_user_meta( $current_user->ID, 'wpskillz_test', $progress );

	return array(
		'mark'	=> $is_answer_correct,
		'correct_answer' => $correct_answer
	);
}

