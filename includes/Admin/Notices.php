<?php
/**
 * Admin notices and translation of validator error codes into messages.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Labels;
use RMD\OpeningHours\PostType;

defined( 'ABSPATH' ) || exit;

final class Notices {

	private const TRANSIENT_PREFIX = 'rmd_oh_errors_';

	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'render' ] );
	}

	/**
	 * Stores validation errors for the current user so they survive the
	 * redirect after saving a post.
	 *
	 * @param list<array{path: string, code: string, args: array}> $errors
	 */
	public static function remember( int $post_id, array $errors ): void {
		set_transient( self::TRANSIENT_PREFIX . get_current_user_id() . '_' . $post_id, $errors, 5 * MINUTE_IN_SECONDS );
	}

	public static function render(): void {
		$screen = get_current_screen();
		if ( ! $screen || PostType::NAME !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id ) {
			return;
		}
		$key    = self::TRANSIENT_PREFIX . get_current_user_id() . '_' . $post_id;
		$errors = get_transient( $key );
		if ( ! is_array( $errors ) || [] === $errors ) {
			return;
		}
		delete_transient( $key );

		echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Some opening hours could not be saved as entered:', 'rmd-opening-hours' ) . '</strong></p><ul class="ul-disc">';
		foreach ( array_slice( $errors, 0, 15 ) as $error ) {
			echo '<li>' . esc_html( self::message( $error ) ) . '</li>';
		}
		if ( count( $errors ) > 15 ) {
			echo '<li>' . esc_html( sprintf( /* translators: %d: number of additional errors */ __( '… and %d more.', 'rmd-opening-hours' ), count( $errors ) - 15 ) ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * @param array{path: string, code: string, args: array} $error
	 */
	public static function message( array $error ): string {
		$args     = $error['args'] ?? [];
		$messages = [
			'invalid_time'        => __( 'Invalid time. Use the format HH:MM.', 'rmd-opening-hours' ),
			'zero_length'         => __( 'Start and end time must differ.', 'rmd-opening-hours' ),
			'slots_overlap'       => __( 'Time slots overlap.', 'rmd-opening-hours' ),
			'slots_required'      => __( 'An open day needs at least one time slot; the day was set to closed.', 'rmd-opening-hours' ),
			'too_many_slots'      => sprintf( /* translators: %d: maximum number of slots */ __( 'At most %d time slots per day are allowed.', 'rmd-opening-hours' ), (int) ( $args[0] ?? 0 ) ),
			'text_required'       => __( 'Enter a text (e.g. “by appointment”) or choose another mode; the day was set to closed.', 'rmd-opening-hours' ),
			'invalid_date'        => __( 'Invalid date.', 'rmd-opening-hours' ),
			'end_before_start'    => __( 'The end date is before the start date; the period was dropped.', 'rmd-opening-hours' ),
			'recurring_too_long'  => __( 'A yearly recurring period may not be longer than one year; the period was dropped.', 'rmd-opening-hours' ),
			'name_required'       => __( 'Every period needs a name.', 'rmd-opening-hours' ),
			'too_many_periods'    => sprintf( /* translators: %d: maximum number of periods */ __( 'At most %d periods are allowed.', 'rmd-opening-hours' ), (int) ( $args[0] ?? 0 ) ),
			'invalid_mode'        => __( 'Unknown mode; the default was used.', 'rmd-opening-hours' ),
			'invalid_state'       => __( 'Unknown federal state; the default was used.', 'rmd-opening-hours' ),
			'invalid_holiday'     => __( 'Unknown holiday; the rule was ignored.', 'rmd-opening-hours' ),
			'invalid_layout'      => __( 'Unknown layout; the default was used.', 'rmd-opening-hours' ),
			'invalid_time_style'  => __( 'Unknown time format; the default was used.', 'rmd-opening-hours' ),
			'invalid_day_names'   => __( 'Unknown day name format; the default was used.', 'rmd-opening-hours' ),
			'invalid_schema_type' => __( 'Unknown business type; “LocalBusiness” was used.', 'rmd-opening-hours' ),
			'invalid_url'         => __( 'Invalid URL (only http and https are allowed).', 'rmd-opening-hours' ),
			'invalid_number'      => __( 'Please enter a whole number.', 'rmd-opening-hours' ),
			'invalid_payload'     => __( 'The editor data could not be read; nothing was changed. Please reload the page and try again.', 'rmd-opening-hours' ),
			'out_of_range'        => sprintf( /* translators: 1: minimum, 2: maximum */ __( 'The value must be between %1$d and %2$d.', 'rmd-opening-hours' ), (int) ( $args[0] ?? 0 ), (int) ( $args[1] ?? 0 ) ),
		];

		$text = $messages[ $error['code'] ] ?? __( 'Invalid value.', 'rmd-opening-hours' );

		return self::describe_path( (string) $error['path'] ) . ': ' . $text;
	}

	/**
	 * Turns "periods.2.day.slots.1.end" into a human readable location.
	 */
	private static function describe_path( string $path ): string {
		$parts    = explode( '.', $path );
		$weekdays = Labels::weekdays();
		$labels   = [];

		for ( $i = 0, $n = count( $parts ); $i < $n; $i++ ) {
			$part = $parts[ $i ];
			switch ( $part ) {
				case 'regular':
					$labels[] = __( 'Regular hours', 'rmd-opening-hours' );
					break;
				case 'periods':
					$index    = isset( $parts[ $i + 1 ] ) && ctype_digit( $parts[ $i + 1 ] ) ? (int) $parts[ ++$i ] + 1 : null;
					$labels[] = null === $index ? __( 'Periods', 'rmd-opening-hours' ) : sprintf( /* translators: %d: period number */ __( 'Period %d', 'rmd-opening-hours' ), $index );
					break;
				case 'holidays':
					$labels[] = __( 'Holidays', 'rmd-opening-hours' );
					break;
				case 'rules':
					if ( isset( $parts[ $i + 1 ] ) ) {
						$labels[] = Labels::holiday( $parts[ ++$i ] );
					}
					break;
				case 'slots':
					$index    = isset( $parts[ $i + 1 ] ) && ctype_digit( $parts[ $i + 1 ] ) ? (int) $parts[ ++$i ] + 1 : null;
					$labels[] = null === $index ? __( 'Time slots', 'rmd-opening-hours' ) : sprintf( /* translators: %d: slot number */ __( 'Slot %d', 'rmd-opening-hours' ), $index );
					break;
				case 'week':
				case 'day':
				case 'settings':
				case 'display':
				case 'schema':
					break;
				default:
					if ( isset( $weekdays[ $part ] ) ) {
						$labels[] = $weekdays[ $part ]['long'];
					} else {
						$labels[] = ucfirst( str_replace( '_', ' ', $part ) );
					}
			}
		}

		return implode( ' › ', $labels );
	}
}
