<?php
/**
 * Renders the opening hours of a set in one of the layouts. Shared by the
 * block render callback and the shortcode.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Render;

use RMD\OpeningHours\Assets;
use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\DayResult;
use RMD\OpeningHours\Domain\Grouper;
use RMD\OpeningHours\Domain\Schedule;
use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Domain\Validator;
use RMD\OpeningHours\StructuredData;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/**
	 * Display options accepted from blocks and shortcodes. Values of "" or
	 * "default" fall back to the set's own display settings.
	 */
	public const OPTION_KEYS = [ 'layout', 'group_days', 'highlight_today', 'show_notes', 'time_style', 'week_mode', 'show_holidays' ];

	/**
	 * @param array<string, mixed> $options Overrides, see OPTION_KEYS. Extra keys: class (string), show_title (bool).
	 */
	public static function render( SetData $set, array $options = [] ): string {
		$display = self::merge_display( $set, $options );
		$today   = Clock::today();

		$days = 'regular' === $display['week_mode'] ? Schedule::regular_week( $set ) : Schedule::week_of( $set, $today );
		$rows = Grouper::group( $days, (bool) $display['group_days'] );

		// Generic "Public holidays" row after Sunday, unless holidays simply follow the regular hours.
		$holiday_spec = $set->holidays->default_spec();
		if ( ! empty( $display['show_holidays'] ) && $holiday_spec ) {
			$rows[] = [
				'days'  => [],
				'spec'  => $holiday_spec,
				'label' => __( 'Public holidays', 'rmd-opening-hours' ),
				'class' => 'is-holidays',
			];
		}

		$formatter = new Formatter( (string) $display['time_style'] );
		$layout    = (string) $display['layout'];

		$classes = [ 'rmd-oh', 'rmd-oh--' . $layout ];
		if ( ! empty( $options['class'] ) ) {
			$classes[] = (string) $options['class'];
		}

		$html = '<div ' . self::wrapper_attributes( $classes, [ 'data-rmd-oh-set' => $set->slug ], ! empty( $options['block'] ) ) . '>';
		if ( ! empty( $options['show_title'] ) ) {
			$html .= '<p class="rmd-oh__title">' . esc_html( $set->title ) . '</p>';
		}

		$html .= match ( $layout ) {
			'list'       => self::list( $rows, $formatter, $display, $today ),
			'compact'    => self::compact( $rows, $formatter, $display, $today ),
			'paragraphs' => self::paragraphs( $rows, $formatter, $display, $today ),
			default      => self::table( $rows, $formatter, $display, $today ),
		};

		$html .= '</div>';

		Assets::enqueue_front();
		StructuredData::mark_used( $set );

		return $html;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function merge_display( SetData $set, array $options ): array {
		$display = array_merge( Validator::display_defaults(), $set->display );
		foreach ( self::OPTION_KEYS as $key ) {
			if ( ! array_key_exists( $key, $options ) ) {
				continue;
			}
			$value = $options[ $key ];
			if ( null === $value || '' === $value || 'default' === $value ) {
				continue;
			}
			if ( in_array( $key, [ 'group_days', 'highlight_today', 'show_notes', 'show_holidays' ], true ) ) {
				$display[ $key ] = self::to_bool( $value );
			} else {
				$display[ $key ] = (string) $value;
			}
		}

		if ( ! in_array( $display['layout'], Validator::LAYOUTS, true ) ) {
			$display['layout'] = 'table';
		}
		if ( ! in_array( $display['time_style'], Validator::TIME_STYLES, true ) ) {
			$display['time_style'] = '24h';
		}
		if ( ! in_array( $display['week_mode'], [ 'current', 'regular' ], true ) ) {
			$display['week_mode'] = 'current';
		}

		return $display;
	}

	/**
	 * Root element attributes. Inside a block render callback the block
	 * supports (colors, spacing, …) are merged in via get_block_wrapper_attributes().
	 *
	 * @param string[]              $classes
	 * @param array<string, string> $extra
	 */
	public static function wrapper_attributes( array $classes, array $extra, bool $is_block ): string {
		$attributes = array_merge( [ 'class' => implode( ' ', array_filter( $classes ) ) ], $extra );
		if ( $is_block && function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( $attributes );
		}
		$pairs = [];
		foreach ( $attributes as $name => $value ) {
			$pairs[] = $name . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $pairs );
	}

	/**
	 * @param mixed $value
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * @param list<array{days: DayResult[], spec: \RMD\OpeningHours\Domain\DaySpec}> $rows
	 */
	private static function table( array $rows, Formatter $formatter, array $display, string $today ): string {
		$html = '<table class="rmd-oh__table"><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr class="' . esc_attr( self::row_classes( $row, $display, $today ) ) . '">';
			$html .= '<th scope="row" class="rmd-oh__day">' . esc_html( self::row_label( $row, $formatter ) ) . '</th>';
			$html .= '<td class="rmd-oh__hours">' . self::hours_cell( $row, $formatter, $display ) . '</td>';
			$html .= '</tr>';
		}
		return $html . '</tbody></table>';
	}

	private static function list( array $rows, Formatter $formatter, array $display, string $today ): string {
		$html = '<dl class="rmd-oh__list">';
		foreach ( $rows as $row ) {
			$classes = self::row_classes( $row, $display, $today );
			$html   .= '<div class="' . esc_attr( $classes ) . '">';
			$html   .= '<dt class="rmd-oh__day">' . esc_html( self::row_label( $row, $formatter ) ) . '</dt>';
			$html   .= '<dd class="rmd-oh__hours">' . self::hours_cell( $row, $formatter, $display ) . '</dd>';
			$html   .= '</div>';
		}
		return $html . '</dl>';
	}

	private static function compact( array $rows, Formatter $formatter, array $display, string $today ): string {
		$items = [];
		foreach ( $rows as $row ) {
			$classes = self::row_classes( $row, $display, $today );
			$item    = '<span class="' . esc_attr( $classes ) . '">';
			$item   .= '<span class="rmd-oh__day">' . esc_html( self::row_label( $row, $formatter ) ) . '</span> ';
			$item   .= '<span class="rmd-oh__hours">' . self::hours_cell( $row, $formatter, $display, true ) . '</span>';
			$item   .= '</span>';
			$items[] = $item;
		}
		return '<p class="rmd-oh__compact">' . implode( '<span class="rmd-oh__sep" aria-hidden="true"> · </span>', $items ) . '</p>';
	}

	/**
	 * Written-out variant: full day names in bold, one line per slot, the note
	 * as its own paragraph.
	 */
	private static function paragraphs( array $rows, Formatter $formatter, array $display, string $today ): string {
		$html = '';
		foreach ( $rows as $row ) {
			$spec  = $row['spec'];
			$label = isset( $row['label'] ) ? (string) $row['label'] : $formatter->day_range( $row['days'], false );
			$html .= '<div class="' . esc_attr( self::row_classes( $row, $display, $today ) . ' rmd-oh__group' ) . '">';
			$html .= '<p class="rmd-oh__day"><strong>' . esc_html( $label ) . ':</strong></p>';
			if ( $spec->is_open() ) {
				$lines = array_map( static fn( $slot ): string => esc_html( $formatter->slot( $slot ) ), $spec->slots );
				$html .= '<p class="rmd-oh__hours">' . implode( '<br>', $lines ) . '</p>';
			} else {
				$html .= '<p class="rmd-oh__hours rmd-oh__closed">' . esc_html( $formatter->day( $spec ) ) . '</p>';
			}
			if ( ! empty( $display['show_notes'] ) ) {
				$note = isset( $row['days'][0] ) ? $formatter->note( $row['days'][0] ) : $spec->note;
				if ( '' !== $note ) {
					$html .= '<p class="rmd-oh__note">' . esc_html( $note ) . '</p>';
				}
			}
			$html .= '</div>';
		}
		return $html;
	}

	private static function hours_cell( array $row, Formatter $formatter, array $display, bool $inline = false ): string {
		$spec = $row['spec'];
		$html = '';

		if ( $spec->is_open() ) {
			$slots = array_map( static fn( $slot ): string => '<span class="rmd-oh__slot">' . esc_html( $formatter->slot( $slot ) ) . '</span>', $spec->slots );
			$html .= implode( $inline ? ', ' : '', $slots );
		} else {
			$html .= '<span class="rmd-oh__closed">' . esc_html( $formatter->day( $spec ) ) . '</span>';
		}

		if ( ! empty( $display['show_notes'] ) ) {
			$note = isset( $row['days'][0] ) ? $formatter->note( $row['days'][0] ) : $spec->note;
			if ( '' !== $note ) {
				$html .= ( $inline ? ' ' : '' ) . '<span class="rmd-oh__note">' . esc_html( $note ) . '</span>';
			}
		}

		return $html;
	}

	private static function row_label( array $row, Formatter $formatter ): string {
		return isset( $row['label'] ) ? (string) $row['label'] : $formatter->day_range( $row['days'] );
	}

	private static function row_classes( array $row, array $display, string $today ): string {
		$classes = [ 'rmd-oh__row' ];
		if ( ! empty( $row['class'] ) ) {
			$classes[] = (string) $row['class'];
		}
		$dates = array_map( static fn( DayResult $day ): string => $day->date, $row['days'] );
		if ( ! empty( $display['highlight_today'] ) && in_array( $today, $dates, true ) ) {
			$classes[] = 'is-today';
		}
		if ( isset( $row['days'][0] ) && $row['days'][0]->is_exception() ) {
			$classes[] = 'is-exception';
			$classes[] = 'is-' . $row['days'][0]->source;
		}
		if ( $row['spec']->is_closed() ) {
			$classes[] = 'is-closed';
		}
		return implode( ' ', $classes );
	}
}
