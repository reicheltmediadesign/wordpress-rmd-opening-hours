<?php
/**
 * Global plugin settings stored in one option.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Domain\Validator;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'rmd_oh_settings';
	public const GROUP  = 'rmd_oh_settings_group';

	private static ?array $cache = null;

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'update_option_' . self::OPTION, [ self::class, 'flush' ] );
		add_action( 'add_option_' . self::OPTION, [ self::class, 'flush' ] );
	}

	public static function defaults(): array {
		return Validator::settings_defaults();
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'default'           => self::defaults(),
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize' ],
			]
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 */
	public static function sanitize( $value ): array {
		$value = is_array( $value ) ? $value : [];

		// The roles checklist posts nothing when every box is unchecked.
		if ( ! isset( $value['roles'] ) && ! empty( $value['roles_submitted'] ) ) {
			$value['roles'] = [];
		}
		unset( $value['roles_submitted'] );

		$validator = new Validator();
		$result    = $validator->normalize_settings( $value );
		$data      = $result['data'];

		// Only users who may manage roles can change who has access; keep the
		// stored value for everyone else and drop unknown role slugs.
		if ( ! current_user_can( 'promote_users' ) || ! isset( $value['roles'] ) ) {
			$data['roles'] = (array) self::get( 'roles', [] );
		} else {
			$existing      = array_keys( wp_roles()->roles );
			$data['roles'] = array_values( array_intersect( $data['roles'], $existing ) );
		}

		foreach ( $result['errors'] as $error ) {
			add_settings_error( self::OPTION, 'rmd_oh_' . str_replace( '.', '_', $error['path'] ), Admin\Notices::message( $error ) );
		}

		return $data;
	}

	/**
	 * @param string|null $key      Setting key; null returns the whole array.
	 * @param mixed       $fallback Returned when the key is unknown.
	 * @return mixed
	 */
	public static function get( ?string $key = null, $fallback = null ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, [] );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : [] );
		}

		if ( null === $key ) {
			return self::$cache;
		}

		return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : $fallback;
	}

	public static function flush(): void {
		self::$cache = null;
	}
}
