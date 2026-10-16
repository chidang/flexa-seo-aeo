<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Rest;

use Flexa\SeoAeo\Services\Aeo\Readiness;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/flexa-seo-aeo/v1/readiness/<id> — the Answer-Engine Readiness
 * score + checklist for a single post. The block-editor sidebar polls this after
 * each save. Read-only, and gated per-post on `edit_post` (not the blanket
 * `edit_posts`) so a contributor can only score content they may edit.
 */
final class ReadinessController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/readiness/(?P<id>[\d]+)',
			[
				'args' => [
					'id' => [
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ),
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_report' ],
					'permission_callback' => [ $this, 'post_permission' ],
				],
			]
		);
	}

	/**
	 * Capability first, with no lookup before it, for the reason given in
	 * {@see \Flexa\SeoAeo\Rest\PostMetaController::post_permission()}: a 404
	 * for a missing id and a 403 for a real one tells a caller which post ids
	 * exist. `edit_post` covers both, because WordPress maps it to
	 * do_not_allow when there is no such post.
	 */
	public function post_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return new WP_Error(
				'flexa_seo_aeo_forbidden',
				__( 'You cannot edit this post.', 'flexa-seo-aeo' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_report( WP_REST_Request $request ): WP_REST_Response {
		$post = get_post( (int) $request['id'] );

		if ( ! $post instanceof WP_Post ) {
			return new WP_REST_Response(
				[
					'score'  => 0,
					'grade'  => 'poor',
					'checks' => [],
				],
				404
			);
		}

		return new WP_REST_Response( Readiness::instance()->report( $post ) );
	}
}
