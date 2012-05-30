<?php

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
	static $question_slug = 'multiplechoice';

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
