<?php
/**
 * German public holidays per federal state, computed locally (no ext/calendar,
 * no external API). Holiday ids are stable and used as keys in HolidaySettings.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Holidays {

	public const STATES = [ 'BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH' ];

	/**
	 * rule: ['fixed', 'MM-DD'] | ['easter', offsetDays] | ['penance'] (Buß- und Bettag).
	 * states: 'all' or list of states where the day is a statutory holiday.
	 * regional: states where it is a holiday only in parts of the state (opt-in).
	 */
	private const DEFINITIONS = [
		'neujahr'                   => [
			'rule'   => [ 'fixed', '01-01' ],
			'states' => 'all',
		],
		'heilige_drei_koenige'      => [
			'rule'   => [ 'fixed', '01-06' ],
			'states' => [ 'BW', 'BY', 'ST' ],
		],
		'frauentag'                 => [
			'rule'   => [ 'fixed', '03-08' ],
			'states' => [ 'BE', 'MV' ],
		],
		'karfreitag'                => [
			'rule'   => [ 'easter', -2 ],
			'states' => 'all',
		],
		'ostersonntag'              => [
			'rule'   => [ 'easter', 0 ],
			'states' => [ 'BB' ],
		],
		'ostermontag'               => [
			'rule'   => [ 'easter', 1 ],
			'states' => 'all',
		],
		'tag_der_arbeit'            => [
			'rule'   => [ 'fixed', '05-01' ],
			'states' => 'all',
		],
		'christi_himmelfahrt'       => [
			'rule'   => [ 'easter', 39 ],
			'states' => 'all',
		],
		'pfingstsonntag'            => [
			'rule'   => [ 'easter', 49 ],
			'states' => [ 'BB' ],
		],
		'pfingstmontag'             => [
			'rule'   => [ 'easter', 50 ],
			'states' => 'all',
		],
		'fronleichnam'              => [
			'rule'     => [ 'easter', 60 ],
			'states'   => [ 'BW', 'BY', 'HE', 'NW', 'RP', 'SL' ],
			'regional' => [ 'SN', 'TH' ],
		],
		'friedensfest'              => [
			'rule'     => [ 'fixed', '08-08' ],
			'states'   => [],
			'regional' => [ 'BY' ],
		],
		'mariae_himmelfahrt'        => [
			'rule'     => [ 'fixed', '08-15' ],
			'states'   => [ 'SL' ],
			'regional' => [ 'BY' ],
		],
		'weltkindertag'             => [
			'rule'   => [ 'fixed', '09-20' ],
			'states' => [ 'TH' ],
		],
		'tag_der_deutschen_einheit' => [
			'rule'   => [ 'fixed', '10-03' ],
			'states' => 'all',
		],
		'reformationstag'           => [
			'rule'   => [ 'fixed', '10-31' ],
			'states' => [ 'BB', 'HB', 'HH', 'MV', 'NI', 'SN', 'ST', 'SH', 'TH' ],
		],
		'allerheiligen'             => [
			'rule'   => [ 'fixed', '11-01' ],
			'states' => [ 'BW', 'BY', 'NW', 'RP', 'SL' ],
		],
		'buss_und_bettag'           => [
			'rule'   => [ 'penance' ],
			'states' => [ 'SN' ],
		],
		'erster_weihnachtstag'      => [
			'rule'   => [ 'fixed', '12-25' ],
			'states' => 'all',
		],
		'zweiter_weihnachtstag'     => [
			'rule'   => [ 'fixed', '12-26' ],
			'states' => 'all',
		],
	];

	/**
	 * @return string[] All holiday ids in calendar order.
	 */
	public static function ids(): array {
		return array_keys( self::DEFINITIONS );
	}

	public static function is_valid_id( string $id ): bool {
		return isset( self::DEFINITIONS[ $id ] );
	}

	public static function is_valid_state( string $state ): bool {
		return in_array( $state, self::STATES, true );
	}

	/**
	 * Easter Sunday (Gregorian, anonymous algorithm).
	 */
	public static function easter( int $year ): string {
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Buß- und Bettag: the Wednesday before 23 November.
	 */
	public static function penance_day( int $year ): string {
		$reference = sprintf( '%04d-11-22', $year );
		$weekday   = (int) Dates::date( $reference )->format( 'N' ); // 1 = Monday … 7 = Sunday.
		return Dates::add_days( $reference, -( ( $weekday - 3 + 7 ) % 7 ) );
	}

	public static function date_of( string $id, int $year ): ?string {
		$definition = self::DEFINITIONS[ $id ] ?? null;
		if ( null === $definition ) {
			return null;
		}
		$rule = $definition['rule'];
		return match ( $rule[0] ) {
			'fixed'   => sprintf( '%04d-%s', $year, $rule[1] ),
			'easter'  => Dates::add_days( self::easter( $year ), (int) $rule[1] ),
			'penance' => self::penance_day( $year ),
			default   => null,
		};
	}

	/**
	 * Every known holiday with its date in $year, regardless of state.
	 *
	 * @return array<string, string> id => date.
	 */
	public static function all_for_year( int $year ): array {
		$result = [];
		foreach ( self::ids() as $id ) {
			$date = self::date_of( $id, $year );
			if ( null !== $date ) {
				$result[ $id ] = $date;
			}
		}
		asort( $result );
		return $result;
	}

	/**
	 * Whether $id is a statutory holiday in $state, or an observed regional one.
	 *
	 * @param string[] $regional Opted-in regional holiday ids.
	 */
	public static function applies( string $id, string $state, array $regional = [] ): bool {
		$definition = self::DEFINITIONS[ $id ] ?? null;
		if ( null === $definition ) {
			return false;
		}
		if ( 'all' === $definition['states'] || in_array( $state, $definition['states'], true ) ) {
			return true;
		}
		return in_array( $state, $definition['regional'] ?? [], true ) && in_array( $id, $regional, true );
	}

	/**
	 * @param string[] $regional
	 * @return array<string, string> id => date, sorted by date.
	 */
	public static function for_year( int $year, string $state, array $regional = [] ): array {
		return array_filter(
			self::all_for_year( $year ),
			static fn( string $id ): bool => self::applies( $id, $state, $regional ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * @param string[] $regional
	 * @return string|null Holiday id or null when $ymd is a normal day.
	 */
	public static function for_date( string $ymd, string $state, array $regional = [] ): ?string {
		$id = array_search( $ymd, self::for_year( Dates::year( $ymd ), $state, $regional ), true );
		return false === $id ? null : (string) $id;
	}

	/**
	 * Regional holidays a business in $state may opt into.
	 *
	 * @return string[]
	 */
	public static function regional_options( string $state ): array {
		$options = [];
		foreach ( self::DEFINITIONS as $id => $definition ) {
			if ( in_array( $state, $definition['regional'] ?? [], true ) ) {
				$options[] = $id;
			}
		}
		return $options;
	}
}
