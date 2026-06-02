( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	var data = window.greenberryNewsletterPosts || { postTypes: [], categories: [] };

	function setAttr( props, key ) {
		return function ( value ) {
			var update = {};
			update[ key ] = value;
			props.setAttributes( update );
		};
	}

	registerBlockType( 'greenberry/newsletter-posts', {
		apiVersion: 3,
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps( { className: 'greenberry-newsletter-posts-editor' } );
			var postTypeOptions = ( data.postTypes && data.postTypes.length )
				? data.postTypes
				: [ { label: __( 'Posts', 'greenberry' ), value: 'post' } ];
			var categoryOptions = [ { label: __( 'All categories', 'greenberry' ), value: 0 } ].concat(
				data.categories || []
			);

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Posts to show', 'greenberry' ), initialOpen: true },
						el( SelectControl, {
							__next40pxDefaultSize: true,
							label: __( 'Post type', 'greenberry' ),
							value: attributes.postType,
							options: postTypeOptions,
							onChange: setAttr( props, 'postType' ),
						} ),
						el( SelectControl, {
							__next40pxDefaultSize: true,
							label: __( 'Category', 'greenberry' ),
							help: __( 'Categories apply to standard posts.', 'greenberry' ),
							value: attributes.category,
							options: categoryOptions,
							onChange: function ( value ) {
								props.setAttributes( { category: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of posts', 'greenberry' ),
							value: attributes.count,
							min: 1,
							max: 20,
							onChange: function ( value ) {
								props.setAttributes( { count: parseInt( value, 10 ) || 1 } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show featured image', 'greenberry' ),
							checked: !! attributes.showImage,
							onChange: setAttr( props, 'showImage' ),
						} ),
						el( ToggleControl, {
							label: __( 'Show excerpt', 'greenberry' ),
							checked: !! attributes.showExcerpt,
							onChange: setAttr( props, 'showExcerpt' ),
						} ),
						el( ToggleControl, {
							label: __( 'Show "Read more" button', 'greenberry' ),
							checked: !! attributes.showButton,
							onChange: setAttr( props, 'showButton' ),
						} )
					)
				),
				ServerSideRender
					? el( ServerSideRender, {
							block: 'greenberry/newsletter-posts',
							attributes: attributes,
						} )
					: el(
							'p',
							{ style: { color: '#646970', fontStyle: 'italic' } },
							__( 'The latest posts will appear here in the email.', 'greenberry' )
						)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
