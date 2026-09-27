<?php
/**
 * Formats times, dates and day labels for output.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Render;

use DateTimeImmutable;
use RMD\OpeningHours\Domain\DaySpec;
use RMD\OpeningHours\Domain\DayResult;
use RMD\OpeningHours\Domain\Slot;
use RMD\OpeningHours\Labels;

defined( 'ABSPATH' ) || exit;

final class Formatter {

	public function __construct( private readonly string $time_style = '24h' ) {}

	public function time( string $time ): string {
		if ( Slot::MIDNIGHT_END === $time ) {
			return '24h-short' === $this->time_style ? '24' : ( '12h' === $this->time_style ? '12:00 am' : '24:00' );
		}
		[ $hour, $minute ] = array_map( 'intval', explode( ':', $time ) );

		switch ( $this->time_style ) {
			case '24h-short':
				return 0 === $minute ? (string) $hour : sprintf( '%d:%02d', $hour, $minute );
			case '12h':
				$suffix = $hour >= 12 ? 'pm' : 'am';
				$h12    = $hour % 12;
				$h12    = 0 === $h12 ? 12 : $h12;
				return sprintf( '%d:%02d %s', $h12, $minute, $suffix );
			default:
				return sprintf( '%02d:%02d', $hour, $minute );
		}
	}

	public function slot( Slot $slot ): string {
		$range = sprintf(
			/* translators: 1: opening time, 2: closing time */
			_x( '%1$s–%2$s', 'time range', 'rmd-opening-hours' ),
			$this->time( $slot->start ),
			$this->time( $slot->end )
		);
		if ( '24h-short' === $this->time_style ) {
			/* translators: %s: time range in short 24-hour style, e.g. "9–18" */
			$range = sprintf( _x( '%s h', 'short time range suffix', 'rmd-opening-hours' ), $range );
		}
		return $range;
	}

	/**
	 * Plain-text summary of a day: "09:00–12:00, 14:00–18:00", "closed", or the free text.
	 */
	public function day( DaySpec $spec ): string {
		if ( $spec->is_open() ) {
			return implode( ', ', array_map( [ $this, 'slot' ], $spec->slots ) );
		}
		if ( DaySpec::TEXT === $spec->mode && '' !== $spec->text ) {
			return $spec->text;
		}
		return __( 'closed', 'rmd-opening-hours' );
	}

	/**
	 * Row label for a group of days: "Mon–Fri", "Sat", or "Mon, Tue" for non-consecutive input.
	 *
	 * @param DayResult[] $days
	 */
	public function day_range( array $days, bool $short = true ): string {
		$names = Labels::weekdays();
		$key   = $short ? 'short' : 'long';
		$label = static fn( DayResult $day ): string => $names[ $day->weekday ][ $key ] ?? $day->weekday;

		if ( 1 === count( $days ) ) {
			return $label( $days[0] );
		}
		return sprintf(
			/* translators: 1: first weekday, 2: last weekday */
			_x( '%1$s–%2$s', 'weekday range', 'rmd-opening-hours' ),
			$label( $days[0] ),
			$label( $days[ count( $days ) - 1 ] )
		);
	}

	public function date( string $ymd, ?string $format = null ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );
		if ( ! $date ) {
			return $ymd;
		}
		return wp_date( $format ?? (string) get_option( 'date_format', 'j. F Y' ), $date->getTimestamp(), wp_timezone() );
	}

	/**
	 * Additional remark shown with a day: the period/holiday note and, for
	 * holidays, the holiday name.
	 */
	public function note( DayResult $day ): string {
		$parts = [];
		if ( $day->holiday_id && DayResult::SOURCE_PERIOD !== $day->source ) {
			$parts[] = Labels::holiday( $day->holiday_id );
		}
		if ( '' !== $day->spec->note ) {
			$parts[] = $day->spec->note;
		}
		return implode( ' · ', array_unique( $parts ) );
	}
}
