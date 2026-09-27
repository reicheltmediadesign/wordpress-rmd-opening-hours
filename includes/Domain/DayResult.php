<?php
/**
 * The effective opening hours of one concrete date and where they came from.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class DayResult {

	public const SOURCE_REGULAR = 'regular';
	public const SOURCE_PERIOD  = 'period';
	public const SOURCE_HOLIDAY = 'holiday';

	public function __construct(
		public readonly string $date,
		public readonly string $weekday,
		public readonly DaySpec $spec,
		public readonly string $source,
		public readonly ?string $period_uid = null,
		public readonly ?string $holiday_id = null
	) {}

	public function is_exception(): bool {
		return self::SOURCE_REGULAR !== $this->source;
	}

	/**
	 * Identity used for grouping consecutive days into one row.
	 */
	public function group_key(): string {
		return $this->spec->signature() . '|' . $this->source . '|' . ( $this->period_uid ?? '' ) . '|' . ( $this->holiday_id ?? '' );
	}
}
