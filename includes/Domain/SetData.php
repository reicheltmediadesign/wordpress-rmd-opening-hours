<?php
/**
 * In-memory representation of one opening hours set.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class SetData {

	/**
	 * @param array<string, DaySpec> $regular Keyed by Dates::WEEKDAYS.
	 * @param Period[]               $periods
	 * @param array<string, mixed>   $display Validated display defaults.
	 * @param array<string, mixed>   $schema  Validated structured data settings.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $slug,
		public readonly string $title,
		public readonly array $regular,
		public readonly array $periods,
		public readonly HolidaySettings $holidays,
		public readonly array $display,
		public readonly array $schema
	) {}

	/**
	 * @param array<string, mixed> $data Validated storage arrays: regular, periods, holidays, display, schema.
	 */
	public static function from_arrays( int $id, string $slug, string $title, array $data ): self {
		$regular = [];
		foreach ( Dates::WEEKDAYS as $weekday ) {
			$regular[ $weekday ] = DaySpec::from_array( (array) ( $data['regular'][ $weekday ] ?? [] ) );
		}

		$periods = [];
		foreach ( (array) ( $data['periods'] ?? [] ) as $period ) {
			if ( is_array( $period ) ) {
				$periods[] = Period::from_array( $period );
			}
		}

		return new self(
			$id,
			$slug,
			$title,
			$regular,
			$periods,
			HolidaySettings::from_array( (array) ( $data['holidays'] ?? [] ) ),
			array_merge( Validator::display_defaults(), (array) ( $data['display'] ?? [] ) ),
			array_merge( Validator::schema_defaults(), (array) ( $data['schema'] ?? [] ) )
		);
	}

	/**
	 * Storage/export representation (without id and title).
	 */
	public function to_array(): array {
		$regular = [];
		foreach ( $this->regular as $weekday => $spec ) {
			$regular[ $weekday ] = $spec->to_array();
		}

		return [
			'regular'  => $regular,
			'periods'  => array_map( static fn( Period $period ): array => $period->to_array(), $this->periods ),
			'holidays' => $this->holidays->to_array(),
			'display'  => $this->display,
			'schema'   => $this->schema,
		];
	}

	public function regular_for( string $weekday ): DaySpec {
		return $this->regular[ $weekday ] ?? DaySpec::closed();
	}

	public function period( string $uid ): ?Period {
		foreach ( $this->periods as $period ) {
			if ( $period->uid === $uid ) {
				return $period;
			}
		}
		return null;
	}
}
