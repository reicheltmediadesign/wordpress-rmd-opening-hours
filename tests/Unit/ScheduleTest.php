<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\DayResult;
use RMD\OpeningHours\Domain\Period;
use RMD\OpeningHours\Domain\Schedule;

final class ScheduleTest extends TestCase {

	public function test_regular_day(): void {
		$set    = SetFactory::shop();
		$result = Schedule::resolve( $set, '2026-09-28' ); // Monday.

		self::assertSame( 'mon', $result->weekday );
		self::assertSame( DayResult::SOURCE_REGULAR, $result->source );
		self::assertCount( 2, $result->spec->slots );
		self::assertSame( '14:00', $result->spec->slots[1]->start );
	}

	public function test_holiday_is_closed_by_default(): void {
		$result = Schedule::resolve( SetFactory::shop(), '2026-10-31' ); // Reformationstag (Saturday) in SN.

		self::assertSame( DayResult::SOURCE_HOLIDAY, $result->source );
		self::assertSame( 'reformationstag', $result->holiday_id );
		self::assertTrue( $result->spec->is_closed() );
	}

	public function test_holiday_rule_regular_keeps_regular_hours(): void {
		$set    = SetFactory::shop(
			[
				'holidays' => [
					'rules' => [
						'reformationstag' => [
							'mode' => 'regular',
							'day'  => null,
						],
					],
				],
			]
		);
		$result = Schedule::resolve( $set, '2026-10-31' );

		self::assertSame( DayResult::SOURCE_REGULAR, $result->source );
		self::assertSame( 'reformationstag', $result->holiday_id );
		self::assertCount( 1, $result->spec->slots );
	}

	public function test_holiday_rule_custom_hours(): void {
		$set    = SetFactory::shop(
			[
				'holidays' => [
					'rules' => [
						'reformationstag' => [
							'mode' => 'custom',
							'day'  => [
								'mode'  => 'open',
								'slots' => [
									[
										'start' => '10:00',
										'end'   => '13:00',
									],
								],
							],
						],
					],
				],
			]
		);
		$result = Schedule::resolve( $set, '2026-10-31' );

		self::assertSame( DayResult::SOURCE_HOLIDAY, $result->source );
		self::assertSame( '10:00', $result->spec->slots[0]->start );
	}

	public function test_holiday_default_rule_applies_without_override(): void {
		$set = SetFactory::shop(
			[
				'holidays' => [
					'default' => [
						'mode' => 'custom',
						'day'  => [
							'mode'  => 'open',
							'slots' => [
								[
									'start' => '10:00',
									'end'   => '12:00',
								],
							],
						],
					],
					'rules'   => [
						'reformationstag' => [
							'mode' => 'closed',
							'day'  => null,
						],
					],
				],
			]
		);

		$unity = Schedule::resolve( $set, '2026-10-03' );
		self::assertSame( DayResult::SOURCE_HOLIDAY, $unity->source );
		self::assertSame( '10:00', $unity->spec->slots[0]->start );

		$reformation = Schedule::resolve( $set, '2026-10-31' );
		self::assertTrue( $reformation->spec->is_closed(), 'An explicit override beats the default.' );

		$regular_default = SetFactory::shop( [ 'holidays' => [ 'default' => [ 'mode' => 'regular', 'day' => null ] ] ] );
		self::assertSame( DayResult::SOURCE_REGULAR, Schedule::resolve( $regular_default, '2026-10-03' )->source );
		self::assertNull( $regular_default->holidays->default_spec() );
	}

	public function test_closed_period_beats_everything(): void {
		$set = SetFactory::shop(
			[
				'periods' => [
					SetFactory::period(
						[
							'start' => '2026-10-26',
							'end'   => '2026-11-01',
							'note'  => 'Vacation',
						]
					),
				],
			]
		);

		$result = Schedule::resolve( $set, '2026-10-31' );
		self::assertSame( DayResult::SOURCE_PERIOD, $result->source );
		self::assertTrue( $result->spec->is_closed() );
		self::assertSame( 'Vacation', $result->spec->note );
	}

	public function test_holiday_beats_alternative_period_unless_ignored(): void {
		$daily = [
			'mode'  => 'daily',
			'start' => '2026-10-26',
			'end'   => '2026-11-01',
			'day'   => [
				'mode'  => 'open',
				'slots' => [
					[
						'start' => '10:00',
						'end'   => '14:00',
					],
				],
			],
		];

		$set    = SetFactory::shop( [ 'periods' => [ SetFactory::period( $daily ) ] ] );
		$result = Schedule::resolve( $set, '2026-10-31' );
		self::assertSame( DayResult::SOURCE_HOLIDAY, $result->source );
		self::assertTrue( $result->spec->is_closed() );

		$set    = SetFactory::shop( [ 'periods' => [ SetFactory::period( $daily + [ 'ignore_holidays' => true ] ) ] ] );
		$result = Schedule::resolve( $set, '2026-10-31' );
		self::assertSame( DayResult::SOURCE_PERIOD, $result->source );
		self::assertSame( '10:00', $result->spec->slots[0]->start );

		// A normal weekday inside the period simply uses the alternative hours.
		$result = Schedule::resolve( $set, '2026-10-28' );
		self::assertSame( DayResult::SOURCE_PERIOD, $result->source );
		self::assertCount( 1, $result->spec->slots );
	}

	public function test_weekly_period_uses_its_own_week(): void {
		$week        = [];
		$week['wed'] = [
			'mode'  => 'open',
			'slots' => [
				[
					'start' => '08:00',
					'end'   => '20:00',
				],
			],
		];
		$set         = SetFactory::shop(
			[
				'periods' => [
					SetFactory::period(
						[
							'mode'  => 'weekly',
							'start' => '2026-07-01',
							'end'   => '2026-08-31',
							'week'  => $week,
							'note'  => 'Summer',
						]
					),
				],
			]
		);

		$wednesday = Schedule::resolve( $set, '2026-07-08' );
		self::assertSame( '20:00', $wednesday->spec->slots[0]->end );
		self::assertSame( 'Summer', $wednesday->spec->note );

		$thursday = Schedule::resolve( $set, '2026-07-09' );
		self::assertTrue( $thursday->spec->is_closed() );
		self::assertSame( DayResult::SOURCE_PERIOD, $thursday->source );
	}

	public function test_recurring_period_wraps_around_new_year(): void {
		$set = SetFactory::shop(
			[
				'periods' => [
					SetFactory::period(
						[
							'start'     => '2024-12-24',
							'end'       => '2025-01-02',
							'recurring' => true,
						]
					),
				],
			]
		);

		self::assertSame( DayResult::SOURCE_PERIOD, Schedule::resolve( $set, '2026-12-30' )->source );
		self::assertSame( DayResult::SOURCE_PERIOD, Schedule::resolve( $set, '2027-01-02' )->source );
		self::assertSame( DayResult::SOURCE_REGULAR, Schedule::resolve( $set, '2027-01-04' )->source );
		self::assertSame( DayResult::SOURCE_REGULAR, Schedule::resolve( $set, '2026-12-23' )->source );
	}

	public function test_more_specific_period_wins(): void {
		$long  = SetFactory::period(
			[
				'uid'   => '22222222-2222-4222-8222-222222222222',
				'mode'  => 'daily',
				'start' => '2026-07-01',
				'end'   => '2026-08-31',
				'day'   => [
					'mode'  => 'open',
					'slots' => [
						[
							'start' => '10:00',
							'end'   => '16:00',
						],
					],
				],
			]
		);
		$short = SetFactory::period(
			[
				'uid'   => '33333333-3333-4333-8333-333333333333',
				'mode'  => 'daily',
				'start' => '2026-07-10',
				'end'   => '2026-07-12',
				'day'   => [
					'mode'  => 'open',
					'slots' => [
						[
							'start' => '11:00',
							'end'   => '13:00',
						],
					],
				],
			]
		);

		$set    = SetFactory::shop( [ 'periods' => [ $long, $short ] ] );
		$active = Schedule::active_periods( $set, '2026-07-11' );

		self::assertSame( '33333333-3333-4333-8333-333333333333', $active[0]->uid );
		self::assertSame( '11:00', Schedule::resolve( $set, '2026-07-11' )->spec->slots[0]->start );
		self::assertSame( '10:00', Schedule::resolve( $set, '2026-07-20' )->spec->slots[0]->start );
	}

	public function test_week_of_returns_monday_to_sunday(): void {
		$week = Schedule::week_of( SetFactory::shop(), '2026-09-30' ); // Wednesday.

		self::assertSame( [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ], array_keys( $week ) );
		self::assertSame( '2026-09-28', $week['mon']->date );
		self::assertSame( '2026-10-04', $week['sun']->date );
	}

	public function test_period_next_occurrence_for_leap_day(): void {
		$period = Period::from_array(
			SetFactory::period(
				[
					'start'     => '2024-02-29',
					'end'       => '2024-02-29',
					'recurring' => true,
				]
			)
		);

		self::assertSame(
			[
				'start' => '2026-02-28',
				'end'   => '2026-02-28',
			],
			$period->next_occurrence( '2026-01-01' )
		);
	}
}
