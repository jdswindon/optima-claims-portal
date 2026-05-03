/**
 * Full-size portal images in the claim meta box (secure admin-ajax URLs are not real file paths,
 * so core Thickbox does not size them correctly). Uses a simple overlay + img with viewport caps.
 */
( function () {
	'use strict';

	var cfg = window.optimaClaimsPortalLightbox || { closeLabel: 'Close' };

	document.body.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( 'a.optima-claims-portal-lightbox' );
		if ( ! a || ! document.body.contains( a ) ) {
			return;
		}
		e.preventDefault();
		var src = a.getAttribute( 'href' );
		if ( ! src ) {
			return;
		}
		var caption = a.getAttribute( 'title' ) || '';

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'optima-claims-portal-lightbox-backdrop';
		backdrop.setAttribute( 'role', 'dialog' );
		backdrop.setAttribute( 'aria-modal', 'true' );

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'optima-claims-portal-lightbox-backdrop__close';
		closeBtn.setAttribute( 'aria-label', cfg.closeLabel );
		closeBtn.innerHTML = '\u00D7';

		var inner = document.createElement( 'div' );
		inner.className = 'optima-claims-portal-lightbox-backdrop__inner';

		var img = document.createElement( 'img' );
		img.className = 'optima-claims-portal-lightbox-backdrop__img';
		img.src = src;
		img.alt = caption;

		inner.appendChild( img );
		backdrop.appendChild( inner );
		backdrop.appendChild( closeBtn );

		function remove() {
			document.removeEventListener( 'keydown', onKey );
			if ( backdrop.parentNode ) {
				backdrop.parentNode.removeChild( backdrop );
			}
		}

		function onKey( ev ) {
			if ( ev.key === 'Escape' ) {
				remove();
			}
		}

		document.addEventListener( 'keydown', onKey );
		closeBtn.addEventListener( 'click', function ( ev ) {
			ev.stopPropagation();
			remove();
		} );
		backdrop.addEventListener( 'click', function () {
			remove();
		} );
		inner.addEventListener( 'click', function ( ev ) {
			ev.stopPropagation();
		} );

		document.body.appendChild( backdrop );
		closeBtn.focus();
	} );
}() );
