<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\Grouper;
use RMD\OpeningHours\Domain\Schedule;

final class GrouperTest extends TestCase {

	private function labels( array $rows ): array {
		return array_map(
			static fn( array $row ): string => implode( ',', array_map( static fn( $day ) => $day->weekday, $row['days'] ) ),
			$rows
		);
	}

	public function test_groups_consecutive_equal_days(): void {
		$rows = Grouper::group( Schedule::regular_week( SetFactory::shop() ) );

		self::assertSame( [ 'mon,tue,wed,thu,fri', 'sat', 'sun' ], $this->labels( $rows ) );
	}

	public function test_can_be_disabled(): void {
		$rows = Grouper::group( Schedule::regular_week( SetFactory::shop() ), false );

		self::assertCount( 7, $rows );
	}

	public function test_holiday_breaks_a_group(): void {
		$week = Schedule::week_of( SetFactory::shop(), '2026-10-01' ); // Contains 2026-10-03 (Saturday, holiday).
		$rows = Grouper::group( $week );

		self::assertSame( [ 'mon,tue,wed,thu,fri', 'sat', 'sun' ], $this->labels( $rows ) );
		self::assertSame( 'tag_der_deutschen_einheit', $rows[1]['days'][0]->holiday_id );

		$week = Schedule::week_of( SetFactory::shop(), '2026-05-11' ); // Thursday 2026-05-14 is Ascension Day.
		self::assertSame( [ 'mon,tue,wed', 'thu', 'fri', 'sat', 'sun' ], $this->labels( Grouper::group( $week ) ) );
	}
}
