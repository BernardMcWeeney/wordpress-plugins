( function ( wp, data ) {
	if ( ! wp || ! wp.plugins || ! ( wp.editPost || wp.editor ) || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var registerPlugin = wp.plugins.registerPlugin;
	var editPost = wp.editPost || wp.editor;
	var PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
	var PluginPrePublishPanel = editPost.PluginPrePublishPanel;
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

	function getProviderLabels( selectedChannels ) {
		return selectedChannels.length
			? selectedChannels
					.map( function ( provider ) {
						return data.providers[ provider ].label;
					} )
					.join( ', ' )
			: __( 'No channels selected', 'greenberry' );
	}

	function useSocialState() {
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
		var meta = editorState.meta || {};
		var mode = meta[ metaKey ] || 'inherit';
		var customMessage = meta[ messageKey ] || '';
		var selectedChannels = selectedChannelsFromMeta( meta );
		var context = {
			siteName: data.siteName || '',
			title: getTitleText( editorState.title ),
			url: editorState.permalink || data.homeUrl || '',
			excerpt: getExcerptText( editorState.excerpt ),
			author: editorState.author || '',
			date: editorState.date || '',
		};
		var preview = replaceTokens( customMessage || data.messageTemplate, context );

		return {
			meta: meta,
			mode: mode,
			customMessage: customMessage,
			selectedChannels: selectedChannels,
			hasExplicitChannels: Array.isArray( meta[ channelsKey ] ) && meta[ channelsKey ].length > 0,
			metaKey: metaKey,
			channelsKey: channelsKey,
			messageKey: messageKey,
			preview: preview,
		};
	}

	function getChecks( state ) {
		var checks = [];

		if ( ! data.enabled ) {
			checks.push( {
				status: 'error',
				label: __( 'Social publishing is disabled in Greenberry settings.', 'greenberry' ),
			} );
		}

		if ( 'off' === state.mode ) {
			checks.push( {
				status: 'info',
				label: __( 'This post is set not to publish socially.', 'greenberry' ),
			} );
		}

		if ( data.enabled && 'off' !== state.mode && ! state.selectedChannels.length ) {
			checks.push( {
				status: 'error',
				label: __( 'No ready social channels are selected.', 'greenberry' ),
			} );
		}

		if ( data.enabled && 'off' !== state.mode && ! state.preview ) {
			checks.push( {
				status: 'error',
				label: __( 'The social message preview is empty.', 'greenberry' ),
			} );
		}

		if ( -1 !== state.selectedChannels.indexOf( 'bluesky' ) && state.preview.length > 300 ) {
			checks.push( {
				status: 'warning',
				label: __( 'Bluesky copy is over 300 characters.', 'greenberry' ),
			} );
		}

		if ( ! checks.length ) {
			checks.push( {
				status: 'success',
				label: __( 'Social publishing is ready for the selected channels.', 'greenberry' ),
			} );
		}

		return checks;
	}

	function Checklist( props ) {
		return el(
			'ul',
			{ className: 'greenberry-social-checklist' },
			getChecks( props.state ).map( function ( check, index ) {
				return el(
					'li',
					{ key: index, className: 'is-' + check.status },
					el( 'span', { 'aria-hidden': true }, 'success' === check.status ? 'OK' : 'error' === check.status ? 'X' : '!' ),
					el( 'span', null, check.label )
				);
			} )
		);
	}

	function SocialPreview( props ) {
		var state = props.state;

		return el(
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
					el( 'em', null, getProviderLabels( state.selectedChannels ) )
				)
			),
			el( 'p', null, state.preview || __( 'Preview appears after adding post copy.', 'greenberry' ) ),
			el(
				'div',
				{ className: 'greenberry-social-preview__meta' },
				sprintf(
					/* translators: %d: character count. */
					__( '%d characters', 'greenberry' ),
					state.preview.length
				)
			)
		);
	}

	function SocialPanel() {
		var state = useSocialState();
		var editPostDispatch = useDispatch( 'core/editor' ).editPost;
		var providerKeys = Object.keys( data.providers || {} );

		function updateMeta( key, value ) {
			var next = {};
			Object.keys( state.meta ).forEach( function ( metaName ) {
				next[ metaName ] = state.meta[ metaName ];
			} );
			next[ key ] = value;
			editPostDispatch( { meta: next } );
		}

		function updateChannel( provider, checked ) {
			var next = state.selectedChannels.slice();
			if ( checked && -1 === next.indexOf( provider ) ) {
				next.push( provider );
			}
			if ( ! checked ) {
				next = next.filter( function ( item ) {
					return item !== provider;
				} );
			}
			updateMeta( state.channelsKey, next );
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
				value: state.mode,
				options: [
					{ label: __( 'Use Social rules', 'greenberry' ), value: 'inherit' },
					{ label: __( 'Publish this post', 'greenberry' ), value: 'on' },
					{ label: __( 'Do not publish', 'greenberry' ), value: 'off' },
				],
				onChange: function ( value ) {
					updateMeta( state.metaKey, value );
				},
			} ),
			el(
				'div',
				{ className: 'greenberry-social-editor__channels' },
				providerKeys.map( function ( provider ) {
					var item = data.providers[ provider ];
					return el(
						'div',
						{ className: 'greenberry-social-channel', key: provider },
						el( ToggleControl, {
							label: item.label,
							help: item.status,
							checked: -1 !== state.selectedChannels.indexOf( provider ),
							disabled: 'off' === state.mode || ! data.enabled || ! item.ready,
							onChange: function ( checked ) {
								updateChannel( provider, checked );
							},
						} )
					);
				} )
			),
			state.hasExplicitChannels
				? el(
						Button,
						{
							variant: 'secondary',
							isSmall: true,
							onClick: function () {
								updateMeta( state.channelsKey, [] );
							},
						},
						__( 'Use defaults', 'greenberry' )
					)
				: null,
			el( TextareaControl, {
				label: __( 'Custom message', 'greenberry' ),
				help: __( 'Leave empty to use the Social settings template.', 'greenberry' ),
				value: state.customMessage,
				rows: 5,
				onChange: function ( value ) {
					updateMeta( state.messageKey, value );
				},
			} ),
			el( Checklist, { state: state } ),
			el( SocialPreview, { state: state } )
		);
	}

	function PrePublishChecks() {
		var state = useSocialState();

		if ( ! PluginPrePublishPanel ) {
			return null;
		}

		return el(
			PluginPrePublishPanel,
			{
				title: __( 'Social Checklist', 'greenberry' ),
				initialOpen: false,
			},
			el( Checklist, { state: state } ),
			el(
				'p',
				{ className: 'greenberry-social-prepublish-preview' },
				state.preview || __( 'No social copy is ready.', 'greenberry' )
			)
		);
	}

	registerPlugin( 'greenberry-social', {
		render: function () {
			return el( Fragment, null, el( SocialPanel ), el( PrePublishChecks ) );
		},
	} );
} )( window.wp, window.greenberrySocialEditor );
