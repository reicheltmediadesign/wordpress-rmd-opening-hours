<?php
/**
 * Plugin Name:       RMD Opening Hours
 * Plugin URI:        https://github.com/reicheltmediadesign/wordpress-rmd-opening-hours
 * Description:       Manage regular and seasonal opening hours, show them as a block or shortcode, and announce upcoming changes automatically.
 * Version:           0.1.5
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Philipp Reichelt, reichelt media.design
 * Author URI:        https://reicheltmedia.design
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rmd-opening-hours
 * Domain Path:       /languages
 * Update URI:        https://github.com/reicheltmediadesign/wordpress-rmd-opening-hours
 *
 * @package RMD\OpeningHours
 */

// This file must stay parseable by PHP 7.x so the version notice below can be shown instead of a fatal error.

defined( 'ABSPATH' ) || exit;

define( 'RMD_OH_VERSION', '0.1.5' );
define( 'RMD_OH_FILE', __FILE__ );
define( 'RMD_OH_DIR', plugin_dir_path( __FILE__ ) );
define( 'RMD_OH_URL', plugin_dir_url( __FILE__ ) );
define( 'RMD_OH_BASENAME', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'RMD Opening Hours requires PHP %1$s or newer. This site runs PHP %2$s, so the plugin stays inactive.', 'rmd-opening-hours' ),
					'8.1',
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'RMD\\OpeningHours\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = RMD_OH_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

if ( is_readable( RMD_OH_DIR . 'vendor/autoload.php' ) ) {
	require RMD_OH_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, [ 'RMD\\OpeningHours\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'RMD\\OpeningHours\\Plugin', 'deactivate' ] );

RMD\OpeningHours\Plugin::boot();
