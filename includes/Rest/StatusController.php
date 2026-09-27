<?php
/**
 * Public read-only endpoints used by the front-end script when a cached page
 * is older than its embedded horizon:
 *
 *   GET /rmd-opening-hours/v1/status/{set}
 *   GET /rmd-opening-hours/v1/notices/{set|all}
 *
 * @package RMD\OpeningHours
 */

namespace RMD\OpeningHours\Rest;

use RMD\OpeningHours\Clock;
use RMD\OpeningHours\Domain\Status;
use RMD\OpeningHours\Render\NoticeRenderer;
use RMD\OpeningHours\Render\StatusRenderer;
use RMD\OpeningHours\Repository\SetRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class StatusController {

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$set_arg = [
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
			'validate_callback' => static fn( $value ): bool => is_string( $value ) && (bool) preg_match( '/^[a-z0-9-]{1,200}$/', $value ),
		];

		register_rest_route(
			SetsController::NAMESPACE,
			'/status/(?P<set>[a-z0-9-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'status' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'set' => $set_arg ],
			]
		);

		register_rest_route(
			SetsController::NAMESPACE,
			'/notices/(?P<set>[a-z0-9-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'notices' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'set' => $set_arg ],
			]
		);
	}

	public static function status( WP_REST_Request $request ) {
		$set = SetRepository::get_by_slug( (string) $request['set'] );
		if ( ! $set ) {
			return new WP_Error( 'rmd_oh_unknown_set', __( 'Unknown opening hours set.', 'rmd-opening-hours' ), [ 'status' => 404 ] );
		}

		$payload = StatusRenderer::payload( $set );
		$state   = Status::at( $payload['intervals'], Clock::timestamp() );

		$response = new WP_REST_Response(
			[
				'set'       => $set->slug,
				'now'       => $payload['now'],
				'tz'        => $payload['tz'],
				'horizon'   => $payload['horizon_end'],
				'intervals' => $payload['intervals_compact'],
				'labels'    => $payload['labels'],
				'open'      => $state['open'],
				'text'      => StatusRenderer::text( $state, $payload['labels'], $payload['tz'], true ),
			]
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public static function notices( WP_REST_Request $request ) {
		$sets = NoticeRenderer::sets_for( (string) $request['set'] );
		if ( [] === $sets ) {
			return new WP_Error( 'rmd_oh_unknown_set', __( 'Unknown opening hours set.', 'rmd-opening-hours' ), [ 'status' => 404 ] );
		}

		$response = new WP_REST_Response(
			[
				'now'  => Clock::timestamp(),
				'html' => NoticeRenderer::render( $sets ),
			]
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
