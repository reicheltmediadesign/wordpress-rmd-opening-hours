<?php
/**
 * Small date/time string helpers. All dates are "YYYY-MM-DD", all times "HH:MM";
 * string comparison therefore equals chronological comparison.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class Dates {

	public const WEEKDAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	public static function is_valid( string $ymd ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	public static function is_valid_time( string $time, bool $allow_midnight_end = false ): bool {
		if ( $allow_midnight_end && '24:00' === $time ) {
			return true;
		}
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time );
	}

	public static function minutes( string $time ): int {
		[ $h, $m ] = array_map( 'intval', explode( ':', $time ) );
		return $h * 60 + $m;
	}

	public static function date( string $ymd ): DateTimeImmutable {
		return new DateTimeImmutable( $ymd . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * @return string One of self::WEEKDAYS.
	 */
	public static function weekday( string $ymd ): string {
		return self::WEEKDAYS[ (int) self::date( $ymd )->format( 'N' ) - 1 ];
	}

	public static function add_days( string $ymd, int $days ): string {
		return self::date( $ymd )->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/**
	 * Number of days from $from to $to (negative when $to is earlier).
	 */
	public static function diff_days( string $from, string $to ): int {
		$diff = self::date( $from )->diff( self::date( $to ) );
		return (int) $diff->format( '%r%a' );
	}

	public static function year( string $ymd ): int {
		return (int) substr( $ymd, 0, 4 );
	}

	/**
	 * @return string "MM-DD"
	 */
	public static function month_day( string $ymd ): string {
		return substr( $ymd, 5, 5 );
	}

	/**
	 * Builds a date from year and "MM-DD"; 02-29 falls back to 02-28 in
	 * non-leap years so yearly recurring periods never produce invalid dates.
	 */
	public static function from_month_day( int $year, string $month_day ): string {
		$candidate = sprintf( '%04d-%s', $year, $month_day );
		if ( self::is_valid( $candidate ) ) {
			return $candidate;
		}
		return sprintf( '%04d-02-28', $year );
	}

	/**
	 * Monday of the ISO week that contains $ymd.
	 */
	public static function week_start( string $ymd ): string {
		$offset = (int) self::date( $ymd )->format( 'N' ) - 1;
		return self::add_days( $ymd, -$offset );
	}
}
