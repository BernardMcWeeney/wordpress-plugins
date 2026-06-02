( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;

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

	registerBlockType( 'greenberry/form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var forms = getForms();
			var selectedForm = getSelectedForm( attributes.formId );
			var blockProps = useBlockProps( {
				className: 'greenberry-form',
			} );

			function setAttribute( key ) {
				return function ( value ) {
					var update = {};
					update[ key ] = value;
					props.setAttributes( update );
				};
			}

			var options = [
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
						{ title: __( 'Form', 'greenberry' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Saved form', 'greenberry' ),
							value: attributes.formId,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { formId: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show form title', 'greenberry' ),
							checked: attributes.showTitle,
							onChange: setAttribute( 'showTitle' ),
						} )
					)
				),
				selectedForm && attributes.showTitle
					? el( 'h2', { className: 'greenberry-form__heading' }, selectedForm.title )
					: null,
				selectedForm
					? el(
							'div',
							{ className: 'greenberry-form__form' },
							el(
								'div',
								{ className: 'greenberry-form__fields' },
								el(
									'label',
									{ className: 'greenberry-form__field' },
									el( 'span', { className: 'greenberry-form__label-text' }, __( 'Form fields render on the front end.', 'greenberry' ) ),
									el( 'input', { type: 'text', disabled: true } )
								)
							),
							el(
								'div',
								{ className: 'greenberry-form__submit-row' },
								el(
									'button',
									{ type: 'button', className: 'greenberry-form__button' },
									__( 'Submit', 'greenberry' )
								)
							)
						)
					: el( 'p', { className: 'greenberry-form__description' }, __( 'Create a form in Greenberry Forms.', 'greenberry' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
