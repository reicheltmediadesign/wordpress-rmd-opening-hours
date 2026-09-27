<?php
/**
 * Extra columns in the sets overview.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\NoticeSelector;
use RMD\OpeningHours\Labels;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Render\Formatter;
use RMD\OpeningHours\Repository\SetRepository;
use RMD\OpeningHours\Settings;

defined( 'ABSPATH' ) || exit;

final class ListTable {

	public static function init(): void {
		add_filter( 'manage_' . PostType::NAME . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . PostType::NAME . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_filter( 'post_row_actions', [ self::class, 'row_actions' ], 10, 2 );
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['rmd_oh_shortcode'] = __( 'Shortcode', 'rmd-opening-hours' );
		$columns['rmd_oh_periods']   = __( 'Periods', 'rmd-opening-hours' );
		$columns['rmd_oh_next']      = __( 'Next change', 'rmd-opening-hours' );
		$columns['rmd_oh_state']     = __( 'Holidays', 'rmd-opening-hours' );
		if ( '' !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function column( string $column, int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$set = SetRepository::from_post( $post );

		switch ( $column ) {
			case 'rmd_oh_shortcode':
				echo '<code>[rmd_opening_hours set="' . esc_html( $set->slug ) . '"]</code>';
				break;

			case 'rmd_oh_periods':
				echo esc_html( (string) count( $set->periods ) );
				break;

			case 'rmd_oh_next':
				$today   = Clock::today();
				$notices = NoticeSelector::select( $set, $today, (int) Settings::get( 'lead_days', 7 ), 365 );
				if ( [] === $notices ) {
					echo '<span aria-hidden="true">—</span>';
					break;
				}
				$formatter = new Formatter();
				$next      = $notices[0];
				$running   = $next['start'] <= $today;
				printf(
					'<strong>%s</strong><br>%s',
					esc_html( $next['period']->name ),
					esc_html(
						$running
							/* translators: %s: end date */
							? sprintf( __( 'until %s', 'rmd-opening-hours' ), $formatter->date( $next['end'] ) )
							/* translators: 1: start date, 2: end date */
							: sprintf( __( '%1$s – %2$s', 'rmd-opening-hours' ), $formatter->date( $next['start'] ), $formatter->date( $next['end'] ) )
					)
				);
				break;

			case 'rmd_oh_state':
				echo esc_html( Labels::states()[ $set->holidays->state ] ?? $set->holidays->state );
				break;
		}
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( PostType::NAME === $post->post_type ) {
			unset( $actions['view'], $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}
}
