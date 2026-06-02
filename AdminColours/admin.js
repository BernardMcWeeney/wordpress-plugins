( function( $ ) {
	$( function() {
		$( '.greenberry-color-picker' ).wpColorPicker();

		function syncCustomColourState() {
			var source = $( '[data-greenberry-colour-source] input:checked' ).val();
			var customPanel = $( '[data-greenberry-custom-colours]' );
			var isCustom = 'custom' === source;

			customPanel.toggleClass( 'is-disabled', ! isCustom );
			customPanel.find( 'input' ).prop( 'disabled', ! isCustom );
		}

		$( '[data-greenberry-colour-source] input' ).on( 'change', syncCustomColourState );
		syncCustomColourState();
	} );
}( jQuery ) );
