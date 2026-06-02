( function () {
	function resetTurnstile() {
		if ( window.turnstile && typeof window.turnstile.reset === 'function' ) {
			window.turnstile.reset();
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var wrapper = form.closest( '.greenberry-form' );

		if ( ! wrapper || ! window.fetch ) {
			return;
		}

		event.preventDefault();

		var status = wrapper.querySelector( '.greenberry-form__status' );
		var button = form.querySelector( 'button[type="submit"]' );
		var endpoint = wrapper.getAttribute( 'data-endpoint' );
		var formData = new FormData( form );

		if ( status ) {
			status.textContent = '';
			status.classList.remove( 'greenberry-form__status--error', 'greenberry-form__status--success' );
		}

		if ( button ) {
			button.disabled = true;
		}

		fetch( endpoint, {
			method: 'POST',
			body: formData,
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
					status.classList.add( 'greenberry-form__status--success' );
				}

				form.reset();
				resetTurnstile();
			} )
			.catch( function ( error ) {
				if ( status ) {
					status.textContent =
						( error && error.message ) ||
						( error && error.data && error.data.message ) ||
						'Please check the form and try again.';
					status.classList.add( 'greenberry-form__status--error' );
				}

				resetTurnstile();
			} )
			.finally( function () {
				if ( button ) {
					button.disabled = false;
				}
			} );
	} );
} )();
