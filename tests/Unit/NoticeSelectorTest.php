<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\NoticeSelector;

final class NoticeSelectorTest extends TestCase {

	private function set(): \RMD\OpeningHours\Domain\SetData {
		return SetFactory::shop(
			[
				'periods' => [
					SetFactory::period(
						[
							'uid'   => '11111111-1111-4111-8111-111111111111',
							'name'  => 'Summer break',
							'start' => '2026-07-20',
							'end'   => '2026-08-02',
						]
					),
					SetFactory::period(
						[
							'uid'       => '22222222-2222-4222-8222-222222222222',
							'name'      => 'Training day',
							'start'     => '2026-07-10',
							'end'       => '2026-07-10',
							'lead_days' => 2,
						]
					),
					SetFactory::period(
						[
							'uid'       => '33333333-3333-4333-8333-333333333333',
							'name'      => 'Christmas',
							'start'     => '2024-12-24',
							'end'       => '2025-01-02',
							'recurring' => true,
						]
					),
				],
			]
		);
	}

	public function test_lead_time_and_visibility(): void {
		$notices = NoticeSelector::select( $this->set(), '2026-07-01', 7, 30 );

		self::assertSame( [ 'Training day', 'Summer break' ], array_map( static fn( array $n ) => $n['period']->name, $notices ) );
		self::assertSame( '2026-07-08', $notices[0]['visible_from'] );
		self::assertSame( '2026-07-13', $notices[1]['visible_from'] );

		self::assertFalse( NoticeSelector::is_active( $notices[0], '2026-07-07' ) );
		self::assertTrue( NoticeSelector::is_active( $notices[0], '2026-07-08' ) );
		self::assertTrue( NoticeSelector::is_active( $notices[0], '2026-07-10' ) );
		self::assertFalse( NoticeSelector::is_active( $notices[0], '2026-07-11' ) );
	}

	public function test_past_periods_are_dropped_and_recurring_resolved(): void {
		$notices = NoticeSelector::select( $this->set(), '2026-12-01', 7, 30 );

		self::assertCount( 1, $notices );
		self::assertSame( 'Christmas', $notices[0]['period']->name );
		self::assertSame( '2026-12-24', $notices[0]['start'] );
		self::assertSame( '2027-01-02', $notices[0]['end'] );
		self::assertSame( '2026-12-17', $notices[0]['visible_from'] );
	}

	public function test_horizon_limits_prerendered_notices(): void {
		self::assertCount( 0, NoticeSelector::select( $this->set(), '2026-03-01', 7, 30 ) );
		self::assertCount( 2, NoticeSelector::select( $this->set(), '2026-06-20', 7, 30 ) );
	}
}
