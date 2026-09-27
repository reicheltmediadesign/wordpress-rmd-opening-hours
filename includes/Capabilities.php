<?php
/**
 * Single custom capability that gates every write operation of the plugin.
 * Administrators always have it; further roles are chosen in the settings.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const CAP = 'manage_opening_hours';

	private const VERSION_OPTION = 'rmd_oh_caps_version';
	private const VERSION        = '2';

	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'maybe_heal' ] );
		add_action( 'update_option_' . Settings::OPTION, [ self::class, 'on_settings_saved' ], 10, 2 );
		// The first save creates the option, which fires add_option_* instead of update_option_*.
		add_action( 'add_option_' . Settings::OPTION, [ self::class, 'on_settings_added' ], 10, 2 );
	}

	/**
	 * @param string $option
	 * @param mixed  $value
	 */
	public static function on_settings_added( $option, $value ): void {
		self::sync( is_array( $value ) ? (array) ( $value['roles'] ?? [] ) : [] );
	}

	/**
	 * Applies the configured roles. Runs on activation, when the settings
	 * change and, as a self-heal, whenever the stored capability version is
	 * outdated (roles created after activation, new sites in a network,
	 * restored databases).
	 */
	public static function grant(): void {
		self::sync( (array) Settings::get( 'roles', [] ) );
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Gives the capability to administrators and the given roles and removes
	 * it from every other role.
	 *
	 * @param string[] $role_slugs
	 */
	public static function sync( array $role_slugs ): void {
		$allowed = array_merge( [ 'administrator' ], $role_slugs );
		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			$should_have = in_array( $role_name, $allowed, true );
			if ( $should_have && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			} elseif ( ! $should_have && $role->has_cap( self::CAP ) ) {
				$role->remove_cap( self::CAP );
			}
		}
	}

	public static function maybe_heal(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::grant();
		}
	}

	/**
	 * @param mixed $old_value
	 * @param mixed $new_value
	 */
	public static function on_settings_saved( $old_value, $new_value ): void {
		$old = is_array( $old_value ) ? (array) ( $old_value['roles'] ?? [] ) : [];
		$new = is_array( $new_value ) ? (array) ( $new_value['roles'] ?? [] ) : [];
		if ( $old !== $new ) {
			self::sync( $new );
		}
	}

	public static function revoke_all(): void {
		self::sync( [] );
		$admin = get_role( 'administrator' );
		if ( $admin && $admin->has_cap( self::CAP ) ) {
			$admin->remove_cap( self::CAP );
		}
		delete_option( self::VERSION_OPTION );
	}

	public static function current_user_can(): bool {
		return current_user_can( self::CAP );
	}

	/**
	 * Roles that can be granted access (everything except administrator).
	 *
	 * @return array<string, string> slug => display name.
	 */
	public static function selectable_roles(): array {
		$roles = [];
		foreach ( wp_roles()->roles as $slug => $details ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			$roles[ $slug ] = translate_user_role( (string) ( $details['name'] ?? $slug ) );
		}
		return $roles;
	}
}
