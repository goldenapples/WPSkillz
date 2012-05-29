
Design Considerations:
======================

I built this with several primary considerations in mind:

* 	**Extensibility** - The simple multiple choice questions can only go so far
	in determining expertise. So, while I started with traditional
	multiple-choice questions as the first step in this plugin, I built out the
	WP_Skillz_Question class with the intention that it could be extended by
	other classes to offer different types of questions, appropriate for
	different types of tests.

* 	**Teachability** - While I love testing my knowledge on quizzes and skills
	tests, I often feel like its a waste of time if the question doesn't at
	least teach me something new if I happen to get it wrong. Too many questions
	involve judgement calls on the test writer's part, and if I'm told that my
	answer is _wrong_, I want to know why, so that I can research it and dispute
	the question if necessary.

	So I included two features in this quiz toward that goal:

	+	Each question in the WP_Skillz_Question_MultiChoice class has an
		'explanation' field, to be displayed on an incorrect guess. In addition,
		there is also space to enter separate explanations for each of the
		incorrect answers, to explain exactly _why_ they are wrong.

