<?php
/**
 * Renders announcements for upcoming and running periods. All notices inside
 * the horizon are pre-rendered with their visibility window as timestamps;
 * the front-end script shows and hides them at the right moment, so the
 * markup stays valid in page caches.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Render;

use DateTimeImmutable;
use RMD\OpeningHours\Assets;
use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\Dates;
use RMD\OpeningHours\Domain\Grouper;
use RMD\OpeningHours\Domain\NoticeSelector;
use RMD\OpeningHours\Domain\Period;
use RMD\OpeningHours\Domain\Schedule;
use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Repository\SetRepository;
use RMD\OpeningHours\Settings;

defined( 'ABSPATH' ) || exit;

final class NoticeRenderer {

	/**
	 * @param SetData[]            $sets    Sets to announce (usually one, or all).
	 * @param array<string, mixed> $options lead_days (int, 0 = default), template (string), dismissible (bool|null), class (string).
	 */
	public static function render( array $sets, array $options = [] ): string {
		$today       = Clock::today();
		$tz          = Clock::timezone();
		$lead        = (int) ( $options['lead_days'] ?? 0 );
		$lead        = $lead > 0 ? $lead : (int) Settings::get( 'lead_days', 7 );
		$horizon     = (int) Settings::get( 'notice_horizon_days', 30 );
		$template    = trim( (string) ( $options['template'] ?? '' ) );
		$template    = '' !== $template ? $template : (string) Settings::get( 'notice_template' );
		$dismissible = array_key_exists( 'dismissible', $options ) && null !== $options['dismissible']
			? Renderer::to_bool( $options['dismissible'] )
			: (bool) Settings::get( 'notice_dismissible', true );

		$items = [];
		foreach ( $sets as $set ) {
			foreach ( NoticeSelector::select( $set, $today, $lead, $horizon ) as $notice ) {
				$items[] = self::item( $set, $notice, $template, $today, $tz, $dismissible );
			}
		}

		Assets::enqueue_front();

		$classes = [ 'rmd-oh-notices' ];
		if ( ! empty( $options['class'] ) ) {
			$classes[] = (string) $options['class'];
		}

		$data = [
			'data-rmd-oh-notices' => '',
			'data-dismissible'    => $dismissible ? '1' : '0',
			'data-endpoint'       => rest_url( 'rmd-opening-hours/v1/notices/' . self::reference_slug( $sets ) ),
			'data-horizon'        => (string) ( new DateTimeImmutable( Dates::add_days( $today, $horizon ) . ' 00:00:00', $tz ) )->getTimestamp(),
		];

		return '<div ' . Renderer::wrapper_attributes( $classes, $data, ! empty( $options['block'] ) ) . '>' . implode( '', $items ) . '</div>';
	}

	/**
	 * @param SetData[] $sets
	 */
	private static function reference_slug( array $sets ): string {
		return 1 === count( $sets ) ? $sets[0]->slug : 'all';
	}

	/**
	 * @param array{period: Period, start: string, end: string, visible_from: string} $notice
	 */
	private static function item( SetData $set, array $notice, string $template, string $today, \DateTimeZone $tz, bool $dismissible ): string {
		$from   = ( new DateTimeImmutable( $notice['visible_from'] . ' 00:00:00', $tz ) )->getTimestamp();
		$until  = ( new DateTimeImmutable( Dates::add_days( $notice['end'], 1 ) . ' 00:00:00', $tz ) )->getTimestamp();
		$active = NoticeSelector::is_active( $notice, $today );
		$key    = $set->slug . ':' . $notice['period']->uid . ':' . $notice['start'];

		$classes = [ 'rmd-oh-notice', 'rmd-oh-notice--' . $notice['period']->mode ];
		if ( $notice['start'] <= $today ) {
			$classes[] = 'is-running';
		}

		$html  = sprintf(
			'<div class="%s" role="status" data-from="%d" data-until="%d" data-key="%s"%s>',
			esc_attr( implode( ' ', $classes ) ),
			$from,
			$until,
			esc_attr( $key ),
			$active ? '' : ' hidden'
		);
		$html .= '<div class="rmd-oh-notice__text">' . self::fill( $template, $set, $notice ) . '</div>';
		if ( $dismissible ) {
			$html .= '<button type="button" class="rmd-oh-notice__dismiss" aria-label="' . esc_attr__( 'Dismiss this notice', 'rmd-opening-hours' ) . '">&times;</button>';
		}
		return $html . '</div>';
	}

	/**
	 * Fills the template placeholders. The template itself is trusted HTML
	 * (limited via wp_kses on save); placeholder values are escaped.
	 *
	 * @param array{period: Period, start: string, end: string} $notice
	 */
	public static function fill( string $template, SetData $set, array $notice ): string {
		$period    = $notice['period'];
		$formatter = new Formatter( (string) $set->display['time_style'] );

		$values = [
			'name'  => $period->name,
			'start' => $formatter->date( $notice['start'] ),
			'end'   => $formatter->date( $notice['end'] ),
			'note'  => $period->note,
			'hours' => self::hours_summary( $set, $period, $notice['start'], $formatter ),
			'set'   => $set->title,
		];

		// Each value gets its own span so themes can style parts of the text.
		$replacements = [];
		foreach ( $values as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = '' === $value
				? ''
				: '<span class="rmd-oh-notice__' . $key . '">' . esc_html( $value ) . '</span>';
		}

		$text = strtr( $template, $replacements );
		$text = (string) preg_replace( '/\(\s*\)/', '', $text ); // Empty brackets from unused placeholders.
		return trim( (string) preg_replace( '/[ \t]{2,}/', ' ', $text ) );
	}

	private static function hours_summary( SetData $set, Period $period, string $start, Formatter $formatter ): string {
		if ( $period->is_closed() ) {
			return __( 'closed', 'rmd-opening-hours' );
		}
		if ( Period::DAILY === $period->mode && $period->day ) {
			return $formatter->day( $period->day );
		}
		if ( Period::WEEKLY === $period->mode && $period->week ) {
			$days = [];
			foreach ( Dates::WEEKDAYS as $weekday ) {
				$days[ $weekday ] = new \RMD\OpeningHours\Domain\DayResult( '', $weekday, $period->week[ $weekday ], \RMD\OpeningHours\Domain\DayResult::SOURCE_PERIOD, $period->uid );
			}
			$parts = [];
			foreach ( Grouper::group( $days ) as $row ) {
				if ( $row['spec']->is_closed() ) {
					continue;
				}
				$parts[] = $formatter->day_range( $row['days'] ) . ' ' . $formatter->day( $row['spec'] );
			}
			return $parts ? implode( ', ', $parts ) : __( 'closed', 'rmd-opening-hours' );
		}
		return '';
	}

	/**
	 * Resolves the "set" option of the notice block/shortcode: a specific set,
	 * or every published set when empty/"all".
	 *
	 * @return SetData[]
	 */
	public static function sets_for( int|string $reference ): array {
		if ( 'all' === $reference || '' === (string) $reference || 0 === $reference || '0' === $reference ) {
			return SetRepository::all();
		}
		$set = SetRepository::resolve( $reference );
		return $set ? [ $set ] : [];
	}
}
