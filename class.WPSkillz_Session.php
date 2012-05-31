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
	WPSkillz_Session::init( $new_login );
}

add_action( 'wp_login', 'wpskillz_merge_session_progress', 10, 2 );

function wpskillz_merge_session_progress( $user_login, $user ) {
	WPSkillz_Session::init( true );
	WPSkillz_Session::login( $user_login, $user );
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
	static $complete;

	/**
	 * The number of questions answered correctly in the current session
	 *
	 * @var int
	 **/
	static $correct;

	/**
	 * Total number of questions available in test
	 *
	 * @var int
	 **/
	static $oftotal;

	/**
	 * Current question number 
	 * (should be $complete + 1 if currently viewing a question; $complete otherwise)
	 *
	 * @var int
	 **/
	static $current;

	/**
	 * Percent complete
	 *
	 * @var float
	 **/
	static $percent;

	/**
	 * All data about questions answered in the current session.
	 *
	 * In the form of an array with the question numbers as keys, and an array including the
	 * answer given, the time answered, the correct answer, and a correct/incorrect mark as values.
	 *
	 * @var array
	 **/
	static $progress;

	/**
	 * Initialize all the class variables on session start.
	 *
	 * @param	bool	new_login	Whether a new user is being logged in
	 *
	 * @return void
	 */
	function init( $new_login ) {

		if ( !session_id() )
			session_start();
		global $current_user;

		if ( $new_login ) 
			$progress = self::login();
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

		self::$complete = $complete;
		self::$correct = count( $correct_answers );
		self::$oftotal = $questions;
		self::$current = ( is_array( $progress ) && in_array( $current_question, array_keys( $progress ) ) ) ? $complete : $complete + 1;
		self::$percent = ( $questions ) ? $complete / $questions : 0;
		self::$progress = $progress;

		add_shortcode( 'start-quiz', array( 'WPSkillz_Session', 'start_quiz_content' ) );
	}

	/**
	 * Update session variables after a question is answered
	 *
	 * @return	void
	 */
	function update_progress( $question_results ) {

		if ( !is_array( $question_results ) )
			$question_results = array();

		if ( !is_array( self::$progress ) )
			self::$progress = array();

		self::$progress = self::$progress + $question_results;

		// Update progress session variable and user meta
		$_SESSION['wpskillz_test'] = maybe_serialize( self::$progress );
		if ( is_user_logged_in() ) {
			global $current_user;
			update_user_meta( $current_user->ID, 'wpskillz_test', self::$progress );

			// Score is going to be on the typical 800 point scale
			$score = intval( 800 * self::$correct / self::$oftotal );
			update_user_meta( $current_user->ID, 'wpskillz_score', $score );

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

		$questions_done = ( self::$progress ) ? array_keys( self::$progress ) : array();

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
		$link = self::next_question();
		if ( $link ) {
			if ( empty( $text ) )
				$text = __( 'Next question', 'wpskillz' );
			$link_text = '<p><a class="next-question" href="'.$link.'" title="'.esc_attr($text).'">'.$text.'</a></p>';
		} else { 

			// I really didn't want to build out an options page for this one option, so the value $leaderboards page 
			// is determined by searching for the shortcode [leaderboards] on all pages
			global $wpdb;
			$leaderboards_page = $wpdb->get_var( "SELECT ID from {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%[leaderboard%' LIMIT 1" );
			if ( $leaderboards_page )
				$leaderboards_link = '<a href="' . get_permalink( $leaderboards_page ) . '">' . __( 'View leaderboards', 'wpskillz' ) .'</a>';
				
			$link_text = '
				<div class="login-box">
					<p>' . __( 'You have completed all the available questions.', 'wpskillz' ) . '</p>
					<p>' . __( 'See how you stack up against other test-takers!', 'wpskillz' ) . ' &nbsp;' . $leaderboards_link . '</p>
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
		return self::next_question_link( false, __( 'Start the test now!', 'wpskillz' ) );
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

		self::$progress = $user_saved_progress + $anonymous_progress;
		self::update_progress( null );

		return self::$progress;
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

		$login_link = wp_login_url( self::next_question() );

		$login_link_text = sprintf( __('<a href="%s">Login now</a> ', 'wpskillz' ), $login_link );

		if ( get_option( 'users_can_register' ) )
			$registration_link_text = sprintf( 
				__( 'or <a href="%s">register for an account</a> ', 'wpskillz' ), 
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

	global $post;
	WPSkillz_Session::init( false );

	$q = intval( $_POST['question'] );

	query_posts( array( 'p' => $q, 'post_type' => 'quiz' ) );
	if ( have_posts() ) : while ( have_posts() ) : the_post();
		
	$a = $_POST['guess'];

	if ( !$post || $post->post_type !== 'quiz' || !$a )
		return false;

	global $question_post;
	if ( !isset( $question_post ) ) {
		$question_type = get_post_meta( $post->ID, '_question_type', true );
		$question_type_class = "WPSkillz_Question_{$question_type}";
		if ( $question_type && class_exists( $question_type_class ) )
			$question_post = new $question_type_class( $post );
		else 
			$question_post = new WPSkillz_Question_MultiChoice( $post );
	}

	$response = $question_post->render_answer_mark( $q, $a );

	endwhile; endif; // have_posts()

	echo json_encode ( $response );

	die(-1);

}



add_shortcode( 'leaderboards', 'wpskillz_leaderboards' );

function wpskillz_leaderboards( $atts = null ) {
	$args = shortcode_atts( array( 'number' => 0 ), $atts );

	// Since there aren't filters on the individual parts of WP_User_Query, add an action to tweak
	// the whole query object here:
	add_action( 'pre_user_query', 'sort_by_test_score' );

	function sort_by_test_score( $query ) {
		global $wpdb;
		$query->query_orderby = "ORDER BY ( 0 + {$wpdb->usermeta}.meta_value ) DESC";
	}

	$leaders_query = new WP_User_Query( array(
		'meta_key' => 'wpskillz_score',
		'orderby' => 'meta_value_num',
		'order' => 'DESC',
		'number' => intval( $args['number'] )
	) );

	// ... and remove it here
	remove_action( 'pre_user_query', 'sort_by_test_score' );

	$leaders = $leaders_query->get_results();

	if ( $leaders ) {
		$return = '
			<table class="leaderboards">
				<thead>
					<th width="48">' . __( 'User', 'wpskillz' ) . '</th>
					<th>&nbsp;</th>
					<th>' . __( 'Score', 'wpskillz' ) . '</th>
					<th>' . __( 'Date', 'wpskillz' ) . '</th>
				</thead>
				<tbody>';

		foreach ( $leaders as $leader ) {
			$avatar = get_avatar( $leader->ID, 32 );
			$test_score = get_user_meta( $leader->ID, 'wpskillz_score', true );
			$test_date = get_user_meta( $leader->ID, 'wpskillz_date', true );

			$return .= "
				<tr valign='top'>
					<td>$avatar</td>
					<td>{$leader->display_name}</td>
					<td>$test_score</td>
					<td>$test_date</td>
				</tr>
				";

		}

	$return .= '</table>';

	}
	return $return;

}
