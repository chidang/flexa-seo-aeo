<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Links;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * "Strip Category Base": removes the `/category/` (or custom base) segment from
 * category archive URLs, so `example.com/category/news/` becomes
 * `example.com/news/`. The old base still resolves via a 301 to the clean URL.
 *
 * Adapted from the long-standing "WP No Category Base" approach also used by
 * Yoast and Rank Math. Toggling the setting reschedules a rewrite-rule flush
 * (see Plugin::boot), so the rules stay in sync without re-saving permalinks.
 */
final class CategoryBase {
	use SingletonTrait;

	private const REDIRECT_VAR = 'flexa_seo_aeo_category_redirect';

	public function register(): void {
		if ( ! (bool) Settings::get( 'strip_category_base' ) ) {
			return;
		}

		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_filter( 'request', [ $this, 'redirect_old_base' ] );
		add_filter( 'category_rewrite_rules', [ $this, 'category_rewrite_rules' ] );
		add_filter( 'term_link', [ $this, 'strip_base_from_link' ], 10, 3 );

		// Category changes alter the generated rules, so flush on the next request.
		add_action( 'created_category', [ $this, 'schedule_flush' ] );
		add_action( 'edited_category', [ $this, 'schedule_flush' ] );
		add_action( 'delete_category', [ $this, 'schedule_flush' ] );
	}

	/**
	 * @param array<int, string> $query_vars
	 * @return array<int, string>
	 */
	public function add_query_var( array $query_vars ): array {
		$query_vars[] = self::REDIRECT_VAR;

		return $query_vars;
	}

	/**
	 * 301 the original /category/... URL to its stripped equivalent.
	 *
	 * @param array<string, mixed> $query_vars
	 * @return array<string, mixed>
	 */
	public function redirect_old_base( array $query_vars ): array {
		if ( ! isset( $query_vars[ self::REDIRECT_VAR ] ) ) {
			return $query_vars;
		}

		$slug = is_string( $query_vars[ self::REDIRECT_VAR ] ) ? $query_vars[ self::REDIRECT_VAR ] : '';
		$url  = trailingslashit( (string) get_option( 'home' ) ) . user_trailingslashit( $slug, 'category' );
		wp_safe_redirect( $url, 301 );
		exit;
	}

	/**
	 * Remove the category base from a category term link.
	 *
	 * @param string  $link     The term URL.
	 * @param WP_Term $term     The term object.
	 * @param string  $taxonomy The taxonomy slug.
	 */
	public function strip_base_from_link( string $link, WP_Term $term, string $taxonomy ): string {
		if ( 'category' !== $taxonomy ) {
			return $link;
		}

		$base = (string) get_option( 'category_base' );
		if ( '' === $base ) {
			global $wp_rewrite;
			$base = trim( str_replace( '%category%', '', (string) $wp_rewrite->get_category_permastruct() ), '/' );
		}

		$base = ltrim( $base, '/' ) . '/';

		return (string) preg_replace( '`' . preg_quote( $base, '`' ) . '`u', '', $link, 1 );
	}

	/**
	 * Build rewrite rules that match category archives without the base segment,
	 * plus a catch-all that 301s the old (based) URLs.
	 *
	 * @param array<string, string> $rules
	 * @return array<string, string>
	 */
	public function category_rewrite_rules( array $rules ): array {
		global $wp_rewrite;

		$new_rules  = [];
		$categories = get_categories( [ 'hide_empty' => false ] );
		$pagination = $wp_rewrite->pagination_base;

		foreach ( $categories as $category ) {
			if ( ! $category instanceof WP_Term ) {
				continue;
			}

			$nicename = $this->term_path( $category );

			$new_rules[ '(' . $nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
			$new_rules[ '(' . $nicename . ')/' . $pagination . '/?([0-9]{1,})/?$' ]   = 'index.php?category_name=$matches[1]&paged=$matches[2]';
			$new_rules[ '(' . $nicename . ')/?$' ]                                    = 'index.php?category_name=$matches[1]';
		}

		// Anything still hitting the old base gets captured for the 301 above.
		$old_base                     = trim( str_replace( '%category%', '(.+)', (string) $wp_rewrite->get_category_permastruct() ), '/' );
		$new_rules[ $old_base . '$' ] = 'index.php?' . self::REDIRECT_VAR . '=$matches[1]';

		return $new_rules + $rules;
	}

	/**
	 * Full slug path for a category, including any parent segments.
	 */
	private function term_path( WP_Term $category ): string {
		if ( $category->parent > 0 && $category->parent !== $category->term_id ) {
			$parents = get_category_parents( $category->parent, false, '/', true );
			if ( is_string( $parents ) ) {
				return $parents . $category->slug;
			}
		}

		return $category->slug;
	}

	public function schedule_flush(): void {
		// Deferred: rules are regenerated from the current category set on the next
		// front-end request, so an inline flush here would use stale term data.
		update_option( 'flexa_seo_aeo_flush_rewrite', '1' );
	}
}
