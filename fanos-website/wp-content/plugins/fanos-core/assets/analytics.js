/**
 * FANOS analytics event bindings.
 *
 * Fires GA4 events for the success measures in the questionnaire: document downloads,
 * YouTube click-throughs, newsletter signups, and form submissions. No personal data
 * is sent; only the interaction and a coarse label.
 */
( function () {
	'use strict';

	if ( typeof window.gtag !== 'function' || ! window.FANOS_ANALYTICS ) {
		return;
	}

	var events = window.FANOS_ANALYTICS.events || {};

	function label( el ) {
		return (
			el.getAttribute( 'data-fanos-label' ) ||
			el.getAttribute( 'aria-label' ) ||
			( el.textContent || '' ).trim().slice( 0, 80 ) ||
			el.getAttribute( 'href' ) ||
			'unknown'
		);
	}

	function bindClick( selector, eventName ) {
		if ( ! selector ) {
			return;
		}
		document.querySelectorAll( selector ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				window.gtag( 'event', eventName, { label: label( el ) } );
			} );
		} );
	}

	function bindSubmit( selector, eventName ) {
		if ( ! selector ) {
			return;
		}
		document.querySelectorAll( selector ).forEach( function ( el ) {
			el.addEventListener( 'submit', function () {
				window.gtag( 'event', eventName, {
					label: el.getAttribute( 'data-fanos-label' ) || 'form',
				} );
			} );
		} );
	}

	bindClick( events.file_download, 'file_download' );
	bindClick( events.youtube_click, 'youtube_click' );
	bindSubmit( events.newsletter_signup, 'newsletter_signup' );
	bindSubmit( events.form_submit, 'form_submit' );
}() );
