<?php
/**
 * Renders the live "open now" indicator. The server resolves the coming days
 * into absolute intervals and embeds them; the front-end script only compares
 * them with the visitor's clock, so cached pages stay correct.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Render;

use RMD\OpeningHours\Assets;
use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\Dates;
use RMD\OpeningHours\Domain\Intervals;
use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Domain\Status;
use RMD\OpeningHours\Labels;
use RMD\OpeningHours\Settings;

defined( 'ABSPATH' ) || exit;

final class StatusRenderer {

	/**
	 * @param array<string, mixed> $options show_next (bool), time_style (string), class (string).
	 */
	public static function render( SetData $set, array $options = [] ): string {
		$payload = self::payload( $set, $options );
		$state   = Status::at( $payload['intervals'], Clock::timestamp() );
		$text    = self::text( $state, $payload['labels'], $payload['tz'], (bool) ( $options['show_next'] ?? true ) );

		$classes = [ 'rmd-oh-status', $state['open'] ? 'is-open' : 'is-closed' ];
		if ( ! empty( $options['class'] ) ) {
			$classes[] = (string) $options['class'];
		}

		$data = [
			'data-rmd-oh-status' => '',
			'data-set'           => $set->slug,
			'data-intervals'     => (string) wp_json_encode( $payload['intervals_compact'] ),
			'data-labels'        => (string) wp_json_encode( $payload['labels'] ),
			'data-tz'            => $payload['tz'],
			'data-horizon'       => (string) $payload['horizon_end'],
			'data-show-next'     => ! empty( $options['show_next'] ?? true ) ? '1' : '0',
			'data-endpoint'      => rest_url( 'rmd-opening-hours/v1/status/' . $set->slug ),
		];

		Assets::enqueue_front();

		return '<span ' . Renderer::wrapper_attributes( $classes, $data, ! empty( $options['block'] ) ) . '>'
			. '<span class="rmd-oh-status__dot" aria-hidden="true"></span> '
			. '<span class="rmd-oh-status__text">' . esc_html( $text ) . '</span>'
			. '</span>';
	}

	/**
	 * Everything the client needs; also served by the REST endpoint.
	 *
	 * @return array{intervals: array, intervals_compact: array, labels: array, tz: string, horizon_end: int, now: int}
	 */
	public static function payload( SetData $set, array $options = [] ): array {
		$days      = (int) Settings::get( 'status_horizon_days', 14 );
		$today     = Clock::today();
		$from      = Dates::add_days( $today, -1 ); // Overnight slots that started yesterday.
		$tz        = Clock::timezone();
		$intervals = Intervals::for_range( $set, $from, $days + 1, $tz );
		$formatter = new Formatter( (string) ( $options['time_style'] ?? Settings::get( 'time_style', '24h' ) ) );
		$weekdays  = Labels::weekdays();
		$compact   = [];
		foreach ( $intervals as $interval ) {
			$compact[] = [
				$interval['start'],
				$interval['end'],
				$interval['date'],
				$formatter->time( $interval['start_time'] ),
				$formatter->time( $interval['end_time'] ),
				$weekdays[ $interval['weekday'] ]['long'] ?? $interval['weekday'],
			];
		}

		$horizon_end = ( new \DateTimeImmutable( Dates::add_days( $today, $days ) . ' 00:00:00', $tz ) )->getTimestamp();

		return [
			'intervals'         => $intervals,
			'intervals_compact' => $compact,
			'labels'            => self::labels(),
			'tz'                => $tz->getName(),
			'horizon_end'       => $horizon_end,
			'now'               => Clock::timestamp(),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return [
			'open'           => __( 'Open now', 'rmd-opening-hours' ),
			'closed'         => __( 'Closed now', 'rmd-opening-hours' ),
			/* translators: %s: closing time */
			'closes_at'      => __( 'closes at %s', 'rmd-opening-hours' ),
			/* translators: %s: opening time */
			'opens_today'    => __( 'opens today at %s', 'rmd-opening-hours' ),
			/* translators: %s: opening time */
			'opens_tomorrow' => __( 'opens tomorrow at %s', 'rmd-opening-hours' ),
			/* translators: 1: weekday, 2: opening time */
			'opens_on'       => __( 'opens %1$s at %2$s', 'rmd-opening-hours' ),
			'separator'      => _x( ' · ', 'status separator', 'rmd-opening-hours' ),
		];
	}

	/**
	 * Server-side text for the initial render and no-JS visitors.
	 *
	 * @param array{open: bool, current: array|null, next: array|null} $state
	 * @param array<string, string>                                     $labels
	 */
	public static function text( array $state, array $labels, string $tz, bool $show_next ): string {
		$formatter = new Formatter( (string) Settings::get( 'time_style', '24h' ) );
		$text      = $state['open'] ? $labels['open'] : $labels['closed'];

		if ( ! $show_next ) {
			return $text;
		}

		if ( $state['open'] && $state['current'] ) {
			return $text . $labels['separator'] . sprintf( $labels['closes_at'], $formatter->time( $state['current']['end_time'] ) );
		}

		if ( ! $state['open'] && $state['next'] ) {
			$today    = Clock::today();
			$tomorrow = Dates::add_days( $today, 1 );
			$time     = $formatter->time( $state['next']['start_time'] );
			if ( $state['next']['date'] === $today ) {
				$detail = sprintf( $labels['opens_today'], $time );
			} elseif ( $state['next']['date'] === $tomorrow ) {
				$detail = sprintf( $labels['opens_tomorrow'], $time );
			} else {
				$weekdays = Labels::weekdays();
				$detail   = sprintf( $labels['opens_on'], $weekdays[ $state['next']['weekday'] ]['long'] ?? '', $time );
			}
			return $text . $labels['separator'] . $detail;
		}

		return $text;
	}
}
