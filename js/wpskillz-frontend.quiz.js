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
          console.log(r);
          response = JSON.parse(r);
          return $('#wpskillz-quiz-answers').html(response['answer_section_text']);
        }
      });
      return false;
    });
  });

}).call(this);
