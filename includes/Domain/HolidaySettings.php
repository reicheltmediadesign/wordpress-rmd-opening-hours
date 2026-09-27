<?php
/**
 * How a set treats public holidays: which federal state applies, which optional
 * regional holidays are observed, and per holiday whether the business is
 * closed, keeps regular hours, or uses custom hours.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class HolidaySettings {

	public const CLOSED  = 'closed';
	public const REGULAR = 'regular';
	public const CUSTOM  = 'custom';

	public const MODES = [ self::CLOSED, self::REGULAR, self::CUSTOM ];

	/**
	 * @param array<string, array{mode: string, day: DaySpec|null}> $rules    Keyed by holiday id.
	 * @param string[]                                              $regional Opted-in regional holiday ids.
	 */
	public function __construct(
		public readonly string $state,
		public readonly array $rules = [],
		public readonly array $regional = []
	) {}

	public static function from_array( array $data, string $default_state = 'SN' ): self {
		$rules = [];
		foreach ( (array) ( $data['rules'] ?? [] ) as $id => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rules[ (string) $id ] = [
				'mode' => (string) ( $rule['mode'] ?? self::CLOSED ),
				'day'  => isset( $rule['day'] ) && is_array( $rule['day'] ) ? DaySpec::from_array( $rule['day'] ) : null,
			];
		}

		return new self(
			(string) ( $data['state'] ?? $default_state ),
			$rules,
			array_values( array_map( 'strval', (array) ( $data['regional'] ?? [] ) ) )
		);
	}

	public function to_array(): array {
		$rules = [];
		foreach ( $this->rules as $id => $rule ) {
			$rules[ $id ] = [
				'mode' => $rule['mode'],
				'day'  => $rule['day'] ? $rule['day']->to_array() : null,
			];
		}

		return [
			'state'    => $this->state,
			'rules'    => $rules,
			'regional' => $this->regional,
		];
	}

	/**
	 * @return array{mode: string, day: DaySpec|null}
	 */
	public function rule_for( string $holiday_id ): array {
		return $this->rules[ $holiday_id ] ?? [
			'mode' => self::CLOSED,
			'day'  => null,
		];
	}
}
