<?php
/**
 * Opening Hours → Import/Export. Export downloads a JSON file; import is a
 * two-step flow (upload → preview → apply) so nothing is written unseen.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Capabilities;
use RMD\OpeningHours\ExportImport;
use RMD\OpeningHours\PostType;

defined( 'ABSPATH' ) || exit;

final class ExportImportPage {

	public const SLUG = 'rmd-oh-import-export';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_post_rmd_oh_export', [ self::class, 'handle_export' ] );
		add_action( 'admin_post_rmd_oh_import_preview', [ self::class, 'handle_preview' ] );
		add_action( 'admin_post_rmd_oh_import_apply', [ self::class, 'handle_apply' ] );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::NAME,
			__( 'Import/Export Opening Hours', 'rmd-opening-hours' ),
			__( 'Import/Export', 'rmd-opening-hours' ),
			Capabilities::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	private static function page_url( array $args = [] ): string {
		return add_query_arg(
			$args + [
				'post_type' => PostType::NAME,
				'page'      => self::SLUG,
			],
			admin_url( 'edit.php' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to import or export opening hours.', 'rmd-opening-hours' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Import/Export Opening Hours', 'rmd-opening-hours' ) . '</h1>';
		self::messages();

		$token = isset( $_GET['preview'] ) ? sanitize_key( wp_unslash( $_GET['preview'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token lookup; the apply step is nonce-protected.
		if ( '' !== $token ) {
			self::render_preview( $token );
		}

		echo '<h2>' . esc_html__( 'Export', 'rmd-opening-hours' ) . '</h2>';
		echo '<p>' . esc_html__( 'Downloads all sets (regular hours, periods, holiday rules, display settings) and the plugin settings as a JSON file.', 'rmd-opening-hours' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rmd_oh_export">';
		wp_nonce_field( 'rmd_oh_export' );
		submit_button( __( 'Download export', 'rmd-opening-hours' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import', 'rmd-opening-hours' ) . '</h2>';
		echo '<p>' . esc_html__( 'Sets are matched by their slug: existing sets are overwritten, new ones are created. You will see a summary before anything is written.', 'rmd-opening-hours' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rmd_oh_import_preview">';
		wp_nonce_field( 'rmd_oh_import_preview' );
		echo '<p><input type="file" name="rmd_oh_file" accept="application/json,.json" required></p>';
		submit_button( __( 'Upload and preview', 'rmd-opening-hours' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	private static function messages(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only flags after redirects.
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['imported'] ) ) {
			$created = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
			$updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
			echo '<div class="notice notice-success"><p>' . esc_html(
				sprintf(
					/* translators: 1: number of created sets, 2: number of updated sets */
					__( 'Import finished: %1$d sets created, %2$d sets updated.', 'rmd-opening-hours' ),
					$created,
					$updated
				)
			) . '</p></div>';
		}
		// phpcs:enable
	}

	private static function transient_key( string $token ): string {
		return 'rmd_oh_import_' . get_current_user_id() . '_' . $token;
	}

	private static function render_preview( string $token ): void {
		$parsed = get_transient( self::transient_key( $token ) );
		if ( ! is_array( $parsed ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The uploaded preview has expired. Please upload the file again.', 'rmd-opening-hours' ) . '</p></div>';
			return;
		}

		echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Import preview', 'rmd-opening-hours' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Set', 'rmd-opening-hours' ) . '</th><th>' . esc_html__( 'Slug', 'rmd-opening-hours' ) . '</th><th>' . esc_html__( 'Periods', 'rmd-opening-hours' ) . '</th><th>' . esc_html__( 'Remarks', 'rmd-opening-hours' ) . '</th></tr></thead><tbody>';
		foreach ( $parsed['sets'] as $set ) {
			$remarks = array_map( [ Notices::class, 'message' ], array_slice( $set['errors'], 0, 5 ) );
			printf(
				'<tr><td>%s</td><td><code>%s</code></td><td>%d</td><td>%s</td></tr>',
				esc_html( $set['title'] ),
				esc_html( $set['slug'] ),
				count( $set['data']['periods'] ),
				esc_html( implode( ' ', $remarks ) )
			);
		}
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1em">';
		echo '<input type="hidden" name="action" value="rmd_oh_import_apply">';
		echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
		wp_nonce_field( 'rmd_oh_import_apply_' . $token );
		if ( [] !== $parsed['settings'] ) {
			echo '<p><label><input type="checkbox" name="include_settings" value="1"> ' . esc_html__( 'Also import the plugin settings (lead time, notice text, …)', 'rmd-opening-hours' ) . '</label></p>';
		}
		submit_button( __( 'Import now', 'rmd-opening-hours' ), 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Cancel', 'rmd-opening-hours' ) . '</a>';
		echo '</form></div>';
	}

	public static function handle_export(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export opening hours.', 'rmd-opening-hours' ), 403 );
		}
		check_admin_referer( 'rmd_oh_export' );

		$filename = sanitize_file_name( 'opening-hours-' . wp_parse_url( home_url(), PHP_URL_HOST ) . '-' . gmdate( 'Y-m-d' ) . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( ExportImport::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function handle_preview(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to import opening hours.', 'rmd-opening-hours' ), 403 );
		}
		check_admin_referer( 'rmd_oh_import_preview' );

		$file = $_FILES['rmd_oh_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated below.
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::back( [ 'error' => __( 'No file was uploaded.', 'rmd-opening-hours' ) ] );
		}
		if ( (int) $file['size'] > ExportImport::MAX_BYTES ) {
			self::back( [ 'error' => __( 'The file is larger than 1 MB.', 'rmd-opening-hours' ) ] );
		}

		$json = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Temporary upload, not a remote file.
		if ( false === $json ) {
			self::back( [ 'error' => __( 'The file could not be read.', 'rmd-opening-hours' ) ] );
		}

		$parsed = ExportImport::parse( (string) $json );
		if ( is_wp_error( $parsed ) ) {
			self::back( [ 'error' => $parsed->get_error_message() ] );
		}

		$token = wp_generate_password( 20, false, false );
		set_transient( self::transient_key( $token ), $parsed, 15 * MINUTE_IN_SECONDS );
		self::back( [ 'preview' => $token ] );
	}

	public static function handle_apply(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to import opening hours.', 'rmd-opening-hours' ), 403 );
		}
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified on the next line with the token.
		check_admin_referer( 'rmd_oh_import_apply_' . $token );

		$parsed = get_transient( self::transient_key( $token ) );
		if ( ! is_array( $parsed ) ) {
			self::back( [ 'error' => __( 'The uploaded preview has expired. Please upload the file again.', 'rmd-opening-hours' ) ] );
		}
		delete_transient( self::transient_key( $token ) );

		$result = ExportImport::apply( $parsed, ! empty( $_POST['include_settings'] ) );
		self::back(
			[
				'imported' => 1,
				'created'  => $result['created'],
				'updated'  => $result['updated'],
			]
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function back( array $args ): void {
		wp_safe_redirect( self::page_url( $args ) );
		exit;
	}
}
