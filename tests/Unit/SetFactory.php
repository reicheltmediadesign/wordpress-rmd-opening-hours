<?php
/**
 * Builds SetData fixtures for tests.
 *
 * @package RMD\OpeningHours
 */

declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Domain\Validator;

final class SetFactory {

	/**
	 * Mon–Fri 09:00–12:00 and 14:00–18:00, Sat 09:00–12:00, Sun closed, state SN.
	 */
	public static function shop( array $overrides = [] ): SetData {
		$split = [
			'mode'  => 'open',
			'slots' => [
				[
					'start' => '09:00',
					'end'   => '12:00',
				],
				[
					'start' => '14:00',
					'end'   => '18:00',
				],
			],
		];
		$morning = [
			'mode'  => 'open',
			'slots' => [
				[
					'start' => '09:00',
					'end'   => '12:00',
				],
			],
		];

		$data = [
			'regular'  => array_merge(
				[
					'mon' => $split,
					'tue' => $split,
					'wed' => $split,
					'thu' => $split,
					'fri' => $split,
					'sat' => $morning,
					'sun' => [ 'mode' => 'closed' ],
				],
				$overrides['regular'] ?? []
			),
			'periods'  => $overrides['periods'] ?? [],
			'holidays' => array_replace_recursive( Validator::holidays_defaults( 'SN' ), $overrides['holidays'] ?? [] ),
			'display'  => array_merge( Validator::display_defaults(), $overrides['display'] ?? [] ),
			'schema'   => array_merge( Validator::schema_defaults(), $overrides['schema'] ?? [] ),
		];

		return SetData::from_arrays( 1, 'shop', 'Shop', $data );
	}

	public static function period( array $overrides ): array {
		return array_merge(
			[
				'uid'             => '11111111-1111-4111-8111-111111111111',
				'name'            => 'Holiday',
				'start'           => '2026-07-01',
				'end'             => '2026-07-14',
				'recurring'       => false,
				'mode'            => 'closed',
				'day'             => null,
				'week'            => null,
				'note'            => '',
				'lead_days'       => null,
				'ignore_holidays' => false,
			],
			$overrides
		);
	}
}
