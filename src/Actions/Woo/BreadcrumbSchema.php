<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Woo;

use Flexa\SeoAeo\Services\Aeo\Schema as SchemaService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps one `BreadcrumbList` on a shop page.
 *
 * WooCommerce builds its own list from the trail its theme hook renders, and
 * on a product view that ends up in the markup next to ours. Two lists for one
 * page is a structured-data error: Google picks one and the other is noise at
 * best. Ours wins where it exists, because it is anchored on the canonical and
 * follows the same trail as the visible breadcrumbs.
 *
 * Both halves of the question are asked of the code that answers them: the
 * `aeo.schema` toggle, which decides whether any JSON-LD is printed, and the
 * schema service, which reports what its node list holds after the extension
 * filter has had its say. Schema off, breadcrumbs off, a view with no
 * canonical, or a `flexa_seo_aeo/aeo/schema_nodes` filter that drops the node:
 * in each of those WooCommerce keeps its list, and the page is no worse off
 * than it would be without this plugin.
 */
final class BreadcrumbSchema {
	use SingletonTrait;

	public function register(): void {
		if ( is_admin() ) {
			return;
		}

		add_filter( 'woocommerce_structured_data_breadcrumblist', [ $this, 'maybe_suppress' ] );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function maybe_suppress( array $data ): array {
		if ( ! (bool) Settings::get_aeo( 'schema' ) ) {
			return $data;
		}

		return SchemaService::instance()->emits_breadcrumb_list() ? [] : $data;
	}
}
