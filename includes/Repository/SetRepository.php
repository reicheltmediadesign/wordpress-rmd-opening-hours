<?php
/**
 * Loads and stores sets (post + meta) as SetData objects.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Repository;

use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Domain\Validator;
use RMD\OpeningHours\Meta;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class SetRepository {

	/** @var array<int, SetData|null> */
	private static array $cache = [];

	public static function get( int $id ): ?SetData {
		if ( $id <= 0 ) {
			return null;
		}
		if ( array_key_exists( $id, self::$cache ) ) {
			return self::$cache[ $id ];
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || PostType::NAME !== $post->post_type || 'publish' !== $post->post_status ) {
			self::$cache[ $id ] = null;
			return null;
		}
		self::$cache[ $id ] = self::from_post( $post );
		return self::$cache[ $id ];
	}

	public static function get_by_slug( string $slug ): ?SetData {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$posts = get_posts(
			[
				'post_type'        => PostType::NAME,
				'post_status'      => 'publish',
				'name'             => $slug,
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => false,
			]
		);
		return $posts ? self::get( (int) $posts[0]->ID ) : null;
	}

	/**
	 * Resolves a block/shortcode reference: numeric id, slug, or empty for the default set.
	 */
	public static function resolve( int|string $reference ): ?SetData {
		if ( is_int( $reference ) || ctype_digit( (string) $reference ) ) {
			$id = (int) $reference;
			return $id > 0 ? self::get( $id ) : self::default_set();
		}
		return '' === trim( (string) $reference ) ? self::default_set() : self::get_by_slug( (string) $reference );
	}

	public static function default_set(): ?SetData {
		$configured = (int) Settings::get( 'default_set', 0 );
		if ( $configured > 0 ) {
			$set = self::get( $configured );
			if ( $set ) {
				return $set;
			}
		}
		$all = self::all();
		return $all[0] ?? null;
	}

	/**
	 * @return SetData[] Published sets ordered by title.
	 */
	public static function all(): array {
		$posts = get_posts(
			[
				'post_type'        => PostType::NAME,
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			]
		);
		$sets  = [];
		foreach ( $posts as $post ) {
			$set = self::get( (int) $post->ID );
			if ( $set ) {
				$sets[] = $set;
			}
		}
		return $sets;
	}

	/**
	 * Builds a SetData from a post regardless of status (used by the admin editor).
	 */
	public static function from_post( WP_Post $post ): SetData {
		return SetData::from_arrays( (int) $post->ID, (string) $post->post_name, (string) $post->post_title, self::read_meta( (int) $post->ID ) );
	}

	/**
	 * @return array{regular: array, periods: array, holidays: array, display: array, schema: array}
	 */
	public static function read_meta( int $post_id ): array {
		$regular = get_post_meta( $post_id, Meta::REGULAR, true );
		if ( ! is_array( $regular ) || [] === $regular ) {
			$regular = Validator::default_week();
		}
		$holidays = get_post_meta( $post_id, Meta::HOLIDAYS, true );
		if ( ! is_array( $holidays ) || [] === $holidays ) {
			$holidays = Validator::holidays_defaults( (string) Settings::get( 'default_state', 'SN' ) );
		}

		return [
			'regular'  => $regular,
			'periods'  => self::array_meta( $post_id, Meta::PERIODS ),
			'holidays' => $holidays,
			'display'  => array_merge( Validator::display_defaults(), self::array_meta( $post_id, Meta::DISPLAY ) ),
			'schema'   => array_merge( Validator::schema_defaults(), self::array_meta( $post_id, Meta::SCHEMA ) ),
		];
	}

	/**
	 * Persists validated data. Callers must have validated with Validator first;
	 * the registered meta sanitizers run again as a safety net.
	 *
	 * @param array{regular: array, periods: array, holidays: array, display: array, schema: array} $data
	 */
	public static function write_meta( int $post_id, array $data ): void {
		update_post_meta( $post_id, Meta::REGULAR, $data['regular'] );
		update_post_meta( $post_id, Meta::PERIODS, $data['periods'] );
		update_post_meta( $post_id, Meta::HOLIDAYS, $data['holidays'] );
		update_post_meta( $post_id, Meta::DISPLAY, $data['display'] );
		update_post_meta( $post_id, Meta::SCHEMA, $data['schema'] );
		unset( self::$cache[ $post_id ] );
	}

	public static function flush( int $post_id = 0 ): void {
		if ( $post_id > 0 ) {
			unset( self::$cache[ $post_id ] );
			return;
		}
		self::$cache = [];
	}

	private static function array_meta( int $post_id, string $key ): array {
		$value = get_post_meta( $post_id, $key, true );
		return is_array( $value ) ? $value : [];
	}
}
