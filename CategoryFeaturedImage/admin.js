( () => {
	const config = window.greenberryCategoryFeaturedImage || {};

	const getField = ( element ) => element.closest( '[data-greenberry-image-field]' );

	const getImageUrl = ( attachment ) => {
		if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.url || '';
	};

	const setImage = ( field, imageId, imageUrl ) => {
		const input = field.querySelector( '[data-greenberry-image-input]' );
		const preview = field.querySelector( '[data-greenberry-image-preview]' );
		const empty = field.querySelector( '[data-greenberry-image-empty]' );
		const hasImage = Boolean( imageId && imageUrl );

		if ( input ) {
			input.value = hasImage ? imageId : 0;
		}

		if ( preview ) {
			preview.src = hasImage ? imageUrl : '';
		}

		if ( empty ) {
			empty.textContent = config.emptyText || 'No image selected';
		}

		field.classList.toggle( 'has-image', hasImage );
	};

	const openFrame = ( field ) => {
		const frame = wp.media( {
			title: config.frameTitle || 'Select default featured image',
			button: {
				text: config.frameButton || 'Use this image',
			},
			library: {
				type: 'image',
			},
			multiple: false,
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			setImage( field, attachment.id, getImageUrl( attachment ) );
		} );

		frame.open();
	};

	document.addEventListener( 'click', ( event ) => {
		const chooseButton = event.target.closest( '[data-greenberry-image-choose]' );
		if ( chooseButton ) {
			event.preventDefault();
			openFrame( getField( chooseButton ) );
			return;
		}

		const removeButton = event.target.closest( '[data-greenberry-image-remove]' );
		if ( removeButton ) {
			event.preventDefault();
			setImage( getField( removeButton ), 0, '' );
		}
	} );
} )();
