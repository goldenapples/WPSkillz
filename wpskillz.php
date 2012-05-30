<?php
/*
Plugin Name: WP Skillz
Description: Expert-level WordPress questions and answers
Author: Nathaniel Taintor
Author URI: http://goldenapplesdesign.com
Version: 0.1
License: GPL v2
*/

// Post type registration, bootstrap and functions which should be set up on all pageloads
require_once( 'functions-init.php' ); 

// Include options page on admin page views
if ( is_admin() )
	require_once( 'admin-menu.php' ); 

// Session functions for tracking logged-in or anonymous users during quiz.
//require_once( 'functions-session.php' ); 

// Debug bar plugin to track progress. OK to remove this in production.
require_once( 'functions-debug.php' ); 

// Class definitions for all question types to be registered
require_once( 'class.WPSkillz_Question.php' );
require_once( 'class.WPSkillz_Question_MultiChoice.php' );
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
		$question_type_class = "WPSkillz_Question_{$question_type}";
		if ( $question_type && class_exists( $question_type_class ) )
			$question_post = new $$question_type_class( $post );
		else 
			$question_post = new WPSkillz_Question( $post );
	}

}

/**
 * Enqueues plugin styles on front end views
 *
 * This is mostly handled on WPSkillz_Question::enqueue_scripts for question pages,
 * but there are a couple other pages we need those styles for, such as the start quiz
 * page and the leaderboards page. Although its not great style, we'll enqueue that 
 * stylesheet on every page, because otherwise there's no logical way or determining 
 * where we need it short of preprocessing every page to see if it has one of the
 * shortcodes on it.
 */
add_action( 'wp_enqueue_scripts', 'wpskillz_enqueue_frontend_stylesheet' );

function wpskillz_enqueue_frontend_stylesheet() {
	wp_enqueue_style( 'wpskillz', plugins_url( '/css/wpskillz.css', __FILE__ ) );
}

