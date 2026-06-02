( function () {
	var labels = window.greenberryNewsletterAdmin || {};

	function text( element ) {
		return element ? element.value.trim() : '';
	}

	function renderContent( target, value ) {
		var paragraphs = value
			.split( /\n{2,}/ )
			.map( function ( paragraph ) {
				return paragraph.trim();
			} )
			.filter( Boolean );

		target.innerHTML = '';

		if ( ! paragraphs.length ) {
			var empty = document.createElement( 'p' );
			empty.textContent = labels.defaultContent || 'Write campaign content to preview the email body.';
			target.appendChild( empty );
			return;
		}

		paragraphs.forEach( function ( paragraph ) {
			var node = document.createElement( 'p' );
			node.textContent = paragraph;
			target.appendChild( node );
		} );
	}

	function bindComposer( composer ) {
		var subject = composer.querySelector( '[data-greenberry-newsletter-subject]' );
		var preheader = composer.querySelector( '[data-greenberry-newsletter-preheader]' );
		var content = composer.querySelector( '[data-greenberry-newsletter-content]' );
		var previewSubject = composer.querySelector( '[data-greenberry-newsletter-preview-subject]' );
		var previewPreheader = composer.querySelector( '[data-greenberry-newsletter-preview-preheader]' );
		var previewContent = composer.querySelector( '[data-greenberry-newsletter-preview-content]' );

		function updatePreview() {
			if ( previewSubject ) {
				previewSubject.textContent = text( subject ) || labels.defaultSubject || 'Campaign subject';
			}

			if ( previewPreheader ) {
				previewPreheader.textContent = text( preheader ) || labels.defaultPreheader || 'Preheader text appears here.';
			}

			if ( previewContent ) {
				renderContent( previewContent, text( content ) );
			}
		}

		[ subject, preheader, content ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', updatePreview );
			}
		} );

		updatePreview();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-greenberry-newsletter-composer]' ).forEach( bindComposer );
	} );
} )();
