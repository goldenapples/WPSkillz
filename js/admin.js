(function() {

  jQuery(function($) {
    return jQuery(document).ready(function($) {
      var converter, editor;
      converter = Markdown.getSanitizingConverter();
      editor = new Markdown.Editor(converter);
      editor.run();
      return null;
    });
  });

}).call(this);
