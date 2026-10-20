<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Onboarding;

use Flexa\SeoAeo\Services\Migration\Migrator;
use Flexa\SeoAeo\Support\SeoConflicts;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only site detection for the Setup Assistant. Everything here is derived
 * from WordPress core APIs and services the plugin already ships, so a re-run
 * always sees current reality and nothing needs persisting.
 *
 * The whole payload is cheap: one bounded COUNT query per migration source
 * via {@see Migrator} (single-JOIN `meta_key IN (…)`, never a multi-key OR
 * meta_query), core's cached `wp_count_posts()`, and a one-row latest-post
 * lookup.
 */
final class Detector {
	use SingletonTrait;

	/**
	 * The full detection payload for `GET /onboarding`.
	 *
	 * @return array<string, mixed>
	 */
	public function detect(): array {
		$posts = $this->published_count( 'post' );
		$pages = $this->published_count( 'page' );

		return [
			'site_name'                 => (string) get_bloginfo( 'name' ),
			'tagline'                   => (string) get_bloginfo( 'description' ),
			'site_url'                  => home_url( '/' ),
			'language'                  => determine_locale(),
			'logo_url'                  => $this->logo_url(),
			'site_type'                 => $this->site_type( $posts ),
			'has_woocommerce'           => class_exists( 'WooCommerce' ),
			'pretty_permalinks'         => '' !== (string) get_option( 'permalink_structure' ),
			'posts_published'           => $posts,
			'pages_published'           => $pages,
			'latest_post_id'            => $this->latest_post_id(),
			'knowledge_type_suggestion' => 'organization',
			'seo_plugins'               => $this->seo_plugins(),
			'active_seo_plugins'        => array_values( SeoConflicts::active() ),
			'settings_configured'       => $this->settings_configured(),
		];
	}

	/**
	 * Published entries of a post type, from core's cached counts.
	 */
	private function published_count( string $type ): int {
		$counts = wp_count_posts( $type );

		return (int) ( $counts->publish ?? 0 );
	}

	/**
	 * A rough site classification used only to tune copy and the recommended set.
	 * A store is a shop; otherwise a site with real post volume is a blog; a bare
	 * install is just a "site".
	 */
	private function site_type( int $posts ): string {
		if ( class_exists( 'WooCommerce' ) ) {
			return 'shop';
		}

		return $posts > 5 ? 'blog' : 'site';
	}

	/**
	 * The theme's custom logo, falling back to the site icon. Shown on the welcome
	 * screen only; not written to settings in the MVP.
	 */
	private function logo_url(): ?string {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$icon = get_site_icon_url();

		return '' !== $icon ? $icon : null;
	}

	/**
	 * The most recent published post id, for the AEO live preview. Null on a site
	 * with no posts. `fields => ids` keeps this to a single lightweight query.
	 */
	private function latest_post_id(): ?int {
		$ids = get_posts(
			[
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);

		if ( is_array( $ids ) && isset( $ids[0] ) ) {
			return (int) $ids[0];
		}

		return null;
	}

	/**
	 * Detected legacy SEO plugins: migratable data counts from {@see Migrator}
	 * merged with an is-it-still-active check so the UI can both offer migration
	 * and warn about a duplicate-output conflict.
	 *
	 * @return list<array{id: string, label: string, active: bool, migratable: bool, count: int}>
	 */
	private function seo_plugins(): array {
		$out = [];
		foreach ( Migrator::instance()->sources() as $source ) {
			$id    = (string) $source['id'];
			$count = (int) $source['count'];
			$out[] = [
				'id'         => $id,
				'label'      => (string) $source['label'],
				'active'     => SeoConflicts::is_plugin_active( $id ),
				'migratable' => $count > 0,
				'count'      => $count,
			];
		}

		return $out;
	}

	/**
	 * Whether the user has touched any setting, by deep-comparing the stored
	 * settings against the schema defaults. Both arrays are built from the same
	 * schema in the same key order ({@see Settings::all()} starts from
	 * {@see Settings::defaults()} and overrides in place), so a strict compare is
	 * exact.
	 */
	private function settings_configured(): bool {
		return Settings::all() !== Settings::defaults();
	}
}
