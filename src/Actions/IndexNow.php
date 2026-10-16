<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions;

use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Services\IndexNow as IndexNowService;
use Flexa\SeoAeo\Services\Sitemap as SitemapService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Wires IndexNow into WordPress: pings the API when a sitemap-eligible post is
 * published or updated, serves the verification key file at `/{key}.txt` via a
 * rewrite, and flushes rewrites when the feature is switched on so the key file
 * resolves immediately.
 */
final class IndexNow {
	use SingletonTrait;

	private const KEY_QUERY_VAR = 'flexa_indexnow_key';

	/**
	 * Prefix for the per-URL transient that stops a single edit session from
	 * pinging the same URL over and over.
	 */
	private const SENT_PREFIX = 'flexa_seo_aeo_in_';

	/**
	 * How long a submitted URL stays claimed. Long enough to swallow the burst
	 * of `wp_update_post()` calls an editor save or an importer generates,
	 * short enough that a genuine follow-up edit still gets through the same
	 * working session.
	 */
	private const SENT_TTL = 900;

	public function register(): void {
		// Attached unconditionally so enabling IndexNow for the first time still
		// flushes rewrites and the key file resolves right away.
		add_action( 'flexa_seo_aeo/settings/updated', [ $this, 'maybe_flush' ], 10, 2 );

		if ( ! (bool) Settings::get( 'indexnow' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_action( 'admin_init', [ $this, 'ensure_key' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'serve_key' ], 0 );
		add_action( 'transition_post_status', [ $this, 'on_transition' ], 10, 3 );
	}

	public function add_rewrite_rule(): void {
		// stored_key(), not key(): this runs on `init` for every front-end
		// request, and generating the key here would make an anonymous page
		// view write an option.
		$key = IndexNowService::instance()->stored_key();
		if ( '' === $key ) {
			return;
		}

		// The key is alphanumeric (wp_generate_password without special chars),
		// so it is safe to drop straight into the rewrite pattern.
		add_rewrite_rule( '^' . $key . '\.txt$', 'index.php?' . self::KEY_QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Mint the key from an admin request when the feature is on but no key has
	 * ever been generated, e.g. the site enabled IndexNow before the key stopped
	 * being created on the front end. Flushes once so `/{key}.txt` resolves.
	 */
	public function ensure_key(): void {
		if ( '' !== IndexNowService::instance()->stored_key() ) {
			return;
		}

		IndexNowService::instance()->key();
		$this->add_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::KEY_QUERY_VAR;

		return $vars;
	}

	public function serve_key(): void {
		if ( '' === (string) get_query_var( self::KEY_QUERY_VAR ) ) {
			return;
		}

		$key = IndexNowService::instance()->stored_key();
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=UTF-8' );
		}

		if ( '' === $key ) {
			status_header( 404 );
			exit;
		}

		echo esc_html( $key );
		exit;
	}

	public function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		unset( $old_status );

		if ( 'publish' !== $new_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! $this->is_submittable( $post ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}
		if ( ! $this->claim( $url ) ) {
			return;
		}

		IndexNowService::instance()->submit( [ $url ] );
	}

	/**
	 * Whether this post is one we are willing to hand to a search engine.
	 *
	 * The test mirrors the sitemap: anything the sitemap leaves out has no
	 * business being pushed at a crawler either, and a URL we then answer with
	 * `noindex` only teaches the engine to distrust the feed.
	 */
	private function is_submittable( WP_Post $post ): bool {
		if ( ! (bool) get_option( 'blog_public' ) ) {
			return false;
		}
		if ( '' !== $post->post_password ) {
			return false;
		}
		if ( ! in_array( $post->post_type, SitemapService::instance()->post_types(), true ) ) {
			return false;
		}
		if ( PostMetaRepository::instance()->get( $post->ID )->noindex ) {
			return false;
		}

		return true;
	}

	/**
	 * Take the URL if nobody has submitted it recently.
	 *
	 * `transition_post_status` fires on every `wp_update_post()`, so one editor
	 * save, a bulk edit or an importer can otherwise ping the same URL several
	 * times within a second.
	 */
	private function claim( string $url ): bool {
		$ttl = (int) apply_filters( 'flexa_seo_aeo/indexnow/resubmit_after', self::SENT_TTL, $url );
		if ( $ttl <= 0 ) {
			return true;
		}

		$key = self::SENT_PREFIX . md5( $url );
		if ( false !== get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, time(), $ttl );

		return true;
	}

	/**
	 * When settings are saved with IndexNow enabled, refresh rewrites so the
	 * newly-minted key file starts resolving without a manual permalink re-save.
	 *
	 * @param array<string, mixed> $new_settings
	 * @param array<string, mixed> $old_settings
	 */
	public function maybe_flush( array $new_settings, array $old_settings ): void {
		$now = ! empty( $new_settings['indexnow'] );
		$was = ! empty( $old_settings['indexnow'] );
		if ( $now === $was ) {
			return;
		}

		if ( $now ) {
			// Mint the key here, while we are in a settings-save request, so the
			// front end never has to.
			IndexNowService::instance()->key();
			$this->add_rewrite_rule();
		}
		flush_rewrite_rules( false );
	}
}
