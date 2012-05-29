<?php

class WPSkillz_Question_MultiChoice extends WPSkillz_question {

	public function __construct( &$post ) {
		parent::__construct( $post );
	}

	function title_progress( $title ) {
		parent::title_progress( $title );
	}

	function setup_meta_boxes() {
		parent::setup_meta_boxes();
	}

	function check_answer() {
		parent:: check_answer();
	}

}
