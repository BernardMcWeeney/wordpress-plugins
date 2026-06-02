( function ( wp, data ) {
	if ( ! wp || ! wp.plugins || ! ( wp.editPost || wp.editor ) || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) || ( wp.editor && wp.editor.PluginDocumentSettingPanel );
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var SelectControl = wp.components.SelectControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var useDispatch = wp.data.useDispatch;
	var useSelect = wp.data.useSelect;

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	data = data || {};

	function stripTags( value ) {
		if ( ! value ) {
			return '';
		}

		return String( value ).replace( /<[^>]*>/g, '' );
	}

	function getTitleText( title ) {
		if ( 'string' === typeof title ) {
			return stripTags( title );
		}

		if ( title && title.raw ) {
			return stripTags( title.raw );
		}

		if ( title && title.rendered ) {
			return stripTags( title.rendered );
		}

		return __( 'Untitled post', 'greenberry' );
	}

	function getExcerptText( excerpt ) {
		if ( 'string' === typeof excerpt ) {
			return stripTags( excerpt );
		}

		if ( excerpt && excerpt.raw ) {
			return stripTags( excerpt.raw );
		}

		if ( excerpt && excerpt.rendered ) {
			return stripTags( excerpt.rendered );
		}

		return '';
	}

	function selectedChannelsFromMeta( meta ) {
		var key = data.channelsMeta || 'greenberry_social_channels';
		var selected = Array.isArray( meta[ key ] ) && meta[ key ].length ? meta[ key ] : data.defaultChannels || [];

		return selected.filter( function ( provider ) {
			return data.providers && data.providers[ provider ];
		} );
	}

	function replaceTokens( template, context ) {
		return String( template || '' )
			.replace( /\{site_name\}/g, context.siteName )
			.replace( /\{post_title\}/g, context.title )
			.replace( /\{post_url\}/g, context.url )
			.replace( /\{excerpt\}/g, context.excerpt )
			.replace( /\{author\}/g, context.author )
			.replace( /\{date\}/g, context.date )
			.replace( /\{hashtags\}/g, '' )
			.trim();
	}

	function initials( name ) {
		return String( name || 'G' )
			.trim()
			.split( /\s+/ )
			.map( function ( word ) {
				return word.charAt( 0 ).toUpperCase();
			} )
			.join( '' )
			.substring( 0, 2 ) || 'G';
	}

	function SocialPanel() {
		var metaKey = data.publishModeMeta || 'greenberry_social_enabled';
		var channelsKey = data.channelsMeta || 'greenberry_social_channels';
		var messageKey = data.messageMeta || 'greenberry_social_message';
		var editorState = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var user = select( 'core' ).getCurrentUser ? select( 'core' ).getCurrentUser() : null;

			return {
				meta: editor.getEditedPostAttribute( 'meta' ) || {},
				title: editor.getEditedPostAttribute( 'title' ),
				excerpt: editor.getEditedPostAttribute( 'excerpt' ),
				date: editor.getEditedPostAttribute( 'date' ) || '',
				permalink: editor.getPermalink ? editor.getPermalink() : '',
				author: user && user.name ? user.name : '',
			};
		} );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var meta = editorState.meta || {};
		var mode = meta[ metaKey ] || 'inherit';
		var customMessage = meta[ messageKey ] || '';
		var selectedChannels = selectedChannelsFromMeta( meta );
		var hasExplicitChannels = Array.isArray( meta[ channelsKey ] ) && meta[ channelsKey ].length > 0;
		var providerKeys = Object.keys( data.providers || {} );
		var context = {
			siteName: data.siteName || '',
			title: getTitleText( editorState.title ),
			url: editorState.permalink || data.homeUrl || '',
			excerpt: getExcerptText( editorState.excerpt ),
			author: editorState.author || '',
			date: editorState.date || '',
		};
		var preview = replaceTokens( customMessage || data.messageTemplate, context );

		function updateMeta( key, value ) {
			var next = {};
			Object.keys( meta ).forEach( function ( metaName ) {
				next[ metaName ] = meta[ metaName ];
			} );
			next[ key ] = value;
			editPost( { meta: next } );
		}

		function updateChannel( provider, checked ) {
			var next = selectedChannels.slice();
			if ( checked && -1 === next.indexOf( provider ) ) {
				next.push( provider );
			}
			if ( ! checked ) {
				next = next.filter( function ( item ) {
					return item !== provider;
				} );
			}
			updateMeta( channelsKey, next );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'greenberry-social',
				title: __( 'Greenberry Social', 'greenberry' ),
				className: 'greenberry-social-editor',
			},
			! data.enabled
				? el( Notice, { status: 'warning', isDismissible: false }, __( 'Social publishing is disabled.', 'greenberry' ) )
				: null,
			el( SelectControl, {
				label: __( 'Publishing', 'greenberry' ),
				value: mode,
				options: [
					{ label: __( 'Use Social rules', 'greenberry' ), value: 'inherit' },
					{ label: __( 'Publish this post', 'greenberry' ), value: 'on' },
					{ label: __( 'Do not publish', 'greenberry' ), value: 'off' },
				],
				onChange: function ( value ) {
					updateMeta( metaKey, value );
				},
			} ),
			el(
				'div',
				{ className: 'greenberry-social-editor__channels' },
				providerKeys.map( function ( provider ) {
					var item = data.providers[ provider ];
					return el( ToggleControl, {
						key: provider,
						label: item.label,
						help: item.status,
						checked: -1 !== selectedChannels.indexOf( provider ),
						disabled: 'off' === mode || ! data.enabled || ! item.ready,
						onChange: function ( checked ) {
							updateChannel( provider, checked );
						},
					} );
				} )
			),
			hasExplicitChannels
				? el(
						Button,
						{
							variant: 'secondary',
							isSmall: true,
							onClick: function () {
								updateMeta( channelsKey, [] );
							},
						},
						__( 'Use defaults', 'greenberry' )
					)
				: null,
			el( TextareaControl, {
				label: __( 'Custom message', 'greenberry' ),
				value: customMessage,
				rows: 4,
				onChange: function ( value ) {
					updateMeta( messageKey, value );
				},
			} ),
			el(
				'div',
				{ className: 'greenberry-social-preview' },
				el(
					'div',
					{ className: 'greenberry-social-preview__brand' },
					data.logoUrl
						? el( 'img', { src: data.logoUrl, alt: '' } )
						: el( 'span', null, initials( data.siteName ) ),
					el(
						'div',
						null,
						el( 'strong', null, data.siteName || __( 'Site', 'greenberry' ) ),
						el(
							'em',
							null,
							selectedChannels.length
								? selectedChannels
										.map( function ( provider ) {
											return data.providers[ provider ].label;
										} )
										.join( ', ' )
								: __( 'No channels selected', 'greenberry' )
						)
					)
				),
				el( 'p', null, preview || __( 'Preview appears after adding post copy.', 'greenberry' ) )
			)
		);
	}

	registerPlugin( 'greenberry-social', {
		render: SocialPanel,
	} );
} )( window.wp, window.greenberrySocialEditor );
