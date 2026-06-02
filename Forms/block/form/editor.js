( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var registerBlockVariation = wp.blocks.registerBlockVariation;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Notice = wp.components.Notice;

	var FIELD_TYPES = [
		{ label: __( 'Single line text', 'greenberry' ), value: 'text' },
		{ label: __( 'Paragraph', 'greenberry' ), value: 'paragraph' },
		{ label: __( 'Date', 'greenberry' ), value: 'date' },
		{ label: __( 'Signature', 'greenberry' ), value: 'signature' },
		{ label: __( 'Checkbox', 'greenberry' ), value: 'checkbox' },
		{ label: __( 'Option', 'greenberry' ), value: 'option' },
		{ label: __( 'File upload', 'greenberry' ), value: 'file' },
	];

	var FILE_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp,.pdf,.doc,.docx';

	var FORM_TEMPLATE = [
		[
			'greenberry/form-field',
			{
				label: __( 'Text field', 'greenberry' ),
				key: 'text_field',
				type: 'text',
				required: true,
				placeholder: __( 'Enter text', 'greenberry' ),
			},
		],
		[
			'greenberry/form-field',
			{
				label: __( 'Paragraph field', 'greenberry' ),
				key: 'paragraph_field',
				type: 'paragraph',
				required: true,
				placeholder: __( 'Enter details', 'greenberry' ),
			},
		],
		[
			'greenberry/form-field',
			{
				label: __( 'Checkbox field', 'greenberry' ),
				key: 'checkbox_field',
				type: 'checkbox',
				required: true,
			},
		],
	];

	var FIELD_PRESETS = [
		{
			name: 'text',
			title: __( 'Text Field', 'greenberry' ),
			description: __( 'A clean single-line text field.', 'greenberry' ),
			icon: 'editor-textcolor',
			attributes: {
				label: __( 'Text field', 'greenberry' ),
				key: 'text_field',
				type: 'text',
				required: false,
				placeholder: __( 'Enter text', 'greenberry' ),
			},
		},
		{
			name: 'paragraph',
			title: __( 'Paragraph Field', 'greenberry' ),
			description: __( 'A clean multi-line paragraph field.', 'greenberry' ),
			icon: 'editor-paragraph',
			attributes: {
				label: __( 'Paragraph field', 'greenberry' ),
				key: 'paragraph_field',
				type: 'paragraph',
				required: false,
				placeholder: __( 'Enter details', 'greenberry' ),
			},
		},
		{
			name: 'date',
			title: __( 'Date Field', 'greenberry' ),
			description: __( 'A clean date picker field.', 'greenberry' ),
			icon: 'calendar-alt',
			attributes: {
				label: __( 'Date field', 'greenberry' ),
				key: 'date_field',
				type: 'date',
				required: false,
			},
		},
		{
			name: 'signature',
			title: __( 'Signature Field', 'greenberry' ),
			description: __( 'A clean typed signature field.', 'greenberry' ),
			icon: 'edit',
			attributes: {
				label: __( 'Signature', 'greenberry' ),
				key: 'signature',
				type: 'signature',
				required: false,
				placeholder: __( 'Type your name', 'greenberry' ),
			},
		},
		{
			name: 'checkbox',
			title: __( 'Checkbox Field', 'greenberry' ),
			description: __( 'A clean checkbox confirmation field.', 'greenberry' ),
			icon: 'yes-alt',
			attributes: {
				label: __( 'Checkbox field', 'greenberry' ),
				key: 'checkbox_field',
				type: 'checkbox',
				required: false,
			},
		},
		{
			name: 'option',
			title: __( 'Option Field', 'greenberry' ),
			description: __( 'A clean option selector field.', 'greenberry' ),
			icon: 'list-view',
			attributes: {
				label: __( 'Option field', 'greenberry' ),
				key: 'option_field',
				type: 'option',
				required: false,
				options: __( 'Option one\nOption two\nOption three', 'greenberry' ),
			},
		},
		{
			name: 'file',
			title: __( 'File Upload Field', 'greenberry' ),
			description: __( 'Accepts images, PDF, and Word documents.', 'greenberry' ),
			icon: 'upload',
			attributes: {
				label: __( 'Attachment', 'greenberry' ),
				key: 'attachment',
				type: 'file',
				required: false,
				maxFileSize: 10,
			},
		},
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

	function normalizeFieldType( type ) {
		if ( 'textarea' === type || 'address' === type ) {
			return 'paragraph';
		}

		if ( 'email' === type ) {
			return 'text';
		}

		return FIELD_TYPES.some( function ( fieldType ) {
			return fieldType.value === type;
		} )
			? type
			: 'text';
	}

	function normalizeOptions( value ) {
		if ( Array.isArray( value ) ) {
			value = value.join( '\n' );
		}

		return String( value || '' )
			.split( /\r?\n/ )
			.map( function ( option ) {
				return option.trim();
			} )
			.filter( function ( option ) {
				return option.length;
			} )
			.join( '\n' );
	}

	function getFieldAttributes( attributes ) {
		var type = normalizeFieldType( attributes.type || 'text' );
		var label = attributes.label || __( 'Field label', 'greenberry' );
		var key = attributes.key || slugify( label ) || 'field';

		return {
			label: label,
			key: key,
			type: type,
			required: !! attributes.required,
			placeholder: attributes.placeholder || '',
			helpText: attributes.helpText || '',
			maxFileSize: parseInt( attributes.maxFileSize, 10 ) || 10,
			options: normalizeOptions( attributes.options || __( 'Option one\nOption two\nOption three', 'greenberry' ) ),
		};
	}

	function renderFieldPreview( field ) {
		var requiredMark = field.required
			? el( 'span', { className: 'greenberry-form__required', 'aria-hidden': true }, '*' )
			: null;
		var label = el( 'span', { className: 'greenberry-form__label-text' }, field.label, requiredMark );
		var input;

		if ( 'paragraph' === field.type ) {
			input = el( 'textarea', {
				disabled: true,
				rows: 5,
				placeholder: field.placeholder,
			} );
		} else if ( 'date' === field.type ) {
			input = el( 'input', { type: 'date', disabled: true } );
		} else if ( 'signature' === field.type ) {
			input = el( 'input', {
				type: 'text',
				disabled: true,
				placeholder: field.placeholder || __( 'Type your name', 'greenberry' ),
			} );
		} else if ( 'checkbox' === field.type ) {
			return el(
				'span',
				{
					className:
						'greenberry-form__field greenberry-form__field--checkbox greenberry-form__field--editor',
				},
				el(
					'span',
					{ className: 'greenberry-form__checkbox-row' },
					el( 'input', { type: 'checkbox', disabled: true } ),
					label
				),
				field.helpText
					? el( 'span', { className: 'greenberry-form__help' }, field.helpText )
					: null
			);
		} else if ( 'option' === field.type ) {
			input = el(
				'select',
				{ disabled: true },
				normalizeOptions( field.options )
					.split( '\n' )
					.map( function ( option ) {
						return el( 'option', { key: option, value: option }, option );
					} )
			);
		} else if ( 'file' === field.type ) {
			input = el( 'input', { type: 'file', disabled: true, accept: FILE_ACCEPT } );
		} else {
			input = el( 'input', {
				type: 'text',
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
		apiVersion: 3,
		title: __( 'Form Field', 'greenberry' ),
		description: __( 'A field inside a Greenberry form.', 'greenberry' ),
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
			options: {
				type: 'string',
				default: __( 'Option one\nOption two\nOption three', 'greenberry' ),
			},
			maxFileSize: {
				type: 'number',
				default: 10,
			},
		},
		supports: {
			html: false,
			reusable: false,
		},
		__experimentalLabel: function ( attributes ) {
			var label = attributes && attributes.label ? String( attributes.label ).trim() : '';
			return label || __( 'Form Field', 'greenberry' );
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
							__next40pxDefaultSize: true,
							label: __( 'Type', 'greenberry' ),
							value: field.type,
							options: FIELD_TYPES,
							onChange: function ( value ) {
								props.setAttributes( { type: normalizeFieldType( value ) } );
							},
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
						-1 !== [ 'text', 'paragraph', 'signature' ].indexOf( field.type )
							? el( TextControl, {
									label: __( 'Placeholder', 'greenberry' ),
									value: field.placeholder,
									onChange: setAttribute( props, 'placeholder' ),
								} )
							: null,
						'option' === field.type
							? el( TextareaControl, {
									label: __( 'Options', 'greenberry' ),
									help: __( 'One option per line.', 'greenberry' ),
									value: field.options,
									onChange: function ( value ) {
										props.setAttributes( { options: normalizeOptions( value ) } );
									},
								} )
							: null,
						'file' === field.type
							? el( TextControl, {
									label: __( 'Maximum file size (MB)', 'greenberry' ),
									type: 'number',
									min: 1,
									max: 25,
									value: field.maxFileSize,
									onChange: function ( value ) {
										props.setAttributes( { maxFileSize: parseInt( value, 10 ) || 1 } );
									},
								} )
							: null,
						'file' === field.type
							? el(
									Notice,
									{ status: 'info', isDismissible: false },
									__( 'Accepts images (JPG, PNG, GIF, WebP), PDF, and Word (DOC, DOCX). Uploads are emailed, then deleted — never stored.', 'greenberry' )
								)
							: null,
						el( TextareaControl, {
							label: __( 'Help text', 'greenberry' ),
							value: field.helpText,
							onChange: setAttribute( props, 'helpText' ),
						} )
					)
				),
				renderFieldPreview( field )
			);
		},
		save: function () {
			return null;
		},
	} );

	if ( registerBlockVariation ) {
		FIELD_PRESETS.forEach( function ( preset ) {
			registerBlockVariation( 'greenberry/form-field', {
				name: preset.name,
				title: preset.title,
				description: preset.description,
				icon: preset.icon,
				attributes: preset.attributes,
				scope: [ 'inserter', 'block' ],
				isActive: [ 'type', 'key' ],
			} );
		} );
	}

	registerBlockType( 'greenberry/form', {
		apiVersion: 3,
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
							__next40pxDefaultSize: true,
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
									__next40pxDefaultSize: true,
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
