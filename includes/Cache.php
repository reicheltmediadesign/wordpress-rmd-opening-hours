<?php
/**
 * Best-effort page cache purging. Opening hours change at date boundaries, so
 * besides purging on save a daily event clears caches shortly after midnight.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class Cache {

	public const DAILY_HOOK = 'rmd_oh_daily_purge';

	public static function init(): void {
		add_action( self::DAILY_HOOK, [ self::class, 'purge' ] );
		add_action( 'save_post_' . PostType::NAME, [ self::class, 'purge' ], 100 );
		add_action( 'update_option_' . Settings::OPTION, [ self::class, 'purge' ], 100 );
		add_action( 'init', [ self::class, 'ensure_scheduled' ] );
	}

	public static function purge(): void {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}
		if ( class_exists( '\\autoptimizeCache' ) && method_exists( '\\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}
		// Third-party hooks, intentionally not prefixed.
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'cachify_flush_cache' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/**
		 * Fires after the plugin asked the known page caches to purge.
		 * Hook custom caches here.
		 */
		do_action( 'rmd_oh_cache_purged' );
	}

	public static function schedule_daily(): void {
		if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}
		$start = ( new \DateTimeImmutable( 'tomorrow 00:05', wp_timezone() ) )->getTimestamp();
		wp_schedule_event( $start, 'daily', self::DAILY_HOOK );
	}

	public static function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			self::schedule_daily();
		}
	}

	public static function unschedule_daily(): void {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}
}
