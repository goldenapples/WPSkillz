<?php
/*
Plugin Name: WP Skillz
Description: Expert-level WordPress questions and answers
Author: Nathaniel Taintor
Author URI: http://goldenapplesdesign.com
Version: 0.1
License: GPL v2
*/


// Debug bar plugin to track progress. OK to remove this in production.
// require_once( 'functions-debug.php' ); 

// Class definitions for all question types to be registered
require_once( 'class.WPSkillz_Question.php' );
require_once( 'class.WPSkillz_Question_MultiChoice.php' );

// Session variable setup and definition
require_once( 'class.WPSkillz_Session.php' );

register_activation_hook( __FILE__, 'wpskillz_activation' );

function wpskillz_activation() {
	if ( !post_type_exists( 'quiz' ) )
		wpskillz_post_type_registration();
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

/**
 * This is the magic of this plugin. On the hook 'the_post', the global variable 
 * $question_post is populated with all data specific to the post type in question.
 *
 */ 
add_action ( 'the_post', 'wpskillz_setup_question_data' );

function wpskillz_setup_question_data( $post ) {
	if ( $post->post_type !== 'quiz' )
		return;

	global $question_post;
	if ( !isset( $question_post ) ) {
		$question_type = get_post_meta( $post->ID, '_question_type', true );

		/*
		 * The idea here was to use the ability in PHP 5.3 to reference static 
		 * classes by variable name and do something like this:
		 *
		 *		$question_type_class = "WPSkillz_Question_{$question_type}";
		 *		if ( $question_type && class_exists( $question_type_class ) )
		 *			$question_post = new $question_type_class( $post );
		 *		else 
		 *			$question_post = new WPSkillz_Question_multichoice( $post );
		 *
		 * Since that's not possible in PHP 5.2.x and the majority of WP sites 
		 * are still running some version of 5.2, we have to resort to this 
		 * clumsier approach. 
		 * TODO: make this more extensible...
		 */
		global $question_post;
		$question_type_class = "WPSkillz_Question_{$question_type}";
		if ( $question_type && class_exists( $question_type_class ) ) {
			$question_post = new ReflectionClass( $question_type_class );
			$question_post->__construct( $post );
		} else 
			$question_post = new WPSkillz_Question_multichoice( $post );

	}

}

/**
 * Enqueues plugin styles on front end views
 *
 * This is mostly handled on WPSkillz_Question::enqueue_scripts for question pages,
 * but there are a couple other pages we need those styles for, such as the start quiz
 * page and the leaderboards page. Although its not great style, we'll enqueue that 
 * stylesheet on every page, because otherwise there's no logical way of determining 
 * where we need it short of preprocessing every page to see if it has one of the
 * shortcodes on it.
 */
function wpskillz_enqueue_frontend_stylesheet() {
	wp_enqueue_style( 'wpskillz', plugins_url( '/css/wpskillz.css', __FILE__ ) );
}

add_action( 'wp_enqueue_scripts', 'wpskillz_enqueue_frontend_stylesheet' );


/**
 * Replaces the "Add new question" link from the admin menu and the admin bar menu
 * with links specific to each of the defined question types.
 *
 * This is necessary because meta boxes may be different from one question type 
 * to another. Its potentially a fairly expensive function (filtering through all 
 * the declared class types to find ones which inherit from WPSkillz_Question), so 
 * it should only be run in the admin section.
 */
function wpskillz_question_types() {

	$all_classes = get_declared_classes();
	$question_types = array_filter( 
		$all_classes,
		create_function( '$c', 'return in_array( "WPSkillz_Question", class_parents( $c ) );' )
	);

	remove_submenu_page( 'edit.php?post_type=quiz', 'post-new.php?post_type=quiz' );
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu( 'new-quiz' );

	foreach ( $question_types as $question_class ) {
		$question_type_text = call_user_func( array( $question_class, 'question_type' ) );
		$question_type_slug = call_user_func( array( $question_class, 'question_slug' ) );
		add_submenu_page( 
			'edit.php?post_type=quiz', 
			sprintf( __( 'Add new %s question', 'wpskillz' ), $question_type_text ),
			sprintf( __( 'Add new %s question', 'wpskillz' ), $question_type_text ),
			'edit_posts',
			'post-new.php?post_type=quiz&question_type='.$question_type_slug,
			null
		);
		$wp_admin_bar->add_menu( array(
			'parent' => 'new-content',
			'id' => 'new-'.$question_type_slug,
			'title' => sprintf( __( '%s question', 'wpskillz' ), $question_type_text ),
			'href' => 'post-new.php?post_type=quiz&question_type='.$question_type_slug
		) );
	}

}

add_action( 'admin_menu', 'wpskillz_question_types' );
add_action( 'admin_bar_menu', 'wpskillz_question_types', 100 );

/*
 * Register the "Quiz" post type
 *
 */
function wpskillz_post_type_registration() {
	register_post_type( 'quiz',
		array(
			'labels'		=> array(
				'name'			=> __( 'Quizzes', 'wp_skillz' ),
				'singular_name'	=> __( 'Quiz', 'wp_skillz' ),
				'add_new'		=> __( 'Add New', 'wp_skillz' ),
				'add_new_item'	=> __( 'Add New question', 'wp_skillz' ),
				'edit'			=> __( 'Edit', 'wp_skillz' ),
				'edit_item'		=> __( 'Edit this question', 'wp_skillz' ),
				'new_item'		=> __( 'New quiz', 'wp_skillz' ),
				'view'			=> __( 'View', 'wp_skillz' ),
				'view_item'		=> __( 'View question', 'wp_skillz' ),
				'search_items'	=> __( 'Search quiz questions', 'wp_skillz' ),
				'not_found'		=> __( 'No questions Found', 'wp_skillz' ),
				'not_found_in_trash'		=> __( 'No questions Found in trash', 'wp_skillz' ),
				'parent'		=> __( '', 'wp_skillz' ),
			),
			'public'		=> true,
			'show_ui'		=> true,
			'register_meta_box_cb'	=> 'wpskillz_quiz_meta_boxes',
			'menu_position'	=> 5,
			'has_archive'	=> true,
			'publicly_queryable'	=> true,
			'rewrite'		=> array( 'slug'=>'quiz' ),
			'supports'		=> array( 
				'custom_fields',
				'comments'
			)
		)
	);
} 

add_action( 'init', 'wpskillz_post_type_registration' );


/*
 *	A basic diff function for storing history on answers 
 *
 *	Since revisions don't work off the bat with custom fields, I wanted to store a
 *	history of all changes that have been made to each of the answers. This would
 *	be helpful for going back, discounting wrong answers if there were no good answers
 *	etc. Not really implemented yet, though.
 *
 *
 *	Paul's Simple Diff Algorithm v 0.1
 *	(C) Paul Butler 2007 <http://www.paulbutler.org/>
 *	May be used and distributed under the zlib/libpng license.
 *	Source: http://paulbutler.org/archives/a-simple-diff-algorithm-in-php/
 *	or https://github.com/paulgb/simplediff
 */
function diff($old, $new){
	foreach($old as $oindex => $ovalue){
		$nkeys = array_keys($new, $ovalue);
		foreach($nkeys as $nindex){
			$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
				$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
			if($matrix[$oindex][$nindex] > $maxlen){
				$maxlen = $matrix[$oindex][$nindex];
				$omax = $oindex + 1 - $maxlen;
				$nmax = $nindex + 1 - $maxlen;
			}
		}
	}
	if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
	return array_merge(
		diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
		array_slice($new, $nmax, $maxlen),
		diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}

function htmlDiff($old, $new){
	$diff = diff(explode(' ', $old), explode(' ', $new));
	foreach($diff as $k){
		if(is_array($k))
			$ret .= (!empty($k['d'])?"<del>".implode(' ',$k['d'])."</del> ":'').
			(!empty($k['i'])?"<ins>".implode(' ',$k['i'])."</ins> ":'');
		else $ret .= $k . ' ';
	}
	return $ret;
}


