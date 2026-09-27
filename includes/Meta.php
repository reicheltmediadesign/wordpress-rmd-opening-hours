<?php
/**
 * Registers the post meta that stores a set. The meta keys are private (leading
 * underscore), never exposed via REST, and every write runs through the Validator.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Domain\Validator;

defined( 'ABSPATH' ) || exit;

final class Meta {

	public const REGULAR  = '_rmd_oh_regular';
	public const PERIODS  = '_rmd_oh_periods';
	public const HOLIDAYS = '_rmd_oh_holidays';
	public const DISPLAY  = '_rmd_oh_display';
	public const SCHEMA   = '_rmd_oh_schema';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	/**
	 * @return string[]
	 */
	public static function keys(): array {
		return [ self::REGULAR, self::PERIODS, self::HOLIDAYS, self::DISPLAY, self::SCHEMA ];
	}

	public static function register(): void {
		$validator = new Validator();

		$definitions = [
			self::REGULAR  => [ $validator, 'normalize_week' ],
			self::PERIODS  => [ $validator, 'normalize_periods' ],
			self::HOLIDAYS => [ $validator, 'normalize_holidays' ],
			self::DISPLAY  => [ $validator, 'normalize_display' ],
			self::SCHEMA   => [ $validator, 'normalize_schema' ],
		];

		foreach ( $definitions as $key => $normalizer ) {
			register_post_meta(
				PostType::NAME,
				$key,
				[
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => static fn(): bool => Capabilities::current_user_can(),
					'sanitize_callback' => static function ( $value ) use ( $normalizer ): array {
						$result = call_user_func( $normalizer, is_array( $value ) ? $value : [] );
						return $result['data'];
					},
				]
			);
		}
	}
}
