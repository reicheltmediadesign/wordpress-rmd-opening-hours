<?php
/**
 * Persists the set editor's JSON payload on save_post after nonce, capability
 * and validation checks.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Capabilities;
use RMD\OpeningHours\ExportImport;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Repository\SetRepository;
use RMD\OpeningHours\Settings;

defined( 'ABSPATH' ) || exit;

final class SaveHandler {

	public const FIELD        = 'rmd_oh_data';
	public const NONCE_FIELD  = 'rmd_oh_nonce';
	public const NONCE_ACTION = 'rmd_oh_save_set_';
	public const MAX_BYTES    = 524288; // 512 KiB.

	public static function init(): void {
		add_action( 'save_post_' . PostType::NAME, [ self::class, 'save' ] );
	}

	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::CAP ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$json = wp_unslash( $_POST[ self::FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is validated below.
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BYTES ) {
			Notices::remember(
				$post_id,
				[
					[
						'path' => 'regular',
						'code' => 'invalid_payload',
						'args' => [],
					],
				]
			);
			return;
		}

		$raw = json_decode( $json, true, 16 );
		if ( ! is_array( $raw ) ) {
			Notices::remember(
				$post_id,
				[
					[
						'path' => 'regular',
						'code' => 'invalid_payload',
						'args' => [],
					],
				]
			);
			return;
		}

		$result = ExportImport::validator()->normalize_set( $raw, (string) Settings::get( 'default_state', 'SN' ) );

		SetRepository::write_meta( $post_id, $result['data'] );

		if ( [] !== $result['errors'] ) {
			Notices::remember( $post_id, $result['errors'] );
		}
	}
}
