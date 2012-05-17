<?php

function wpskillz_quiz_meta_boxes() {
	add_meta_box(
		'wpskillz-quiz',
		__( 'Question and answers' ),
		'wpskillz_answers_custom_box',
		'quiz',
		'normal',
		'core'	
	);
}

function wpskillz_answers_custom_box() {
	global $post;
	if ( $post->post_type !== 'quiz' )
		return;

	$old_answers = ( get_post_meta( $post->ID, 'answers', true ) ) ?
		get_post_meta( $post->ID, 'answers', true ) : array();

	$quiz = array(
		'question' => get_post_meta( $post->ID, 'question', true ),
		'answers' => array_pad( $old_answers, 4, array( 'answer_id' => false, 'answer_text' => '', 'is_correct' => false ) )
	);
?>
<table width="100%">
	<tr>
		<td valign="top" width="50%">
        <div class="wmd-panel">
            <div id="wmd-button-bar"></div>
			<textarea name="question-body" class="wmd-input" id="wmd-input"><?php echo get_post_meta( $post->ID, 'question', true ); ?></textarea>
		</div>
        <div id="wmd-preview" class="wmd-panel wmd-preview"></div>
		</td>
		<td valign="top" width="50%">
			<?php 
		foreach ( $quiz['answers'] as $answer ) {
			$id = ( $answer['answer_id'] ) ? $answer['answer_id'] : uniqid();
			echo '<p><input name="is_correct" value="'.$id.'" type="radio" '.checked( $answer['is_correct'], true, false ) . '/>';
			echo '<textarea id="" name="answers['.esc_attr($id).']" rows="3" cols="30">'.$answer['answer_text'].'</textarea></p>';
		} 
			?>
		</td>
	</tr>
</table>


<?
}

add_action( 'admin_enqueue_scripts', 'wpskillz_enqueue_admin_scripts' );

function wpskillz_enqueue_admin_scripts( $page ) {
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
	wp_enqueue_script( 'markdown-converter', plugins_url( '/js/pagedown/Markdown.Converter.js', __FILE__ ) );
	wp_enqueue_script( 'markdown-sanitizer', plugins_url( '/js/pagedown/Markdown.Sanitizer.js', __FILE__ ) );
	wp_enqueue_script( 'markdown-editor', plugins_url( '/js/pagedown/Markdown.Editor.js', __FILE__ ) );

	// TODO: demo.css used for quick setup. Should have actual admin css file
	wp_enqueue_style( 'pagedown-style', plugins_url( '/css/admin.css', __FILE__ ) );

	wp_enqueue_script( 'wpskillz_admin_js', 
		plugins_url( '/js/admin.js', __FILE__ ), 
		array( 'jquery', 'markdown-converter', 'markdown-sanitizer', 'markdown-editor' )
	);

}

add_action( 'save_post', 'wpskillz_save_quiz_meta' );

function wpskillz_save_quiz_meta( $post_ID ) {
	if ( !isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'quiz' )
		return;

	if ( defined( 'UPDATING_POST' ) && UPDATING_POST )
		return;

	global $current_user;

	// Sanitize question body and answers
	$question = $_POST['question-body'];

	define( 'UPDATING_POST', 'true' );
	wp_update_post( array( 'ID' => $post_ID, 'post_title' => $question, 'post_content' => $question ) );

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

