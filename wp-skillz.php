<?php
/*
Plugin Name: WP_Skillz
Description: Expert-level WordPress questions and answers
Author: Nathaniel Taintor
Author URI: http://goldenapplesdesign.com
Version: 0.1
License: GPL v2
*/

// Post type registration, bootstrap and functions which should be 
// set up on all pageloads
require_once( 'functions-init.php' ); 

// Admin-only functionality (since editing can be done on the 
// front-end also, this will be very minimal )
require_once( 'functions-admin.php' ); 

// Session functions for tracking logged-in or anonymous users during quiz.
require_once( 'functions-session.php' ); 

// Front-end display filters, includes answer functionality
require_once( 'display-filters.php' ); 

// Debug bar plugin to track progress
require_once( 'functions-debug.php' ); 


register_activation_hook( __FILE__, 'wpskillz_activation' );

function wpskillz_activation() {
	if ( !post_type_exists( 'quiz' ) )
		wpskillz_post_type_registration();
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
//	echo '<div class="messages updated"><p>Plugin activated. Now go set up some quiz questions.</p></div>';
}

