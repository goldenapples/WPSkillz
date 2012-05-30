jQuery(document).ready ($) ->
	qConverter = Markdown.getSanitizingConverter()
	qEditor = new Markdown.Editor qConverter, '-question'
	qEditor.run()
	qEditor.refreshPreview()
	qConverter.hooks.chain 'postConversion', (text) ->
		jQuery('#quiz-q-html').val text
		text
	eConverter = Markdown.getSanitizingConverter()
	eEditor = new Markdown.Editor eConverter, '-explanation'
	eEditor.run()
	eEditor.refreshPreview()
	eConverter.hooks.chain 'postConversion', (text) ->
		jQuery('#quiz-e-html').val text
		text
