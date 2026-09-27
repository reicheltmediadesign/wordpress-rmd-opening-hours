<?php
/**
 * Plugin bootstrap: wires all services to WordPress hooks.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', [ self::class, 'load_textdomain' ], 1 );

		Capabilities::init();
		PostType::init();
		Meta::init();
		Settings::init();
		Assets::init();
		Blocks::init();
		Shortcodes::init();
		Rest\SetsController::init();
		Rest\StatusController::init();
		StructuredData::init();
		Cache::init();
		Updater::init();

		if ( is_admin() ) {
			Admin\SetMetaBox::init();
			Admin\SaveHandler::init();
			Admin\ListTable::init();
			Admin\SettingsPage::init();
			Admin\ExportImportPage::init();
			Admin\HelpPage::init();
			Admin\Notices::init();
		}
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'rmd-opening-hours', false, dirname( RMD_OH_BASENAME ) . '/languages' );
	}

	public static function activate(): void {
		Capabilities::grant();
		Cache::schedule_daily();
	}

	public static function deactivate(): void {
		Cache::unschedule_daily();
	}
}
