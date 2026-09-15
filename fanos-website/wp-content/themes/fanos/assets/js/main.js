/**
 * FANOS theme front-end behaviour: an accessible mobile nav toggle and a small
 * progressive enhancement for the library filters. Everything degrades gracefully:
 * with JavaScript off, the menu is visible and the filters submit via their button.
 */
( function () {
	'use strict';

	function initNavToggle() {
		var toggle = document.querySelector( '.fanos-nav-toggle' );
		var nav = document.querySelector( '.fanos-nav' );
		if ( ! toggle || ! nav ) {
			return;
		}
		toggle.addEventListener( 'click', function () {
			var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! open ) );
			nav.classList.toggle( 'is-open', ! open );
		} );

		// Close the menu with Escape for keyboard users.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
				nav.classList.remove( 'is-open' );
				toggle.focus();
			}
		} );
	}

	function initFilterAutoSubmit() {
		var form = document.querySelector( '.fanos-filters' );
		if ( ! form ) {
			return;
		}
		// Auto-submit when a dropdown changes; keeps the visible Apply button for others.
		form.querySelectorAll( 'select' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				form.submit();
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initNavToggle();
		initFilterAutoSubmit();
	} );
}() );
