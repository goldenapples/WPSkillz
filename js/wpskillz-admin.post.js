(function() {

  jQuery(document).ready(function($) {
    var converter, editor;
    converter = Markdown.getSanitizingConverter();
    editor = new Markdown.Editor(converter);
    editor.run();
    editor.refreshPreview();
    return converter.hooks.chain('postConversion', function(text) {
      $('#quiz-q-html').val(text);
      return text;
    });
  });

}).call(this);
