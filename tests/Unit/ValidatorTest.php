<?php
declare(strict_types=1);

namespace RMD\OpeningHours\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\OpeningHours\Domain\Validator;

final class ValidatorTest extends TestCase {

	private function codes( array $result ): array {
		return array_map( static fn( array $error ): string => $error['path'] . ':' . $error['code'], $result['errors'] );
	}

	public function test_multiple_slots_are_kept_sorted_and_times_normalized(): void {
		$result = ( new Validator() )->normalize_week(
			[
				'mon' => [
					'mode'  => 'open',
					'slots' => [
						[
							'start' => '14:00',
							'end'   => '18:00',
						],
						[
							'start' => '8:00',
							'end'   => '12:00',
						],
					],
				],
			]
		);

		self::assertSame( [], $result['errors'] );
		self::assertSame( '08:00', $result['data']['mon']['slots'][0]['start'] );
		self::assertSame( '14:00', $result['data']['mon']['slots'][1]['start'] );
		self::assertSame( 'closed', $result['data']['sun']['mode'] );
	}

	public function test_overlapping_slots_are_reported(): void {
		$result = ( new Validator() )->normalize_week(
			[
				'tue' => [
					'mode'  => 'open',
					'slots' => [
						[
							'start' => '09:00',
							'end'   => '13:00',
						],
						[
							'start' => '12:00',
							'end'   => '18:00',
						],
					],
				],
			]
		);

		self::assertSame( [ 'regular.tue.slots.1.start:slots_overlap' ], $this->codes( $result ) );
	}

	public function test_invalid_time_and_zero_length(): void {
		$result = ( new Validator() )->normalize_week(
			[
				'wed' => [
					'mode'  => 'open',
					'slots' => [
						[
							'start' => '25:00',
							'end'   => '12:00',
						],
						[
							'start' => '09:00',
							'end'   => '09:00',
						],
					],
				],
			]
		);

		self::assertSame(
			[ 'regular.wed.slots.0.start:invalid_time', 'regular.wed.slots.1.end:zero_length', 'regular.wed.slots:slots_required' ],
			$this->codes( $result )
		);
		self::assertSame( 'closed', $result['data']['wed']['mode'] );
	}

	public function test_overnight_and_midnight_end_are_valid(): void {
		$result = ( new Validator() )->normalize_week(
			[
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
			]
		);

		self::assertSame( [], $result['errors'] );
	}

	public function test_text_mode_requires_text_and_strips_tags(): void {
		$validator = new Validator();

		$result = $validator->normalize_week( [ 'thu' => [ 'mode' => 'text' ] ] );
		self::assertSame( [ 'regular.thu.text:text_required' ], $this->codes( $result ) );

		$result = $validator->normalize_week(
			[
				'thu' => [
					'mode' => 'text',
					'text' => '<b>By</b> appointment <script>x</script>',
				],
			]
		);
		self::assertSame( 'By appointment x', $result['data']['thu']['text'] );
	}

	public function test_periods(): void {
		$counter   = 0;
		$validator = new Validator(
			null,
			null,
			static function () use ( &$counter ): string {
				++$counter;
				return sprintf( 'aaaaaaaa-aaaa-4aaa-8aaa-%012d', $counter );
			}
		);

		$result = $validator->normalize_periods(
			[
				[
					'name'  => 'Later',
					'start' => '2026-08-01',
					'end'   => '2026-08-10',
					'mode'  => 'closed',
				],
				[
					'uid'       => 'not-a-uuid',
					'name'      => 'Earlier',
					'start'     => '2026-07-01',
					'end'       => '2026-07-05',
					'mode'      => 'daily',
					'lead_days' => '3',
					'day'       => [
						'mode'  => 'open',
						'slots' => [
							[
								'start' => '10:00',
								'end'   => '14:00',
							],
						],
					],
				],
				[
					'name'  => 'Broken',
					'start' => '2026-09-10',
					'end'   => '2026-09-01',
				],
				[
					'name'  => 'No date',
					'start' => '',
					'end'   => '',
				],
			]
		);

		self::assertSame(
			[ 'periods.2.end:end_before_start', 'periods.3.start:invalid_date', 'periods.3.end:invalid_date' ],
			$this->codes( $result )
		);
		self::assertCount( 2, $result['data'] );
		self::assertSame( 'Earlier', $result['data'][0]['name'] );
		self::assertSame( 3, $result['data'][0]['lead_days'] );
		self::assertNull( $result['data'][1]['lead_days'] );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $result['data'][1]['uid'] );
		self::assertNotSame( $result['data'][0]['uid'], $result['data'][1]['uid'], 'Duplicate uids are replaced.' );
	}

	public function test_holidays(): void {
		$result = ( new Validator() )->normalize_holidays(
			[
				'state'    => 'by',
				'regional' => [ 'mariae_himmelfahrt', 'fronleichnam' ],
				'rules'    => [
					'neujahr'    => [ 'mode' => 'closed' ],
					'karfreitag' => [ 'mode' => 'regular' ],
					'unknown'    => [ 'mode' => 'regular' ],
				],
			]
		);

		self::assertSame( [ 'holidays.rules.unknown:invalid_holiday' ], $this->codes( $result ) );
		self::assertSame( 'BY', $result['data']['state'] );
		self::assertSame( [ 'mariae_himmelfahrt' ], $result['data']['regional'], 'Fronleichnam is statutory in BY, not regional.' );
		self::assertSame( [ 'neujahr', 'karfreitag' ], array_keys( $result['data']['rules'] ) );
		self::assertSame( 'closed', $result['data']['default']['mode'] );
	}

	public function test_holiday_default_rule(): void {
		$result = ( new Validator() )->normalize_holidays(
			[
				'state'   => 'SN',
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
					'neujahr'    => [ 'mode' => 'default' ],
					'karfreitag' => [ 'mode' => '' ],
					'ostermontag' => [ 'mode' => 'closed' ],
				],
			]
		);

		self::assertSame( [], $result['errors'] );
		self::assertSame( 'custom', $result['data']['default']['mode'] );
		self::assertSame( '10:00', $result['data']['default']['day']['slots'][0]['start'] );
		self::assertSame( [ 'ostermontag' ], array_keys( $result['data']['rules'] ), '"default" rules are not stored.' );
	}

	public function test_display_and_schema_fall_back(): void {
		$validator = new Validator();

		$display = $validator->normalize_display(
			[
				'layout'     => 'fancy',
				'group_days' => '0',
				'time_style' => '12h',
				'day_names'  => 'tiny',
			]
		);
		self::assertSame( [ 'display.layout:invalid_layout', 'display.day_names:invalid_day_names' ], $this->codes( $display ) );
		self::assertSame( 'table', $display['data']['layout'] );
		self::assertFalse( $display['data']['group_days'] );
		self::assertSame( '12h', $display['data']['time_style'] );
		self::assertSame( 'auto', $display['data']['day_names'] );

		$schema = $validator->normalize_schema(
			[
				'enabled' => 'true',
				'type'    => 'Physician',
				'url'     => 'javascript:alert(1)',
			]
		);
		self::assertSame( [ 'schema.url:invalid_url' ], $this->codes( $schema ) );
		self::assertTrue( $schema['data']['enabled'] );
		self::assertSame( '', $schema['data']['url'] );
	}

	public function test_settings(): void {
		$result = ( new Validator() )->normalize_settings(
			[
				'lead_days'           => 400,
				'notice_horizon_days' => 'abc',
				'default_state'       => 'XX',
				'notice_template'     => '',
			]
		);

		self::assertSame(
			[ 'settings.lead_days:out_of_range', 'settings.notice_horizon_days:invalid_number', 'settings.default_state:invalid_state' ],
			$this->codes( $result )
		);
		self::assertSame( 7, $result['data']['lead_days'] );
		self::assertSame( 30, $result['data']['notice_horizon_days'] );
		self::assertSame( 'SN', $result['data']['default_state'] );
		self::assertNotSame( '', $result['data']['notice_template'] );
		self::assertSame( [ 'editor' ], $result['data']['roles'] );
	}

	public function test_settings_roles(): void {
		$result = ( new Validator() )->normalize_settings(
			[ 'roles' => [ 'administrator', 'Editor', 'shop_manager', 'shop_manager', 'bad slug!', '' ] ]
		);

		self::assertSame( [ 'editor', 'shop_manager' ], $result['data']['roles'] );
		self::assertSame( [], ( new Validator() )->normalize_settings( [ 'roles' => [] ] )['data']['roles'] );
	}

	public function test_normalize_set_round_trip(): void {
		$validator = new Validator();
		$first     = $validator->normalize_set(
			[
				'regular' => Validator::default_week(),
				'periods' => [ SetFactory::period( [] ) ],
			]
		);
		$second    = $validator->normalize_set( $first['data'] );

		self::assertSame( [], $first['errors'] );
		self::assertSame( $first['data'], $second['data'] );
	}
}
