/**
 * Remove portal upload attachments from the claim edit screen.
 */
( function () {
	'use strict';

	var cfg = window.optimaClaimsPortalUploads || {};

	document.body.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.optima-claims-portal-remove' );
		if ( ! btn || ! document.body.contains( btn ) ) {
			return;
		}
		e.preventDefault();
		if ( ! window.confirm( cfg.confirmRemove || 'Remove this image?' ) ) {
			return;
		}

		var fd = new window.FormData();
		fd.append( 'action', cfg.deleteAction || '' );
		fd.append( 'nonce', btn.getAttribute( 'data-nonce' ) || '' );
		fd.append( 'attachment_id', btn.getAttribute( 'data-attachment-id' ) || '0' );
		fd.append( 'claim_id', btn.getAttribute( 'data-claim-id' ) || '0' );

		btn.setAttribute( 'disabled', 'disabled' );

		window
			.fetch( cfg.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: fd,
			} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				if ( ! data || ! data.success ) {
					window.alert( ( data && data.data && data.data.message ) || cfg.errorGeneric || 'Could not remove the file.' );
					btn.removeAttribute( 'disabled' );
					return;
				}
				var item = btn.closest( '.optima-claims-portal-uploads__item' );
				var wrap = document.querySelector( '.optima-claims-portal-uploads' );
				if ( item && item.parentNode ) {
					item.parentNode.removeChild( item );
				}
				if ( wrap && ! wrap.querySelector( '.optima-claims-portal-uploads__item' ) ) {
					var p = document.createElement( 'p' );
					p.className = 'description optima-claims-portal-uploads__empty';
					p.textContent = cfg.emptyMessage || '';
					wrap.parentNode.insertBefore( p, wrap );
					wrap.parentNode.removeChild( wrap );
				}
			} )
			.catch( function () {
				window.alert( cfg.errorGeneric || 'Could not remove the file.' );
				btn.removeAttribute( 'disabled' );
			} );
	} );
}() );
