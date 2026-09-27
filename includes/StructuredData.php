<?php
/**
 * Schema.org JSON-LD output (openingHoursSpecification and
 * specialOpeningHoursSpecification). Disabled per set by default to avoid
 * duplicating a LocalBusiness entity that an SEO plugin may already emit.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Domain\Dates;
use RMD\OpeningHours\Domain\DayResult;
use RMD\OpeningHours\Domain\Schedule;
use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Repository\SetRepository;

defined( 'ABSPATH' ) || exit;

final class StructuredData {

	private const DAY_NAMES = [
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday',
		'sun' => 'Sunday',
	];

	/** @var array<int, SetData> */
	private static array $used = [];

	public static function init(): void {
		add_action( 'wp_footer', [ self::class, 'output' ], 20 );
	}

	public static function mark_used( SetData $set ): void {
		self::$used[ $set->id ] = $set;
	}

	public static function output(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$sets = self::$used;
		if ( Settings::get( 'jsonld_everywhere', false ) ) {
			foreach ( SetRepository::all() as $set ) {
				$sets[ $set->id ] = $set;
			}
		}

		foreach ( $sets as $set ) {
			if ( empty( $set->schema['enabled'] ) ) {
				continue;
			}
			$graph = self::graph( $set );

			/**
			 * Filters the JSON-LD node emitted for a set. Return an empty array
			 * to suppress the output (e.g. when merging into another graph).
			 *
			 * @param array   $graph The schema.org node.
			 * @param SetData $set   The set.
			 */
			$graph = apply_filters( 'rmd_oh_jsonld', $graph, $set );
			if ( ! is_array( $graph ) || [] === $graph ) {
				continue;
			}
			echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function graph( SetData $set ): array {
		$url  = '' !== $set->schema['url'] ? $set->schema['url'] : home_url( '/' );
		$name = '' !== $set->schema['name'] ? $set->schema['name'] : get_bloginfo( 'name' );

		return [
			'@context'                         => 'https://schema.org',
			'@type'                            => $set->schema['type'],
			'@id'                              => trailingslashit( $url ) . '#rmd-opening-hours-' . $set->slug,
			'name'                             => $name,
			'url'                              => $url,
			'openingHoursSpecification'        => self::regular( $set ),
			'specialOpeningHoursSpecification' => self::special( $set ),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function regular( SetData $set ): array {
		$specs = [];
		foreach ( Dates::WEEKDAYS as $weekday ) {
			$spec = $set->regular_for( $weekday );
			if ( ! $spec->is_open() ) {
				continue;
			}
			foreach ( $spec->slots as $slot ) {
				$key = $slot->start . '-' . $slot->end;
				if ( ! isset( $specs[ $key ] ) ) {
					$specs[ $key ] = [
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => [],
						'opens'     => $slot->start,
						'closes'    => '24:00' === $slot->end ? '23:59' : $slot->end,
					];
				}
				$specs[ $key ]['dayOfWeek'][] = 'https://schema.org/' . self::DAY_NAMES[ $weekday ];
			}
		}
		return array_values( $specs );
	}

	/**
	 * Exceptions for the next twelve months: one entry per consecutive run of
	 * days that share the same deviation.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function special( SetData $set ): array {
		$today   = Clock::today();
		$days    = Schedule::resolve_range( $set, $today, 366 );
		$entries = [];
		$run     = null;

		foreach ( $days as $day ) {
			$regular = $set->regular_for( $day->weekday );
			$differs = $day->is_exception() && $day->spec->signature() !== $regular->signature();
			$key     = $differs ? $day->spec->signature() . '|' . ( $day->period_uid ?? '' ) : null;

			if ( null !== $run && $run['key'] === $key && Dates::add_days( $run['end'], 1 ) === $day->date ) {
				$run['end'] = $day->date;
				continue;
			}
			if ( null !== $run && null !== $run['key'] ) {
				$entries = array_merge( $entries, self::run_entries( $run ) );
			}
			$run = [
				'key'   => $key,
				'start' => $day->date,
				'end'   => $day->date,
				'day'   => $day,
			];
		}
		if ( null !== $run && null !== $run['key'] ) {
			$entries = array_merge( $entries, self::run_entries( $run ) );
		}

		return $entries;
	}

	/**
	 * @param array{start: string, end: string, day: DayResult} $run
	 * @return list<array<string, mixed>>
	 */
	private static function run_entries( array $run ): array {
		$spec = $run['day']->spec;
		$base = [
			'@type'        => 'OpeningHoursSpecification',
			'validFrom'    => $run['start'],
			'validThrough' => $run['end'],
		];

		if ( ! $spec->is_open() ) {
			return [
				$base + [
					'opens'  => '00:00',
					'closes' => '00:00',
				],
			];
		}

		$entries = [];
		foreach ( $spec->slots as $slot ) {
			$entries[] = $base + [
				'opens'  => $slot->start,
				'closes' => '24:00' === $slot->end ? '23:59' : $slot->end,
			];
		}
		return $entries;
	}
}
