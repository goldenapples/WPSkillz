jQuery(document).ready ($) ->
	converter = Markdown.getSanitizingConverter()
	editor = new Markdown.Editor(converter)
	editor.run()
	editor.refreshPreview()
	converter.hooks.chain 'postConversion', (text) ->
		jQuery('#quiz-q-html').val text
		text
