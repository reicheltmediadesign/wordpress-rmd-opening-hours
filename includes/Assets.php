<?php
/**
 * Registers front-end assets. They are plain files in assets/ (no build step),
 * shared by blocks and shortcodes, and only enqueued when something renders.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public const FRONT_SCRIPT = 'rmd-oh-front';
	public const FRONT_STYLE  = 'rmd-oh-front-style';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		wp_register_style(
			self::FRONT_STYLE,
			RMD_OH_URL . 'assets/css/front.css',
			[],
			self::version( 'assets/css/front.css' )
		);

		wp_register_script(
			self::FRONT_SCRIPT,
			RMD_OH_URL . 'assets/js/front.js',
			[],
			self::version( 'assets/js/front.js' ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
	}

	public static function enqueue_front(): void {
		wp_enqueue_style( self::FRONT_STYLE );
		wp_enqueue_script( self::FRONT_SCRIPT );
	}

	/**
	 * Cache-busting version: file modification time while SCRIPT_DEBUG is on,
	 * plugin version otherwise (stable URLs for page caches and CDNs).
	 */
	public static function version( string $relative_path ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$file = RMD_OH_DIR . $relative_path;
			if ( is_readable( $file ) ) {
				$mtime = filemtime( $file );
				if ( false !== $mtime ) {
					return (string) $mtime;
				}
			}
		}
		return RMD_OH_VERSION;
	}

	/**
	 * Reads a wp-scripts generated *.asset.php file.
	 *
	 * @return array{dependencies: string[], version: string}
	 */
	public static function build_asset( string $relative_js_path ): array {
		$asset_file = RMD_OH_DIR . preg_replace( '/\.js$/', '.asset.php', $relative_js_path );
		$asset      = is_readable( $asset_file ) ? include $asset_file : [];

		return [
			'dependencies' => is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : [],
			'version'      => (string) ( $asset['version'] ?? RMD_OH_VERSION ),
		];
	}
}
