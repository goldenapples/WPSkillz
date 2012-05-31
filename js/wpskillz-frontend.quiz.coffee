jQuery(document).ready ($) ->
	$('.answer-text').on 'click', () ->
		$.ajax
			type: 'POST'
			url: wpSkillz.ajaxURL + '?' + wpSkillz.sessionID
			data:
				action: 'wpskillz_answer'
				question: wpSkillz.thisQuestion
				guess: $(this).data('answer')
			success: (r) ->
				response = JSON.parse r
				$('#wpskillz-quiz-answers').html response['answer_section_text']
				if response['comments_section'] and $('#comments')
					$('#comments').html response['comments_section']
		false

