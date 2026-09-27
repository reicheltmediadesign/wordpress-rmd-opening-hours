<?php
/**
 * Shortcodes mirroring the three blocks.
 *
 * [rmd_opening_hours set="shop" layout="table|list|compact" group="yes|no" today="yes|no" notes="yes|no" time_style="24h|24h-short|12h" week="current|regular" title="yes|no" class=""]
 * [rmd_opening_hours_notice set="shop|all" lead_days="7" template="" dismissible="yes|no" class=""]
 * [rmd_opening_hours_status set="shop" next="yes|no" class=""]
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Render\NoticeRenderer;
use RMD\OpeningHours\Render\Renderer;
use RMD\OpeningHours\Render\StatusRenderer;
use RMD\OpeningHours\Repository\SetRepository;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		add_shortcode( 'rmd_opening_hours', [ self::class, 'hours' ] );
		add_shortcode( 'rmd_opening_hours_notice', [ self::class, 'notice' ] );
		add_shortcode( 'rmd_opening_hours_status', [ self::class, 'status' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function hours( $atts ): string {
		$atts = shortcode_atts(
			[
				'set'        => '',
				'layout'     => '',
				'group'      => '',
				'today'      => '',
				'notes'      => '',
				'time_style' => '',
				'week'       => '',
				'title'      => 'no',
				'class'      => '',
			],
			is_array( $atts ) ? $atts : [],
			'rmd_opening_hours'
		);

		$set = SetRepository::resolve( self::reference( $atts['set'] ) );
		if ( ! $set ) {
			return self::missing();
		}

		return Renderer::render(
			$set,
			[
				'layout'          => sanitize_key( $atts['layout'] ),
				'group_days'      => $atts['group'],
				'highlight_today' => $atts['today'],
				'show_notes'      => $atts['notes'],
				'time_style'      => sanitize_key( $atts['time_style'] ),
				'week_mode'       => sanitize_key( $atts['week'] ),
				'show_title'      => Renderer::to_bool( $atts['title'] ),
				'class'           => self::css_class( $atts['class'] ),
			]
		);
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function notice( $atts ): string {
		$atts = shortcode_atts(
			[
				'set'         => 'all',
				'lead_days'   => '0',
				'template'    => '',
				'dismissible' => '',
				'class'       => '',
			],
			is_array( $atts ) ? $atts : [],
			'rmd_opening_hours_notice'
		);

		$sets = NoticeRenderer::sets_for( self::reference( $atts['set'] ) );
		if ( [] === $sets ) {
			return '';
		}

		return NoticeRenderer::render(
			$sets,
			[
				'lead_days'   => absint( $atts['lead_days'] ),
				'template'    => wp_kses( (string) $atts['template'], self::template_html() ),
				'dismissible' => '' === $atts['dismissible'] ? null : Renderer::to_bool( $atts['dismissible'] ),
				'class'       => self::css_class( $atts['class'] ),
			]
		);
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function status( $atts ): string {
		$atts = shortcode_atts(
			[
				'set'   => '',
				'next'  => 'yes',
				'class' => '',
			],
			is_array( $atts ) ? $atts : [],
			'rmd_opening_hours_status'
		);

		$set = SetRepository::resolve( self::reference( $atts['set'] ) );
		if ( ! $set ) {
			return self::missing();
		}

		return StatusRenderer::render(
			$set,
			[
				'show_next' => Renderer::to_bool( $atts['next'] ),
				'class'     => self::css_class( $atts['class'] ),
			]
		);
	}

	/**
	 * Allowed HTML inside notice templates.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function template_html(): array {
		return [
			'strong' => [],
			'em'     => [],
			'b'      => [],
			'i'      => [],
			'br'     => [],
			'span'   => [ 'class' => true ],
			'a'      => [
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			],
		];
	}

	private static function reference( string $value ): int|string {
		$value = trim( $value );
		return ctype_digit( $value ) ? (int) $value : sanitize_title( $value );
	}

	private static function css_class( string $value ): string {
		return implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', trim( $value ) ) ?: [] ) ) );
	}

	private static function missing(): string {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			return '';
		}
		return '<p class="rmd-oh rmd-oh--missing">' . esc_html__( 'RMD Opening Hours: no matching set found. This message is only visible to editors.', 'rmd-opening-hours' ) . '</p>';
	}
}
