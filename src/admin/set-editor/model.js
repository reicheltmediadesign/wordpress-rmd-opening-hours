/**
 * Shapes, defaults and small helpers shared by the editor components. Mirrors
 * the PHP Validator; PHP remains the authority on save.
 */

export const WEEKDAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

export function uuid() {
	if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
		return window.crypto.randomUUID();
	}
	const bytes = new Uint8Array( 16 );
	window.crypto.getRandomValues( bytes );
	// eslint-disable-next-line no-bitwise
	bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40;
	// eslint-disable-next-line no-bitwise
	bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80;
	const hex = Array.from( bytes, ( b ) =>
		b.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	return `${ hex.slice( 0, 8 ) }-${ hex.slice( 8, 12 ) }-${ hex.slice( 12, 16 ) }-${ hex.slice( 16, 20 ) }-${ hex.slice( 20 ) }`;
}

export function emptyDay() {
	return { mode: 'closed', slots: [], text: '', note: '' };
}

export function emptyWeek() {
	return Object.fromEntries( WEEKDAYS.map( ( day ) => [ day, emptyDay() ] ) );
}

export function normalizeDay( raw ) {
	const day = { ...emptyDay(), ...( raw || {} ) };
	day.slots = Array.isArray( day.slots )
		? day.slots.map( ( slot ) => ( {
				start: slot?.start || '',
				end: slot?.end || '',
			} ) )
		: [];
	return day;
}

export function normalizeWeek( raw ) {
	const week = emptyWeek();
	WEEKDAYS.forEach( ( key ) => {
		week[ key ] = normalizeDay( raw?.[ key ] );
	} );
	return week;
}

export function newPeriod( today ) {
	return {
		uid: uuid(),
		name: '',
		start: today,
		end: today,
		recurring: false,
		mode: 'closed',
		day: {
			mode: 'open',
			slots: [ { start: '10:00', end: '14:00' } ],
			text: '',
			note: '',
		},
		week: emptyWeek(),
		note: '',
		lead_days: null,
		ignore_holidays: false,
	};
}

export function normalizePeriod( raw, today ) {
	const base = newPeriod( today );
	return {
		...base,
		...raw,
		uid: raw?.uid || base.uid,
		day: normalizeDay( raw?.day || base.day ),
		week: normalizeWeek( raw?.week ),
		lead_days:
			raw?.lead_days === null ||
			raw?.lead_days === undefined ||
			raw?.lead_days === ''
				? null
				: parseInt( raw.lead_days, 10 ),
	};
}

export function normalizeSet( raw, config ) {
	const holidays = raw?.holidays || {};
	return {
		regular: normalizeWeek( raw?.regular ),
		periods: Array.isArray( raw?.periods )
			? raw.periods.map( ( p ) => normalizePeriod( p, config.today ) )
			: [],
		holidays: {
			state: holidays.state || config.defaultState,
			rules: Object.fromEntries(
				Object.entries( holidays.rules || {} ).map(
					( [ id, rule ] ) => [
						id,
						{
							mode: rule?.mode || 'closed',
							day: normalizeDay( rule?.day ),
						},
					]
				)
			),
			regional: Array.isArray( holidays.regional )
				? holidays.regional
				: [],
		},
		display: { ...config.defaults.display, ...( raw?.display || {} ) },
		schema: { ...config.defaults.schema, ...( raw?.schema || {} ) },
	};
}

/**
 * Completes short input to HH:MM: "14" → "14:00", "9" → "09:00",
 * "1430" → "14:30", "14.30" / "14,30" → "14:30", "8:5" → "08:05".
 * Returns the input unchanged when it is not recognisable as a time.
 *
 * @param {string} value
 * @return {string} Normalized time.
 */
export function normalizeTime( value ) {
	const raw = ( value || '' ).trim();
	let match = /^(\d{1,2})$/.exec( raw );
	if ( match ) {
		return `${ match[ 1 ].padStart( 2, '0' ) }:00`;
	}
	match = /^(\d{1,2})(\d{2})$/.exec( raw );
	if ( match ) {
		return `${ match[ 1 ].padStart( 2, '0' ) }:${ match[ 2 ] }`;
	}
	match = /^(\d{1,2})[:.,](\d{1,2})$/.exec( raw );
	if ( match ) {
		return `${ match[ 1 ].padStart( 2, '0' ) }:${ match[ 2 ].padStart( 2, '0' ) }`;
	}
	return value;
}

export function isValidTime( value, allowMidnight = false ) {
	if ( allowMidnight && value === '24:00' ) {
		return true;
	}
	return /^([01]\d|2[0-3]):[0-5]\d$/.test( value || '' );
}

export function isValidDate( value ) {
	if ( ! /^\d{4}-\d{2}-\d{2}$/.test( value || '' ) ) {
		return false;
	}
	const date = new Date( `${ value }T00:00:00Z` );
	return (
		! Number.isNaN( date.getTime() ) &&
		date.toISOString().slice( 0, 10 ) === value
	);
}

export function periodStatus( period, today ) {
	if ( period.recurring ) {
		return 'recurring';
	}
	if ( period.end < today ) {
		return 'past';
	}
	if ( period.start <= today ) {
		return 'running';
	}
	return 'upcoming';
}
