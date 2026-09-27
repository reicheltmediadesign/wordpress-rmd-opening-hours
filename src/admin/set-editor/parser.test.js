import { parseOpeningHours, parseSlots } from './parser';

const hours = ( result, day ) => {
	const spec = result.week[ day ];
	if ( spec.mode === 'open' ) {
		return spec.slots.map( ( s ) => `${ s.start }-${ s.end }` ).join( ',' );
	}
	return spec.mode === 'text' ? `text:${ spec.text }` : 'closed';
};

describe( 'parseSlots', () => {
	it( 'reads common German notations', () => {
		expect( parseSlots( '7 - 16 Uhr' ).slots ).toEqual( [
			{ start: '07:00', end: '16:00' },
		] );
		expect(
			parseSlots( '08:00 – 12:00 und 14.30 – 18 Uhr' ).slots
		).toEqual( [
			{ start: '08:00', end: '12:00' },
			{ start: '14:30', end: '18:00' },
		] );
		expect( parseSlots( 'von 9 bis 5' ).slots ).toEqual( [
			{ start: '09:00', end: '17:00' },
		] );
		expect( parseSlots( '18 - 2 Uhr' ).slots ).toEqual( [
			{ start: '18:00', end: '02:00' },
		] );
		expect( parseSlots( '22 - 0 Uhr' ).slots ).toEqual( [
			{ start: '22:00', end: '24:00' },
		] );
	} );

	it( 'reads 12-hour notation', () => {
		expect( parseSlots( '9 am - 5:30 pm' ).slots ).toEqual( [
			{ start: '09:00', end: '17:30' },
		] );
		expect( parseSlots( '10 - 11 am' ).slots ).toEqual( [
			{ start: '10:00', end: '11:00' },
		] );
	} );
} );

describe( 'parseOpeningHours', () => {
	it( 'handles day lists and ranges', () => {
		const result = parseOpeningHours(
			'Dienstag, Donnerstag: 7 - 16 Uhr\nMo, Mi und Fr: 8-12 Uhr\nSamstag geschlossen'
		);
		expect( hours( result, 'tue' ) ).toBe( '07:00-16:00' );
		expect( hours( result, 'thu' ) ).toBe( '07:00-16:00' );
		expect( hours( result, 'mon' ) ).toBe( '08:00-12:00' );
		expect( hours( result, 'wed' ) ).toBe( '08:00-12:00' );
		expect( hours( result, 'fri' ) ).toBe( '08:00-12:00' );
		expect( hours( result, 'sat' ) ).toBe( 'closed' );
		expect( hours( result, 'sun' ) ).toBe( 'closed' );
		expect( result.matched ).toHaveLength( 3 );
	} );

	it( 'handles ranges written with dash or "bis" and multiple slots', () => {
		const result = parseOpeningHours(
			'Mo–Fr 08:00–12:00, 14:00–18:00 · Sa 9–12 Uhr'
		);
		expect( hours( result, 'mon' ) ).toBe( '08:00-12:00,14:00-18:00' );
		expect( hours( result, 'fri' ) ).toBe( '08:00-12:00,14:00-18:00' );
		expect( hours( result, 'sat' ) ).toBe( '09:00-12:00' );

		const prose = parseOpeningHours(
			'Wir sind Montag bis Freitag von 8 bis 16 Uhr für Sie da.'
		);
		expect( hours( prose, 'wed' ) ).toBe( '08:00-16:00' );
	} );

	it( 'handles a pasted table with tabs', () => {
		const table =
			'Montag\t08:00 - 12:00\t14:00 - 18:00\nDienstag\t08:00 - 12:00\t\nMittwoch\tgeschlossen\nDonnerstag\tnach Vereinbarung\nFreitag\t08:00 - 13:00';
		const result = parseOpeningHours( table );
		expect( hours( result, 'mon' ) ).toBe( '08:00-12:00,14:00-18:00' );
		expect( hours( result, 'tue' ) ).toBe( '08:00-12:00' );
		expect( hours( result, 'wed' ) ).toBe( 'closed' );
		expect( hours( result, 'thu' ) ).toBe( 'text:nach Vereinbarung' );
		expect( hours( result, 'fri' ) ).toBe( '08:00-13:00' );
	} );

	it( 'understands group words and English names', () => {
		expect( hours( parseOpeningHours( 'täglich 10-22 Uhr' ), 'sun' ) ).toBe(
			'10:00-22:00'
		);
		const result = parseOpeningHours(
			'Weekdays 9am-6pm, Saturday 10am-2pm, Sunday closed'
		);
		expect( hours( result, 'thu' ) ).toBe( '09:00-18:00' );
		expect( hours( result, 'sat' ) ).toBe( '10:00-14:00' );
		expect( hours( result, 'sun' ) ).toBe( 'closed' );
	} );

	it( 'keeps extra words as note and turns holiday lines into the default rule', () => {
		const result = parseOpeningHours(
			'Mo-Fr 8-18 Uhr (nur mit Termin)\nAn Feiertagen geschlossen.'
		);
		expect( hours( result, 'mon' ) ).toBe( '08:00-18:00' );
		expect( result.week.mon.note ).toBe( '(nur mit Termin)' );
		expect( result.skipped ).toEqual( [] );
		expect( result.holidayDefault ).toEqual( {
			mode: 'closed',
			day: { mode: 'closed', slots: [], text: '', note: '' },
		} );

		const custom = parseOpeningHours( 'Feiertage: 10-12 Uhr' );
		expect( custom.holidayDefault.mode ).toBe( 'custom' );
		expect( custom.holidayDefault.day.slots ).toEqual( [
			{ start: '10:00', end: '12:00' },
		] );

		const unclear = parseOpeningHours( 'Feiertage siehe Aushang' );
		expect( unclear.holidayDefault ).toBeNull();
		expect( unclear.skipped ).toEqual( [ 'Feiertage siehe Aushang' ] );
	} );

	it( 'reports when nothing was found', () => {
		const result = parseOpeningHours(
			'Herzlich willkommen auf unserer Website.'
		);
		expect( result.found ).toBe( false );
		expect( result.matched ).toEqual( [] );
	} );
} );
