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

function wpskillz_session_start( $new_login = false ) {
	global $wpskillz_session;
	$wpskillz_session = new WPSkillz_Session( $new_login );

}

add_action( 'wp_login', 'wpskillz_merge_session_progress', 10, 2 );

function wpskillz_merge_session_progress( $user_login, $user ) {
	global $wpskillz_session;
	if ( !isset( $wpskillz_session ) )
		wpskillz_session_start( true );
	$wpskillz_session->login( $user_login, $user );
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
	 * @param	bool	new_login	Whether a new user is being logged in
	 *
	 * @return void
	 */
	function __construct( $new_login ) {

		session_start();
		global $current_user;

		if ( $new_login ) 
			$progress = $this->login();
		else if ( is_user_logged_in() )
			$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
		else
			$progress = ( isset( $_SESSION['wpskillz_test'] ) ) ? maybe_unserialize( $_SESSION['wpskillz_test'] ) : false;

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
	 * Update session variables after a question is answered
	 *
	 * @return	void
	 */
	function update_progress( $question_results ) {

		if ( !is_array( $question_results ) )
			$question_results = array();

		if ( !is_array( $this->progress ) )
			$this->progress = array();

		$this->progress = $this->progress + $question_results;

		// Update progress session variable and user meta
		$_SESSION['wpskillz_test'] = maybe_serialize( $this->progress );
		if ( is_user_logged_in() ) {
			global $current_user;
			update_user_meta( $current_user->ID, 'wpskillz_test', $this->progress );
		}
	}

	/**
	 * Pick a next question at random.
	 *
	 * @return	url|bool	permlink for next question if there are more questions in the test;
	 * 						otherwise false if user has completed all the questions,
	 * 						
	 */
	function next_question() {

		$questions_done = ( $this->progress ) ? array_keys( $this->progress ) : array();

		$next_question_array = get_posts(
			array(
				'numberposts' => 1,
				'post_type' => 'quiz',
				'exclude' => array_filter( $questions_done ),
				'orderby' => 'rand'
			)
		);

		if ( !count( $next_question_array ) )
			return false;

		$next_question = array_pop( $next_question_array );
			
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
			$link_text = '<p><a class="next-question" href="'.$link.'" title="'.esc_attr($text).'">'.$text.'</a></p>';
		} else {
			$link_text = '
				<div class="login-box">
					<p>' . __( 'You have completed all the available questions.', 'wpskillz' ) . '</p>
					<p>' . __( 'See how you stack up against other test-takers!', 'wpskillz' ) . ' &nbsp;' .
					'<a href="leaderboards">' . __( 'View leaderboards', 'wpskillz' ) .'</a></p>
				</div>';
		}

		if ( $echo )
			echo $link_text;
		else
			return $link_text;
	}


	/**
	 * Shortcode handler for the [start-quiz] shortcode
	 *
	 * Returns an HTML formatted link to a random question to start the quiz.
	 *
	 * @uses	WPSkillz_Session::next_question_link()	Format the link to start the test
	 *
	 * @return	str		link to a random question
	 */
	function start_quiz_content( $atts ) {
		return $this->next_question_link( false, __( 'Start the test now!', 'wpskillz' ) );
	}

	/**
	 * Logs user in and update user meta with any progress made while logged out
	 *
	 * When user is logging in, merge their current progress as tracked in $_SESSION 
	 * with saved progress in user meta. If any keys exist in both arrays (user took
	 * a question while logged out that they had already received a mark for while 
	 * logged in) the earlier mark should be preserved.
	 *
	 * @param	str		user_login
	 * @param	object	WP_User object (passed by reference in 'wp_login' action)
	 * @return	array 	progress array
	 *
	 */
	function login( $user_login, $user ) {

		$user_saved_progress = (array)get_user_meta( $user->ID, 'wpskillz_test', true );
		$anonymous_progress = maybe_unserialize( $_SESSION['wpskillz_test'] );
		if ( !$anonymous_progress )
			$anonymous_progress = array();

		$this->progress = $user_saved_progress + $anonymous_progress;
		$this->update_progress( null );

		return $this->progress;
	}

	/**
	 * Returns or echoes a formatted div containing login / register links
	 *
	 * @param	bool	$echo	Whether to echo (true) or return (false)
	 * @return	str		the formatted html string, or nothing in the case of
	 * 					$echo=true
	 */
	function login_invitation( $echo = false ) {
		if ( is_user_logged_in() )
			return;

		$login_link = wp_login_url( $this->next_question() );

		$login_link_text = sprintf( __('<a href="%s">Login now</a> ', 'wpskillz' ), $login_link );

		if ( get_option( 'users_can_register' ) )
			$registration_link_text = sprintf( 
				__( 'or <a href="%s">register for an account</a>', 'wpskillz' ), 
				add_query_arg( 'action', 'register', $login_link ) 
			);

		$login_box = '
			<div class="login-box">
				<p>' . __( 'You are not logged in, and your test results will not be saved.', 'wpskillz' ) .'</p>
				<p>' . __( 'Show off your score!', 'wpskillz' ) . ' ' .
				$login_link_text . $registration_link_text . 
				__( 'and get your name on our leaderboards.', 'wpskillz' ) . '</p>
			</div>';

		if ( $echo )
			echo $login_box;
		else
			return $login_box;

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
 *
 */
function wpskillz_ajax_handle_answer() {
	if ( !defined( 'DOING_AJAX' ) )
		return false;

	global $wpskillz_session;
	$wpskillz_session = new WPSkillz_Session;

	$q = intval( $_POST['question'] );
	$post = get_post( $q ); 
	$a = $_POST['guess'];

	if ( !$post || $post->post_type !== 'quiz' || !$a )
		return false;

	global $question_post;
	if ( !isset( $question_post ) ) {
		$question_type = get_post_meta( $post->ID, '_question_type', true );
		$question_type_class = "WPSkillz_Question_{$question_type}";
		if ( $question_type && class_exists( $question_type_class ) )
			$question_post = new $$question_type_class( $post );
		else 
			$question_post = new WPSkillz_Question_MultiChoice( $post );
	}

	$response = $question_post->render_answer_mark( $q, $a );

	echo json_encode ( $response );

	die(-1);

}



add_shortcode( 'leaderboards', 'wpskillz_leaderboards' );

function wpskillz_leaderboards( $args, $content = null ) {
	$args = shortcode_atts( $args, array( 'leaders' => 10 ) );
}
