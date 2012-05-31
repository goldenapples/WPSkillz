(function() {

  jQuery(document).ready(function($) {
    var createMarkdownEditor;
    createMarkdownEditor = function(suffix, previewElt) {
      var converter, editor;
      converter = Markdown.getSanitizingConverter();
      editor = new Markdown.Editor(converter, suffix);
      editor.run();
      editor.refreshPreview();
      if (previewElt) {
        return converter.hooks.chain('postConversion', function(text) {
          jQuery(previewElt).val(text);
          return text;
        });
      }
    };
    createMarkdownEditor('-question', '#quiz-q-html');
    createMarkdownEditor('-explanation', '#quiz-e-html');
    $('tr.wmd-panel').each(function() {
      var key;
      key = $(this).data('editorkey');
      return createMarkdownEditor("-" + key, "#quiz-" + key + "-html");
    });
    return null;
  });

}).call(this);
