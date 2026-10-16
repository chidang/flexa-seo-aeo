<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Rest;

use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Services\Variables;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /wp-json/flexa-seo-aeo/v1/post-meta/<id> — read or persist a single
 * post's SEO overrides. The block-editor sidebar (Phase 4) talks to this. Every
 * route is gated per-post on `edit_post`, not just the blanket `edit_posts`.
 */
final class PostMetaController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/post-meta/(?P<id>[\d]+)',
			[
				'args' => [
					'id' => [
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ),
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_meta' ],
					'permission_callback' => [ $this, 'post_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_meta' ],
					'permission_callback' => [ $this, 'post_permission' ],
				],
			]
		);
	}

	/**
	 * The capability is the only check, and it runs before anything looks the
	 * post up.
	 *
	 * Resolving the post first meant a caller without rights could tell a
	 * missing id (404) from a real one (403) and walk the id space that way.
	 * `edit_post` already answers both questions at once: WordPress maps it to
	 * `do_not_allow` when the post does not exist, so one failure mode and one
	 * response code cover "no such post" and "not yours". A caller that passes
	 * this check is guaranteed a real post, which is why the handlers below do
	 * not re-check.
	 */
	public function post_permission( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'flexa_seo_aeo_forbidden',
				__( 'You cannot edit this post.', 'flexa-seo-aeo' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_meta( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$meta    = PostMetaRepository::instance()->get( $post_id );

		return new WP_REST_Response(
			[
				'meta'     => $meta->to_array(),
				'defaults' => $this->defaults( $post_id ),
			]
		);
	}

	public function update_meta( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$payload = (array) $request->get_json_params();

		$meta = PostMetaRepository::instance()->save( $post_id, $payload );

		/**
		 * Fires after a post's SEO meta is saved via REST.
		 *
		 * @param int                         $post_id
		 * @param array<string, bool|string> $meta
		 */
		do_action( 'flexa_seo_aeo/post_meta/updated', $post_id, $meta->to_array() );

		return new WP_REST_Response(
			[
				'meta'     => $meta->to_array(),
				'defaults' => $this->defaults( $post_id ),
			]
		);
	}

	/**
	 * Computed fallbacks so the editor can show placeholders that match what the
	 * frontend would output when a field is left blank.
	 *
	 * @return array<string, string>
	 */
	private function defaults( int $post_id ): array {
		$title = (string) get_the_title( $post_id );

		return [
			'title'       => Variables::replace( '%%title%% %%sep%% %%sitename%%', [ 'title' => $title ] ),
			'description' => wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ),
			'canonical'   => (string) get_permalink( $post_id ),
		];
	}
}
