(function() {

  jQuery(document).ready(function($) {
    return $('.answer-text').on('click', function() {
      $.ajax({
        type: 'POST',
        url: wpSkillz.ajaxURL + '?' + wpSkillz.sessionID,
        data: {
          action: 'wpskillz_answer',
          question: wpSkillz.thisQuestion,
          guess: $(this).data('answer')
        },
        success: function(r) {
          var response;
          response = JSON.parse(r);
          $('#wpskillz-quiz-answers').html(response['answer_section_text']);
          if (response['comments_section'] && $('#comments')) {
            return $('#comments').html(response['comments_section']);
          }
        }
      });
      return false;
    });
  });

}).call(this);
