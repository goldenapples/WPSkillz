(function() {

  jQuery(document).ready(function($) {
    var eConverter, eEditor, qConverter, qEditor;
    qConverter = Markdown.getSanitizingConverter();
    qEditor = new Markdown.Editor(qConverter, '-question');
    qEditor.run();
    qEditor.refreshPreview();
    qConverter.hooks.chain('postConversion', function(text) {
      jQuery('#quiz-q-html').val(text);
      return text;
    });
    eConverter = Markdown.getSanitizingConverter();
    eEditor = new Markdown.Editor(eConverter, '-explanation');
    eEditor.run();
    eEditor.refreshPreview();
    return eConverter.hooks.chain('postConversion', function(text) {
      jQuery('#quiz-e-html').val(text);
      return text;
    });
  });

}).call(this);
