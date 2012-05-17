<?php

add_action( 'init', 'wpskillz_post_type_registration' );

function wpskillz_post_type_registration() {
	register_post_type( 'quiz',
		array(
			'labels'		=> array(
				'name'			=> __( 'Quizzes' ),
				'singular_name'	=> __( 'Quiz' ),
				'add_new'		=> __( 'Add New' ),
				'add_new_item'	=> __( 'Add New quizzes' ),
				'edit'			=> __( 'Edit' ),
				'edit_item'		=> __( 'Edit quizzes' ),
				'new_item'		=> __( 'New quizzes' ),
				'view'			=> __( 'View quizzes' ),
				'view_item'		=> __( 'View quizzes' ),
				'search_items'	=> __( 'Search quizzes' ),
				'not_found'		=> __( 'No quizzes Found' ),
				'not_found_in_trash'		=> __( 'No quizzes Found in trash' ),
				'parent'		=> __( '' ),
			),
			'public'		=> true,
			'show_ui'		=> true,
			'register_meta_box_cb'	=> 'wpskillz_quiz_meta_boxes',
			'menu_position'	=> 5,
			'has_archive'	=> true,
			'publicly_queryable'		=> true,
			'rewrite'		=> array('slug'=>'quiz'),
			'supports'		=> array( 
				'custom_fields'
			)
		)
	);
} 



/*
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


