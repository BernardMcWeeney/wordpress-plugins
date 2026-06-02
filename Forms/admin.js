( function () {
	function getNextIndex( tbody ) {
		var rows = tbody.querySelectorAll( '.greenberry-form-field-row' );
		var max = -1;

		rows.forEach( function ( row ) {
			row.querySelectorAll( '[name^="fields["]' ).forEach( function ( input ) {
				var match = input.name.match( /^fields\[(\d+)\]/ );
				if ( match ) {
					max = Math.max( max, parseInt( match[ 1 ], 10 ) );
				}
			} );
		} );

		return max + 1;
	}

	document.addEventListener( 'click', function ( event ) {
		var addButton = event.target.closest( '[data-greenberry-add-field]' );
		if ( addButton ) {
			event.preventDefault();

			var template = document.getElementById( 'greenberry-field-template' );
			var tbody = document.querySelector( '[data-greenberry-fields]' );
			if ( ! template || ! tbody ) {
				return;
			}

			var index = getNextIndex( tbody );
			var wrapper = document.createElement( 'tbody' );
			wrapper.innerHTML = template.innerHTML.replaceAll( '__INDEX__', index );
			tbody.appendChild( wrapper.firstElementChild );
			return;
		}

		var removeButton = event.target.closest( '[data-greenberry-remove-field]' );
		if ( removeButton ) {
			event.preventDefault();

			var row = removeButton.closest( '.greenberry-form-field-row' );
			var tbody = row ? row.parentNode : null;
			if ( row && tbody && tbody.querySelectorAll( '.greenberry-form-field-row' ).length > 1 ) {
				row.remove();
			} else if ( row ) {
				row.querySelectorAll( 'input[type="text"], input[type="number"]' ).forEach( function ( input ) {
					input.value = '';
				} );
				row.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( input ) {
					input.checked = false;
				} );
			}
		}
	} );
} )();
