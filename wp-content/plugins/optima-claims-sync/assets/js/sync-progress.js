/**
 * Streamed manual sync progress (NDJSON) and live-update the summary line.
 */
( function () {
	'use strict';

	var cfg = window.optimaClaimsSync;
	if ( ! cfg || ! cfg.ajaxUrl || ! cfg.nonce ) {
		return;
	}

	var summary = document.getElementById( 'optima-claims-sync-summary' );
	var summaryText = document.getElementById( 'optima-claims-sync-summary-text' );
	var spinner = document.getElementById( 'optima-claims-sync-spinner' );
	var form = document.getElementById( 'optima-claims-run-sync-form' );
	if ( ! summary || ! form ) {
		return;
	}
	if ( ! summaryText ) {
		summaryText = summary;
	}

	var S = cfg.strings || {};

	var lastPhase = '';
	var lastFetchMsg = null;
	var pulseTimer = null;
	var pulseStartedAt = 0;

	function clearPulse() {
		if ( pulseTimer ) {
			window.clearInterval( pulseTimer );
			pulseTimer = null;
		}
	}

	function startPulse() {
		clearPulse();
		pulseStartedAt = Date.now();
		pulseTimer = window.setInterval( function () {
			var sec = Math.floor( ( Date.now() - pulseStartedAt ) / 1000 );
			if ( lastPhase === 'fetch_api' ) {
				var tpl = S.fetchingElapsed || S.fetching || 'Fetching claims from API… (%d s)';
				summaryText.textContent = tpl.replace( '%d', String( sec ) );
			} else if ( lastPhase === 'fetch_page' && lastFetchMsg ) {
				summaryText.textContent = formatFetchPage( lastFetchMsg ) + ' (' + sec + 's)';
			}
		}, 1000 );
	}

	function setSpinner( on ) {
		if ( ! spinner ) {
			return;
		}
		if ( on ) {
			spinner.classList.add( 'is-active' );
		} else {
			spinner.classList.remove( 'is-active' );
		}
	}

	function formatStats( stats ) {
		var tpl = S.statsInline || '%1$d created, %2$d updated, %3$d skipped.';
		var c = stats.created != null ? String( stats.created ) : '0';
		var u = stats.updated != null ? String( stats.updated ) : '0';
		var k = stats.skipped != null ? String( stats.skipped ) : '0';
		return tpl.replace( /%1\$[sd]/g, c ).replace( /%2\$[sd]/g, u ).replace( /%3\$[sd]/g, k );
	}

	function formatComplete( msg ) {
		var prefix = S.lastSyncPrefix || 'Last sync finished at (UTC):';
		var utc = msg.finished_at_utc || '';
		var st = msg.stats || {};
		return prefix + ' ' + utc + ' — ' + formatStats( st );
	}

	function formatFetchPage( msg ) {
		var hp = msg.http_page != null ? String( msg.http_page ) : '';
		var items = msg.items_so_far != null ? String( msg.items_so_far ) : '0';
		var tp = msg.total_pages != null ? parseInt( msg.total_pages, 10 ) : 0;
		if ( tp > 0 ) {
			var tplOf = S.fetchPageOf || 'API page %1$s of %2$s (%3$s claims retrieved)…';
			return tplOf.replace( /%1\$[sd]/g, hp ).replace( /%2\$[sd]/g, String( tp ) ).replace( /%3\$[sd]/g, items );
		}
		var tpl = S.fetchPage || 'API page %1$s (%2$s claims retrieved)…';
		return tpl.replace( /%1\$[sd]/g, hp ).replace( /%2\$[sd]/g, items );
	}

	function formatProgress( msg ) {
		if ( msg.phase === 'fetch_api' ) {
			return S.fetching || 'Fetching claims from API…';
		}
		if ( msg.phase === 'fetch_page' ) {
			return formatFetchPage( msg );
		}
		if ( msg.phase === 'api_done' ) {
			var n = msg.total != null ? String( msg.total ) : '0';
			var tpl = S.apiReturned || 'API returned %s claim(s). Saving…';
			return tpl.replace( '%s', n );
		}
		if ( msg.phase === 'process' ) {
			var cur = msg.current != null ? String( msg.current ) : '0';
			var tot = msg.total != null ? String( msg.total ) : '0';
			var tpl2 = S.processing || 'Processing claim %1$s of %2$s…';
			var line = tpl2.replace( /%1\$[sd]/g, cur ).replace( /%2\$[sd]/g, tot );
			var st = msg.stats || {};
			return line + ' ' + formatStats( st );
		}
		return '';
	}

	function applyProgress( msg ) {
		lastPhase = msg.phase || '';
		if ( lastPhase === 'fetch_page' ) {
			lastFetchMsg = msg;
		} else if ( lastPhase === 'api_done' || lastPhase === 'process' ) {
			clearPulse();
		}
		var line = formatProgress( msg );
		if ( line !== '' ) {
			summaryText.textContent = line;
		}
	}

	form.addEventListener( 'submit', function ( e ) {
		if ( typeof window.fetch !== 'function' || typeof window.ReadableStream === 'undefined' ) {
			return;
		}
		e.preventDefault();

		var btn = form.querySelector( 'button[type="submit"], input[type="submit"]' );
		var prevDisabled = btn ? btn.disabled : false;
		if ( btn ) {
			btn.disabled = true;
		}

		lastPhase = 'fetch_api';
		lastFetchMsg = null;
		summaryText.textContent = S.fetching || 'Fetching claims from API…';
		setSpinner( true );
		startPulse();

		var fd = new window.FormData();
		fd.append( 'action', 'optima_claims_sync_stream' );
		fd.append( 'nonce', cfg.nonce );

		var streamFinishedOk = false;

		window
			.fetch( cfg.ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin',
			} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				if ( ! res.body || ! res.body.getReader ) {
					throw new Error( 'no stream' );
				}
				var reader = res.body.getReader();
				var dec = new window.TextDecoder();
				var buf = '';
				function pump() {
					return reader.read().then( function ( chunk ) {
						if ( chunk.done ) {
							if ( ! streamFinishedOk ) {
								clearPulse();
								setSpinner( false );
								summaryText.textContent = S.syncFailed || 'Sync failed.';
							}
							return;
						}
						buf += dec.decode( chunk.value, { stream: true } );
						var ix;
						while ( ( ix = buf.indexOf( '\n' ) ) !== -1 ) {
							var line = buf.slice( 0, ix ).trim();
							buf = buf.slice( ix + 1 );
							if ( ! line ) {
								continue;
							}
							var msg;
							try {
								msg = JSON.parse( line );
							} catch ( err ) {
								continue;
							}
							if ( msg.type === 'ready' ) {
								continue;
							}
							if ( msg.type === 'progress' ) {
								applyProgress( msg );
							} else if ( msg.type === 'complete' ) {
								streamFinishedOk = true;
								clearPulse();
								setSpinner( false );
								summaryText.textContent = formatComplete( msg );
							} else if ( msg.type === 'error' ) {
								streamFinishedOk = true;
								clearPulse();
								setSpinner( false );
								summaryText.textContent = ( msg.message || S.syncFailed || 'Sync failed.' ) + '';
							}
						}
						return pump();
					} );
				}
				return pump();
			} )
			.catch( function () {
				streamFinishedOk = true;
				clearPulse();
				setSpinner( false );
				summaryText.textContent = S.syncFailed || 'Sync failed.';
			} )
			.then( function () {
				if ( btn ) {
					btn.disabled = prevDisabled;
				}
			} );
	} );
}() );
