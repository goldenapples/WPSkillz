<?php

/**
 * WPSkillz_Question class
 *
 * Think of this as extending the Post class, except that as of now there isn't really 
 * a WP_Post (or WP_Object_Post: see http://core.trac.wordpress.org/ticket/12267) class.
 *
 * This class is designed to be extendable for many different types of questions. The 
 * methods defined in the base class are for the most common case, multiple-choice 
 * questions. See the WPSkillz_Question_MultiChoice class for details of the methods that 
 * most likely need to be overridden in child classes to enable different types of test 
 * questions.
 *
 * Note: because they can be called from the post-new.php screen where the class may 
 * not be instantiated yet, the setup_meta_boxes() method and any methods called 
 * from within it have to be static functions, meaning that $this is not defined 
 * inside them.
 *
 * @package wpskillz
 * @author	goldenapples
**/
class WPSkillz_Question {

	/**
	 * The post ID this question references
	 *
	 * @var int
	 **/
	var $ID;

	/**
	 * String to represent the question type in the admin menu
	 *
	 * @var string
	 **/
	public static $question_type = '';

	/**
	 * Slug of the term representing the question type
	 *
	 * @var string
	 **/
	public static $question_slug = '';

	/*
	 * Constructor function: called on 'the_post' hook in setup_postdata(). 
	 *
	 */
	public function __construct( &$post = null ) {

		add_filter( 'the_title', array( &$this, 'title_progress' ) );
		add_filter( 'comments_open', array( &$this, 'close_comments' ) );

		/*
		 * Since the questions are not set up until the_post hook is run, it's too late
		 * to catch the 'wp_enqueue_scripts' hook. This is sort of a hackaround.
		 */
		if ( !is_admin() ) $this->enqueue_scripts();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			$this->ID = intval( $_POST['question'] );
		else if ( $post )
			$this->ID = $post->ID;
		else 
			$this->ID = false;

	}

	/*
	 * Display filters for all WPSkillz_Question types
	 *
	 * Can be extended by child classes
	 *
	 */

	/**
	 * Replaces the question title with helpful information about the user 
	 * progress in the quiz so far, ie "Question 23 of 45 (49% complete)"
	 *
	 * This can be overridden, however its probably best to leave this filter alone
	 * for consistency of user experience through the quiz.
	 *
	 * @uses	WPSkillz_Session::progress		Get the current user's progress
	 *
	 */
	function title_progress( $title ) {

		/* 
		 * This logic is awful. Basically skip this filter in the
		 * admin section, EXCEPT when being called through admin ajax.
		 */
		if ( is_admin() && !defined( 'DOING_AJAX' ) )
			return $title;

		if ( ( !is_main_query() || !in_the_loop() ) && !defined( 'DOING_AJAX' ) )
			return $title;

		global $post;
		if ( $post->post_type !== 'quiz' )
			return $title;

		$title = 'Question '.WPSkillz_Session::$current.' of '.WPSkillz_Session::$oftotal;
	   	$title .= ' ('.number_format( WPSkillz_Session::$complete / WPSkillz_Session::$oftotal * 100, 0 ).'% complete)';
		return '<span class="title_progress">'.$title.'</span>';
	}


	/**
	 * Filters the content of question posts to include the answer options at the end 
	 * and any other meta data called for.
	 *
	 * Called on `the_content` filter
	 *
	 */
	public function display_question( $content ) {
		return $content;
	}

	/**
	 * Hides comments on quiz type posts until user has answered the question. 
	 * (preventing spoilers and giveaways).
	 *
	 * Called on `comments_open` filter
	 *
	 * @param	array	$open		Are comments enabled in the first place?
	 * @return	bool	false if user hasn't already answered question;
	 * 					same value as $open otherwise
	 */
	public function close_comments( $open ) {

		if ( !is_array( WPSkillz_Session::$progress ) )
			return false;
		if ( !in_array( $this->ID, array_keys( WPSkillz_Session::$progress ) ) )
			return false;
		return $open;
	}
	


	/*
	 * Render the answers with the correct answer marked. Can be called through Ajax, in which case
	 * it will return the HTML content to be loaded into div#wpskillz-quiz-answers, or it can be called
	 * directly, in which case it can be used to echo the content.
	 *
	 * @uses	$this->check_answer()	Called to check whether the answer given is correct, and update
	 * 										usermeta and session with progress variables
	 *
	 * @param	int			$question	question ID
	 * @param	unknown		$answer		answer provided
	 * @param	bool		$echo		whether to echo (true) or return (false) response
	 * @param	bool		$past_tense	if reviewing an old answer, changes wording to past tense
	 * @return	void|str
	 *
	 */
	public function render_answer_mark( $question, $answer, $echo = false, $past_tense = false ) {
		$correct_answer = $this->check_answer( $answer );

		$mark_language = array(
			'correct' => array(
				'present' => __( 'Correct!', 'wpskillz' ),
				'past' => __( 'You answered correctly.', 'wpskillz' )
			),
			'incorrect' => array(
				'present' => __( 'Sorry, that is the wrong answer.', 'wpskillz' ),
				'past' => __( 'You answered incorrectly.', 'wpskillz' )
			)
		);

		$correct = ( $correct_answer['mark'] ) ? 'correct' : 'incorrect';
		$tense = ( $past_tense ) ? 'past' : 'present';

		$response = '<p class="'.$correct.'">'. $mark_language[ $correct ][ $tense ] . '</p>';

		$answers = get_post_meta( $question, 'answers', true );

		$response .= '<ol class="wpskillz-answer-choices">' . "\r\n\t";

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

		if ( !is_user_logged_in() )
			$response .= WPSkillz_session::login_invitation();
		
		$response .= WPSkillz_Session::next_question_link( false );

		/*
		 * If echoing, just echo the generated html box now. Otherwise, if being called through Ajax,
		 * add the generated html as an additional element to the array returned by check_answer()
		 * and return the entire array.
		 */
		if ( $echo ) {
			echo $response;
		} else {
			$correct_answer['answer_section_text'] = $response;
			return $correct_answer;
		}

	}

	static function setup_meta_boxes() {
	}


	/**
	 * Enqueues all scripts necessary for the question display, Ajax polling, and 
	 * rendering of correct answer on completion.
	 *
	 * @return	void
	 **/
	function enqueue_scripts() {
	}

	/*
	 * Called on 'save_post' hook. Should handle sanitization and whiteliisting, 
	 * where appropriate, of form inputs, and converting inputs into the post 
	 * meta fields required for the check_answer function defined here. 
	 *
	 */
	static function save_post( $post_ID ) {
	}
	
	/**
	 * Check if answer is correct or not
	 *
	 * Can be called through Ajax or directly.
	 *
	 * Calls WPSkillz_Session::update progress(), marks answer and returns an array containing 
	 * 		[mark]				bool	Correct/incorrect
	 * 		[correct_answer]	str		The correct answer
	 * 		[explanation]		str		If provided, the explanation provided for the correct answer
	 *
	 * @uses	WPSkillz_Session::update_progress()
	 *
	 * @param	unknown	Answer provided
	 * @return	array 	See keys listed above
	 */
	function check_answer( $answer ) {
	}

}


function wpskillz_quiz_meta_boxes() {
	global $question_post;

	/*
	 * If the post ID hasn't been set yet (ie, on the post-new.php page), 
	 * we need to check the question type from the GET variable and set up 
	 * the proper question type class here.
	 */
	if ( isset( $question_post ) ) {
		$question_type_class = get_class( $question_post );
	} else {
		if ( isset( $_GET['post'] ) ) {
			$question_type = get_post_meta( intval( $_GET['post'] ), '_question_type', true );
			if ( !$question_type ) $question_type = 'multichoice';

		} else {

			if ( !isset( $_REQUEST['question_type'] ) )
				wp_die( 'You must specify a question type to add a new question.' );

			$question_type = $_REQUEST['question_type'];
		}

		$question_type_class = "WPSkillz_Question_{$question_type}";

		if ( !class_exists( $question_type_class ) )
			wp_die( 'Not a valid question type' );
	}

	$question_type_class::setup_meta_boxes();
}

add_action( 'save_post', 'wpskillz_save_post' );

function wpskillz_save_post( $post_ID ) {
	if ( !isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'quiz' )
		return;

	if ( !isset( $_REQUEST['question_type'] ) )
		wp_die( 'You must specify a question type to add a new question.' );

	$question_type = $_REQUEST['question_type'];
	$question_type_class = "WPSkillz_Question_{$question_type}";

	$question_type_class::save_post( $post_ID );

}
