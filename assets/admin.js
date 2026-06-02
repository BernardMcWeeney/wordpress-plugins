/**
 * Greenberry shared admin behaviour.
 *
 * In-page tabs: wrap controls in [data-greenberry-tabs] with
 * <button data-greenberry-tab="key"> triggers and matching
 * [data-greenberry-panel="key"] sections. All panels stay in the same form,
 * so a single Save button still submits every tab.
 */
( function () {
	'use strict';

	function initTabs( root ) {
		var buttons = Array.prototype.slice.call(
			root.querySelectorAll( '[data-greenberry-tab]' )
		);
		var panels = Array.prototype.slice.call(
			root.querySelectorAll( '[data-greenberry-panel]' )
		);

		if ( ! buttons.length || ! panels.length ) {
			return;
		}

		function activate( key ) {
			buttons.forEach( function ( button ) {
				var isActive = button.getAttribute( 'data-greenberry-tab' ) === key;
				button.classList.toggle( 'nav-tab-active', isActive );
				button.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-greenberry-panel' ) !== key;
			} );
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				activate( button.getAttribute( 'data-greenberry-tab' ) );
			} );
		} );

		var initial = root.querySelector( '[data-greenberry-tab].nav-tab-active' )
			|| buttons[ 0 ];
		activate( initial.getAttribute( 'data-greenberry-tab' ) );
	}

	function initEditorReturn() {
		var returnUrl = window.greenberryEditorReturnUrl;

		if ( ! returnUrl ) {
			return;
		}

		function wireControl( control ) {
			if ( control.dataset.greenberryReturnReady ) {
				return;
			}

			control.dataset.greenberryReturnReady = '1';

			if ( control.tagName === 'A' ) {
				control.setAttribute( 'href', returnUrl );
				return;
			}

			control.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				window.location.assign( returnUrl );
			} );
		}

		function updateControls() {
			Array.prototype.forEach.call(
				document.querySelectorAll(
					'.edit-post-fullscreen-mode-close, .edit-site-fullscreen-mode-close, a[aria-label="Back"], button[aria-label="Back"]'
				),
				wireControl
			);
		}

		updateControls();

		if ( window.MutationObserver ) {
			new MutationObserver( updateControls ).observe( document.body, {
				childList: true,
				subtree: true,
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-greenberry-tabs]' ),
			initTabs
		);

		initEditorReturn();
	} );
}() );
