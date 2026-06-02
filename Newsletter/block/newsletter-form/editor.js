( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var RichText = wp.blockEditor.RichText;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;

	registerBlockType( 'greenberry/newsletter-form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps( {
				className: 'greenberry-newsletter-form',
			} );

			function setAttribute( key ) {
				return function ( value ) {
					var update = {};
					update[ key ] = value;
					props.setAttributes( update );
				};
			}

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form', 'greenberry' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Heading', 'greenberry' ),
							value: attributes.heading,
							onChange: setAttribute( 'heading' ),
						} ),
						el( TextareaControl, {
							label: __( 'Description', 'greenberry' ),
							value: attributes.description,
							onChange: setAttribute( 'description' ),
						} ),
						el( TextControl, {
							label: __( 'Button label', 'greenberry' ),
							value: attributes.buttonLabel,
							onChange: setAttribute( 'buttonLabel' ),
						} ),
						el( ToggleControl, {
							label: __( 'Show name field', 'greenberry' ),
							checked: attributes.showName,
							onChange: setAttribute( 'showName' ),
						} ),
						el( TextControl, {
							label: __( 'Tags', 'greenberry' ),
							help: __( 'Comma-separated tags applied to new contacts.', 'greenberry' ),
							value: attributes.tags,
							onChange: setAttribute( 'tags' ),
						} ),
						el( TextareaControl, {
							label: __( 'Consent text', 'greenberry' ),
							value: attributes.consentText,
							onChange: setAttribute( 'consentText' ),
						} ),
						el( TextControl, {
							label: __( 'Success message', 'greenberry' ),
							value: attributes.successMessage,
							onChange: setAttribute( 'successMessage' ),
						} )
					)
				),
				el( RichText, {
					tagName: 'h2',
					className: 'greenberry-newsletter-form__heading',
					value: attributes.heading,
					allowedFormats: [],
					placeholder: __( 'Newsletter heading', 'greenberry' ),
					onChange: setAttribute( 'heading' ),
				} ),
				el( RichText, {
					tagName: 'p',
					className: 'greenberry-newsletter-form__description',
					value: attributes.description,
					allowedFormats: [],
					placeholder: __( 'Short description', 'greenberry' ),
					onChange: setAttribute( 'description' ),
				} ),
				el(
					'div',
					{ className: 'greenberry-newsletter-form__form' },
					el(
						'div',
						{ className: 'greenberry-newsletter-form__fields' },
						attributes.showName
							? el(
									'label',
									{ className: 'greenberry-newsletter-form__field' },
									el( 'span', null, __( 'Name', 'greenberry' ) ),
									el( 'input', { type: 'text', disabled: true } )
								)
							: null,
						el(
							'label',
							{ className: 'greenberry-newsletter-form__field' },
							el( 'span', null, __( 'Email', 'greenberry' ) ),
							el( 'input', { type: 'email', disabled: true } )
						)
					),
					el(
						'label',
						{ className: 'greenberry-newsletter-form__consent' },
						el( 'input', { type: 'checkbox', disabled: true } ),
						el( RichText, {
							tagName: 'span',
							value: attributes.consentText,
							allowedFormats: [],
							placeholder: __( 'Consent text', 'greenberry' ),
							onChange: setAttribute( 'consentText' ),
						} )
					),
					el(
						'div',
						{ className: 'greenberry-newsletter-form__submit-row' },
						el(
							'button',
							{ type: 'button', className: 'greenberry-newsletter-form__button' },
							el( RichText, {
								tagName: 'span',
								value: attributes.buttonLabel,
								allowedFormats: [],
								placeholder: __( 'Subscribe', 'greenberry' ),
								onChange: setAttribute( 'buttonLabel' ),
							} )
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
