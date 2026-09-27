<?php
/**
 * Turns resolved days into absolute open intervals (Unix timestamps) in the
 * site's timezone, so that clients only need to compare against "now".
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class Intervals {

	/**
	 * @return list<array{start: int, end: int, date: string, start_time: string, end_time: string, weekday: string}>
	 */
	public static function for_range( SetData $set, string $from, int $days, DateTimeZone $tz ): array {
		$intervals = [];

		foreach ( Schedule::resolve_range( $set, $from, $days ) as $day ) {
			if ( ! $day->spec->is_open() ) {
				continue;
			}
			$midnight = new DateTimeImmutable( $day->date . ' 00:00:00', $tz );

			foreach ( $day->spec->slots as $slot ) {
				[ $sh, $sm ] = array_map( 'intval', explode( ':', $slot->start ) );
				$start       = $midnight->setTime( $sh, $sm );

				if ( Slot::MIDNIGHT_END === $slot->end ) {
					$end = $midnight->modify( '+1 day' )->setTime( 0, 0 );
				} else {
					[ $eh, $em ] = array_map( 'intval', explode( ':', $slot->end ) );
					$end         = $slot->is_overnight() ? $midnight->modify( '+1 day' )->setTime( $eh, $em ) : $midnight->setTime( $eh, $em );
				}

				if ( $end <= $start ) {
					continue;
				}

				$intervals[] = [
					'start'      => $start->getTimestamp(),
					'end'        => $end->getTimestamp(),
					'date'       => $day->date,
					'weekday'    => $day->weekday,
					'start_time' => $slot->start,
					'end_time'   => $slot->end,
				];
			}
		}

		usort( $intervals, static fn( array $a, array $b ): int => $a['start'] <=> $b['start'] );

		return $intervals;
	}
}
