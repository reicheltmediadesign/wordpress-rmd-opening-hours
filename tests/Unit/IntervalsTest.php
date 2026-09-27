<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\Intervals;
use RMD\OpeningHours\Domain\Status;

final class IntervalsTest extends TestCase {

	private function berlin(): DateTimeZone {
		return new DateTimeZone( 'Europe/Berlin' );
	}

	public function test_two_slots_per_day(): void {
		$intervals = Intervals::for_range( SetFactory::shop(), '2026-09-28', 1, $this->berlin() );

		self::assertCount( 2, $intervals );
		self::assertSame( strtotime( '2026-09-28 09:00:00 Europe/Berlin' ), $intervals[0]['start'] );
		self::assertSame( strtotime( '2026-09-28 12:00:00 Europe/Berlin' ), $intervals[0]['end'] );
		self::assertSame( strtotime( '2026-09-28 14:00:00 Europe/Berlin' ), $intervals[1]['start'] );
	}

	public function test_overnight_and_midnight_end(): void {
		$set = SetFactory::shop(
			[
				'regular' => [
					'fri' => [
						'mode'  => 'open',
						'slots' => [
							[
								'start' => '18:00',
								'end'   => '02:00',
							],
						],
					],
					'sat' => [
						'mode'  => 'open',
						'slots' => [
							[
								'start' => '20:00',
								'end'   => '24:00',
							],
						],
					],
				],
			]
		);

		$intervals = Intervals::for_range( $set, '2026-10-09', 2, $this->berlin() ); // Fri + Sat.

		self::assertCount( 2, $intervals );
		self::assertSame( strtotime( '2026-10-10 02:00:00 Europe/Berlin' ), $intervals[0]['end'] );
		self::assertSame( strtotime( '2026-10-11 00:00:00 Europe/Berlin' ), $intervals[1]['end'] );

		// 2026-10-03 (German Unity Day) is a holiday: the Saturday slot is dropped.
		self::assertCount( 1, Intervals::for_range( $set, '2026-10-02', 2, $this->berlin() ) );
	}

	public function test_dst_transitions_keep_wall_clock_times(): void {
		$set = SetFactory::shop(
			[
				'regular' => [
					'sun' => [
						'mode'  => 'open',
						'slots' => [
							[
								'start' => '01:00',
								'end'   => '04:00',
							],
						],
					],
				],
			]
		);

		$spring = Intervals::for_range( $set, '2026-03-29', 1, $this->berlin() );
		self::assertSame( 2 * 3600, $spring[0]['end'] - $spring[0]['start'] );

		$autumn = Intervals::for_range( $set, '2026-10-25', 1, $this->berlin() );
		self::assertSame( 4 * 3600, $autumn[0]['end'] - $autumn[0]['start'] );
	}

	public function test_status_at(): void {
		$intervals = Intervals::for_range( SetFactory::shop(), '2026-09-28', 3, $this->berlin() );

		$open = Status::at( $intervals, strtotime( '2026-09-28 10:30:00 Europe/Berlin' ) );
		self::assertTrue( $open['open'] );
		self::assertSame( '12:00', $open['current']['end_time'] );
		self::assertSame( '14:00', $open['next']['start_time'] );

		$lunch = Status::at( $intervals, strtotime( '2026-09-28 12:30:00 Europe/Berlin' ) );
		self::assertFalse( $lunch['open'] );
		self::assertSame( '14:00', $lunch['next']['start_time'] );

		$evening = Status::at( $intervals, strtotime( '2026-09-28 19:00:00 Europe/Berlin' ) );
		self::assertFalse( $evening['open'] );
		self::assertSame( '2026-09-29', $evening['next']['date'] );

		$closing = Status::at( $intervals, strtotime( '2026-09-28 12:00:00 Europe/Berlin' ) );
		self::assertFalse( $closing['open'], 'The end of an interval is exclusive.' );
	}
}
