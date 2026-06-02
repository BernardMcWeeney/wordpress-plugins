( function () {
	function serializeForm( form ) {
		var data = {};
		var formData = new FormData( form );

		formData.forEach( function ( value, key ) {
			data[ key ] = value;
		} );

		return data;
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var wrapper = form.closest( '.greenberry-newsletter-form' );

		if ( ! wrapper || ! window.fetch ) {
			return;
		}

		event.preventDefault();

		var status = wrapper.querySelector( '.greenberry-newsletter-form__status' );
		var button = form.querySelector( 'button[type="submit"]' );
		var endpoint = wrapper.getAttribute( 'data-endpoint' );

		if ( status ) {
			status.textContent = '';
		}

		if ( button ) {
			button.disabled = true;
		}

		fetch( endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( serializeForm( form ) ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					if ( ! response.ok ) {
						throw body;
					}

					return body;
				} );
			} )
			.then( function ( body ) {
				if ( status ) {
					status.textContent = body.message || wrapper.getAttribute( 'data-success-message' );
				}

				form.reset();
			} )
			.catch( function ( error ) {
				if ( status ) {
					status.textContent =
						( error && error.message ) ||
						( error && error.data && error.data.message ) ||
						'Please check the form and try again.';
				}
			} )
			.finally( function () {
				if ( button ) {
					button.disabled = false;
				}
			} );
	} );
} )();
