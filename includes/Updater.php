<?php
/**
 * Update checks against GitHub releases. Only the release asset named
 * rmd-opening-hours.zip is used, never GitHub's automatic source archive
 * (its top-level folder would not match the plugin slug).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class Updater {

	public const REPOSITORY = 'https://github.com/reicheltmediadesign/wordpress-rmd-opening-hours/';

	public static function init(): void {
		$factory = '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
		if ( ! class_exists( $factory ) ) {
			return;
		}

		$checker = $factory::buildUpdateChecker( self::REPOSITORY, RMD_OH_FILE, 'rmd-opening-hours' );

		$api = method_exists( $checker, 'getVcsApi' ) ? $checker->getVcsApi() : null;
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/^rmd-opening-hours\.zip$/i' );
		}
	}
}
