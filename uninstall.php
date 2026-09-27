<?php
/**
 * Removes all plugin data when the plugin is deleted, but only if the site
 * owner opted in under Opening Hours → Settings.
 *
 * @package RMD\OpeningHours
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes options, posts and capabilities of the current site.
 */
function rmd_oh_uninstall_site(): void {
	$settings = get_option( 'rmd_oh_settings', [] );
	if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	$post_ids = get_posts(
		[
			'post_type'      => 'rmd_oh_set',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	delete_option( 'rmd_oh_settings' );
	delete_option( 'rmd_oh_caps_version' );
	delete_option( 'external_updates-rmd-opening-hours' );
	wp_clear_scheduled_hook( 'rmd_oh_daily_purge' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_rmd\_oh\_%' OR option_name LIKE '\_transient\_timeout\_rmd\_oh\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$roles = wp_roles();
	foreach ( array_keys( $roles->roles ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role && $role->has_cap( 'manage_opening_hours' ) ) {
			$role->remove_cap( 'manage_opening_hours' );
		}
	}
}

if ( is_multisite() ) {
	$rmd_oh_site_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $rmd_oh_site_ids as $rmd_oh_site_id ) {
		switch_to_blog( (int) $rmd_oh_site_id );
		rmd_oh_uninstall_site();
		restore_current_blog();
	}
} else {
	rmd_oh_uninstall_site();
}
