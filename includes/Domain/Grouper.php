<?php
/**
 * Merges days with identical hours into one row: consecutive days ("Mon–Fri")
 * and, optionally, days that are not adjacent ("Mon, Wed").
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Grouper {

	/**
	 * @param DayResult[] $days              In display order.
	 * @param bool        $merge_consecutive Merge neighbouring days with equal hours.
	 * @param bool        $merge_equal       Also merge equal days that are not neighbours; the row
	 *                                       stays at the position of its first day. Needs $merge_consecutive.
	 * @return list<array{days: DayResult[], spec: DaySpec}>
	 */
	public static function group( array $days, bool $merge_consecutive = true, bool $merge_equal = false ): array {
		$rows = [];

		foreach ( array_values( $days ) as $day ) {
			$target = -1;
			if ( $merge_consecutive ) {
				for ( $i = count( $rows ) - 1; $i >= 0; $i-- ) {
					if ( $rows[ $i ]['key'] === $day->group_key() ) {
						$target = $i;
						break;
					}
					if ( ! $merge_equal ) {
						break;
					}
				}
			}
			if ( $target >= 0 ) {
				$rows[ $target ]['days'][] = $day;
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
