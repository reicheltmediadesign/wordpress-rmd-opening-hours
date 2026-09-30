<?php
/**
 * A planned date range with different opening hours: closed, the same hours on
 * every day of the range, or an alternative weekly schedule. May recur yearly.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Period {

	public const CLOSED = 'closed';
	public const DAILY  = 'daily';
	public const WEEKLY = 'weekly';

	public const MODES = [ self::CLOSED, self::DAILY, self::WEEKLY ];

	/**
	 * @param array<string, DaySpec>|null $week Keyed by Dates::WEEKDAYS (mode weekly).
	 */
	public function __construct(
		public readonly string $uid,
		public readonly string $name,
		public readonly string $start,
		public readonly string $end,
		public readonly bool $recurring,
		public readonly string $mode,
		public readonly ?DaySpec $day,
		public readonly ?array $week,
		public readonly string $note = '',
		public readonly ?int $lead_days = null,
		public readonly bool $ignore_holidays = false
	) {}

	public static function from_array( array $data ): self {
		$week = null;
		if ( isset( $data['week'] ) && is_array( $data['week'] ) ) {
			$week = [];
			foreach ( Dates::WEEKDAYS as $weekday ) {
				$week[ $weekday ] = DaySpec::from_array( (array) ( $data['week'][ $weekday ] ?? [] ) );
			}
		}

		return new self(
			(string) ( $data['uid'] ?? '' ),
			(string) ( $data['name'] ?? '' ),
			(string) ( $data['start'] ?? '' ),
			(string) ( $data['end'] ?? '' ),
			(bool) ( $data['recurring'] ?? false ),
			(string) ( $data['mode'] ?? self::CLOSED ),
			isset( $data['day'] ) && is_array( $data['day'] ) ? DaySpec::from_array( $data['day'] ) : null,
			$week,
			(string) ( $data['note'] ?? '' ),
			isset( $data['lead_days'] ) && null !== $data['lead_days'] ? (int) $data['lead_days'] : null,
			(bool) ( $data['ignore_holidays'] ?? false )
		);
	}

	public function to_array(): array {
		$week = null;
		if ( null !== $this->week ) {
			$week = [];
			foreach ( $this->week as $weekday => $spec ) {
				$week[ $weekday ] = $spec->to_array();
			}
		}

		return [
			'uid'             => $this->uid,
			'name'            => $this->name,
			'start'           => $this->start,
			'end'             => $this->end,
			'recurring'       => $this->recurring,
			'mode'            => $this->mode,
			'day'             => $this->day ? $this->day->to_array() : null,
			'week'            => $week,
			'note'            => $this->note,
			'lead_days'       => $this->lead_days,
			'ignore_holidays' => $this->ignore_holidays,
		];
	}

	/**
	 * Why a date range cannot be used, as a Validator error code, or null
	 * when it is fine. Stored periods with a problem are ignored.
	 */
	public static function date_problem( string $start, string $end, bool $recurring ): ?string {
		if ( ! Dates::is_valid( $start ) || ! Dates::is_valid( $end ) ) {
			return 'invalid_date';
		}
		if ( ! $recurring && $end < $start ) {
			return 'end_before_start';
		}
		if ( $recurring && Dates::diff_days( $start, $end ) > 365 ) {
			return 'recurring_too_long';
		}
		return null;
	}

	public function is_usable(): bool {
		return null === self::date_problem( $this->start, $this->end, $this->recurring );
	}

	/**
	 * The occurrence that is running on $ymd or the next one after it.
	 * Unusable periods (invalid dates) never occur.
	 * Recurring periods are matched by month and day and may wrap around the
	 * turn of the year (e.g. 24 Dec – 2 Jan).
	 *
	 * @return array{start: string, end: string}|null
	 */
	public function next_occurrence( string $ymd ): ?array {
		if ( ! $this->is_usable() ) {
			return null;
		}
		if ( ! $this->recurring ) {
			if ( $this->end < $ymd ) {
				return null;
			}
			return [
				'start' => $this->start,
				'end'   => $this->end,
			];
		}

		$start_md = Dates::month_day( $this->start );
		$end_md   = Dates::month_day( $this->end );
		$wraps    = $end_md < $start_md;
		$year     = Dates::year( $ymd );
		$best     = null;

		foreach ( [ $year - 1, $year, $year + 1 ] as $candidate_year ) {
			$start = Dates::from_month_day( $candidate_year, $start_md );
			$end   = Dates::from_month_day( $wraps ? $candidate_year + 1 : $candidate_year, $end_md );
			if ( $end < $ymd ) {
				continue;
			}
			if ( null === $best || $start < $best['start'] ) {
				$best = [
					'start' => $start,
					'end'   => $end,
				];
			}
		}

		return $best;
	}

	public function contains( string $ymd ): bool {
		$occurrence = $this->next_occurrence( $ymd );
		return null !== $occurrence && $occurrence['start'] <= $ymd;
	}

	/**
	 * Length in days of the occurrence relevant for $ymd (used to rank
	 * overlapping periods: the more specific, shorter one wins).
	 */
	public function length_days( string $ymd ): int {
		$occurrence = $this->next_occurrence( $ymd );
		if ( null === $occurrence ) {
			return $this->is_usable() ? Dates::diff_days( $this->start, $this->end ) + 1 : 0;
		}
		return Dates::diff_days( $occurrence['start'], $occurrence['end'] ) + 1;
	}

	public function is_closed(): bool {
		return self::CLOSED === $this->mode;
	}

	/**
	 * The day specification this period imposes on a weekday, or null for
	 * closed periods (which are handled separately).
	 */
	public function spec_for( string $weekday ): ?DaySpec {
		if ( self::DAILY === $this->mode ) {
			return $this->day ?? DaySpec::closed();
		}
		if ( self::WEEKLY === $this->mode ) {
			return $this->week[ $weekday ] ?? DaySpec::closed();
		}
		return null;
	}
}
