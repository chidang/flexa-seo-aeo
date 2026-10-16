<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Sitemap;

use Flexa\SeoAeo\Services\Sitemap as SitemapService;
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
			$this->send_headers( 'application/xslt+xml' );
			$this->template( 'stylesheet', [] );
			exit;
		}

		if ( 'index' === $what ) {
			$this->send_headers( 'application/xml' );
			$this->template(
				'index',
				[
					'entries'    => SitemapService::instance()->index(),
					'stylesheet' => SitemapService::instance()->stylesheet_url(),
				]
			);
			exit;
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

		$this->send_headers( 'application/xml' );
		$this->template(
			'urlset',
			[
				'entries'     => $entries,
				'stylesheet'  => SitemapService::instance()->stylesheet_url(),
				'with_images' => (bool) Settings::get( 'xml_sitemap_images' ),
			]
		);
		exit;
	}

	private function send_headers( string $content_type ): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $content_type . '; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow', true );
		}
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
	 * @param array<string, mixed> $vars
	 */
	private function template( string $name, array $vars ): void {
		$file = FLEXA_SEO_AEO_PATH . 'templates/sitemap/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return;
		}

		( static function () use ( $file, $vars ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, sanitized template vars.
			extract( $vars, EXTR_SKIP );
			require $file;
		} )();
	}
}
