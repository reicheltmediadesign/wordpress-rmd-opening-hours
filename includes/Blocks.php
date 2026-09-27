<?php
/**
 * Registers the three blocks from the built metadata collection and wires the
 * render callbacks to the renderers.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Render\NoticeRenderer;
use RMD\OpeningHours\Render\Renderer;
use RMD\OpeningHours\Render\StatusRenderer;
use RMD\OpeningHours\Repository\SetRepository;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public const NAMES = [ 'opening-hours', 'notice', 'status' ];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 20 );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'editor_translations' ] );
	}

	public static function register(): void {
		$path     = RMD_OH_DIR . 'build/blocks';
		$manifest = RMD_OH_DIR . 'build/blocks-manifest.php';

		if ( ! is_readable( $manifest ) ) {
			// Assets not built (development checkout without `npm run build`).
			add_action( 'admin_notices', [ self::class, 'missing_build_notice' ] );
			return;
		}

		wp_register_block_types_from_metadata_collection( $path, $manifest );
	}

	/**
	 * block.json's "textdomain" makes core call wp_set_script_translations()
	 * without a path, which only finds translations in WP_LANG_DIR. Point the
	 * editor scripts at the bundled languages/ folder instead.
	 */
	public static function editor_translations(): void {
		foreach ( self::NAMES as $name ) {
			$handle = 'rmd-opening-hours-' . $name . '-editor-script';
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_set_script_translations( $handle, 'rmd-opening-hours', RMD_OH_DIR . 'languages' );
			}
		}
	}

	public static function missing_build_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'RMD Opening Hours: the built block files are missing. You probably installed the “Source code” download from GitHub. Please install the file rmd-opening-hours.zip from the release page instead (or run “npm install && npm run build” in a development checkout).', 'rmd-opening-hours' ) . '</p></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Render callbacks (called from build/blocks/<name>/render.php)       */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render_opening_hours( array $attributes ): string {
		$set = SetRepository::resolve( (int) ( $attributes['setId'] ?? 0 ) );
		if ( ! $set ) {
			return self::placeholder( $attributes );
		}

		return Renderer::render(
			$set,
			[
				'block'           => true,
				'layout'          => (string) ( $attributes['layout'] ?? '' ),
				'group_days'      => self::tri_state( $attributes['groupDays'] ?? '' ),
				'highlight_today' => self::tri_state( $attributes['highlightToday'] ?? '' ),
				'show_notes'      => self::tri_state( $attributes['showNotes'] ?? '' ),
				'time_style'      => (string) ( $attributes['timeStyle'] ?? '' ),
				'week_mode'       => (string) ( $attributes['weekMode'] ?? '' ),
				'show_title'      => ! empty( $attributes['showTitle'] ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render_notice( array $attributes ): string {
		$sets = NoticeRenderer::sets_for( (int) ( $attributes['setId'] ?? 0 ) );
		if ( [] === $sets ) {
			return self::placeholder( $attributes );
		}

		$html = NoticeRenderer::render(
			$sets,
			[
				'block'       => true,
				'lead_days'   => (int) ( $attributes['leadDays'] ?? 0 ),
				'template'    => wp_kses( (string) ( $attributes['template'] ?? '' ), Shortcodes::template_html() ),
				'dismissible' => self::tri_state( $attributes['dismissible'] ?? '' ),
			]
		);

		if ( ! empty( $attributes['isPreview'] ) && false === strpos( $html, 'rmd-oh-notice ' ) ) {
			$html .= '<p class="rmd-oh-editor-hint">' . esc_html__( 'No notice is scheduled right now. This block stays empty on the website until a period comes within its lead time.', 'rmd-opening-hours' ) . '</p>';
		}

		return $html;
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render_status( array $attributes ): string {
		$set = SetRepository::resolve( (int) ( $attributes['setId'] ?? 0 ) );
		if ( ! $set ) {
			return self::placeholder( $attributes );
		}

		return StatusRenderer::render(
			$set,
			[
				'block'     => true,
				'show_next' => ! isset( $attributes['showNext'] ) || ! empty( $attributes['showNext'] ),
			]
		);
	}

	/**
	 * "", "yes" or "no" from the block inspector; "" keeps the set's default.
	 *
	 * @param mixed $value
	 */
	private static function tri_state( $value ): ?bool {
		$value = strtolower( trim( (string) $value ) );
		if ( 'yes' === $value ) {
			return true;
		}
		if ( 'no' === $value ) {
			return false;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	private static function placeholder( array $attributes ): string {
		if ( empty( $attributes['isPreview'] ) ) {
			return '';
		}
		return '<p class="rmd-oh-editor-hint">' . esc_html__( 'No opening hours set found. Create one under Opening Hours in the admin menu, then select it in the block settings.', 'rmd-opening-hours' ) . '</p>';
	}
}
