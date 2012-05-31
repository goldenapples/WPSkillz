**WPSkillz** is my attempt at creating a smartly-designed and usable
skills-testing plugin for WordPress.

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

	+	Each question in the `WP_Skillz_Question_MultiChoice` class has an
		'explanation' field, to be displayed on an incorrect guess. In addition,
		there is also space to enter separate explanations for each of the
		incorrect answers, to explain exactly _why_ they are wrong.
	+	**Question Review** - This is the killer feature that sites like
		smarterer.com get right and most traditional quiz plugins fail at; letting
		users rank questions, edit them if necessary, and add new questions.
		Otherwise the test grows stale rapidly, and poorly-worded questions
		remain as a drag on the curve. The test becomes a judge of people's
		ability to parse confusingly-worded questions rather than expertise in
		the subject matter.

* 	**Editability** - After some hesitation, I decided to make Stack
	Overflow-flavored Markdown the default editor style. This has the benefit of
	speed in editing, familiarity, and ease of deployment - Markdown editors are
	lightweight, can be deployed on the front end as well as the administrative
	screens, and do not have issues being used in meta boxes like the
	wp_editor() function does. I know there are a few drawbacks: for a quiz
	which is specifically testing WordPress knowledge, it is a shame not to use
	as much of WordPress's standard API as possible. But I thought the benefits
	outweighed the drawbacks. And if not, its fairly easy to build another 
	question class type that registers different meta boxes on the `post.php`
	screen.

The _WordPress Way_
===================

I tried to build the plugin with the guiding principle of doing things _"the
WordPress way"_. Not the simplest way to do them inside WordPress, or the way
that a tutorial explaining how to achieve this functionality inside WordPress
would explain, but the way that these functions would look if they were hashed
out by core developers for inclusion into WP core functionality. This was
something of a thought experiment for me: a lot of the choices I ended up
making as far as object-oriented programming don't really make much sense on
their own, but I tried to imagine what a "skills test API" would look like in
WordPress, given the recent move toward adding classes and eschewing globals.

Obviously this approach - creating a class which doesn't interact with the
built-in objects in WordPress - is less than ideal. If the core development
continues in the direction that a lot of discussions seem to be heading, in
particular the discussions about creating a generic `WP_Object` class, then
`WPSkillz_Question` is well suited to extend upon that. But for now... not so
much.

Live Demo:
==========

<del>Live plugin demo populated with sample questions is online at
[wpskillz.com](http://wpskillz.com). Try your hand - registration is open and
scores will be tallied!</del> Live demo not functional yet, but it will be soon.
