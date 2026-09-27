/**
 * RMD Opening Hours – front-end behaviour.
 *
 * The server embeds absolute timestamps (opening intervals, notice windows).
 * This script only compares them with the visitor's clock, so pages served
 * from a page cache stay correct. Once the embedded horizon has passed, fresh
 * data is fetched from the public REST endpoint.
 */
( function () {
	'use strict';

	const STORAGE_KEY = 'rmdOhDismissed';
	const TICK_MS = 30000;

	function nowSeconds() {
		return Math.floor( Date.now() / 1000 );
	}

	function format( template, args ) {
		let index = 0;
		return String( template ).replace(
			/%(\d+\$)?s/g,
			function ( match, position ) {
				const i = position ? parseInt( position, 10 ) - 1 : index++;
				return args[ i ] !== undefined ? args[ i ] : '';
			}
		);
	}

	function parseJSON( value, fallback ) {
		try {
			return value ? JSON.parse( value ) : fallback;
		} catch {
			return fallback;
		}
	}

	/**
	 * Local calendar date (YYYY-MM-DD) of a timestamp in the site's timezone.
	 * @param {number} seconds  Unix timestamp.
	 * @param {string} timeZone IANA timezone name.
	 * @return {string} Date as YYYY-MM-DD.
	 */
	function localDate( seconds, timeZone ) {
		const date = new Date( seconds * 1000 );
		try {
			return new Intl.DateTimeFormat( 'en-CA', {
				timeZone,
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
			} ).format( date );
		} catch {
			return date.toISOString().slice( 0, 10 );
		}
	}

	function fetchJSON( url ) {
		if ( ! window.fetch ) {
			return Promise.reject( new Error( 'fetch unavailable' ) );
		}
		return window
			.fetch( url, { credentials: 'omit', cache: 'no-store' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} );
	}

	/* ---------------------------------------------------------------- */
	/* Open now                                                           */
	/* ---------------------------------------------------------------- */

	function initStatus( element ) {
		const state = {
			intervals: parseJSON(
				element.getAttribute( 'data-intervals' ),
				[]
			),
			labels: parseJSON( element.getAttribute( 'data-labels' ), {} ),
			tz: element.getAttribute( 'data-tz' ) || 'UTC',
			horizon:
				parseInt( element.getAttribute( 'data-horizon' ), 10 ) || 0,
			showNext: element.getAttribute( 'data-show-next' ) !== '0',
			endpoint: element.getAttribute( 'data-endpoint' ),
			refreshing: false,
		};
		const textNode = element.querySelector( '.rmd-oh-status__text' );

		function compute( now ) {
			let current = null;
			let next = null;
			state.intervals.forEach( function ( iv ) {
				if ( iv[ 0 ] <= now && now < iv[ 1 ] ) {
					if ( ! current || iv[ 1 ] > current[ 1 ] ) {
						current = iv;
					}
				} else if (
					iv[ 0 ] > now &&
					( ! next || iv[ 0 ] < next[ 0 ] )
				) {
					next = iv;
				}
			} );
			return { open: !! current, current, next };
		}

		function text( result, now ) {
			const l = state.labels;
			const base = result.open ? l.open : l.closed;
			if ( ! state.showNext ) {
				return base;
			}
			if ( result.open && result.current ) {
				return (
					base +
					l.separator +
					format( l.closes_at, [ result.current[ 4 ] ] )
				);
			}
			if ( ! result.open && result.next ) {
				const today = localDate( now, state.tz );
				const tomorrow = localDate( now + 86400, state.tz );
				const day = result.next[ 2 ];
				const time = result.next[ 3 ];
				if ( day === today ) {
					return (
						base + l.separator + format( l.opens_today, [ time ] )
					);
				}
				if ( day === tomorrow ) {
					return (
						base +
						l.separator +
						format( l.opens_tomorrow, [ time ] )
					);
				}
				return (
					base +
					l.separator +
					format( l.opens_on, [ result.next[ 5 ], time ] )
				);
			}
			return base;
		}

		function refresh() {
			if ( state.refreshing || ! state.endpoint ) {
				return;
			}
			state.refreshing = true;
			fetchJSON( state.endpoint )
				.then( function ( data ) {
					state.intervals = data.intervals || [];
					state.labels = data.labels || state.labels;
					state.horizon = data.horizon || state.horizon;
					state.tz = data.tz || state.tz;
					render();
				} )
				.catch( function () {} )
				.then( function () {
					state.refreshing = false;
				} );
		}

		function render() {
			const now = nowSeconds();
			if ( state.horizon && now > state.horizon ) {
				refresh();
				return;
			}
			const result = compute( now );
			element.classList.toggle( 'is-open', result.open );
			element.classList.toggle( 'is-closed', ! result.open );
			if ( textNode ) {
				textNode.textContent = text( result, now );
			}
		}

		render();
		window.setInterval( render, TICK_MS );
	}

	/* ---------------------------------------------------------------- */
	/* Notices                                                            */
	/* ---------------------------------------------------------------- */

	function readDismissed() {
		try {
			const stored = parseJSON(
				window.localStorage.getItem( STORAGE_KEY ),
				{}
			);
			return stored && typeof stored === 'object' ? stored : {};
		} catch {
			return {};
		}
	}

	function writeDismissed( map ) {
		try {
			const now = nowSeconds();
			const pruned = {};
			Object.keys( map ).forEach( function ( key ) {
				if ( map[ key ] > now ) {
					pruned[ key ] = map[ key ];
				}
			} );
			window.localStorage.setItem(
				STORAGE_KEY,
				JSON.stringify( pruned )
			);
		} catch {
			// Storage unavailable (private mode); dismissals simply don't persist.
		}
	}

	function initNotices( container ) {
		let horizon =
			parseInt( container.getAttribute( 'data-horizon' ), 10 ) || 0;
		const endpoint = container.getAttribute( 'data-endpoint' );
		const dismissible =
			container.getAttribute( 'data-dismissible' ) === '1';
		let refreshing = false;

		function items() {
			return Array.prototype.slice.call(
				container.querySelectorAll( '.rmd-oh-notice' )
			);
		}

		function render() {
			const now = nowSeconds();
			if ( horizon && now > horizon ) {
				refresh();
				return;
			}
			const dismissed = readDismissed();
			let anyVisible = false;
			items().forEach( function ( item ) {
				const from =
					parseInt( item.getAttribute( 'data-from' ), 10 ) || 0;
				const until =
					parseInt( item.getAttribute( 'data-until' ), 10 ) || 0;
				const key = item.getAttribute( 'data-key' );
				const visible =
					from <= now &&
					now < until &&
					! ( dismissible && dismissed[ key ] );
				item.hidden = ! visible;
				anyVisible = anyVisible || visible;
			} );
			container.classList.toggle( 'has-visible', anyVisible );
		}

		function bind() {
			items().forEach( function ( item ) {
				const button = item.querySelector( '.rmd-oh-notice__dismiss' );
				if ( ! button || button.__rmdOhBound ) {
					return;
				}
				button.__rmdOhBound = true;
				button.addEventListener( 'click', function () {
					const map = readDismissed();
					map[ item.getAttribute( 'data-key' ) ] =
						parseInt( item.getAttribute( 'data-until' ), 10 ) ||
						nowSeconds() + 86400;
					writeDismissed( map );
					render();
				} );
			} );
		}

		function refresh() {
			if ( refreshing || ! endpoint ) {
				return;
			}
			refreshing = true;
			fetchJSON( endpoint )
				.then( function ( data ) {
					const parser = new window.DOMParser();
					const doc = parser.parseFromString(
						String( data.html || '' ),
						'text/html'
					);
					const fresh = doc.querySelector( '[data-rmd-oh-notices]' );
					if ( fresh ) {
						container.innerHTML = fresh.innerHTML;
						horizon =
							parseInt(
								fresh.getAttribute( 'data-horizon' ),
								10
							) || 0;
						bind();
						render();
					}
				} )
				.catch( function () {} )
				.then( function () {
					refreshing = false;
				} );
		}

		bind();
		render();
		window.setInterval( render, TICK_MS * 2 );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-rmd-oh-status]' ),
			initStatus
		);
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-rmd-oh-notices]' ),
			initNotices
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
