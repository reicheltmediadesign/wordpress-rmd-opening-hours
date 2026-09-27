<?php
/**
 * How a set treats public holidays: which federal state applies, which optional
 * regional holidays are observed, a default rule for every holiday, and per
 * holiday overrides (closed, regular hours, or custom hours).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Domain;

final class HolidaySettings {

	public const CLOSED  = 'closed';
	public const REGULAR = 'regular';
	public const CUSTOM  = 'custom';

	/** Per-holiday value meaning "use the default rule"; never stored. */
	public const USE_DEFAULT = 'default';

	public const MODES = [ self::CLOSED, self::REGULAR, self::CUSTOM ];

	/**
	 * @param array<string, array{mode: string, day: DaySpec|null}> $rules        Keyed by holiday id.
	 * @param string[]                                              $regional     Opted-in regional holiday ids.
	 * @param array{mode: string, day: DaySpec|null}                $default_rule Rule for holidays without an override.
	 */
	public function __construct(
		public readonly string $state,
		public readonly array $rules = [],
		public readonly array $regional = [],
		public readonly array $default_rule = [
			'mode' => self::CLOSED,
			'day'  => null,
		]
	) {}

	public static function from_array( array $data, string $default_state = 'SN' ): self {
		$rules = [];
		foreach ( (array) ( $data['rules'] ?? [] ) as $id => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rules[ (string) $id ] = self::rule_from_array( $rule );
		}

		$default_rule = isset( $data['default'] ) && is_array( $data['default'] )
			? self::rule_from_array( $data['default'] )
			: [
				'mode' => self::CLOSED,
				'day'  => null,
			];

		return new self(
			(string) ( $data['state'] ?? $default_state ),
			$rules,
			array_values( array_map( 'strval', (array) ( $data['regional'] ?? [] ) ) ),
			$default_rule
		);
	}

	/**
	 * @return array{mode: string, day: DaySpec|null}
	 */
	private static function rule_from_array( array $rule ): array {
		$mode = (string) ( $rule['mode'] ?? self::CLOSED );
		return [
			'mode' => in_array( $mode, self::MODES, true ) ? $mode : self::CLOSED,
			'day'  => isset( $rule['day'] ) && is_array( $rule['day'] ) ? DaySpec::from_array( $rule['day'] ) : null,
		];
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
			'default'  => [
				'mode' => $this->default_rule['mode'],
				'day'  => $this->default_rule['day'] ? $this->default_rule['day']->to_array() : null,
			],
			'rules'    => $rules,
			'regional' => $this->regional,
		];
	}

	/**
	 * The rule that applies to a holiday: its override, or the default.
	 *
	 * @return array{mode: string, day: DaySpec|null}
	 */
	public function rule_for( string $holiday_id ): array {
		return $this->rules[ $holiday_id ] ?? $this->default_rule;
	}

	/**
	 * What the generic "Public holidays" row shows: the default rule as a DaySpec,
	 * or null when holidays follow the regular hours.
	 */
	public function default_spec(): ?DaySpec {
		if ( self::REGULAR === $this->default_rule['mode'] ) {
			return null;
		}
		if ( self::CUSTOM === $this->default_rule['mode'] && $this->default_rule['day'] ) {
			return $this->default_rule['day'];
		}
		return DaySpec::closed();
	}
}
