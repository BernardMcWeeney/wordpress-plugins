( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var RangeControl = wp.components.RangeControl;
	var Notice = wp.components.Notice;

	var FIELD_TYPES = [
		{ label: __( 'Text', 'greenberry' ), value: 'text' },
		{ label: __( 'Email', 'greenberry' ), value: 'email' },
		{ label: __( 'Long text', 'greenberry' ), value: 'textarea' },
		{ label: __( 'Address', 'greenberry' ), value: 'address' },
		{ label: __( 'Checkbox', 'greenberry' ), value: 'checkbox' },
		{ label: __( 'File upload', 'greenberry' ), value: 'file' },
	];

	var FORM_TEMPLATE = [
		[
			'greenberry/form-field',
			{
				label: __( 'Name', 'greenberry' ),
				key: 'name',
				type: 'text',
				required: true,
			},
		],
		[
			'greenberry/form-field',
			{
				label: __( 'Email address', 'greenberry' ),
				key: 'email',
				type: 'email',
				required: true,
				helpText: __( 'Used only to reply to this enquiry.', 'greenberry' ),
			},
		],
		[
			'greenberry/form-field',
			{
				label: __( 'Message', 'greenberry' ),
				key: 'message',
				type: 'textarea',
				required: true,
			},
		],
		[
			'greenberry/form-field',
			{
				label: __( 'I consent to this information being emailed to the site owner for the purpose of responding to my enquiry.', 'greenberry' ),
				key: 'privacy_consent',
				type: 'checkbox',
				required: true,
			},
		],
	];

	function getForms() {
		return ( window.greenberryFormsBlock && window.greenberryFormsBlock.forms ) || [];
	}

	function getSelectedForm( formId ) {
		var forms = getForms();
		var selected = forms.filter( function ( form ) {
			return parseInt( form.id, 10 ) === parseInt( formId, 10 );
		} );

		return selected.length ? selected[ 0 ] : forms[ 0 ];
	}

	function setAttribute( props, key ) {
		return function ( value ) {
			var update = {};
			update[ key ] = value;
			props.setAttributes( update );
		};
	}

	function slugify( value ) {
		return String( value || '' )
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}

	function getFieldAttributes( attributes ) {
		var type = attributes.type || 'text';
		var label = attributes.label || __( 'Field label', 'greenberry' );
		var key = attributes.key || slugify( label ) || 'field';

		return {
			label: label,
			key: key,
			type: FIELD_TYPES.some( function ( fieldType ) {
				return fieldType.value === type;
			} )
				? type
				: 'text',
			required: !! attributes.required,
			placeholder: attributes.placeholder || '',
			helpText: attributes.helpText || '',
			accept: attributes.accept || '',
			maxFileSize: parseInt( attributes.maxFileSize, 10 ) || 5,
		};
	}

	function renderFieldPreview( field ) {
		var label = el(
			'span',
			{ className: 'greenberry-form__label-text' },
			field.label,
			field.required
				? el( 'span', { className: 'greenberry-form__required', 'aria-hidden': true }, '*' )
				: null
		);
		var input;

		if ( 'textarea' === field.type || 'address' === field.type ) {
			input = el( 'textarea', {
				disabled: true,
				rows: 'address' === field.type ? 3 : 5,
				placeholder: field.placeholder,
			} );
		} else if ( 'checkbox' === field.type ) {
			input = el(
				'span',
				{ className: 'greenberry-form__checkbox-row' },
				el( 'input', { type: 'checkbox', disabled: true } ),
				el( 'span', null, __( 'Confirmed', 'greenberry' ) )
			);
		} else if ( 'file' === field.type ) {
			input = el( 'input', { type: 'file', disabled: true } );
		} else {
			input = el( 'input', {
				type: 'email' === field.type ? 'email' : 'text',
				disabled: true,
				placeholder: field.placeholder,
			} );
		}

		return el(
			'label',
			{
				className:
					'greenberry-form__field greenberry-form__field--' +
					field.type +
					' greenberry-form__field--editor',
			},
			label,
			input,
			field.helpText
				? el( 'span', { className: 'greenberry-form__help' }, field.helpText )
				: null
		);
	}

	function renderSavedFormPreview( selectedForm ) {
		if ( ! selectedForm ) {
			return el(
				'p',
				{ className: 'greenberry-form__description' },
				__( 'Create a form in Greenberry Forms or switch this block to visual builder mode.', 'greenberry' )
			);
		}

		return el(
			'div',
			{ className: 'greenberry-form__form' },
			el(
				'div',
				{ className: 'greenberry-form__fields' },
				( selectedForm.fields || [] ).map( function ( field ) {
					return el(
						'div',
						{ key: field.key || field.label },
						renderFieldPreview( getFieldAttributes( field ) )
					);
				} )
			),
			el(
				'div',
				{ className: 'greenberry-form__submit-row' },
				el(
					'button',
					{ type: 'button', className: 'greenberry-form__button' },
					selectedForm.submitLabel || __( 'Submit', 'greenberry' )
				)
			)
		);
	}

	registerBlockType( 'greenberry/form-field', {
		title: __( 'Form Field', 'greenberry' ),
		description: __( 'A field inside a Greenberry visual form.', 'greenberry' ),
		parent: [ 'greenberry/form' ],
		category: 'widgets',
		icon: 'editor-table',
		attributes: {
			label: {
				type: 'string',
				default: __( 'Field label', 'greenberry' ),
			},
			key: {
				type: 'string',
				default: '',
			},
			type: {
				type: 'string',
				default: 'text',
			},
			required: {
				type: 'boolean',
				default: false,
			},
			placeholder: {
				type: 'string',
				default: '',
			},
			helpText: {
				type: 'string',
				default: '',
			},
			accept: {
				type: 'string',
				default: '',
			},
			maxFileSize: {
				type: 'number',
				default: 5,
			},
		},
		supports: {
			html: false,
			reusable: false,
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var field = getFieldAttributes( attributes );
			var blockProps = useBlockProps( {
				className: 'greenberry-form-field-editor greenberry-form-field-editor--' + field.type,
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Field', 'greenberry' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Type', 'greenberry' ),
							value: field.type,
							options: FIELD_TYPES,
							onChange: setAttribute( props, 'type' ),
						} ),
						el( TextControl, {
							label: __( 'Label', 'greenberry' ),
							value: attributes.label,
							onChange: function ( value ) {
								var update = { label: value };
								if ( ! attributes.key ) {
									update.key = slugify( value );
								}
								props.setAttributes( update );
							},
						} ),
						el( TextControl, {
							label: __( 'Field key', 'greenberry' ),
							help: __( 'Used in email templates, for example {email}.', 'greenberry' ),
							value: field.key,
							onChange: function ( value ) {
								props.setAttributes( { key: slugify( value ) } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Required', 'greenberry' ),
							checked: field.required,
							onChange: setAttribute( props, 'required' ),
						} ),
						'checkbox' !== field.type && 'file' !== field.type
							? el( TextControl, {
									label: __( 'Placeholder', 'greenberry' ),
									value: field.placeholder,
									onChange: setAttribute( props, 'placeholder' ),
								} )
							: null,
						el( TextareaControl, {
							label: __( 'Help text', 'greenberry' ),
							value: field.helpText,
							onChange: setAttribute( props, 'helpText' ),
						} ),
						'file' === field.type
							? el(
									Fragment,
									null,
									el( TextControl, {
										label: __( 'Accepted file types', 'greenberry' ),
										help: __( 'Example: .pdf,.jpg,.png', 'greenberry' ),
										value: field.accept,
										onChange: setAttribute( props, 'accept' ),
									} ),
									el( RangeControl, {
										label: __( 'Maximum file size', 'greenberry' ),
										value: field.maxFileSize,
										min: 1,
										max: 25,
										onChange: setAttribute( props, 'maxFileSize' ),
									} )
								)
							: null
					)
				),
				renderFieldPreview( field )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'greenberry/form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var forms = getForms();
			var mode = attributes.mode || 'saved';
			var selectedForm = getSelectedForm( attributes.formId );
			var blockProps = useBlockProps( {
				className: 'greenberry-form greenberry-form--editor',
			} );
			var formOptions = [
				{
					label: __( 'First available form', 'greenberry' ),
					value: 0,
				},
			].concat(
				forms.map( function ( form ) {
					return {
						label: form.title,
						value: form.id,
					};
				} )
			);

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form setup', 'greenberry' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Builder mode', 'greenberry' ),
							value: mode,
							options: [
								{ label: __( 'Build visually with field blocks', 'greenberry' ), value: 'visual' },
								{ label: __( 'Display a saved Greenberry form', 'greenberry' ), value: 'saved' },
							],
							onChange: setAttribute( props, 'mode' ),
						} ),
						'saved' === mode
							? el( SelectControl, {
									label: __( 'Saved form', 'greenberry' ),
									value: attributes.formId,
									options: formOptions,
									onChange: function ( value ) {
										props.setAttributes( { formId: parseInt( value, 10 ) || 0 } );
									},
								} )
							: null,
						el( ToggleControl, {
							label: __( 'Show form title', 'greenberry' ),
							checked: attributes.showTitle,
							onChange: setAttribute( props, 'showTitle' ),
						} )
					),
					'visual' === mode
						? el(
								Fragment,
								null,
								el(
									PanelBody,
									{ title: __( 'Email delivery', 'greenberry' ), initialOpen: false },
									el( TextControl, {
										label: __( 'Form title', 'greenberry' ),
										value: attributes.title,
										onChange: setAttribute( props, 'title' ),
									} ),
									el( TextareaControl, {
										label: __( 'Description', 'greenberry' ),
										value: attributes.description,
										onChange: setAttribute( props, 'description' ),
									} ),
									el( TextControl, {
										label: __( 'Send submissions to', 'greenberry' ),
										type: 'email',
										value: attributes.recipientEmail,
										onChange: setAttribute( props, 'recipientEmail' ),
									} ),
									el( TextControl, {
										label: __( 'Email subject', 'greenberry' ),
										help: __( 'Use {site_name}, {form_title}, or field keys like {email}.', 'greenberry' ),
										value: attributes.subject,
										onChange: setAttribute( props, 'subject' ),
									} ),
									el( TextControl, {
										label: __( 'Reply-To field key', 'greenberry' ),
										value: attributes.replyToField,
										onChange: function ( value ) {
											props.setAttributes( { replyToField: slugify( value ) } );
										},
									} ),
									el( TextControl, {
										label: __( 'Submitter copy field key', 'greenberry' ),
										value: attributes.copyToField,
										onChange: function ( value ) {
											props.setAttributes( { copyToField: slugify( value ) } );
										},
									} )
								),
								el(
									PanelBody,
									{ title: __( 'Messages and protection', 'greenberry' ), initialOpen: false },
									el( TextControl, {
										label: __( 'Submit button label', 'greenberry' ),
										value: attributes.submitLabel,
										onChange: setAttribute( props, 'submitLabel' ),
									} ),
									el( TextControl, {
										label: __( 'Success message', 'greenberry' ),
										value: attributes.successMessage,
										onChange: setAttribute( props, 'successMessage' ),
									} ),
									el( TextControl, {
										label: __( 'Submitter copy subject', 'greenberry' ),
										value: attributes.copySubject,
										onChange: setAttribute( props, 'copySubject' ),
									} ),
									el( TextareaControl, {
										label: __( 'Submitter copy message', 'greenberry' ),
										value: attributes.copyMessage,
										onChange: setAttribute( props, 'copyMessage' ),
									} ),
									el( ToggleControl, {
										label: __( 'Require Simple Cloudflare Turnstile', 'greenberry' ),
										checked: attributes.turnstileRequired,
										onChange: setAttribute( props, 'turnstileRequired' ),
									} )
								)
							)
						: null
				),
				'saved' === mode && ! selectedForm
					? el( Notice, { status: 'warning', isDismissible: false }, __( 'No saved forms are available.', 'greenberry' ) )
					: null,
				attributes.showTitle
					? el(
							'h2',
							{ className: 'greenberry-form__heading' },
							'visual' === mode
								? attributes.title || __( 'Contact form', 'greenberry' )
								: selectedForm && selectedForm.title
						)
					: null,
				'visual' === mode && attributes.description
					? el( 'p', { className: 'greenberry-form__description' }, attributes.description )
					: null,
				'visual' === mode
					? el(
							'div',
							{ className: 'greenberry-form__form' },
							el(
								'div',
								{ className: 'greenberry-form__fields greenberry-form__fields--editor' },
								el( InnerBlocks, {
									allowedBlocks: [ 'greenberry/form-field' ],
									template: FORM_TEMPLATE,
									renderAppender: InnerBlocks.ButtonBlockAppender,
								} )
							),
							el(
								'div',
								{ className: 'greenberry-form__submit-row' },
								el(
									'button',
									{ type: 'button', className: 'greenberry-form__button' },
									attributes.submitLabel || __( 'Send', 'greenberry' )
								)
							)
						)
					: renderSavedFormPreview( selectedForm )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );
} )( window.wp );
