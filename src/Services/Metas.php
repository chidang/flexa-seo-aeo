<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services;

use Flexa\SeoAeo\Domain\PostMeta;
use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Support\PostSummary;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the `<head>` metadata for the current main query into a plain
 * {@see ResolvedMeta}. This class is pure logic — it reads the query, settings,
 * and per-post overrides, substitutes `%%tokens%%`, and returns data. All HTML
 * output and escaping lives in {@see \Flexa\SeoAeo\Actions\Front\Metas}.
 */
final class Metas {
	use SingletonTrait;

	/**
	 * Where a generated description is cut. Google renders roughly this much,
	 * and the readiness scorer treats 50-160 as the healthy band, so a value the
	 * plugin generated itself should never land outside it.
	 */
	private const DESCRIPTION_LIMIT = 160;

	private ?ResolvedMeta $cache = null;

	private PostMetaRepository $repository;

	public function __construct() {
		$this->repository = PostMetaRepository::instance();
	}

	public function resolve(): ResolvedMeta {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$meta        = null;
		$context     = [];
		$title_tpl   = '%%sitename%% %%sep%% %%tagline%%';
		$description = '';
		$canonical   = '';
		$og_type     = 'website';
		$og_image    = (string) Settings::get( 'og_default_image' );
		$noindex     = false;
		$nofollow    = false;

		if ( is_front_page() ) {
			$home_title = (string) Settings::get( 'home_title' );
			$home_desc  = (string) Settings::get( 'home_description' );
			$title_tpl  = '' !== $home_title ? $home_title : '%%sitename%% %%sep%% %%tagline%%';
			$canonical  = home_url( '/' );

			// A static front page is `is_singular()` as well, but this branch wins
			// the chain, so its own SEO panel has to be read here or the page
			// silently loses every override it was given. Null when the front page
			// is the post index, in which case nothing below applies.
			$front_page = $this->repository->current_post();
			if ( $front_page instanceof WP_Post ) {
				$meta               = $this->repository->get( $front_page->ID );
				$noindex            = $meta->noindex;
				$nofollow           = $meta->nofollow;
				$context['title']   = get_the_title( $front_page );
				$context['excerpt'] = $this->excerpt( $front_page );
				$og_image           = $this->og_image( $meta, $front_page, $og_image );

				if ( '' === $home_title && '' !== $meta->title ) {
					$title_tpl = $meta->title;
				}
				if ( '' !== $meta->canonical ) {
					$canonical = $meta->canonical;
				}
			}

			// The global Home description keeps priority so installs that already
			// filled it in see no change. Without a static page behind it the front
			// page *is* the post index, so it borrows that wording as a last resort.
			$last_resort = $front_page instanceof WP_Post
				? $this->site_description()
				: $this->blog_description();

			$description = '' !== $home_desc
				? $home_desc
				: $this->describe( $meta, $context['excerpt'] ?? '', $last_resort );
		} elseif ( is_home() ) {
			$posts_page = $this->repository->current_post();
			$title_tpl  = '%%title%% %%sep%% %%sitename%%';

			if ( $posts_page instanceof WP_Post ) {
				$meta               = $this->repository->get( $posts_page->ID );
				$noindex            = $meta->noindex;
				$nofollow           = $meta->nofollow;
				$context['title']   = get_the_title( $posts_page );
				$context['excerpt'] = $this->excerpt( $posts_page );
				$canonical          = '' !== $meta->canonical ? $meta->canonical : (string) get_permalink( $posts_page );
				$og_image           = $this->og_image( $meta, $posts_page, $og_image );

				if ( '' !== $meta->title ) {
					$title_tpl = $meta->title;
				}
			}

			$description = $this->describe( $meta, $context['excerpt'] ?? '', $this->blog_description() );
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$meta               = $this->repository->get( $post->ID );
				$noindex            = $meta->noindex;
				$nofollow           = $meta->nofollow;
				$context['title']   = get_the_title( $post );
				$context['excerpt'] = $this->excerpt( $post );
				$title_tpl          = '' !== $meta->title ? $meta->title : '%%title%% %%sep%% %%sitename%%';
				$description        = $this->describe( $meta, $context['excerpt'], '' );
				$canonical          = '' !== $meta->canonical ? $meta->canonical : (string) get_permalink( $post );
				$og_type            = 'post' === get_post_type( $post ) ? 'article' : 'website';
				$og_image           = $this->og_image( $meta, $post, $og_image );

				// %%author%% on a post means the post's author, which is what
				// Yoast's %%name%% resolves to and therefore what a migrated
				// template expects. Without this the token silently collapses to
				// nothing on every singular view.
				$author = get_user_by( 'id', (int) $post->post_author );
				if ( $author instanceof \WP_User ) {
					$context['author'] = $author->display_name;
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$context['term_title'] = $term->name;
				$title_tpl             = '%%term_title%% %%sep%% %%sitename%%';
				$description           = $this->term_description( $term );
				$link                  = get_term_link( $term );
				$canonical             = is_string( $link ) ? $link : '';
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				$context['author'] = $author->display_name;
				$title_tpl         = '%%author%% %%sep%% %%sitename%%';
				$description       = $this->author_description( $author );
				$canonical         = (string) get_author_posts_url( $author->ID );
			}
		} elseif ( is_search() ) {
			$context['searchphrase'] = get_search_query();
			$title_tpl               = '%%searchphrase%% %%sep%% %%sitename%%';
			$description             = $this->search_description();
			$noindex                 = true;
		} elseif ( is_404() ) {
			$context['archive_title'] = __( 'Page not found', 'flexa-seo-aeo' );
			$title_tpl                = '%%archive_title%% %%sep%% %%sitename%%';
			$description              = $this->not_found_description();
			$noindex                  = true;
		} elseif ( is_archive() ) {
			$context['archive_title'] = wp_strip_all_tags( get_the_archive_title() );
			$title_tpl                = '%%archive_title%% %%sep%% %%sitename%%';
			$description              = $this->archive_description();
		}

		// Every branch above can still come up empty: an author with no bio, an
		// archive nobody described, a post with no content. A generic sentence beats
		// a page with no description at all, so the tagline closes the gap.
		if ( '' === $description ) {
			$description = $this->site_description();
		}

		/**
		 * Filters the resolved description before `%%token%%` substitution, e.g. to
		 * supply per-view wording of your own.
		 *
		 * @param string                $description
		 * @param array<string, string> $context
		 */
		$description = (string) apply_filters( 'flexa_seo_aeo/metas/description', $description, $context );

		/**
		 * Filters the raw title template (still containing `%%tokens%%`) before
		 * substitution, e.g. to make templates configurable per post type.
		 *
		 * @param string                $title_tpl
		 * @param array<string, string> $context
		 */
		$title_tpl = (string) apply_filters( 'flexa_seo_aeo/metas/title_template', $title_tpl, $context );

		$title       = Variables::replace( $title_tpl, $context );
		$description = PostSummary::truncate( Variables::replace( $description, $context ), self::DESCRIPTION_LIMIT );
		$canonical   = (string) apply_filters( 'flexa_seo_aeo/metas/canonical', $canonical );

		$resolved = new ResolvedMeta(
			title: $title,
			description: $description,
			canonical: $canonical,
			robots: $this->robots( $noindex, $nofollow, $meta ),
			og: $this->open_graph( $meta, $title, $description, $canonical, $og_type, $og_image ),
			twitter: $this->twitter( $meta, $title, $description, $og_image ),
		);

		/**
		 * Filters the fully-resolved metadata for the current request.
		 *
		 * @param ResolvedMeta $resolved
		 */
		$resolved = apply_filters( 'flexa_seo_aeo/metas/resolved', $resolved );

		$this->cache = $resolved;

		return $resolved;
	}

	/**
	 * @return list<string>
	 */
	private function robots( bool $noindex, bool $nofollow, ?PostMeta $meta ): array {
		// "Discourage search engines from indexing this site" in Settings >
		// Reading is a site-wide answer, and it outranks anything configured per
		// post. A staging site whose owner ticked that box must not carry a
		// plugin tag inviting crawlers in, next to core's tag telling them to
		// stay out.
		if ( ! (bool) get_option( 'blog_public' ) ) {
			$noindex  = true;
			$nofollow = true;
		}

		$robots = [
			$noindex ? 'noindex' : 'index',
			$nofollow ? 'nofollow' : 'follow',
		];

		// AEO-friendly defaults: let answer engines quote content in full. These
		// only make sense on indexable pages.
		if ( ! $noindex ) {
			$robots[] = 'max-snippet:-1';
			$robots[] = 'max-image-preview:large';
			$robots[] = 'max-video-preview:-1';
		}

		/**
		 * Filters the robots directives for the current request.
		 *
		 * @param list<string>  $robots
		 * @param PostMeta|null $meta
		 */
		$robots = apply_filters( 'flexa_seo_aeo/metas/robots', $robots, $meta );

		return array_values( array_unique( array_map( 'strval', (array) $robots ) ) );
	}

	/**
	 * @return array<string, string>
	 */
	private function open_graph(
		?PostMeta $meta,
		string $title,
		string $description,
		string $canonical,
		string $og_type,
		string $og_image
	): array {
		$og_title = ( $meta && '' !== $meta->og_title ) ? $meta->og_title : $title;
		$og_desc  = ( $meta && '' !== $meta->og_description ) ? $meta->og_description : $description;

		$og = [
			'og:type'        => $og_type,
			'og:title'       => $og_title,
			'og:description' => $og_desc,
			'og:url'         => '' !== $canonical ? $canonical : $this->current_url(),
			'og:site_name'   => (string) get_bloginfo( 'name' ),
			'og:locale'      => str_replace( '-', '_', (string) get_bloginfo( 'language' ) ),
		];

		if ( '' !== $og_image ) {
			$og['og:image'] = $og_image;
		}

		return array_filter( $og, static fn( string $v ): bool => '' !== $v );
	}

	/**
	 * @return array<string, string>
	 */
	private function twitter( ?PostMeta $meta, string $title, string $description, string $og_image ): array {
		$tw_title = ( $meta && '' !== $meta->twitter_title ) ? $meta->twitter_title : $title;
		$tw_desc  = ( $meta && '' !== $meta->twitter_description ) ? $meta->twitter_description : $description;
		$tw_image = ( $meta && '' !== $meta->twitter_image ) ? $meta->twitter_image : $og_image;

		$twitter = [
			'twitter:card'        => (string) Settings::get( 'twitter_card_type' ),
			'twitter:title'       => $tw_title,
			'twitter:description' => $tw_desc,
		];

		if ( '' !== $tw_image ) {
			$twitter['twitter:image'] = $tw_image;
		}

		return array_filter( $twitter, static fn( string $v ): bool => '' !== $v );
	}

	/**
	 * First non-empty description source, in order: the post's own SEO field,
	 * its auto-excerpt, then whatever the caller offers as a last resort.
	 */
	private function describe( ?PostMeta $meta, string $excerpt, string $fallback ): string {
		if ( null !== $meta && '' !== $meta->description ) {
			return $meta->description;
		}

		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		return $fallback;
	}

	/**
	 * Per-post OG image override, else the featured image, else the site default.
	 */
	private function og_image( PostMeta $meta, WP_Post $post, string $fallback ): string {
		if ( '' !== $meta->og_image ) {
			return $meta->og_image;
		}

		$featured = $this->featured_image( $post );

		return '' !== $featured ? $featured : $fallback;
	}

	/**
	 * Description for a taxonomy archive: the term's own description, else a
	 * sentence naming the term so each archive still says something of its own.
	 */
	private function term_description( WP_Term $term ): string {
		$own = $this->condense( (string) $term->description );
		if ( '' !== $own ) {
			return $own;
		}

		return sprintf(
			/* translators: 1: taxonomy term name, 2: site name. */
			__( 'Posts filed under %1$s on %2$s.', 'flexa-seo-aeo' ),
			$term->name,
			$this->site_name()
		);
	}

	/**
	 * Description for an author archive: their bio if they wrote one.
	 */
	private function author_description( WP_User $author ): string {
		$bio = $this->condense( (string) get_the_author_meta( 'description', $author->ID ) );
		if ( '' !== $bio ) {
			return $bio;
		}

		return sprintf(
			/* translators: 1: author display name, 2: site name. */
			__( 'Posts written by %1$s on %2$s.', 'flexa-seo-aeo' ),
			$author->display_name,
			$this->site_name()
		);
	}

	private function search_description(): string {
		$phrase = trim( (string) get_search_query() );

		if ( '' === $phrase ) {
			return sprintf(
				/* translators: %s: site name. */
				__( 'Search results on %s.', 'flexa-seo-aeo' ),
				$this->site_name()
			);
		}

		return sprintf(
			/* translators: 1: search phrase, 2: site name. */
			__( 'Search results for “%1$s” on %2$s.', 'flexa-seo-aeo' ),
			$phrase,
			$this->site_name()
		);
	}

	private function not_found_description(): string {
		return sprintf(
			/* translators: %s: site name. */
			__( 'This page could not be found on %s. Try a search or start again from the homepage.', 'flexa-seo-aeo' ),
			$this->site_name()
		);
	}

	/**
	 * Description for the archives that reach the generic branch: a post type
	 * archive, which uses the description given at registration when there is
	 * one, and date archives. Returns '' when there is no name to build a
	 * sentence from, leaving the site-wide fallback to take over.
	 */
	private function archive_description(): string {
		$object = get_queried_object();

		if ( $object instanceof WP_Post_Type ) {
			$registered = $this->condense( $object->description );
			if ( '' !== $registered ) {
				return $registered;
			}

			return sprintf(
				/* translators: 1: post type plural label, 2: site name. */
				__( 'All %1$s on %2$s.', 'flexa-seo-aeo' ),
				$object->label,
				$this->site_name()
			);
		}

		$name = $this->archive_name();
		if ( '' === $name ) {
			return '';
		}

		if ( is_date() ) {
			return sprintf(
				/* translators: 1: a date or period such as "September 2026", 2: site name. */
				__( 'Posts from %1$s on %2$s.', 'flexa-seo-aeo' ),
				$name,
				$this->site_name()
			);
		}

		return sprintf(
			/* translators: 1: archive name, 2: site name. */
			__( 'The %1$s archive on %2$s.', 'flexa-seo-aeo' ),
			$name,
			$this->site_name()
		);
	}

	/**
	 * The archive title without the "Category:" / "Year:" prefix core prepends,
	 * so it reads correctly inside a sentence.
	 */
	private function archive_name(): string {
		add_filter( 'get_the_archive_title_prefix', '__return_empty_string', 99 );
		$name = wp_strip_all_tags( (string) get_the_archive_title() );
		remove_filter( 'get_the_archive_title_prefix', '__return_empty_string', 99 );

		return trim( $name );
	}

	/**
	 * What the site says about itself: the tagline, or the AEO site summary when
	 * the tagline was never filled in. Both are empty on plenty of installs, so
	 * callers still have to cope with ''.
	 */
	private function site_description(): string {
		$tagline = trim( (string) get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			return $tagline;
		}

		return trim( (string) Settings::get_aeo( 'site_description' ) );
	}

	/**
	 * Last resort for the blog index, which has no content of its own to quote
	 * and is the one view where a bare "latest posts" line is accurate.
	 */
	private function blog_description(): string {
		$site = $this->site_description();
		if ( '' !== $site ) {
			return $site;
		}

		return sprintf(
			/* translators: %s: site name. */
			__( 'The latest posts on %s.', 'flexa-seo-aeo' ),
			$this->site_name()
		);
	}

	private function site_name(): string {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * The description the frontend falls back to for a post when it carries no
	 * explicit override: its excerpt, else the opening of its content. Public so
	 * the readiness scorer can measure the string this site actually emits
	 * instead of deriving its own, which is how the two drifted apart.
	 */
	public function fallback_description( WP_Post $post ): string {
		return $this->excerpt( $post );
	}

	private function excerpt( WP_Post $post ): string {
		return PostSummary::text( $post, self::DESCRIPTION_LIMIT );
	}

	/**
	 * Squash arbitrary content down to a single line of plain text, short enough
	 * for a meta description. Only for strings that are not post content, which
	 * goes through {@see excerpt()} so it keeps the password guard.
	 */
	private function condense( string $raw ): string {
		return PostSummary::truncate( PostSummary::condense( $raw ), self::DESCRIPTION_LIMIT );
	}

	private function featured_image( WP_Post $post ): string {
		$thumb_id = get_post_thumbnail_id( $post );
		if ( ! $thumb_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );

		return is_string( $url ) ? $url : '';
	}

	private function current_url(): string {
		$wp      = $GLOBALS['wp'] ?? null;
		$request = $wp instanceof \WP ? (string) $wp->request : '';

		return home_url( '' !== $request ? '/' . ltrim( $request, '/' ) : '/' );
	}
}
