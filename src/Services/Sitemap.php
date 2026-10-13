<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;
use WP_Query;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Pure builder for the XML/HTML sitemaps. It enumerates the enabled post types
 * and taxonomies, paginates them, and returns plain data arrays. It never emits
 * markup — the rendering + escaping lives in the sitemap templates and the
 * {@see \Flexa\SeoAeo\Actions\Sitemap\Router}. Per-post `noindex` overrides are
 * honoured so a page hidden from search never leaks into the sitemap.
 */
final class Sitemap {
	use SingletonTrait;

	/**
	 * Max URLs per sub-sitemap. The sitemaps.org ceiling is 50k; 2000 keeps each
	 * document small and matches WordPress core's own default.
	 */
	public const MAX_PER_PAGE = 2000;

	private const NOINDEX_META = '_flexa_seo_aeo_noindex';

	/**
	 * Memoized {@see self::noindexed_ids()} result. One sitemap request builds
	 * an index plus several sub-sitemap pages, all of which want the same list.
	 *
	 * @var list<int>|null
	 */
	private ?array $noindexed_ids = null;

	/**
	 * Public post types the site owner opted into, intersected with what is
	 * actually registered + public right now.
	 *
	 * @return list<string>
	 */
	public function post_types(): array {
		$chosen = (array) Settings::get( 'sitemap_post_types' );
		$public = get_post_types( [ 'public' => true ], 'names' );

		$out = [];
		foreach ( $chosen as $type ) {
			$type = (string) $type;
			if ( in_array( $type, $public, true ) && 'attachment' !== $type ) {
				$out[] = $type;
			}
		}

		/**
		 * Filters the post types included in the sitemap.
		 *
		 * @param list<string> $out
		 */
		return array_values( (array) apply_filters( 'flexa_seo_aeo/sitemap/post_types', $out ) );
	}

	/**
	 * @return list<string>
	 */
	public function taxonomies(): array {
		$chosen = (array) Settings::get( 'sitemap_taxonomies' );
		$public = get_taxonomies( [ 'public' => true ], 'names' );

		$out = [];
		foreach ( $chosen as $tax ) {
			$tax = (string) $tax;
			if ( in_array( $tax, $public, true ) ) {
				$out[] = $tax;
			}
		}

		/**
		 * Filters the taxonomies included in the sitemap.
		 *
		 * @param list<string> $out
		 */
		return array_values( (array) apply_filters( 'flexa_seo_aeo/sitemap/taxonomies', $out ) );
	}

	/**
	 * The sitemap index: one entry per (object type, subtype, page) that has at
	 * least one URL.
	 *
	 * @return list<array{loc: string, lastmod: string}>
	 */
	public function index(): array {
		$entries = [];

		foreach ( $this->post_types() as $type ) {
			$pages = $this->page_count( $this->count_posts( $type ) );
			if ( 0 === $pages ) {
				continue;
			}
			$lastmod = $this->post_type_lastmod( $type );
			for ( $page = 1; $page <= $pages; $page++ ) {
				$entries[] = [
					'loc'     => $this->entry_url( 'posts', $type, $page ),
					'lastmod' => $lastmod,
				];
			}
		}

		foreach ( $this->taxonomies() as $tax ) {
			$pages = $this->page_count( $this->count_terms( $tax ) );
			if ( 0 === $pages ) {
				continue;
			}
			for ( $page = 1; $page <= $pages; $page++ ) {
				$entries[] = [
					'loc'     => $this->entry_url( 'taxonomies', $tax, $page ),
					'lastmod' => '',
				];
			}
		}

		/**
		 * Filters the full sitemap index entry list.
		 *
		 * @param list<array{loc: string, lastmod: string}> $entries
		 */
		return array_values( (array) apply_filters( 'flexa_seo_aeo/sitemap/index', $entries ) );
	}

	/**
	 * URL entries for one page of a post-type sub-sitemap.
	 *
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	public function posts( string $post_type, int $page ): array {
		if ( ! in_array( $post_type, $this->post_types(), true ) ) {
			return [];
		}

		$with_images = (bool) Settings::get( 'xml_sitemap_images' );
		$query       = new WP_Query( $this->post_query_args( $post_type, $page ) );
		$entries     = [];

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$entries[] = [
				'loc'     => (string) get_permalink( $post ),
				'lastmod' => $this->to_iso( $post->post_modified_gmt ),
				'images'  => $with_images ? $this->post_images( $post ) : [],
			];
		}

		/**
		 * Filters the URL entries for a post-type sub-sitemap page.
		 *
		 * @param list<array{loc: string, lastmod: string, images: list<string>}> $entries
		 * @param string                                                           $post_type
		 * @param int                                                              $page
		 */
		return array_values(
			(array) apply_filters( 'flexa_seo_aeo/sitemap/posts', $entries, $post_type, $page )
		);
	}

	/**
	 * URL entries for one page of a taxonomy sub-sitemap.
	 *
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	public function terms( string $taxonomy, int $page ): array {
		if ( ! in_array( $taxonomy, $this->taxonomies(), true ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => self::MAX_PER_PAGE,
				'offset'     => ( max( 1, $page ) - 1 ) * self::MAX_PER_PAGE,
				'orderby'    => 'id',
				'order'      => 'ASC',
			]
		);

		$entries = [];
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$link = get_term_link( $term );
				if ( ! is_string( $link ) ) {
					continue;
				}
				$entries[] = [
					'loc'     => $link,
					'lastmod' => '',
					'images'  => [],
				];
			}
		}

		/**
		 * Filters the URL entries for a taxonomy sub-sitemap page.
		 *
		 * @param list<array{loc: string, lastmod: string, images: list<string>}> $entries
		 * @param string                                                           $taxonomy
		 * @param int                                                              $page
		 */
		return array_values(
			(array) apply_filters( 'flexa_seo_aeo/sitemap/terms', $entries, $taxonomy, $page )
		);
	}

	/**
	 * Grouped data for the HTML sitemap shortcode: one section per post type,
	 * each with its published entries. Intentionally flat (no pagination) — the
	 * `flexa_seo_aeo/sitemap/html_limit` filter caps runaway lists.
	 *
	 * @return list<array{label: string, items: list<array{title: string, url: string}>}>
	 */
	public function html_tree(): array {
		$limit    = (int) apply_filters( 'flexa_seo_aeo/sitemap/html_limit', 500 );
		$sections = [];

		foreach ( $this->post_types() as $type ) {
			$object = get_post_type_object( $type );
			$label  = $object instanceof \WP_Post_Type ? (string) $object->labels->name : $type;

			$query = new WP_Query(
				[
					'post_type'              => $type,
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'post__not_in'           => $this->noindexed_ids(),
				]
			);

			$items = [];
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$items[] = [
					'title' => (string) get_the_title( $post ),
					'url'   => (string) get_permalink( $post ),
				];
			}

			if ( [] !== $items ) {
				$sections[] = [
					'label' => $label,
					'items' => $items,
				];
			}
		}

		/**
		 * Filters the grouped HTML-sitemap sections.
		 *
		 * @param list<array{label: string, items: list<array{title: string, url: string}>}> $sections
		 */
		return array_values( (array) apply_filters( 'flexa_seo_aeo/sitemap/html_tree', $sections ) );
	}

	/**
	 * Build the public URL for a sub-sitemap document. Uses pretty rewrites when
	 * permalinks are on, else falls back to query-var form so the sitemap still
	 * resolves on a plain-permalink site.
	 */
	public function entry_url( string $object_type, string $subtype, int $page ): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg(
				[
					'flexa_sitemap'         => $object_type,
					'flexa_sitemap_subtype' => $subtype,
					'flexa_sitemap_page'    => $page,
				],
				home_url( '/' )
			);
		}

		return home_url( sprintf( '/sitemap-%s-%s-%d.xml', $object_type, $subtype, $page ) );
	}

	/**
	 * Public URL of the sitemap index.
	 */
	public function index_url(): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( [ 'flexa_sitemap' => 'index' ], home_url( '/' ) );
		}

		return home_url( '/sitemap.xml' );
	}

	/**
	 * Public URL of the XSL stylesheet the XML documents reference.
	 */
	public function stylesheet_url(): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( [ 'flexa_sitemap' => 'xsl' ], home_url( '/' ) );
		}

		return home_url( '/sitemap.xsl' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function post_query_args( string $post_type, int $page ): array {
		return [
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_PER_PAGE,
			'paged'                  => max( 1, $page ),
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'post__not_in'           => $this->noindexed_ids(),
		];
	}

	/**
	 * IDs of the posts flagged `noindex` through the per-post override.
	 *
	 * Resolved as a lookup of its own rather than as a meta_query on the sitemap
	 * query itself. The natural meta_query here reads "no row at all, OR a row
	 * that is not 1", and WP cannot pool those two clauses onto one join: an OR
	 * relation only shares a table alias between positive operators (see
	 * WP_Meta_Query::find_compatible_table_alias), and neither NOT EXISTS nor
	 * != qualifies. Every sub-sitemap page therefore LEFT JOINed postmeta twice,
	 * which is what made this query a problem on large sites.
	 *
	 * A bare NOT EXISTS would be one join but the wrong answer: saving a post in
	 * the block editor writes the unchecked box as an empty row, so most posts
	 * on a live site *do* have a row and would silently vanish from the sitemap.
	 *
	 * The flagged set is small (you noindex a handful of pages, not a library)
	 * and identical for every page of every sub-sitemap, so one indexed lookup
	 * answers all of them and the sitemap queries themselves need no join.
	 *
	 * @return list<int>
	 */
	private function noindexed_ids(): array {
		if ( null !== $this->noindexed_ids ) {
			return $this->noindexed_ids;
		}

		$types = $this->post_types();
		if ( [] === $types ) {
			$this->noindexed_ids = [];

			return [];
		}

		/** @var list<int> $ids */
		$ids = get_posts(
			[
				'post_type'              => $types,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => self::NOINDEX_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => '1',                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		$this->noindexed_ids = array_values( array_map( 'intval', $ids ) );

		return $this->noindexed_ids;
	}

	private function count_posts( string $post_type ): int {
		$args                   = $this->post_query_args( $post_type, 1 );
		$args['fields']         = 'ids';
		$args['posts_per_page'] = 1;
		$args['no_found_rows']  = false;

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	private function count_terms( string $taxonomy ): int {
		$count = wp_count_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			]
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	private function page_count( int $total ): int {
		if ( $total <= 0 ) {
			return 0;
		}

		return (int) ceil( $total / self::MAX_PER_PAGE );
	}

	private function post_type_lastmod( string $post_type ): string {
		$latest = get_lastpostmodified( 'gmt', $post_type );

		return is_string( $latest ) ? $this->to_iso( $latest ) : '';
	}

	/**
	 * @return list<string>
	 */
	private function post_images( WP_Post $post ): array {
		$images   = [];
		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );
			if ( is_string( $url ) ) {
				$images[] = $url;
			}
		}

		/**
		 * Filters the image URLs advertised for a post in the sitemap.
		 *
		 * @param list<string> $images
		 * @param WP_Post      $post
		 */
		$images = (array) apply_filters( 'flexa_seo_aeo/sitemap/post_images', $images, $post );

		return array_values( array_unique( array_filter( array_map( 'strval', $images ) ) ) );
	}

	private function to_iso( string $gmt_datetime ): string {
		if ( '' === $gmt_datetime || '0000-00-00 00:00:00' === $gmt_datetime ) {
			return '';
		}

		$iso = mysql2date( 'c', $gmt_datetime, false );

		return is_string( $iso ) ? $iso : '';
	}
}
