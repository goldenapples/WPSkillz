<?php

/*
 * Session functions for tracking logged-in or anonymous users during quiz.
 *
 *
 */

add_action( 'init', 'wpskillz_session_start' );

function wpskillz_session_start() {
	if ( is_admin() )
		return;

	$session = new WPSkillz_Session;

}

/**
 * undocumented class
 *
 * @packaged default
 * @author goldenapples
 **/
class WPSkillz_Session {

	public $complete;
	public $correct;
	public $oftotal;
	public $current;
	public $progress;

	function __construct() {
		session_start();
		global $current_user;

		if ( is_user_logged_in() )
			$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
		else 
			$progress = $_SESSION['wpskillz_test'];

		$complete = ( $progress ) ? count( $progress ) : 0;
		$questions = wp_count_posts( 'quiz' )->publish;

		$correct_answers = array_filter(
			(array)$progress,
			create_function( '$a', 'return ( isset( $a["correct"] ) && $a["correct"] );' ) 
		);

		$current_question = get_queried_object_id();

		$this->complete = $complete,
		$this->correct = count( $correct_answers ),
		$this->oftotal = $questions,
		$this->current = ( in_array( $current_question, array_keys( $progress ) ) ) ? $complete : $complete + 1,
		$this->percent = $complete / $questions,
		$this->progress = $progress
	}

	/*
	 * Pick a next question at random.
	 *
	 * @return	bool|url	false if user has completed all the questions,
	 * 						permlink for next question otherwise
	 */
	function next_question() {

		$questions_done = array_keys( $this->progress );

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
	 * @uses	next_question()
	 *
	 * @param	bool	True to echo link, false to return formatted string
	 * @param	string	Text of link (defaults to "Next question")
	 *
	 * @return	str|null	<a> element with href of the next question, or null if 
	 * 						all questions have already been completed.
	 */
	function next_question_link( $echo = true, $text = '' ) {
		$link = $this->next_question();
		if ( $link ) {
			if ( empty( $text ) )
				$text = __( 'Next question', 'wpskillz' );
			$link_text = '<a href="'.$link.'" title="'.esc_attr($text).'">'.$text.'</a>';
		} else {
			$link_text = __( 'You have completed all the available questions.', 'wpskillz' );
		}

		if ( $echo )
			echo $link_text;
		else
			return $link_text;
	}


} // end class



/*
 * Ajax handlers to check user-submitted answer and return correct answer section.
 *
 * @uses	wpskillz_render_answer_mark()	Format response, which will be used to replace
 * 											the possible answers list with the correct answer
 * 											highlighted.
 * @uses	
 *
 */
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
