<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Rest;

use Flexa\SeoAeo\Support\CachePlugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/flexa-seo-aeo/v1/hints/cache-plugin/dismiss — remembers that
 * an administrator closed the Flexa Cache card on the dashboard.
 *
 * Dismissals are not settings: they never appear in the export file and are
 * not part of the settings schema, so they keep their own routes here. The
 * gate is still manage_options, because the card itself is only rendered for
 * users who have it.
 */
final class HintsController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/hints/cache-plugin/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss_cache_plugin' ],
				'permission_callback' => [ $this, 'settings_permission' ],
			]
		);
	}

	public function dismiss_cache_plugin( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		CachePlugin::dismiss();

		return new WP_REST_Response( [ 'dismissed' => true ] );
	}
}
