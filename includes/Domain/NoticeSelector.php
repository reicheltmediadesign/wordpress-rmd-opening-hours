<?php
/**
 * Picks the periods a visitor should be warned about: each period becomes
 * visible a configurable number of days before it starts and disappears after
 * it ends. Periods whose window starts within the pre-render horizon are also
 * returned so cached pages can reveal them client-side at the right moment.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class NoticeSelector {

	/**
	 * @return list<array{period: Period, start: string, end: string, visible_from: string, lead_days: int}>
	 */
	public static function select( SetData $set, string $today, int $default_lead_days, int $horizon_days ): array {
		$limit  = Dates::add_days( $today, max( 0, $horizon_days ) );
		$result = [];

		foreach ( $set->periods as $period ) {
			$occurrence = $period->next_occurrence( $today );
			if ( null === $occurrence ) {
				continue;
			}
			$lead         = max( 0, $period->lead_days ?? $default_lead_days );
			$visible_from = Dates::add_days( $occurrence['start'], -$lead );
			if ( $visible_from > $limit ) {
				continue;
			}
			$result[] = [
				'period'       => $period,
				'start'        => $occurrence['start'],
				'end'          => $occurrence['end'],
				'visible_from' => $visible_from,
				'lead_days'    => $lead,
			];
		}

		usort( $result, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );

		return $result;
	}

	/**
	 * Whether a selected notice is active on $today (server-side default state).
	 *
	 * @param array{start: string, end: string, visible_from: string} $notice
	 */
	public static function is_active( array $notice, string $today ): bool {
		return $notice['visible_from'] <= $today && $today <= $notice['end'];
	}
}
