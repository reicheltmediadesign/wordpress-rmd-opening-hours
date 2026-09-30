<?php
/**
 * The set editor: one meta box hosting a React app that keeps the whole set
 * in a hidden JSON field, plus a sidebar box with usage hints.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Assets;
use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\Holidays;
use RMD\OpeningHours\Domain\Validator;
use RMD\OpeningHours\Labels;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Repository\SetRepository;
use RMD\OpeningHours\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class SetMetaBox {

	public const HANDLE = 'rmd-oh-set-editor';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . PostType::NAME, [ self::class, 'add' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'enter_title_here', [ self::class, 'title_placeholder' ], 10, 2 );
	}

	public static function add(): void {
		add_meta_box(
			'rmd-oh-editor',
			__( 'Opening hours', 'rmd-opening-hours' ),
			[ self::class, 'render_editor' ],
			PostType::NAME,
			'normal',
			'high'
		);
		add_meta_box(
			'rmd-oh-usage',
			__( 'Show on the website', 'rmd-opening-hours' ),
			[ self::class, 'render_usage' ],
			PostType::NAME,
			'side',
			'default'
		);
	}

	public static function title_placeholder( string $placeholder, WP_Post $post ): string {
		return PostType::NAME === $post->post_type ? __( 'Name of this set, e.g. “Shop” or “Phone hours”', 'rmd-opening-hours' ) : $placeholder;
	}

	public static function render_editor( WP_Post $post ): void {
		$data = SetRepository::read_meta( (int) $post->ID );

		wp_nonce_field( SaveHandler::NONCE_ACTION . $post->ID, SaveHandler::NONCE_FIELD );
		echo '<input type="hidden" id="rmd-oh-data" name="' . esc_attr( SaveHandler::FIELD ) . '" value="' . esc_attr( (string) wp_json_encode( $data ) ) . '">';
		echo '<div id="rmd-oh-editor-root" class="rmd-oh-editor">';
		echo '<noscript><p>' . esc_html__( 'The opening hours editor needs JavaScript.', 'rmd-opening-hours' ) . '</p></noscript>';
		echo '<p class="rmd-oh-editor__loading">' . esc_html__( 'Loading editor …', 'rmd-opening-hours' ) . '</p>';
		echo '</div>';
	}

	public static function render_usage( WP_Post $post ): void {
		$slug = '' !== $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		if ( '' === $slug ) {
			echo '<p>' . esc_html__( 'Save the set to get its shortcode.', 'rmd-opening-hours' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Block editor: add the blocks “Opening Hours”, “Opening Hours Notice” or “Open Now Status” and pick this set.', 'rmd-opening-hours' ) . '</p>';
		echo '<p>' . esc_html__( 'Shortcodes:', 'rmd-opening-hours' ) . '</p>';
		echo '<code class="rmd-oh-usage__code">[rmd_opening_hours set="' . esc_html( $slug ) . '"]</code><br>';
		echo '<code class="rmd-oh-usage__code">[rmd_opening_hours_notice set="' . esc_html( $slug ) . '"]</code><br>';
		echo '<code class="rmd-oh-usage__code">[rmd_opening_hours_status set="' . esc_html( $slug ) . '"]</code>';
		printf(
			'<p class="description"><a href="%s">%s</a></p>',
			esc_url( admin_url( 'edit.php?post_type=' . PostType::NAME . '&page=' . HelpPage::SLUG ) ),
			esc_html__( 'All fields, options and shortcode attributes are explained on the Help page.', 'rmd-opening-hours' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || PostType::NAME !== $screen->post_type ) {
			return;
		}

		$asset = Assets::build_asset( 'build/admin/set-editor.js' );
		if ( ! is_readable( RMD_OH_DIR . 'build/admin/set-editor.js' ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			RMD_OH_URL . 'build/admin/set-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::HANDLE, 'rmd-opening-hours', RMD_OH_DIR . 'languages' );

		if ( is_readable( RMD_OH_DIR . 'build/admin/set-editor.css' ) ) {
			wp_enqueue_style( self::HANDLE, RMD_OH_URL . 'build/admin/set-editor.css', [ 'wp-components' ], $asset['version'] );
		}

		wp_add_inline_script( self::HANDLE, 'window.rmdOhEditorConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * Static configuration for the editor app.
	 *
	 * @return array<string, mixed>
	 */
	private static function config(): array {
		$year     = (int) substr( Clock::today(), 0, 4 );
		$holidays = [];
		foreach ( Holidays::ids() as $id ) {
			$holidays[] = [
				'id'       => $id,
				'label'    => Labels::holiday( $id ),
				'dates'    => [
					$year     => Holidays::date_of( $id, $year ),
					$year + 1 => Holidays::date_of( $id, $year + 1 ),
				],
				'states'   => array_values( array_filter( Holidays::STATES, static fn( string $state ): bool => Holidays::applies( $id, $state ) ) ),
				'regional' => array_values( array_filter( Holidays::STATES, static fn( string $state ): bool => in_array( $id, Holidays::regional_options( $state ), true ) ) ),
			];
		}

		return [
			'fieldId'      => 'rmd-oh-data',
			'today'        => Clock::today(),
			'weekdays'     => Labels::weekdays(),
			'states'       => Labels::states(),
			'defaultState' => (string) Settings::get( 'default_state', 'SN' ),
			'holidays'     => $holidays,
			'layouts'      => Labels::layouts(),
			'timeStyles'   => Labels::time_styles(),
			'dayNames'     => Labels::day_names(),
			'schemaTypes'  => Validator::SCHEMA_TYPES,
			'limits'       => [
				'slots'   => Validator::MAX_SLOTS_PER_DAY,
				'periods' => Validator::MAX_PERIODS,
				'name'    => Validator::MAX_NAME,
				'text'    => Validator::MAX_TEXT,
				'note'    => Validator::MAX_NOTE,
			],
			'defaults'     => [
				'leadDays' => (int) Settings::get( 'lead_days', 7 ),
				'display'  => Validator::display_defaults(),
				'schema'   => Validator::schema_defaults(),
			],
			'dateFormat'   => (string) get_option( 'date_format', 'j. F Y' ),
		];
	}
}
