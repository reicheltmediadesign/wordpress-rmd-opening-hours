/**
 * Best-effort parser that turns pasted opening hours text (a copied table,
 * a list, or prose such as "Dienstag, Donnerstag: 7 - 16 Uhr") into a week
 * of DaySpecs. German and English day names are recognised.
 */
import { WEEKDAYS, emptyDay, emptyWeek } from './model';

const DAY_ALIASES = {
	mon: [ 'montags', 'montag', 'monday', 'mon', 'mo' ],
	tue: [ 'dienstags', 'dienstag', 'tuesday', 'tues', 'tue', 'di' ],
	wed: [ 'mittwochs', 'mittwoch', 'wednesday', 'wed', 'mi' ],
	thu: [
		'donnerstags',
		'donnerstag',
		'thursday',
		'thurs',
		'thur',
		'thu',
		'do',
	],
	fri: [ 'freitags', 'freitag', 'friday', 'fri', 'fr' ],
	sat: [
		'samstags',
		'samstag',
		'sonnabends',
		'sonnabend',
		'saturday',
		'sat',
		'sa',
	],
	sun: [ 'sonntags', 'sonntag', 'sunday', 'sun', 'so' ],
};

const GROUP_ALIASES = {
	all: [
		'täglich',
		'taeglich',
		'jeden tag',
		'alle tage',
		'every day',
		'everyday',
		'daily',
	],
	weekdays: [
		'werktags',
		'werktage',
		'wochentags',
		'wochentage',
		'weekdays',
		'mo-fr',
		'mo–fr',
	],
	weekend: [ 'wochenende', 'am wochenende', 'weekends', 'weekend' ],
};

const CLOSED_WORDS = /\b(geschlossen|geschl\.?|ruhetag|closed|zu)\b/i;
const APPOINTMENT_WORDS =
	/(nach\s+vereinbarung|nach\s+absprache|auf\s+anfrage|termine?\s+nach|by\s+appointment|on\s+request|appointments?\s+only)/i;
const SKIP_WORDS =
	/\b(feiertage?n?s?|(?:public\s+|bank\s+)?holidays?|ferien)\b/i;

const escapeRegExp = ( s ) => s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

function buildTokenRegExp() {
	const entries = [];
	Object.entries( GROUP_ALIASES ).forEach( ( [ key, aliases ] ) => {
		aliases.forEach( ( alias ) =>
			entries.push( { alias, key, kind: 'group' } )
		);
	} );
	Object.entries( DAY_ALIASES ).forEach( ( [ key, aliases ] ) => {
		aliases.forEach( ( alias ) =>
			entries.push( { alias, key, kind: 'day' } )
		);
	} );
	entries.sort( ( a, b ) => b.alias.length - a.alias.length );
	const pattern = entries.map( ( e ) => escapeRegExp( e.alias ) ).join( '|' );
	return {
		entries,
		regExp: new RegExp(
			`(?<![\\p{L}])(${ pattern })(?![\\p{L}])\\.?`,
			'giu'
		),
	};
}

const TOKENS = buildTokenRegExp();

function lookupToken( text ) {
	const lower = text.toLowerCase().replace( /\.$/, '' );
	return TOKENS.entries.find( ( e ) => e.alias === lower ) || null;
}

/**
 * Splits text into day groups with the text that follows each group.
 *
 * @param {string} text
 * @return {Array<{days: string[], label: string, rest: string}>} Groups.
 */
function findGroups( text ) {
	const matches = [];
	let match;
	TOKENS.regExp.lastIndex = 0;
	while ( ( match = TOKENS.regExp.exec( text ) ) !== null ) {
		const token = lookupToken( match[ 1 ] );
		if ( token ) {
			matches.push( {
				...token,
				start: match.index,
				end: match.index + match[ 0 ].length,
				text: match[ 0 ],
			} );
		}
	}
	if ( matches.length === 0 ) {
		return [];
	}

	const isConnector = ( between ) =>
		/^[\s,./&+]*(?:(?:und|and|bis|to|-|–|—)[\s,./&+]*)*$/i.test( between );
	const isRange = ( between ) => /(-|–|—|\bbis\b|\bto\b)/i.test( between );

	const groups = [];
	let current = null;
	matches.forEach( ( m, index ) => {
		const previous = matches[ index - 1 ];
		const between = previous ? text.slice( previous.end, m.start ) : '';
		if ( current && previous && isConnector( between ) ) {
			if (
				isRange( between ) &&
				previous.kind === 'day' &&
				m.kind === 'day'
			) {
				current.days.push( ...expandRange( previous.key, m.key ) );
			} else {
				current.days.push( ...tokenDays( m ) );
			}
			current.end = m.end;
			current.label += between + m.text;
			return;
		}
		if ( current ) {
			current.rest = text.slice( current.end, m.start );
			groups.push( current );
		}
		current = { days: tokenDays( m ), label: m.text, end: m.end, rest: '' };
	} );
	current.rest = text.slice( current.end );
	groups.push( current );

	return groups.map( ( g ) => ( {
		days: WEEKDAYS.filter( ( d ) => g.days.includes( d ) ),
		label: g.label.trim(),
		rest: g.rest,
	} ) );
}

function tokenDays( token ) {
	if ( token.kind === 'day' ) {
		return [ token.key ];
	}
	if ( token.key === 'all' ) {
		return [ ...WEEKDAYS ];
	}
	if ( token.key === 'weekdays' ) {
		return WEEKDAYS.slice( 0, 5 );
	}
	return [ 'sat', 'sun' ];
}

function expandRange( from, to ) {
	const a = WEEKDAYS.indexOf( from );
	const b = WEEKDAYS.indexOf( to );
	const days = [];
	for ( let i = a; ; i = ( i + 1 ) % 7 ) {
		days.push( WEEKDAYS[ i ] );
		if ( i === b || days.length > 7 ) {
			break;
		}
	}
	return days;
}

const TIME_RANGE =
	/(\d{1,2})(?:[:.,](\d{2}))?\s*(uhr|h|a\.?m\.?|p\.?m\.?)?\s*(?:-|–|—|bis|to|until)\s*(\d{1,2})(?:[:.,](\d{2}))?\s*(uhr|h|a\.?m\.?|p\.?m\.?)?/gi;

function toMinutes( hour, minute, meridiem ) {
	let h = parseInt( hour, 10 );
	const m = minute ? parseInt( minute, 10 ) : 0;
	if ( Number.isNaN( h ) || h > 24 || m > 59 ) {
		return null;
	}
	if ( meridiem ) {
		const mer = meridiem.toLowerCase().replace( /\./g, '' );
		if ( mer === 'pm' && h < 12 ) {
			h += 12;
		} else if ( mer === 'am' && h === 12 ) {
			h = 0;
		}
	}
	return h * 60 + m;
}

const fmt = ( minutes ) =>
	`${ String( Math.floor( minutes / 60 ) ).padStart( 2, '0' ) }:${ String( minutes % 60 ).padStart( 2, '0' ) }`;

/**
 * Extracts time slots from a piece of text.
 *
 * @param {string} text
 * @return {{slots: Array<{start: string, end: string}>, remainder: string}} Slots and the text without them.
 */
export function parseSlots( text ) {
	const slots = [];
	const remainder = text.replace(
		TIME_RANGE,
		( all, h1, m1, mer1, h2, m2, mer2 ) => {
			const isMeridiem = ( v ) => v && /m/i.test( v );
			const start = toMinutes( h1, m1, isMeridiem( mer1 ) ? mer1 : null );
			const startMeridiem = isMeridiem( mer1 ) ? mer1 : null;
			const endMeridiem = isMeridiem( mer2 ) ? mer2 : startMeridiem;
			let end = toMinutes( h2, m2, endMeridiem );
			if ( start === null || end === null ) {
				return ' ';
			}
			if ( end === 0 ) {
				end = 24 * 60; // "22 - 0 Uhr" means until midnight.
			}
			if ( end <= start && ! isMeridiem( mer2 ) ) {
				// "9 - 5" means 09:00–17:00; "18 - 2" stays overnight.
				const candidate = end + 12 * 60;
				if ( candidate > start && candidate <= 24 * 60 ) {
					end = candidate;
				}
			}
			if ( start >= 24 * 60 || end === start ) {
				return ' ';
			}
			slots.push( {
				start: fmt( start ),
				end: fmt( end === 24 * 60 ? 24 * 60 : end % ( 24 * 60 ) ),
			} );
			return ' ';
		}
	);

	const unique = [];
	slots.forEach( ( slot ) => {
		if (
			! unique.some(
				( s ) => s.start === slot.start && s.end === slot.end
			)
		) {
			unique.push( slot );
		}
	} );
	unique.sort( ( a, b ) => a.start.localeCompare( b.start ) );
	return { slots: unique, remainder };
}

function cleanText( text ) {
	return text
		.replace( /[\t\r\n]+/g, ' ' )
		.replace( /^[\s:;,.–—\-|]+|[\s:;,.–—\-|]+$/g, '' )
		.replace( /\s{2,}/g, ' ' )
		.trim();
}

/**
 * Parses the hours text of one group into a DaySpec.
 *
 * @param {string} text
 * @return {Object|null} DaySpec, or null when nothing usable was found.
 */
export function parseDaySpec( text ) {
	const { slots, remainder } = parseSlots( text );
	const leftover = cleanText(
		remainder.replace( /\b(uhr|und|and|von|from)\b/gi, ' ' )
	);

	if ( slots.length > 0 ) {
		return {
			...emptyDay(),
			mode: 'open',
			slots,
			note: leftover.length > 0 && leftover.length <= 100 ? leftover : '',
		};
	}
	if ( CLOSED_WORDS.test( text ) ) {
		return { ...emptyDay(), mode: 'closed', note: '' };
	}
	if ( APPOINTMENT_WORDS.test( text ) ) {
		return {
			...emptyDay(),
			mode: 'text',
			text: cleanText( text ).slice( 0, 200 ),
		};
	}
	const plain = cleanText( text );
	if ( plain.length > 0 && plain.length <= 60 && /\p{L}/u.test( plain ) ) {
		return { ...emptyDay(), mode: 'text', text: plain };
	}
	return null;
}

/**
 * Turns a DaySpec into the holiday default rule ({mode, day}).
 *
 * @param {Object} spec
 * @return {Object} Holiday rule.
 */
function toHolidayRule( spec ) {
	if ( spec.mode === 'open' ) {
		return { mode: 'custom', day: spec };
	}
	return { mode: 'closed', day: emptyDay() };
}

/**
 * @param {string} input Pasted text.
 * @return {{week: Object, matched: Array<{days: string[], label: string, spec: Object}>, skipped: string[], holidayDefault: Object|null, found: boolean}} Result.
 */
export function parseOpeningHours( input ) {
	const text = ( input || '' ).replace(
		new RegExp( String.fromCharCode( 160 ), 'g' ),
		' '
	);
	const week = emptyWeek();
	const matched = [];
	const skipped = [];
	let holidayDefault = null;

	// Lines about public holidays ("Feiertage geschlossen") feed the holiday default rule
	// as long as they do not also name weekdays.
	const usable = text
		.split( '\n' )
		.filter( ( line ) => {
			TOKENS.regExp.lastIndex = 0;
			if (
				SKIP_WORDS.test( line ) &&
				! TOKENS.regExp.test( line.replace( SKIP_WORDS, '' ) )
			) {
				const spec = parseDaySpec(
					line
						.replace( SKIP_WORDS, '' )
						.replace( /\b(an|am|on)\b/gi, '' )
				);
				if ( spec && spec.mode !== 'text' ) {
					holidayDefault = toHolidayRule( spec );
					matched.push( { days: [], label: line.trim(), spec } );
				} else {
					skipped.push( line.trim() );
				}
				return false;
			}
			return true;
		} )
		.join( '\n' );

	findGroups( usable ).forEach( ( group ) => {
		const spec = parseDaySpec( group.rest );
		if ( ! spec || group.days.length === 0 ) {
			if ( cleanText( group.rest ) ) {
				skipped.push( cleanText( `${ group.label } ${ group.rest }` ) );
			}
			return;
		}
		group.days.forEach( ( day ) => {
			week[ day ] = JSON.parse( JSON.stringify( spec ) );
		} );
		matched.push( { days: group.days, label: group.label, spec } );
	} );

	return {
		week,
		matched,
		skipped,
		holidayDefault,
		found: matched.length > 0,
	};
}
