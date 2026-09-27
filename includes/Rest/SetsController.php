<?php
/**
 * GET /rmd-opening-hours/v1/sets – minimal list of published sets for the
 * block editor's set picker. Public (contains only id, slug and title).
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Rest;

use RMD\OpeningHours\Domain\SetData;
use RMD\OpeningHours\Repository\SetRepository;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class SetsController {

	public const NAMESPACE = 'rmd-opening-hours/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/sets',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'list' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function list(): WP_REST_Response {
		$items = array_map(
			static fn( SetData $set ): array => [
				'id'    => $set->id,
				'slug'  => $set->slug,
				'title' => $set->title,
			],
			SetRepository::all()
		);

		$response = new WP_REST_Response( $items );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
