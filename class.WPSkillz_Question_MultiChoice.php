<?php

/**
 * Extends the WPSkillz_Question class for multiple choice questions
 *
 * This is currently the only question type that's built out, but the idea is 
 * for add-on plugins to be able to define additional question types by 
 * extending the WPSkillz_Question class in the same way as this class does.
 *
 * Each question type needs to define the static variables $question_type and 
 * $question_slug. $question_type is the string as it will appear in menus, and 
 * $question_slug is the custom field value used to mark this question type.
 *
 *
 * Methods that need to be overridden for each question type include:
 *
 * display_question()	Called as a filter on `the_content`; should handle displaying
 * 						the question within the content, and providing a means 
 * 						to handle user input for answer
 *
 * render_answer_mark()	Called through Ajax or directly after user inputs an
 * 						answer. Should call the check_answer() function, and 
 * 						return an array including the marked up answer for 
 * 						display.
 *
 * check_answer()		Checks if answer is correct or not, should update 
 * 						WPSkillz_Session::update_progress() with result
 *
 * enqueue_scripts()	Any front-end scripts or styles that need to be enqueued 
 * 						on single quiz pages of this question type
 *
 * admin_scripts()		
 * setup_meta_boxes()	Called on `admin_enqueue_scripts` and 
 * 						`setup_meta_boxes`, respectively for post.php & 
 * 						post-new.php screens of this question type
 *
 * save_post()			Called on `save_post` for this question type
 *
 * @package	wpskillz
 * @author	goldenapples
 */
class WPSkillz_Question_MultiChoice extends WPSkillz_Question {

	/**
	 * String to represent the question type in the admin menu
	 *
	 * @var string
	 **/
	static $question_type = 'Multiple Choice';

	/**
	 * Slug of the term representing the question type
	 *
	 * @var string
	 **/
	static $question_slug = 'multichoice';

	public function __construct( &$post ) {
		parent::__construct( $post );
		add_filter( 'the_content', array( &$this, 'display_question' ) );
	}

	
	/*
	 * Render the answers with the correct answer marked. Can be called through 
	 * Ajax, in which case it will return an array including the HTML content to 
	 * be loaded into div#wpskillz-quiz-answers as well as other content for updating
	 * the page; or it can be called directly, in which case it simply echoes the content.
	 *
	 * @uses	$this->check_answer()	Called to check whether the answer given 
	 * 									is correct, and update usermeta and session 
	 * 									with progress variables
	 *
	 * @param	int			$question	question ID
	 * @param	unknown		$answer		answer provided
	 * @param	bool		$echo		whether to echo (true) or return (false) response
	 * @param	bool		$past_tense	whether to change wording to past tense
	 * @return	void|str
	 *
	 */
	public function render_answer_mark( $question, $answer, $echo = false, $past_tense = false ) {
		$correct_answer = $this->check_answer( $answer );

		$answers = get_post_meta( $question, 'answers', true );

		$response = '<ol class="wpskillz-answer-choices">' . "\r\n\t";

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

		$response .= '<div class="answer-explanation">
			<p class="'.$correct.'">'. $mark_language[ $correct ][ $tense ] . '</p>'
			. $correct_answer['explanation'] .
			'</div>';

		global $wpskillz_session;

		if ( !is_user_logged_in() )
			$response .= $wpskillz_session->login_invitation();
		
		$response .= $wpskillz_session->next_question_link( false );

		/*
		 * If echoing, just echo the generated html box now. Otherwise, if 
		 * being called through Ajax, add the generated html as an additional 
		 * element to the array returned by check_answer() and return the 
		 * entire array.
		 */
		if ( $echo ) {
			echo $response;
		} else {
			$correct_answer['answer_section_text'] = $response;

			/*
			 * Return comments section along with answer mark.
			 */
			ob_start();
			comments_template( '', true );
			$correct_answer['comments_section'] = ob_get_clean();

			return $correct_answer;
		}

	}

	static function setup_meta_boxes() {
		add_meta_box(
			'wpskillz-quiz',
			__( 'Question and answers', 'wp_skillz' ),
			'WPSkillz_Question_MultiChoice::question_box',
			'quiz',
			'normal',
			'core'
		);
		add_meta_box(
			'wpskillz-difficulty',
			__('Difficulty and explanation', 'wp_skillz' ),
			'WPSkillz_Question_MultiChoice::explanation_box',
			'quiz',
			'normal',
			'core'
		);
		add_action( 'admin_enqueue_scripts', 'WPSkillz_Question_MultiChoice::admin_scripts' );
	}

	static function question_box() {
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
					<div id="wmd-button-bar-question"></div>
					<textarea name="question-body" class="wmd-input" id="wmd-input-question"><?php echo get_post_meta( $post->ID, 'question', true ); ?></textarea>
				</div>
				<div id="wmd-preview-question" class="wmd-panel wmd-preview"></div>
				<input type="hidden" name="quiz-q-html" id="quiz-q-html" value="<?php echo esc_attr( $post->post_content ); ?>"/>
			</td>
			<td valign="top" width="50%">
			<table class="widefat" id="wpskillz-answers">
				<thead>
					<tr>
						<th>Correct?</th>
						<th>Answer</th>
						<th>Preview</th>
					</tr>
				</thead>
				<tbody>
					<?php 
						foreach ( $quiz['answers'] as $i => $answer ) {
							$id = ( $answer['answer_id'] ) ? $answer['answer_id'] : uniqid();
						?>
							<tr valign="top" class="wmd-panel <?php echo ($i % 2) ? 'alternate' : ''; ?>" data-editorkey="<?php echo $id; ?>" >
						<td><input name="is_correct" value="<?php echo $id; ?>" type="radio" <?php checked( $answer['is_correct'] ); ?> /></td>
						<td>
						<div id="wmd-button-bar-<?php echo $id; ?>" style="display:none;"></div>
							<textarea id="wmd-input-<?php echo $id; ?>" name="answers[<?php echo $id; ?>][answer_md]" class="wmd-panel wmd-input" rows="3" cols="30"><?php echo esc_textarea($answer['answer_md']); ?></textarea></td>
						<td>
							<div id="wmd-preview-<?php echo $id; ?>" class="wmd-panel wmd-preview"></div>
							<input type="hidden" name="answers[<?php echo $id; ?>][answer_html]" id="quiz-<?php echo $id; ?>-html" value="<?php echo esc_attr($answer['answer_text']); ?>" />
						</td>
					</tr>
						<?php 		
						} 
					?>
				</tbody>
			</table>
			</td>
		</tr>
	</table>
	<input type="hidden" name="question_type" value="multichoice" />
		<?
	}

	static function explanation_box() {
		global $post;

		$explanation_html = esc_attr( get_post_meta( $post->ID, 'explanation_html', true ) );
		$explanation_md = esc_textarea( get_post_meta( $post->ID, 'explanation_md', true ) );

		echo <<<EOF

		<table>
			<tr valign="top">
				<td>
					<p class="description">Use this quiz for educational purposes. Give a helpful message to users who answer incorrectly as to why the correct answer is designated that way.</p>
				<div class="wmd-panel">
					<div id="wmd-button-bar-explanation"></div>
					<textarea id="wmd-input-explanation" class="wmd-input" name="wpskillz_explanation" rows="10" cols="30">{$explanation_md}</textarea>
				</div>
				<div id="wmd-preview-explanation" class="wmd-panel wmd-preview"></div>
				<input type="hidden" name="quiz-e-html" id="quiz-e-html" value="{$explanation_html}"/>
				</td>
			</tr>
		</table>
EOF;
	}

	/**
	 * Filters the content of question posts to include the answer options at the end
	 * and any other meta data called for.
	 *
	 * @uses	render_answer_mark() 	Show correct answer and explanation, if
	 * 									question was already answered
	 */
	public function display_question( $content ) {
		if ( is_admin() )
			return $content;

		global $post;
		if ( $post->post_type !== 'quiz' )
			return $content;

		global $wpskillz_session;

		/*
		 * If question has already been answered, prepend message to questions 
		 * indicating that user has already answered the question, and call 
		 * render_answer_mark to append correct answer and explanation. 
		 * Otherwise just append answer choices to question.
		 */
		if ( is_array( $wpskillz_session->progress ) &&
			in_array( $this->ID, array_keys( $wpskillz_session->progress ) ) ) {

				$date = $wpskillz_session->progress[$this->ID]['date'];
				$date_format_local = mysql2date( get_option('date_format'), $date, true );

				$content = '<p class="already-done">You\'ve answered this question (on '.$date_format_local.')</p>' . $content;

				$content .= '<div id="wpskillz-quiz-answers">';

				$answer_mark = $this->render_answer_mark( $this->ID, $wpskillz_session->progress[$this->ID]['answer_given'], false, true );
				$content .= $answer_mark['answer_section_text'];

				$content .= '</div>';

			} else {

				if ( $answers = get_post_meta( $post->ID, 'answers', true ) ) {
					$content .= '<div id="wpskillz-quiz-answers"><ol class="wpskillz-answer-choices">';
					foreach ( $answers as $answer ) {
						$non_ajax_url = add_query_arg( 'guess', $answer['answer_id'], get_permalink() );
						$content .= <<<EOF

<a name="{$answer['answer_id']}" data-answer="{$answer['answer_id']}" class="answer-text" href="{$non_ajax_url}">
	<li>{$answer['answer_text']}</li>
</a>
EOF;
					}
					$content .= '</ol></div>';
				}
			}

		return $content;
	}

	/**
	 * Enqueues all scripts necessary for the question display, Ajax polling, and 
	 * rendering of correct answer on completion.
	 *
	 * @return	void
	 **/
	function enqueue_scripts() {

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
	 *
	 * @return	void
	 */
	static function admin_scripts( $page ) {
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
	static function save_post( $post_ID ) {

		/*
		 * Constant definition necessary to avoid infinite loop - otherwise the 
		 * 'save_post' hook is called when post is updating, thus running this 
		 * function again.
		 */
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

		update_post_meta( $post_ID, '_question_type', 'multichoice' );
	
		update_post_meta( $post_ID, 'question', $_POST['question-body'] );

		$old_answers = get_post_meta( $post_ID, 'answers', true );
		$answers = array();

		foreach ( $_POST['answers'] as $id => $answer ) {

			$answer_html = wp_filter_post_kses( $answer['answer_html'] );
			$history = ( isset( $old_answers[$id] ) && isset( $old_answers[$id]['history'] ) ) ?
				$old_answers[$id]['history'] : array();
		
			// There may be blank fields from the post, skip them if so
			if ( empty( $answer ) && !count( $history ) )
				continue;

			// If an answer is being edited, log the revision history so that we 
			// can track these things (if a question has no good answers, we 
			// should devalue the scoring of wrong guesses, etc.)
			if ( isset( $old_answers[$id] ) && $old_answers[$id] !== $answer )
				$history[] = array(
					'user' => $current_user->ID,
					'date' => current_time( 'mysql' ),
					'diff' => htmlDiff( $old_answers['id']['answer_text'], $answer_html )
				);

			$answers[] = array(
				'answer_id' => $id,
				'answer_text' => $answer_html,
				'answer_md' => $answer['answer_md'],
				'is_correct' => ( $_POST['is_correct'] == $id ),
				'history' => $history
			);
		}

		update_post_meta( $post_ID, 'answers', $answers );

		update_post_meta( $post_ID, 'explanation_html', 
			wp_filter_post_kses( $_POST['quiz-e-html'] ) );
		update_post_meta( $post_ID, 'explanation_md', 
			wp_filter_post_kses( $_POST['wpskillz_explanation'] ) );	

	}

	/**
	 * Check if answer is correct or not
	 *
	 * Can be called through Ajax or directly.
	 *
	 * Calls WPSkillz_Session::update progress(), marks answer and returns an 
	 * array containing 
	 *
	 * 		[mark]				bool	Correct/incorrect
	 * 		[correct_answer]	str		The correct answer
	 * 		[explanation]		str		If provided, the explanation provided 
	 * 									for the correct answer
	 *
	 * @uses	WPSkillz_Session::update_progress()
	 *
	 * @param	unknown	Answer provided
	 * @return	array 	See keys listed above
	 */
	function check_answer( $answer ) {

		$question = $this->ID;
		$answers = get_post_meta( $question, 'answers', true );
		$explanation = get_post_meta( $this->ID, 'explanation_html', true );

		$correct_answers = array_filter( 
			$answers,
			create_function( '$a', 'return $a["is_correct"];' )
		);

		$correct_answer = array_shift( $correct_answers );
		$is_answer_correct = ( $answer == $correct_answer['answer_id'] );

		$current_question_result = array(
			$question => array(
				'answer_given' => $answer,
				'correct' => $is_answer_correct,
				'date' => current_time( 'mysql' )
			)
		);

		global $wpskillz_session;
		$wpskillz_session->update_progress( $current_question_result );

		return array(
			'mark'	=> $is_answer_correct,
			'correct_answer' => $correct_answer,
			'explanation' => $explanation
		);
	}

}


