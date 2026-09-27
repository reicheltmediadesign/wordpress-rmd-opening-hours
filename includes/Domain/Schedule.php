<?php
/**
 * Resolves which opening hours apply on a given date.
 *
 * Precedence:
 *  1. a period marked "closed" always wins,
 *  2. a public holiday (unless the rule says "regular hours", or an active
 *     alternative period explicitly ignores holidays),
 *  3. a period with alternative hours,
 *  4. the regular weekly hours.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Schedule {

	public static function resolve( SetData $set, string $ymd ): DayResult {
		$weekday = Dates::weekday( $ymd );
		$periods = self::active_periods( $set, $ymd );

		foreach ( $periods as $period ) {
			if ( $period->is_closed() ) {
				return new DayResult( $ymd, $weekday, DaySpec::closed( $period->note ), DayResult::SOURCE_PERIOD, $period->uid );
			}
		}

		$alternative = $periods[0] ?? null;
		$holiday_id  = Holidays::for_date( $ymd, $set->holidays->state, $set->holidays->regional );

		if ( null !== $holiday_id ) {
			$rule           = $set->holidays->rule_for( $holiday_id );
			$period_ignores = null !== $alternative && $alternative->ignore_holidays;

			if ( HolidaySettings::REGULAR !== $rule['mode'] && ! $period_ignores ) {
				$spec = HolidaySettings::CUSTOM === $rule['mode'] && $rule['day'] ? $rule['day'] : DaySpec::closed();
				return new DayResult( $ymd, $weekday, $spec, DayResult::SOURCE_HOLIDAY, null, $holiday_id );
			}
		}

		if ( null !== $alternative ) {
			$spec = $alternative->spec_for( $weekday ) ?? DaySpec::closed();
			if ( '' === $spec->note && '' !== $alternative->note ) {
				$spec = $spec->with_note( $alternative->note );
			}
			return new DayResult( $ymd, $weekday, $spec, DayResult::SOURCE_PERIOD, $alternative->uid, $holiday_id );
		}

		return new DayResult( $ymd, $weekday, $set->regular_for( $weekday ), DayResult::SOURCE_REGULAR, null, $holiday_id );
	}

	/**
	 * Periods covering $ymd, most specific first: one-off before recurring,
	 * later start before earlier start, shorter before longer.
	 *
	 * @return Period[]
	 */
	public static function active_periods( SetData $set, string $ymd ): array {
		$active = array_values( array_filter( $set->periods, static fn( Period $period ): bool => $period->contains( $ymd ) ) );

		usort(
			$active,
			static function ( Period $a, Period $b ) use ( $ymd ): int {
				if ( $a->recurring !== $b->recurring ) {
					return $a->recurring ? 1 : -1;
				}
				$start_a = $a->next_occurrence( $ymd )['start'] ?? $a->start;
				$start_b = $b->next_occurrence( $ymd )['start'] ?? $b->start;
				if ( $start_a !== $start_b ) {
					return $start_a < $start_b ? 1 : -1;
				}
				return $a->length_days( $ymd ) <=> $b->length_days( $ymd );
			}
		);

		return $active;
	}

	/**
	 * @return DayResult[] One result per day, starting at $from.
	 */
	public static function resolve_range( SetData $set, string $from, int $days ): array {
		$results = [];
		for ( $i = 0; $i < $days; $i++ ) {
			$results[] = self::resolve( $set, Dates::add_days( $from, $i ) );
		}
		return $results;
	}

	/**
	 * Monday to Sunday of the week containing $ymd.
	 *
	 * @return DayResult[] Keyed by weekday.
	 */
	public static function week_of( SetData $set, string $ymd ): array {
		$results = [];
		foreach ( self::resolve_range( $set, Dates::week_start( $ymd ), 7 ) as $result ) {
			$results[ $result->weekday ] = $result;
		}
		return $results;
	}

	/**
	 * The regular week as DayResults (no date context), for the "regular" view.
	 *
	 * @return DayResult[] Keyed by weekday.
	 */
	public static function regular_week( SetData $set ): array {
		$results = [];
		foreach ( Dates::WEEKDAYS as $weekday ) {
			$results[ $weekday ] = new DayResult( '', $weekday, $set->regular_for( $weekday ), DayResult::SOURCE_REGULAR );
		}
		return $results;
	}
}
