<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Sitemap;

use Flexa\SeoAeo\Services\Sitemap as SitemapService;
use Flexa\SeoAeo\Support\CacheHeaders;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend I/O for the XML sitemap: registers the rewrite rules + query vars,
 * intercepts the matching request on template_redirect, and streams the correct
 * XML (index, posts, taxonomies) or the XSL stylesheet, then exits. All data
 * comes from {@see SitemapService}; all escaping happens in the templates.
 */
final class Router {
	use SingletonTrait;

	private const QUERY_VARS = [ 'flexa_sitemap', 'flexa_sitemap_subtype', 'flexa_sitemap_page' ];

	public function register(): void {
		// Attached unconditionally so flipping the sitemap on for the first time
		// (which happens on a request where the feature was still off at init)
		// still triggers the one-time rewrite flush.
		add_action( 'flexa_seo_aeo/settings/updated', [ $this, 'maybe_flush' ], 10, 2 );

		if ( ! (bool) Settings::get( 'xml_sitemap' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'render' ], 0 );
		add_action( 'template_redirect', [ $this, 'redirect_legacy' ], 1 );
		add_filter( 'wp_sitemaps_enabled', [ $this, 'disable_core_sitemaps' ] );
	}

	/**
	 * Switch core's own sitemap off while ours is serving.
	 *
	 * Both indexes are otherwise live at once: ours at `/sitemap.xml`, core's at
	 * `/wp-sitemap.xml`, each listing the same URLs, and core adds its line to
	 * robots.txt so crawlers are handed two competing indexes. Core's is also
	 * the weaker of the two here (no image entries, no per-post noindex
	 * exclusion), so ours wins and core's goes away.
	 *
	 * @param bool $enabled Whether core would serve its sitemap.
	 */
	public function disable_core_sitemaps( bool $enabled ): bool {
		unset( $enabled );

		/**
		 * Filters whether core's `/wp-sitemap.xml` is turned off while the Flexa
		 * sitemap is on. Return true to keep both live, e.g. when something
		 * downstream has already been pointed at the core URL.
		 *
		 * @param bool $keep Whether to leave core's sitemap enabled.
		 */
		return (bool) apply_filters( 'flexa_seo_aeo/sitemap/keep_core_sitemap', false );
	}

	/**
	 * Flush rewrites once when the XML sitemap toggle changes so `/sitemap.xml`
	 * starts (or stops) resolving without a manual permalink re-save.
	 *
	 * @param array<string, mixed> $new_settings
	 * @param array<string, mixed> $old_settings
	 */
	public function maybe_flush( array $new_settings, array $old_settings ): void {
		$now = ! empty( $new_settings['xml_sitemap'] );
		$was = ! empty( $old_settings['xml_sitemap'] );
		if ( $now === $was ) {
			return;
		}

		if ( $now ) {
			$this->add_rewrite_rules();
		}
		flush_rewrite_rules( false );
	}

	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?flexa_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap\.xsl$', 'index.php?flexa_sitemap=xsl', 'top' );
		add_rewrite_rule(
			'^sitemap-(posts|taxonomies)-([A-Za-z0-9_-]+)-([0-9]+)\.xml$',
			'index.php?flexa_sitemap=$matches[1]&flexa_sitemap_subtype=$matches[2]&flexa_sitemap_page=$matches[3]',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_vars( array $vars ): array {
		return array_merge( $vars, self::QUERY_VARS );
	}

	public function render(): void {
		$what = (string) get_query_var( 'flexa_sitemap' );
		if ( '' === $what ) {
			return;
		}

		if ( 'xsl' === $what ) {
			// The stylesheet ships with the plugin, so its own file date is the
			// only thing that can change it.
			$this->send( 'application/xslt+xml', $this->render_template( 'stylesheet', [] ), $this->stylesheet_mtime() );
		}

		if ( 'index' === $what ) {
			$entries = SitemapService::instance()->index();
			$body    = $this->render_template(
				'index',
				[
					'entries'    => $entries,
					'stylesheet' => SitemapService::instance()->stylesheet_url(),
				]
			);

			$this->send( 'application/xml', $body, CacheHeaders::newest_lastmod( $entries ) );
		}

		$subtype = (string) get_query_var( 'flexa_sitemap_subtype' );
		$page    = max( 1, (int) get_query_var( 'flexa_sitemap_page' ) );

		$entries = 'posts' === $what
			? SitemapService::instance()->posts( $subtype, $page )
			: ( 'taxonomies' === $what ? SitemapService::instance()->terms( $subtype, $page ) : [] );

		if ( [] === $entries ) {
			$this->not_found();
			return;
		}

		$body = $this->render_template(
			'urlset',
			[
				'entries'     => $entries,
				'stylesheet'  => SitemapService::instance()->stylesheet_url(),
				'with_images' => (bool) Settings::get( 'xml_sitemap_images' ),
			]
		);

		$this->send( 'application/xml', $body, CacheHeaders::newest_lastmod( $entries ) );
	}

	/**
	 * Point an old Yoast or Rank Math sitemap URL at the matching Flexa one
	 * with a 301.
	 *
	 * `sitemap_index.xml` and `post-sitemap.xml` are the paths a migrating site
	 * has already submitted to Search Console and linked from robots.txt, and
	 * they were answering 404.
	 *
	 * This runs on a request WordPress has already decided is a 404, rather
	 * than from a rewrite rule, for two reasons: a site with a real page at
	 * `/anything-sitemap.xml` keeps it, and a name that matches the pattern but
	 * maps to nothing we publish stays the 404 it always was instead of
	 * quietly rendering the homepage at a junk URL.
	 */
	public function redirect_legacy(): void {
		if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$path = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$file = strtolower( basename( rawurldecode( $path ) ) );
		if ( '' === $file ) {
			return;
		}

		$sitemap = SitemapService::instance();

		if ( 'sitemap_index.xml' === $file ) {
			wp_safe_redirect( $sitemap->index_url(), 301 );
			exit;
		}

		if ( 1 !== preg_match( '/^([a-z0-9_-]+)-sitemap([0-9]*)\.xml$/', $file, $match ) ) {
			return;
		}

		$subtype = $match[1];
		$page    = max( 1, (int) $match[2] );

		if ( in_array( $subtype, $sitemap->post_types(), true ) ) {
			$object_type = 'posts';
		} elseif ( in_array( $subtype, $sitemap->taxonomies(), true ) ) {
			$object_type = 'taxonomies';
		} else {
			return;
		}

		$has_entries = $sitemap->has_entries( $object_type, $subtype, $page );

		// The old URL named something we publish, but this particular document
		// has no URLs on this site (an empty taxonomy, a page number past the
		// end). Sending the crawler to the index beats a 301 into a 404.
		$target = $has_entries
			? $sitemap->entry_url( $object_type, $subtype, $page )
			: $sitemap->index_url();

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Send a finished document and stop. A crawler that already holds this
	 * exact body gets a 304 and no bytes.
	 */
	private function send( string $content_type, string $body, int $modified ): void {
		if ( CacheHeaders::serve( $content_type, $body, $modified ) ) {
			exit;
		}

		// Already-escaped XML assembled by the template; escaping again would
		// corrupt it.
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function stylesheet_mtime(): int {
		$file  = FLEXA_SEO_AEO_PATH . 'templates/sitemap/stylesheet.php';
		$stamp = is_readable( $file ) ? filemtime( $file ) : false;

		return false !== $stamp ? $stamp : 0;
	}

	/**
	 * A requested sub-sitemap page that resolves to no URLs (bad subtype, page
	 * out of range) is a genuine 404 — hand back to WordPress's 404 template.
	 */
	private function not_found(): void {
		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Run a template and capture what it prints, so the body can be hashed into
	 * an ETag before anything is sent.
	 *
	 * @param array<string, mixed> $vars
	 */
	private function render_template( string $name, array $vars ): string {
		$file = FLEXA_SEO_AEO_PATH . 'templates/sitemap/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}

		ob_start();
		( static function () use ( $file, $vars ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, sanitized template vars.
			extract( $vars, EXTR_SKIP );
			require $file;
		} )();

		return (string) ob_get_clean();
	}
}
