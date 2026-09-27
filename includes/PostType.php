<?php
/**
 * Registers the "set" post type. A set is one named collection of opening hours
 * (e.g. "Shop", "Phone hours"); everything else is stored as post meta.
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours;

defined( 'ABSPATH' ) || exit;

final class PostType {

	public const NAME = 'rmd_oh_set';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		$cap = Capabilities::CAP;

		register_post_type(
			self::NAME,
			[
				'labels'              => [
					'name'                   => __( 'Opening Hours', 'rmd-opening-hours' ),
					'singular_name'          => __( 'Opening hours set', 'rmd-opening-hours' ),
					'menu_name'              => __( 'Opening Hours', 'rmd-opening-hours' ),
					'all_items'              => __( 'All sets', 'rmd-opening-hours' ),
					'add_new'                => __( 'Add set', 'rmd-opening-hours' ),
					'add_new_item'           => __( 'Add new set', 'rmd-opening-hours' ),
					'edit_item'              => __( 'Edit set', 'rmd-opening-hours' ),
					'new_item'               => __( 'New set', 'rmd-opening-hours' ),
					'view_item'              => __( 'View set', 'rmd-opening-hours' ),
					'search_items'           => __( 'Search sets', 'rmd-opening-hours' ),
					'not_found'              => __( 'No sets found. Create one to start managing opening hours.', 'rmd-opening-hours' ),
					'not_found_in_trash'     => __( 'No sets in the trash.', 'rmd-opening-hours' ),
					'item_published'         => __( 'Set saved.', 'rmd-opening-hours' ),
					'item_updated'           => __( 'Set updated.', 'rmd-opening-hours' ),
					'item_trashed'           => __( 'Set moved to the trash.', 'rmd-opening-hours' ),
					'item_scheduled'         => __( 'Set saved.', 'rmd-opening-hours' ),
					'item_reverted_to_draft' => __( 'Set reverted to draft.', 'rmd-opening-hours' ),
				],
				'description'         => __( 'Named sets of opening hours managed by RMD Opening Hours.', 'rmd-opening-hours' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'hierarchical'        => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-clock',
				'supports'            => [ 'title' ],
				'map_meta_cap'        => false,
				'capabilities'        => [
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'read'                   => 'read',
					'delete_posts'           => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'create_posts'           => $cap,
				],
			]
		);
	}
}
