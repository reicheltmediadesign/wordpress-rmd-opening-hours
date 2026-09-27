<?php
/**
 * Opening Hours → Settings (Settings API).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Admin;

use RMD\OpeningHours\Capabilities;
use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Labels;
use RMD\OpeningHours\PostType;
use RMD\OpeningHours\Repository\SetRepository;
use RMD\OpeningHours\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'rmd-oh-settings';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'fields' ] );
		add_filter( 'option_page_capability_' . Settings::GROUP, static fn(): string => Capabilities::CAP );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::NAME,
			__( 'Opening Hours Settings', 'rmd-opening-hours' ),
			__( 'Settings', 'rmd-opening-hours' ),
			Capabilities::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function fields(): void {
		$page = self::SLUG;

		add_settings_section( 'general', __( 'General', 'rmd-opening-hours' ), '__return_empty_string', $page );
		self::field( 'default_set', __( 'Default set', 'rmd-opening-hours' ), 'general', 'select_set' );
		self::field( 'default_state', __( 'Federal state for new sets', 'rmd-opening-hours' ), 'general', 'select', [ 'options' => Labels::states() ] );
		self::field( 'time_style', __( 'Time format for the status', 'rmd-opening-hours' ), 'general', 'select', [ 'options' => Labels::time_styles() ] );

		add_settings_section( 'notices', __( 'Notices', 'rmd-opening-hours' ), [ self::class, 'notices_intro' ], $page );
		self::field(
			'lead_days',
			__( 'Lead time (days)', 'rmd-opening-hours' ),
			'notices',
			'number',
			[
				'min'         => 0,
				'max'         => 365,
				'description' => __( 'How many days before a period starts the notice appears. Each period can override this.', 'rmd-opening-hours' ),
			]
		);
		self::field( 'notice_template', __( 'Notice text', 'rmd-opening-hours' ), 'notices', 'textarea', [ 'description' => __( 'Placeholders: {name}, {start}, {end}, {hours}, {note}, {set}. Allowed HTML: strong, em, br, a, span.', 'rmd-opening-hours' ) ] );
		self::field( 'notice_dismissible', __( 'Visitors can dismiss notices', 'rmd-opening-hours' ), 'notices', 'checkbox' );
		self::field(
			'notice_horizon_days',
			__( 'Pre-render horizon (days)', 'rmd-opening-hours' ),
			'notices',
			'number',
			[
				'min'         => 1,
				'max'         => 90,
				'description' => __( 'Notices starting within this many days are embedded (hidden) so cached pages can reveal them on time.', 'rmd-opening-hours' ),
			]
		);
		self::field(
			'status_horizon_days',
			__( 'Status horizon (days)', 'rmd-opening-hours' ),
			'notices',
			'number',
			[
				'min'         => 2,
				'max'         => 60,
				'description' => __( 'How many days of opening intervals the “open now” status embeds for cached pages.', 'rmd-opening-hours' ),
			]
		);

		add_settings_section( 'seo', __( 'Structured data', 'rmd-opening-hours' ), [ self::class, 'seo_intro' ], $page );
		self::field( 'jsonld_everywhere', __( 'Output JSON-LD on every page', 'rmd-opening-hours' ), 'seo', 'checkbox', [ 'description' => __( 'Otherwise it is only added to pages that show the set. Applies to sets with structured data enabled.', 'rmd-opening-hours' ) ] );

		add_settings_section( 'access', __( 'Access', 'rmd-opening-hours' ), [ self::class, 'access_intro' ], $page );
		self::field( 'roles', __( 'Roles that may manage opening hours', 'rmd-opening-hours' ), 'access', 'roles' );

		add_settings_section( 'danger', __( 'Uninstall', 'rmd-opening-hours' ), '__return_empty_string', $page );
		self::field( 'delete_on_uninstall', __( 'Delete all sets and settings when the plugin is deleted', 'rmd-opening-hours' ), 'danger', 'checkbox' );
	}

	public static function access_intro(): void {
		echo '<p>' . esc_html__( 'Administrators always have access. Tick the roles that should also see the Opening Hours menu and edit sets, settings and imports.', 'rmd-opening-hours' ) . '</p>';
		if ( ! current_user_can( 'promote_users' ) ) {
			echo '<p class="description">' . esc_html__( 'Only administrators can change this.', 'rmd-opening-hours' ) . '</p>';
		}
	}

	public static function notices_intro(): void {
		echo '<p>' . esc_html__( 'The notice block announces planned periods automatically and disappears when they are over.', 'rmd-opening-hours' ) . '</p>';
	}

	public static function seo_intro(): void {
		echo '<p>' . esc_html__( 'Structured data is enabled per set in the set editor. Leave it off if your SEO plugin already describes the business, to avoid duplicate entities.', 'rmd-opening-hours' ) . '</p>';
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'rmd-opening-hours' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Opening Hours Settings', 'rmd-opening-hours' ) . '</h1>';
		settings_errors( Settings::OPTION );
		echo '<form method="post" action="options.php">';
		settings_fields( Settings::GROUP );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form></div>';
	}

	private static function field( string $key, string $label, string $section, string $type, array $args = [] ): void {
		add_settings_field(
			$key,
			$label,
			[ self::class, 'render_field' ],
			self::SLUG,
			$section,
			[
				'key'       => $key,
				'type'      => $type,
				'label_for' => 'checkbox' === $type ? '' : 'rmd-oh-' . $key,
			] + $args
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function render_field( array $args ): void {
		$key   = (string) $args['key'];
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'rmd-oh-' . $key;
		$value = Settings::get( $key );

		switch ( $args['type'] ) {
			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%d" min="%d" max="%d" class="small-text">',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) $value,
					(int) ( $args['min'] ?? 0 ),
					(int) ( $args['max'] ?? 999 )
				);
				break;

			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="3" class="large-text code">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;

			case 'roles':
				$can_edit = current_user_can( 'promote_users' );
				$selected = (array) $value;
				echo '<fieldset>';
				printf(
					'<label><input type="checkbox" checked disabled> %s <span class="description">(%s)</span></label><br>',
					esc_html( translate_user_role( 'Administrator' ) ),
					esc_html__( 'always', 'rmd-opening-hours' )
				);
				if ( $can_edit ) {
					printf( '<input type="hidden" name="%s" value="1">', esc_attr( Settings::OPTION . '[roles_submitted]' ) );
				}
				foreach ( Capabilities::selectable_roles() as $slug => $label ) {
					printf(
						'<label><input type="checkbox" name="%s" value="%s" %s %s> %s</label><br>',
						esc_attr( $name . '[]' ),
						esc_attr( $slug ),
						checked( in_array( $slug, $selected, true ), true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute string.
						$can_edit ? '' : 'disabled',
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'checkbox':
				printf( '<input type="hidden" name="%s" value="0">', esc_attr( $name ) );
				printf(
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute string.
					esc_html( (string) ( $args['checkbox_label'] ?? __( 'Enabled', 'rmd-opening-hours' ) ) )
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( (array) $args['options'] as $option_value => $option_label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $option_value ), selected( (string) $value, (string) $option_value, false ), esc_html( (string) $option_label ) );
				}
				echo '</select>';
				break;

			case 'select_set':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				printf( '<option value="0">%s</option>', esc_html__( 'First set (alphabetically)', 'rmd-opening-hours' ) );
				foreach ( SetRepository::all() as $set ) {
					/** @var SetData $set */
					printf( '<option value="%d" %s>%s</option>', (int) $set->id, selected( (int) $value, $set->id, false ), esc_html( $set->title ) );
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'Used when a block or shortcode does not name a set.', 'rmd-opening-hours' ) . '</p>';
				break;
		}

		if ( ! empty( $args['description'] ) && 'select_set' !== $args['type'] ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}
}
