<?php

/**
 * Session functions for tracking logged-in or anonymous users during quiz.
 *
 * Defines the WPSkillz_Session class, which is instantiated on `init`, and is
 * responsible for maintaining a record of the current user's progress through
 * the test.
 *
 * Session data is kept in two places, in a user meta field called 'wpskillz-test'
 * and in $_SESSION['wpskillz-test']. This is for the purpose of maintaining test
 * status when a user begins a test while not logged in, and then registers or logs 
 * in to save her progress.
 *
 * @package	wpskillz
 * @author	goldenapples
 *
 */

add_action( 'init', 'wpskillz_session_start' );

function wpskillz_session_start() {
	if ( is_admin() )
		return;

	global $wpskillz_session;
	$wpskillz_session = new WPSkillz_Session;
}

/**
 * The WPSkillz_Session class.
 *
 * Instantiated on init, and accessible through the global variable $wpskillz_session
 *
 * @package wpskillz
 * @author 	goldenapples
 **/
class WPSkillz_Session {

	/**
	 * The number of questions answered in the current session
	 *
	 * @var int
	 **/
	var $complete;

	/**
	 * The number of questions answered correctly in the current session
	 *
	 * @var int
	 **/
	var $correct;

	/**
	 * Total number of questions available in test
	 *
	 * @var int
	 **/
	var $oftotal;

	/**
	 * Current question number 
	 * (should be $complete + 1 if currently viewing a question; $complete otherwise)
	 *
	 * @var int
	 **/
	var $current;

	/**
	 * All data about questions answered in the current session.
	 *
	 * In the form of an array with the question numbers as keys, and an array including the
	 * answer given, the time answered, the correct answer, and a correct/incorrect mark as values.
	 *
	 * @var array
	 **/
	var $progress;

	/**
	 * Initialize all the class variables on session start.
	 *
	 * @return void
	 */
	function __construct() {

		session_start();
		global $current_user;

		if ( is_user_logged_in() )
			$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
		else 
			$progress = ( isset( $_SESSION['wpskillz_test'] ) ) ? $_SESSION['wpskillz_test'] : false;

		$complete = ( $progress ) ? count( $progress ) : 0;
		$questions = wp_count_posts( 'quiz' )->publish;

		$correct_answers = array_filter(
			(array)$progress,
			create_function( '$a', 'return ( isset( $a["correct"] ) && $a["correct"] );' ) 
		);

		$current_question = get_queried_object_id();

		$this->complete = $complete;
		$this->correct = count( $correct_answers );
		$this->oftotal = $questions;
		$this->current = ( is_array( $progress ) && in_array( $current_question, array_keys( $progress ) ) ) ? $complete : $complete + 1;
		$this->percent = $complete / $questions;
		$this->progress = $progress;

		add_shortcode( 'start-quiz', array( &$this, 'start_quiz_content' ) );
	}

	/**
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

	/**
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


	/*
	 * TODO: write block for here
	 *
	 * @uses	WPSkillz_Session::next_question_link()	Format the link to start the test
	 *
	 */
	function start_quiz_content( $args, $content = null ) {
		return $this->next_question_link( false, __( 'Start the test now!', 'wpskillz' ) );
	}
} 

add_action( 'wp_ajax_wpskillz_answer', 'wpskillz_ajax_handle_answer' );
add_action( 'wp_ajax_nopriv_wpskillz_answer', 'wpskillz_ajax_handle_answer' );

/**
 * Ajax handlers to check user-submitted answer and return correct answer section.
 *
 * @uses	wpskillz_render_answer_mark()	Format response, which will be used to replace
 * 											the possible answers list with the correct answer
 * 											highlighted.
 * @uses	
 *
 */
function wpskillz_ajax_handle_answer() {
	if ( !defined( 'DOING_AJAX' ) )
		return false;

	$post = get_post( intval( $_REQUEST['question'] ) ); 
	$a = $_REQUEST['guess'];

	if ( !$post || $post->post_type !== 'quiz' || !$a )
		return false;

	global $question_post;
	if ( !isset( $question_post ) ) {
		$question_type = get_post_meta( $post->ID, '_question_type', true );
		$question_type_class = "WPSkillz_Question_{$question_type}";
		if ( $question_type && class_exists( $question_type_class ) )
			$question_post = new $$question_type_class( $post );
		else 
			$question_post = new WPSkillz_Question( $post );
	}

	$response['answer_section_text'] = $question_post->render_answer_mark( $q, $a );

	echo json_encode ( $response );

	die(-1);

}



add_shortcode( 'leaderboards', 'wpskillz_leaderboards' );

function wpskillz_leaderboards( $args, $content = null ) {
	$args = shortcode_atts( $args, array( 'leaders' => 10 ) );

}
