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
	if ( $post->post_type !== 'quiz' )
		return $title;
	$progress = wpskillz_user_progress();

	$title = 'Question '.$progress['current'].'/'.$progress['oftotal']. ' ('.number_format( $progress['percent']*100, 0 ).'% complete)';
	return $title;
}

/**
 * By default, filters the_content t
 *
 */
add_filter( 'the_content', 'wpskillz_show_quiz_answers' );

function wpskillz_show_quiz_answers( $content ) {
	if ( is_admin() )
		return $content;

	global $post;
	if ( $post->post_type !== 'quiz' )
		return $content;

	global $current_user;

	if ( is_user_logged_in() )
		$progress = get_user_meta( $current_user->user_id, 'wpskillz_test', true );
	else 
		$progress = $_SESSION['wpskillz_test'];

	if ( $answers = get_post_meta( $post->ID, 'answers', true ) ) {
		$content .= '<div id="wpskillz-quiz-answers"><ol>';
		foreach ( $answers as $answer ) {
			$non_ajax_url = add_query_arg( 'guess', $answer['answer_id'], get_permalink() );
			$content .= <<<EOF
		<li>
			<a name="{$answer['answer_id']}" data-answer="{$answer['answer_id']}" class="answer-text" href="{$non_ajax_url}">
				{$answer['answer_text']}
			</a>
		</li>
EOF;
		}
		$content .= '</ol></div>';

	global $current_user;
		$content .= print_r( get_user_meta( $current_user->user_id, 'wpskillz_test', true ), true );
	}
	
	return $content;
}

/*
 * Render the answers with the correct answer marked. Can be called through Ajax, in which case
 * it will return the HTML content to be loaded into div#wpskillz-quiz-answers, or it can be called
 * directly, in which case it can be used to echo the content.
 *
 * @uses	wpskillz_handle_answer()	Called to check whether the answer given is correct, and update
 * 											usermeta and session with progress variables
 *
 * @param	int			question ID
 * @param	unknown		answer provided
 * @param	bool		whether to echo (true) or return (false) response
 *
 */
function wpskillz_render_answer_mark( $question, $answer, $echo = false ) {
	$correct_answer = wpskillz_handle_answer();

	$response = ( $correct_answer['mark'] ) ? 
		'<p class="correct">Correct!</p>' :
		'<p class="incorrect">Sorry, that is the wrong answer.</p>';

	$answers = get_post_meta( $question, 'answers', true );

	$response .= '<ol>' . "\r\n\t";

	foreach ( $answers as $this_answer ) {
		$answer_classes = array( 'answer-list' );
		if ( $answer == $this_answer['answer_id'] )
			$answer_classes[] = 'chosen';
		if ( $this_answer['is_correct'] )
			$answer_classes[] = 'correct';
		$response .= '<li class="' . implode( ' ', $answer_classes ) . '">';
		$response .= $this_answer['answer_text'];
		$response .= '</li>';
	}

	$response .= '</ol>';

	$response .= wpskillz_next_question_link( false );

	if ( $echo )
		echo $response;
	else
		return $response;

}

add_action( 'wp_enqueue_scripts', 'wpskillz_enqueue_scripts' );

function wpskillz_enqueue_scripts() {
	if ( !is_singular( 'quiz' ) )
		return;

	wp_enqueue_script( 'wpskillz-frontend', plugins_url( '/js/wpskillz-frontend.quiz.js', __FILE__ ), array( 'jquery' ) );
	wp_localize_script( 'wpskillz-frontend', 'wpSkillz',
		array( 
			'ajaxURL' => admin_url( 'admin-ajax.php' ),
			'thisQuestion' => get_queried_object_id(),
	   		'sessionID' => session_id()
		) );

}

