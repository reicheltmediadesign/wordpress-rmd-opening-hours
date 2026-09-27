<?php
/**
 * Merges consecutive days with identical hours into one row ("Mon–Fri").
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Grouper {

	/**
	 * @param DayResult[] $days In display order.
	 * @return list<array{days: DayResult[], spec: DaySpec}>
	 */
	public static function group( array $days, bool $merge_consecutive = true ): array {
		$rows = [];

		foreach ( array_values( $days ) as $day ) {
			$last = count( $rows ) - 1;
			if ( $merge_consecutive && $last >= 0 && $rows[ $last ]['key'] === $day->group_key() ) {
				$rows[ $last ]['days'][] = $day;
				continue;
			}
			$rows[] = [
				'key'  => $day->group_key(),
				'days' => [ $day ],
				'spec' => $day->spec,
			];
		}

		return array_map(
			static fn( array $row ): array => [
				'days' => $row['days'],
				'spec' => $row['spec'],
			],
			$rows
		);
	}
}
