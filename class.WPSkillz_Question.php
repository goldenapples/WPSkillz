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
 * @package wpskillz
 * @author goldenapples
**/
class WPSkillz_Question {

	/*
	 * Constructor function: called on 'the_post' hook in setup_postdata(). 
	 *
	 */
	public function __construct( &$post = null ) {

		add_filter( 'the_title', array( &$this, 'title_progress' ) );
		add_filter( 'the_content', array( &$this, 'display_question' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ) );
		add_action( 'save_post', array( &$this, 'save_post' ) );

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
	 * @uses	wpskillz_user_progress()		Get the current user's progress
	 *
	 */
	protected function title_progress( $title ) {

		if ( is_admin() || !is_main_query() || !in_the_loop() )
			return $title;

		global $post;
		if ( $post->post_type !== 'quiz' )
			return $title;

		$progress = wpskillz_user_progress();

		$title = 'Question '.$progress['current'].'/'.$progress['oftotal']. ' ('.number_format( $progress['percent']*100, 0 ).'% complete)';
		return $title;
	}


	/**
	 * Filters the content of question posts to include the answer options at the end 
	 * and any other meta data called for.
	 *
	 * @uses	wpskillz_render_answer_mark()	Show correct answer and explanation, if
	 * 											question was already answered
	 */
	public function display_question( $content ) {
		if ( is_admin() )
			return $content;

		global $post;
		if ( $post->post_type !== 'quiz' )
			return $content;

		$progress = wpskillz_user_progress(); 

		if ( in_array( $post->ID, array_keys( $progress['questions'] ) ) ) {
			$date = $progress['questions'][$post->ID]['date'];
			$date_format_local = mysql2date( get_option('date_format'), $date, true );

			$content = '<p class="already-done">You\'ve answered this question (on '.$date_format_local.')</p>' . $content;

			$content .= '<div id="wpskillz-quiz-answers">';

			$content .= wpskillz_render_answer_mark( $post->ID, $progress['questions'][$post->ID]['answer_given'] );

			$content .= '</div>';

		} else {

			if ( $answers = get_post_meta( $post->ID, 'answers', true ) ) {
				$content .= '<div id="wpskillz-quiz-answers"><ol class="wpskillz-answer-choices">';
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
			}
		}
		
		return $content;
	}


	
	/*
	 * Render the answers with the correct answer marked. Can be called through Ajax, in which case
	 * it will return the HTML content to be loaded into div#wpskillz-quiz-answers, or it can be called
	 * directly, in which case it can be used to echo the content.
	 *
	 * @uses	wpskillz_handle_answer()	Called to check whether the answer given is correct, and update
	 * 										usermeta and session with progress variables
	 *
	 * @param	int			question ID
	 * @param	unknown		answer provided
	 * @param	bool		whether to echo (true) or return (false) response
	 * @return	void|str
	 *
	 */
	public function wpskillz_render_answer_mark( $question, $answer, $echo = false ) {
		$correct_answer = wpskillz_handle_answer( $question, $answer );

		$response = ( $correct_answer['mark'] ) ? 
			'<p class="correct">Correct!</p>' :
			'<p class="incorrect">Sorry, that is the wrong answer.</p>';

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

		$response .= wpskillz_next_question_link( false );

		if ( $echo )
			echo $response;
		else
			return $response;

	}

	function setup_meta_boxes() {
		add_meta_box(
			'wpskillz-quiz',
			__( 'Question and answers', 'wp_skillz' ),
			array( &$this, 'question_box' ),
			'quiz',
			'normal',
			'core'
		);
		add_meta_box(
			'wpskillz-difficulty',
			__('Difficulty and explanation', 'wp_skillz' ),
			array( &$this, 'explanation_box' ),
			'quiz',
			'normal',
			'core'
		);
	}

	function question_box() {
		global $post;
		if ( $post->post_type !== 'quiz' )
			return;

		$old_answers = ( get_post_meta( $post->ID, 'answers', true ) ) ?
			get_post_meta( $post->ID, 'answers', true ) : array();

		$quiz = array(
			'question' => get_post_meta( $post->ID, 'question', true ),
			'history' => get_post_meta( $post->ID, 'history', true ),
			'answers' => array_pad( $old_answers, 4, array( 'answer_id' => false, 'answer_text' => '', 'is_correct' => false ) )
		);

		?>
	<table width="100%">
		<tr>
			<td valign="top" width="50%">
				<p class="description">Both question and answers can be formatted with StackOverflow-flavored Markdown.</p>
				<div class="wmd-panel">
					<div id="wmd-button-bar"></div>
					<textarea name="question-body" class="wmd-input" id="wmd-input"><?php echo get_post_meta( $post->ID, 'question', true ); ?></textarea>
				</div>
				<div id="wmd-preview" class="wmd-panel wmd-preview"></div>
				<input type="hidden" name="quiz-q-html" id="quiz-q-html" value="<?php echo esc_attr( $post->post_content ); ?>"/>
			</td>
			<td valign="top" width="50%">
			<table class="widefat">
				<thead>
					<tr>
						<th>Correct?</th>
						<th>Answer</th>
					</tr>
				</thead>
				<tbody>
					<?php 
						foreach ( $quiz['answers'] as $i => $answer ) {
							$id = ( $answer['answer_id'] ) ? $answer['answer_id'] : uniqid();
						?>
					<tr valign="top" class="<?php echo ($i % 2) ? 'alternate' : ''; ?>" >
						<td><input name="is_correct" value="<?php echo $id; ?>" type="radio" <?php checked( $answer['is_correct'] ); ?> /></td>
						<td><textarea id="" name="answers[<?php echo $id; ?>]" rows="3" cols="30"><?php echo $answer['answer_text']; ?></textarea></td>
						<td><input type="text" /></td>
					</tr>
						<?php 		
						} 
					?>
				</tbody>
			</table>
			</td>
		</tr>
	</table>
		<?
	}


	/**
	 * Enqueues all scripts necessary for the question display, Ajax polling, and 
	 * rendering of correct answer on completion.
	 *
	 **/
	function enqueue_scripts() {
		if ( !is_singular( 'quiz' ) )
			return;

		wp_enqueue_script( 'wpskillz-frontend', plugins_url( '/js/wpskillz-frontend.quiz.js', __FILE__ ), array( 'jquery' ) );
		wp_localize_script( 'wpskillz-frontend', 'wpSkillz',
			array( 
				'ajaxURL' => admin_url( 'admin-ajax.php' ),
				'thisQuestion' => get_queried_object_id(),
				'sessionID' => session_id()
			) );
		wp_enqueue_style( 'wpskillz', plugins_url( '/css/wpskillz.css', __FILE__ ) );

	}

	/**
	 * Enqueues all scripts necessary for editing the question on the post.php 
	 * screen.
	 */
	function admin_scripts( $page ) {
		if ( !in_array( $page, array( 'post.php', 'post-new.php' ) ) )
			return;
		global $post;
		if ( $post->post_type !== 'quiz' )
			return;

		/*
		 * PageDown scripts: Stack Overflow-flavored Markdown editor
		 *
		 * see http://code.google.com/p/pagedown/wiki/PageDown for usage information
		 */
		wp_enqueue_script( 'markdown-converter', 
			plugins_url( '/js/pagedown/Markdown.Converter.js', __FILE__ ) );
		wp_enqueue_script( 'markdown-sanitizer', 
			plugins_url( '/js/pagedown/Markdown.Sanitizer.js', __FILE__ ) );
		wp_enqueue_script( 'markdown-editor', 
			plugins_url( '/js/pagedown/Markdown.Editor.js', __FILE__ ) );

		// TODO: demo.css used for quick setup. Should have actual admin css file
		wp_enqueue_style( 'pagedown-style', plugins_url( '/css/admin.css', __FILE__ ) );

		wp_enqueue_script( 'wpskillz_admin_js', 
			plugins_url( '/js/wpskillz-admin.post.js', __FILE__ ), 
			array( 'jquery', 'markdown-converter', 'markdown-sanitizer', 'markdown-editor' )
		);

	}

	/*
	 * Called on 'save_post' hook. Should handle sanitization and whiteliisting, 
	 * where appropriate, of form inputs, and converting inputs into the post 
	 * meta fields required for the check_answer function defined here. 
	 *
	 */
	function save_post( $post_ID ) {
		if ( !isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'quiz' )
			return;

		if ( defined( 'UPDATING_POST' ) && UPDATING_POST )
			return;

		global $current_user;

		// Sanitize question body and answers
		$question = $_POST['question-body'];

		define( 'UPDATING_POST', 'true' );
		wp_update_post( 
			array( 
				'ID' => $post_ID,
				'post_title' => wp_strip_all_tags( $question ),
				'post_name' => $post_ID,
				'post_content' => wp_filter_post_kses( $_POST['quiz-q-html'] ) 
			)
		);

		update_post_meta( $post_ID, 'question', $_POST['question-body'] );

		$old_answers = get_post_meta( $post_ID, 'answers', true );
		$answers = array();

		foreach ( $_POST['answers'] as $id => $answer ) {

			$answer = sanitize_text_field( $answer );
			$history = ( isset( $old_answers[$id] ) && isset( $old_answers[$id]['history'] ) ) ?
				$old_answers[$id]['history'] : array();
		
			// There may be blank fields from the post, skip them if so
			if ( empty( $answer ) && !count( $history ) )
				continue;

			// If an answer is being edited, log the revision history so that we can track these things
			// (if a question has no good answers, we should devalue the scoring of wrong guesses, etc.)
			if ( isset( $old_answers[$id] ) && $old_answers[$id] !== $answer )
				$history[] = array(
					'user' => $current_user->ID,
					'date' => current_time( 'mysql' ),
					'diff' => htmlDiff( $old_answers['id']['answer_text'], $answer )
				);

			$answers[] = array(
				'answer_id' => $id,
				'answer_text' => $answer,
				'is_correct' => ( $_POST['is_correct'] == $id ),
				'history' => $history
			);
		}

		update_post_meta( $post_ID, 'answers', $answers );

	}
	
	function explanation_box() {
		global $post;

		$explanation = get_post_meta( $post->ID, 'explanation', true );
		echo <<<EOF

		<table>
			<tr valign="top">
				<td>
					<p class="description">Use this quiz for educational purposes. Give a helpful message to users who answer incorrectly as to why the correct answer is designated that way.</p>
					<textarea id="explanation" name="wpskillz_explanation" rows="10" cols="30">{$explanation}</textarea>
				</td>
			</tr>
		</table>
EOF;
	}

	/**
	 *
	 * Check if answer is correct or not; mark answer and return an array containing 
	 * 		[mark]				bool	Correct/incorrect
	 * 		[correct_answer]	str		The correct answer
	 * 		[explanation]		str		If provided, the explanation provided in question
	 *
	 * Can be called through Ajax or directly.
	 *
	 * @param	unknown	Answer provided
	 *
	 * @return	array 	See keys listed above
	 */
	function check_answer( $answer ) {

		global $current_user;

		if ( is_user_logged_in() )
			$progress = get_user_meta( $current_user->ID, 'wpskillz_test', true );
		else 
			$progress = $_SESSION['wpskillz_test'];

		if ( empty( $progress ) )
			$progress = array();

		$question = $this->ID;

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
		$_SESSION['wpskillz_test'] = maybe_serialize( $progress );
		if ( is_user_logged_in() )
			update_user_meta( $current_user->ID, 'wpskillz_test', $progress );

		return array(
			'mark'	=> $is_answer_correct,
			'correct_answer' => $correct_answer
		);
	}

}


function wpskillz_quiz_meta_boxes() {
	global $question_post;
	if ( !isset( $question_post ) ) {
		$question_post = new WPSkillz_Question( $post );
	}

	$question_post->setup_meta_boxes();
}
