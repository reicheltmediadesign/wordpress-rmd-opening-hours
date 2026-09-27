<?php
/**
 * JSON export/import of all sets and the plugin settings, e.g. to move a
 * configuration between staging and production.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Domain\Validator;
use RMD\OpeningHours\Repository\SetRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ExportImport {

	public const FORMAT    = 'rmd-opening-hours/1';
	public const MAX_BYTES = 1048576; // 1 MiB.

	/**
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$sets = array_map(
			static fn( SetData $set ): array => [
				'slug'  => $set->slug,
				'title' => $set->title,
			] + $set->to_array(),
			SetRepository::all()
		);

		$settings = Settings::get();
		unset( $settings['default_set'], $settings['version'], $settings['roles'] ); // Site-specific.

		return [
			'format'         => self::FORMAT,
			'plugin_version' => RMD_OH_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site'           => home_url( '/' ),
			'settings'       => $settings,
			'sets'           => $sets,
		];
	}

	/**
	 * Parses and validates an uploaded JSON document.
	 *
	 * @return array{settings: array, sets: list<array{slug: string, title: string, data: array, errors: array}>}|WP_Error
	 */
	public static function parse( string $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'rmd_oh_import_too_large', __( 'The file is larger than 1 MB.', 'rmd-opening-hours' ) );
		}

		$decoded = json_decode( $json, true, 16 );
		if ( ! is_array( $decoded ) || ( $decoded['format'] ?? '' ) !== self::FORMAT || ! isset( $decoded['sets'] ) || ! is_array( $decoded['sets'] ) ) {
			return new WP_Error( 'rmd_oh_import_invalid', __( 'This is not an RMD Opening Hours export file.', 'rmd-opening-hours' ) );
		}
		if ( count( $decoded['sets'] ) > 50 ) {
			return new WP_Error( 'rmd_oh_import_too_many', __( 'The file contains more than 50 sets.', 'rmd-opening-hours' ) );
		}

		$validator = self::validator();
		$sets      = [];
		foreach ( $decoded['sets'] as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $raw['title'] ?? '' ) );
			$slug  = sanitize_title( (string) ( $raw['slug'] ?? $title ) );
			if ( '' === $slug ) {
				$slug = 'set-' . ( (int) $index + 1 );
			}
			if ( '' === $title ) {
				$title = $slug;
			}
			$result = $validator->normalize_set( $raw, (string) Settings::get( 'default_state', 'SN' ) );
			$sets[] = [
				'slug'   => $slug,
				'title'  => $title,
				'data'   => $result['data'],
				'errors' => $result['errors'],
			];
		}

		$settings = [];
		if ( isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) {
			$merged   = array_merge( Settings::get(), $decoded['settings'] );
			$settings = $validator->normalize_settings( $merged )['data'];
			// Never let an import change site-specific or destructive settings.
			$settings['default_set']         = (int) Settings::get( 'default_set', 0 );
			$settings['delete_on_uninstall'] = (bool) Settings::get( 'delete_on_uninstall', false );
			$settings['roles']               = (array) Settings::get( 'roles', [] );
		}

		return [
			'settings' => $settings,
			'sets'     => $sets,
		];
	}

	/**
	 * Writes parsed data. Existing sets are matched by slug and overwritten.
	 *
	 * @param array{settings: array, sets: array} $parsed
	 * @return array{created: int, updated: int, settings: bool}
	 */
	public static function apply( array $parsed, bool $include_settings ): array {
		$created = 0;
		$updated = 0;

		foreach ( $parsed['sets'] as $set ) {
			$post_id = self::find_post_id( $set['slug'] );
			if ( $post_id ) {
				wp_update_post(
					[
						'ID'         => $post_id,
						'post_title' => $set['title'],
					]
				);
				++$updated;
			} else {
				$post_id = wp_insert_post(
					[
						'post_type'   => PostType::NAME,
						'post_status' => 'publish',
						'post_title'  => $set['title'],
						'post_name'   => $set['slug'],
					],
					true
				);
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				++$created;
			}
			SetRepository::write_meta( (int) $post_id, $set['data'] );
		}

		$settings_written = false;
		if ( $include_settings && [] !== $parsed['settings'] ) {
			update_option( Settings::OPTION, $parsed['settings'] );
			Settings::flush();
			$settings_written = true;
		}

		SetRepository::flush();
		Cache::purge();

		return [
			'created'  => $created,
			'updated'  => $updated,
			'settings' => $settings_written,
		];
	}

	/**
	 * Validator wired to WordPress sanitizers.
	 */
	public static function validator(): Validator {
		return new Validator(
			static fn( string $value ): string => sanitize_text_field( $value ),
			static fn( string $value ): string => wp_kses( $value, Shortcodes::template_html() ),
			static fn(): string => wp_generate_uuid4()
		);
	}

	private static function find_post_id( string $slug ): int {
		$posts = get_posts(
			[
				'post_type'      => PostType::NAME,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		return $posts ? (int) $posts[0] : 0;
	}
}
