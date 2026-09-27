<?php
/**
 * One opening interval within a day. An end time that is not after the start
 * time means the slot runs into the next day (e.g. 18:00–02:00).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class Slot {

	public const MIDNIGHT_END = '24:00';

	public function __construct(
		public readonly string $start,
		public readonly string $end
	) {}

	public static function from_array( array $data ): self {
		return new self( (string) ( $data['start'] ?? '00:00' ), (string) ( $data['end'] ?? '00:00' ) );
	}

	/**
	 * @return array{start: string, end: string}
	 */
	public function to_array(): array {
		return [
			'start' => $this->start,
			'end'   => $this->end,
		];
	}

	public function start_minutes(): int {
		return Dates::minutes( $this->start );
	}

	/**
	 * Minutes since the start of the slot's day; values >= 1440 lie in the next day.
	 */
	public function end_minutes(): int {
		if ( self::MIDNIGHT_END === $this->end ) {
			return 1440;
		}
		$end = Dates::minutes( $this->end );
		return $end <= $this->start_minutes() ? $end + 1440 : $end;
	}

	public function is_overnight(): bool {
		return $this->end_minutes() > 1440;
	}

	public function duration_minutes(): int {
		return $this->end_minutes() - $this->start_minutes();
	}
}
