<?php
/**
 * Open/closed state at a point in time, derived from absolute intervals.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Status {

	/**
	 * @param list<array{start: int, end: int}> $intervals Sorted by start.
	 * @return array{open: bool, current: array|null, next: array|null}
	 */
	public static function at( array $intervals, int $timestamp ): array {
		$current = null;
		$next    = null;

		foreach ( $intervals as $interval ) {
			if ( $interval['start'] <= $timestamp && $timestamp < $interval['end'] ) {
				if ( null === $current || $interval['end'] > $current['end'] ) {
					$current = $interval;
				}
			} elseif ( $interval['start'] > $timestamp && ( null === $next || $interval['start'] < $next['start'] ) ) {
				$next = $interval;
			}
		}

		return [
			'open'    => null !== $current,
			'current' => $current,
			'next'    => $next,
		];
	}
}
