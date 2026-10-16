<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services;

use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the breadcrumb trail for the current request, as data.
 *
 * One trail serves two consumers that must never disagree: the
 * `BreadcrumbList` node in the JSON-LD graph and the visible trail rendered by
 * {@see \Flexa\SeoAeo\Actions\Front\Breadcrumbs}. Structured data that
 * describes a trail the page does not show is exactly the kind of mismatch
 * Google's guidelines call out, so both read from here.
 */
final class Breadcrumbs {
	use SingletonTrait;

	/**
	 * @var list<array{name: string, url: string}>|null
	 */
	private ?array $cache = null;

	/**
	 * The trail for the current request, home first, current page last.
	 *
	 * The last crumb carries an empty `url`: it is where the visitor already
	 * is. Returns fewer than two crumbs (usually none) when there is no trail
	 * worth showing, such as on the front page.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	public function trail(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$crumbs = is_front_page() ? [] : $this->build();

		/**
		 * Filters the breadcrumb trail before it is rendered or turned into
		 * schema. Both the visible trail and the JSON-LD read this, so a change
		 * here keeps the two in agreement.
		 *
		 * @param list<array{name: string, url: string}> $crumbs
		 */
		$crumbs = array_values( (array) apply_filters( 'flexa_seo_aeo/breadcrumbs/trail', $crumbs ) );

		$this->cache = $crumbs;

		return $crumbs;
	}

	/**
	 * @return list<array{name: string, url: string}>
	 */
	private function build(): array {
		$crumbs = [ $this->crumb( $this->home_label(), home_url( '/' ) ) ];

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$crumbs = array_merge( $crumbs, $this->singular( $post ) );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$crumbs = array_merge( $crumbs, $this->term( $term ) );
			}
		} elseif ( is_post_type_archive() ) {
			$type = get_queried_object();
			if ( $type instanceof WP_Post_Type ) {
				$crumbs[] = $this->crumb( $type->labels->name ?? $type->name, '' );
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				$crumbs[] = $this->crumb( $author->display_name, '' );
			}
		} elseif ( is_search() ) {
			/* translators: %s: the search term. */
			$crumbs[] = $this->crumb( sprintf( __( 'Search results for "%s"', 'flexa-seo-aeo' ), get_search_query() ), '' );
		} elseif ( is_404() ) {
			$crumbs[] = $this->crumb( __( 'Page not found', 'flexa-seo-aeo' ), '' );
		} elseif ( is_archive() ) {
			$crumbs[] = $this->crumb( wp_strip_all_tags( get_the_archive_title() ), '' );
		}

		// A lone Home crumb is not a trail.
		if ( count( $crumbs ) < 2 ) {
			return [];
		}

		// The last crumb is where the visitor already is, so it never links,
		// whichever branch above produced it.
		$crumbs[ count( $crumbs ) - 1 ]['url'] = '';

		return $crumbs;
	}

	/**
	 * Ancestors, then the post itself. A hierarchical post type walks its page
	 * parents; a flat one is placed under its post type archive and, for core
	 * posts, its first category, which is the closest thing WordPress has to
	 * "where this post lives".
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function singular( WP_Post $post ): array {
		$crumbs = [];

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$crumbs[] = $this->crumb(
					(string) get_the_title( (int) $ancestor_id ),
					(string) get_permalink( (int) $ancestor_id )
				);
			}
		} else {
			$crumbs = array_merge( $crumbs, $this->post_type_crumb( $post ), $this->primary_term_crumbs( $post ) );
		}

		$crumbs[] = $this->crumb( (string) get_the_title( $post ), '' );

		return $crumbs;
	}

	/**
	 * @return list<array{name: string, url: string}>
	 */
	private function post_type_crumb( WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );
		if ( ! $type instanceof WP_Post_Type || ! $type->has_archive ) {
			return [];
		}

		$link = get_post_type_archive_link( $post->post_type );
		if ( ! is_string( $link ) || '' === $link ) {
			return [];
		}

		return [ $this->crumb( $type->labels->name ?? $type->name, $link ) ];
	}

	/**
	 * The post's first term in its primary hierarchical taxonomy, with that
	 * term's own ancestors above it.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function primary_term_crumbs( WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public || ! $taxonomy->hierarchical ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy->name );
			if ( ! is_array( $terms ) || [] === $terms ) {
				continue;
			}

			$term = reset( $terms );

			return $term instanceof WP_Term ? $this->term( $term ) : [];
		}

		return [];
	}

	/**
	 * A term and its ancestors. The term itself is the last crumb only when it
	 * is what the visitor is looking at; on a singular view the post title is
	 * appended after it, so it keeps its link.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function term( WP_Term $term ): array {
		$crumbs = [];

		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );
			if ( ! $ancestor instanceof WP_Term ) {
				continue;
			}

			$link     = get_term_link( $ancestor );
			$crumbs[] = $this->crumb( $ancestor->name, is_string( $link ) ? $link : '' );
		}

		$link     = get_term_link( $term );
		$crumbs[] = $this->crumb( $term->name, is_string( $link ) ? $link : '' );

		return $crumbs;
	}

	private function home_label(): string {
		/**
		 * Filters the label of the first breadcrumb.
		 *
		 * @param string $label
		 */
		return (string) apply_filters( 'flexa_seo_aeo/breadcrumbs/home_label', __( 'Home', 'flexa-seo-aeo' ) );
	}

	/**
	 * @return array{name: string, url: string}
	 */
	private function crumb( string $name, string $url ): array {
		return [
			'name' => wp_strip_all_tags( $name ),
			'url'  => $url,
		];
	}
}
