<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\Holidays;

final class HolidaysTest extends TestCase {

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function easter_dates(): array {
		return [
			'2024' => [ 2024, '2024-03-31' ],
			'2025' => [ 2025, '2025-04-20' ],
			'2026' => [ 2026, '2026-04-05' ],
			'2027' => [ 2027, '2027-03-28' ],
			'2028' => [ 2028, '2028-04-16' ],
			'2029' => [ 2029, '2029-04-01' ],
			'2030' => [ 2030, '2030-04-21' ],
		];
	}

	/**
	 * @dataProvider easter_dates
	 */
	public function test_easter( int $year, string $expected ): void {
		self::assertSame( $expected, Holidays::easter( $year ) );
	}

	public function test_penance_day_is_wednesday_before_23_november(): void {
		self::assertSame( '2025-11-19', Holidays::penance_day( 2025 ) );
		self::assertSame( '2026-11-18', Holidays::penance_day( 2026 ) );
		self::assertSame( '2027-11-17', Holidays::penance_day( 2027 ) );
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function statutory_counts(): array {
		return [
			'BW' => [ 'BW', 12 ],
			'BY' => [ 'BY', 12 ],
			'BE' => [ 'BE', 10 ],
			'BB' => [ 'BB', 12 ],
			'HB' => [ 'HB', 10 ],
			'HH' => [ 'HH', 10 ],
			'HE' => [ 'HE', 10 ],
			'MV' => [ 'MV', 11 ],
			'NI' => [ 'NI', 10 ],
			'NW' => [ 'NW', 11 ],
			'RP' => [ 'RP', 11 ],
			'SL' => [ 'SL', 12 ],
			'SN' => [ 'SN', 11 ],
			'ST' => [ 'ST', 11 ],
			'SH' => [ 'SH', 10 ],
			'TH' => [ 'TH', 11 ],
		];
	}

	/**
	 * @dataProvider statutory_counts
	 */
	public function test_number_of_statutory_holidays_per_state( string $state, int $expected ): void {
		self::assertCount( $expected, Holidays::for_year( 2026, $state ) );
	}

	public function test_saxony_2026(): void {
		$expected = [
			'neujahr'                   => '2026-01-01',
			'karfreitag'                => '2026-04-03',
			'ostermontag'               => '2026-04-06',
			'tag_der_arbeit'            => '2026-05-01',
			'christi_himmelfahrt'       => '2026-05-14',
			'pfingstmontag'             => '2026-05-25',
			'tag_der_deutschen_einheit' => '2026-10-03',
			'reformationstag'           => '2026-10-31',
			'buss_und_bettag'           => '2026-11-18',
			'erster_weihnachtstag'      => '2026-12-25',
			'zweiter_weihnachtstag'     => '2026-12-26',
		];
		self::assertSame( $expected, Holidays::for_year( 2026, 'SN' ) );
	}

	public function test_regional_opt_in(): void {
		self::assertSame( [ 'fronleichnam' ], Holidays::regional_options( 'SN' ) );
		self::assertSame( [ 'friedensfest', 'mariae_himmelfahrt' ], Holidays::regional_options( 'BY' ) );
		self::assertSame( [], Holidays::regional_options( 'BE' ) );

		self::assertNull( Holidays::for_date( '2026-06-04', 'SN' ) );
		self::assertSame( 'fronleichnam', Holidays::for_date( '2026-06-04', 'SN', [ 'fronleichnam' ] ) );
		// Opting into a holiday that is not regional in the state has no effect.
		self::assertNull( Holidays::for_date( '2026-08-15', 'SN', [ 'mariae_himmelfahrt' ] ) );
	}

	public function test_for_date(): void {
		self::assertSame( 'erster_weihnachtstag', Holidays::for_date( '2026-12-25', 'SN' ) );
		self::assertSame( 'heilige_drei_koenige', Holidays::for_date( '2026-01-06', 'BY' ) );
		self::assertNull( Holidays::for_date( '2026-01-06', 'SN' ) );
		self::assertNull( Holidays::for_date( '2026-09-27', 'SN' ) );
	}

	public function test_all_for_year_is_sorted_by_date(): void {
		$dates = array_values( Holidays::all_for_year( 2026 ) );
		$copy  = $dates;
		sort( $copy );
		self::assertSame( $copy, $dates );
		self::assertCount( count( Holidays::ids() ), $dates );
	}
}
