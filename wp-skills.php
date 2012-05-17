<?php
/*
Plugin Name: WP_Skillz
Description: Expert-level WordPress questions and answers
Author: Nathaniel Taintor
Author URI: http://goldenapplesdesign.com
Version: 0.1
License: GPL v2
*/

require_once( 'functions-init.php' ); // Post type registration, bootstrap and functions which should be set up on all pageloads

if ( is_admin() )
	require_once( 'functions-admin.php' ); // Admin-only functionality (since editing can be done on the front-end also, this will be very minimal )
else 
	require_once( 'display-filters.php' ); // Front-end display filters, includes answer functionality


register_activation_hook( __FILE__, 'wpskillz_activation' );

function wpskillz_activation() {
	if ( !post_type_exists( 'quiz' ) )
		wpskillz_post_type_registration();
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

