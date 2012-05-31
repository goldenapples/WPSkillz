<?php

//add_action('admin_menu','wpskillz_admin_pages');

function wpskillz_admin_pages() {
	add_submenu_page(
		'edit.php?post_type=quiz',
		__( 'WP-Skillz Test Settings', 'wpskillz' ),
		'Test Settings',
		'activate_plugins',
		'wpskillz_plugin_settings',
		'wpskillz_plugin_settings'
	);
}

add_action( 'admin_menu', 'wpskillz_question_types' );

/**
 * Replaces the "Add new question" link from the admin menu with links specific
 * to each of the defined question types.
 *
 * This is necessary because meta boxes may be different from one question type 
 * to another. Its a fairly expensive function (filtering through all the 
 * declared class types to find ones which inherit from WPSkillz_Question), so 
 * it should only be run in the admin section.
 */
function wpskillz_question_types() {
	$all_classes = get_declared_classes();
	$question_types = array_filter( 
		$all_classes,
		create_function( '$c', 'return in_array( "WPSkillz_Question", class_parents( $c ) );' )
	);
	remove_submenu_page( 'edit.php?post_type=quiz', 'post-new.php?post_type=quiz' );
	/*
	 * It would be really nice to assume PHP 5.3 and just loop through defined 
	 * classes like this:
	 *
	 *	foreach ( $question_types as $question_class ) {
	 *		$question_type_text = $question_class::$question_type;
	 *		$question_type_slug = $question_class::$question_slug;
	 *		add_submenu_page( 
	 *			'edit.php?post_type=quiz', 
	 *			sprintf( __( 'Add new %s question', 'wpskillz' ), $question_type_text ),
	 *			sprintf( __( 'Add new %s question', 'wpskillz' ), $question_type_text ),
	 *			'edit_posts',
	 *			'post-new.php?post_type=quiz&question_type='.$question_type_slug,
	 *			null
	 *		);
	 *	}
	 *
	 *	But -sigh- no go, so for now I'm going to just hardcode the question 
	 *	type link.
	 *
	 *	TODO: make this extensible (maybe a filter would work and the other 
	 *	places this problem is encountered)
	 */

	add_submenu_page( 
		'edit.php?post_type=quiz', 
		 __( 'Add new multiple choice question', 'wpskillz' ), 
		 __( 'Add new multiple choice question', 'wpskillz' ), 
		'edit_posts',
		'post-new.php?post_type=quiz&question_type=multichoice',
		null
	);
}



function wpskillz_plugin_settings() {

	if ( !empty( $_POST ) && check_admin_referer( 'wpskillz-settings', '_wpnonce') )
		update_wpskillz_settings( $page );
	$current_settings = get_option( 'wpskillz_plugin_options' );

?>
<div class="wrap">
	<div id="icon-themes" class="icon32"></div>
	<h2><?php _e( 'WPSkillz Test Settings', 'wpskillz' ); ?></h2>

	<form method="post">
		<table class="form-table">
		<tr>
			<th scope="row">
			<label for="start-test-page"><?php _e( 'Select a page to start the test on:', 'wpskillz' ); ?></label>
			</th>
			<td><?php 
				wp_dropdown_pages(
					array(
						'name' => 'start-test-page',
						'show_option_none' => false,
						'exclude' => array( get_option('page_for_posts') ),
						'selected' => $current_settings['start-test-page']
					)
				); ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
			<label for="leaderboard-page"><?php _e( 'Select a page to hold the leaderboard:', 'wpskillz' ); ?></label>
			</th>
			<td><?php
				wp_dropdown_pages(
					array(
						'name' => 'leaderboard-page',
						'show_option_none' => false,
						'exclude' => array( get_option('page_for_posts') ),
						'selected' => $current_settings['leaderboard-page']
					)
				); ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for=""></label>
			</th>
			<td>
				<input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpskillz' ); ?>" />
			</td>
		</tr>

		</table>
		<?php wp_nonce_field( 'wpskillz-settings' ); ?>
	</form>
</div>

<?php
}

function update_wpskillz_settings( $page ) {

	$settings = get_option( 'wpskillz_plugin_options' );

	$settings['start-test-page'] = intval( $_POST['start-test-page'] );
	$settings['leaderboard-page'] = intval( $_POST['leaderboard-page'] );

	update_option( 'wpskillz_plugin_options', $settings );

	echo '<div id="message" class="messages updated"><p>Plugin settings updated!</p></div>';
}
