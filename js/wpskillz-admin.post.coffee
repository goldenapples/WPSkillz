jQuery(document).ready ($) ->
	createMarkdownEditor = ( suffix, previewElt ) ->
		converter = Markdown.getSanitizingConverter()
		editor = new Markdown.Editor converter, suffix
		editor.run()
		editor.refreshPreview()
		if previewElt
			converter.hooks.chain 'postConversion', (text) ->
				jQuery(previewElt).val text
				text

	createMarkdownEditor '-question', '#quiz-q-html'
	createMarkdownEditor '-explanation', '#quiz-e-html'
	$('tr.wmd-panel').each ()->
		key = $(this).data 'editorkey'
		createMarkdownEditor "-#{key}", "#quiz-#{key}-html"
	null

