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
	 * How many flagged IDs may be inlined into `post__not_in` before the
	 * exclusion switches to a subquery. Each ID costs around eight bytes of SQL
	 * and is re-sent with every sub-sitemap page, so a site that noindexes a
	 * whole post type would otherwise build a multi-megabyte IN list. The cap is
	 * deliberately generous: the handful of pages this feature is meant for sits
	 * nowhere near it.
	 */
	private const NOINDEX_ID_LIMIT = 1000;

	/**
	 * Query var marking a query that must exclude noindexed posts through
	 * {@see self::filter_noindex_where()} rather than through `post__not_in`.
	 */
	private const EXCLUDE_QUERY_VAR = 'flexa_seo_aeo_exclude_noindex';

	/**
	 * Memoized {@see self::noindexed_ids()} result: the flagged IDs, or false
	 * when there are more of them than self::NOINDEX_ID_LIMIT allows inlining.
	 * Null until the first lookup. One sitemap request builds an index plus
	 * several sub-sitemap pages, all of which want the same answer.
	 *
	 * @var list<int>|false|null
	 */
	private array|bool|null $noindexed_ids = null;

	/**
	 * Whether {@see self::filter_noindex_where()} is already on `posts_where`.
	 * The filter is attached on first use, so a site under the cap never pays
	 * for it.
	 */
	private bool $where_filter_attached = false;

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

		// Term archives carry no lastmod, here or in the sub-sitemap itself. A
		// term has no modification date of its own, and deriving one from its
		// newest post means a query per term, which on a site with thousands of
		// tags costs more than the hint is worth. `lastmod` is optional, and
		// both templates skip the element when it is empty, so a crawler sees a
		// clean entry rather than a guess.
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
	 * Whether one page of a sub-sitemap would carry at least one URL.
	 *
	 * Callers that only need the yes/no (the legacy-URL redirect decides
	 * between the real document and the index with it) would otherwise build
	 * the whole entry set: up to 2000 permalinks, each with its images, thrown
	 * away a line later. This asks for one row.
	 */
	public function has_entries( string $object_type, string $subtype, int $page ): bool {
		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * self::MAX_PER_PAGE;

		if ( 'taxonomies' === $object_type ) {
			if ( ! in_array( $subtype, $this->taxonomies(), true ) ) {
				return false;
			}

			$terms = get_terms(
				[
					'taxonomy'   => $subtype,
					'hide_empty' => true,
					'number'     => 1,
					'offset'     => $offset,
					'orderby'    => 'id',
					'order'      => 'ASC',
					'fields'     => 'ids',
				]
			);

			return is_array( $terms ) && [] !== $terms;
		}

		if ( ! in_array( $subtype, $this->post_types(), true ) ) {
			return false;
		}

		$args                   = $this->post_query_args( $subtype, $page );
		$args['posts_per_page'] = 1;
		$args['fields']         = 'ids';
		$args['no_found_rows']  = true;
		// An explicit offset, because `paged` would now step one post at a time.
		$args['offset'] = $offset;
		unset( $args['paged'] );

		$query = new WP_Query( $args );

		return [] !== $query->posts;
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
				$this->exclude_noindexed(
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
					]
				)
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
		return $this->exclude_noindexed(
			[
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_PER_PAGE,
				'paged'                  => max( 1, $page ),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);
	}

	/**
	 * Add the "not for crawlers" exclusions to a post query: posts flagged
	 * noindex, and posts behind a password.
	 *
	 * The password half is a one-line `has_password` argument, applied here
	 * rather than at each call site because this method is the single point the
	 * XML sitemap, the HTML sitemap and llms.txt all pass through, and a
	 * protected post has no business being advertised by any of the three.
	 *
	 * Two shapes, because one does not cover both sizes of site. The flagged set
	 * is normally tiny (you noindex a handful of pages), and for that an
	 * `post__not_in` list is the cheapest thing there is: no join, no subquery,
	 * and the same list reused by every sub-sitemap page. Past
	 * self::NOINDEX_ID_LIMIT that inverts, because the list is re-sent with each
	 * page and grows the SQL text without bound, so the exclusion moves into a
	 * correlated NOT EXISTS evaluated by MySQL against the postmeta `post_id`
	 * index and nothing is carried in PHP memory at all.
	 *
	 * Note what the subquery tests: `meta_value = '1'`. WP_Meta_Query's own NOT
	 * EXISTS cannot express that (it asks only whether a row is there), and a
	 * row can be there holding an empty string, so a key-only test would drop
	 * live posts from the sitemap.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function exclude_noindexed( array $args ): array {
		/**
		 * Filters whether password-protected posts are kept out of the sitemap,
		 * the HTML sitemap and llms.txt. Return true only if the titles are
		 * meant to be discoverable and the bodies really are the only secret.
		 *
		 * @param bool $include Whether to advertise protected posts.
		 */
		if ( ! (bool) apply_filters( 'flexa_seo_aeo/sitemap/include_password_protected', false ) ) {
			$args['has_password'] = false;
		}

		$ids = $this->noindexed_ids();

		if ( false !== $ids ) {
			$args['post__not_in'] = $ids;

			return $args;
		}

		if ( ! $this->where_filter_attached ) {
			add_filter( 'posts_where', [ $this, 'filter_noindex_where' ], 10, 2 );
			$this->where_filter_attached = true;
		}

		$args[ self::EXCLUDE_QUERY_VAR ] = true;

		return $args;
	}

	/**
	 * The subquery half of {@see self::exclude_noindexed()}. Attached only once
	 * the flagged set has outgrown an inline list, and it leaves every other
	 * query on the request untouched.
	 */
	public function filter_noindex_where( string $where, WP_Query $query ): string {
		if ( ! $query->get( self::EXCLUDE_QUERY_VAR ) ) {
			return $where;
		}

		global $wpdb;

		return $where . $wpdb->prepare(
			" AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS flexa_ni"
			. " WHERE flexa_ni.post_id = {$wpdb->posts}.ID"
			. ' AND flexa_ni.meta_key = %s AND flexa_ni.meta_value = %s )',
			self::NOINDEX_META,
			'1'
		);
	}

	/**
	 * IDs of the posts flagged `noindex` through the per-post override, or false
	 * when there are more than self::NOINDEX_ID_LIMIT of them.
	 *
	 * Resolved as a lookup of its own rather than as a meta_query on the sitemap
	 * query itself. The natural meta_query here reads "no row at all, OR a row
	 * that is not 1", and WP cannot pool those two clauses onto one join: an OR
	 * relation shares a table alias only between positive operators, so NOT
	 * EXISTS and != each take their own LEFT JOIN on postmeta and every sitemap
	 * query pays for both.
	 *
	 * One row over the cap is enough to know the set is too big, so the lookup
	 * asks for exactly that one extra row and never materialises more.
	 *
	 * @return list<int>|false
	 */
	private function noindexed_ids(): array|false {
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
				'posts_per_page'         => self::NOINDEX_ID_LIMIT + 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => self::NOINDEX_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => '1',                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		$this->noindexed_ids = count( $ids ) > self::NOINDEX_ID_LIMIT
			? false
			: array_values( array_map( 'intval', $ids ) );

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

	/**
	 * The newest modification date among the posts this sitemap actually lists.
	 *
	 * Not `get_lastpostmodified()`: that reads the newest published post of the
	 * type whatever its state, so noindexing or password-protecting the most
	 * recent post left the index advertising a date no URL in the file carried,
	 * and every crawl after it looked stale. One extra query per post type,
	 * reused by all of that type's pages.
	 */
	private function post_type_lastmod( string $post_type ): string {
		$args                           = $this->post_query_args( $post_type, 1 );
		$args['posts_per_page']         = 1;
		$args['paged']                  = 1;
		$args['orderby']                = 'modified';
		$args['order']                  = 'DESC';
		$args['update_post_meta_cache'] = false;

		$query  = new WP_Query( $args );
		$latest = $query->posts[0] ?? null;

		return $latest instanceof WP_Post ? $this->to_iso( $latest->post_modified_gmt ) : '';
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
