<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Aeo;

use Flexa\SeoAeo\Services\Aeo\LlmsTxt as LlmsTxtService;
use Flexa\SeoAeo\Support\CacheHeaders;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Serves `/llms.txt` (llmstxt.org) via a rewrite rule: on a matching request it
 * streams the plain-text document built by {@see LlmsTxtService} and exits. Like
 * the sitemap router it attaches its flush listener unconditionally so switching
 * the feature on — which happens on a request where it was still off at `init` —
 * still triggers the one-time rewrite flush that makes the pretty URL resolve.
 */
final class LlmsTxt {
	use SingletonTrait;

	private const QUERY_VAR = 'flexa_llms';

	public function register(): void {
		add_action( 'flexa_seo_aeo/settings/updated', [ $this, 'maybe_flush' ], 10, 2 );

		if ( ! (bool) Settings::get_aeo( 'enabled' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'render' ], 0 );
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function render(): void {
		if ( '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$document = LlmsTxtService::instance()->document();

		// The file lists posts, so the newest post edit is the only thing that
		// can date it. The ETag covers the rest, including a settings change.
		$modified = (int) strtotime( (string) get_lastpostmodified( 'gmt' ) . ' GMT' );

		if ( CacheHeaders::serve( 'text/plain', $document, max( 0, $modified ) ) ) {
			exit;
		}

		// The document is plain text assembled from already-escaped URLs/titles;
		// echoing raw is correct here (esc_html would corrupt the markdown).
		echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Public URL of the llms.txt document (pretty permalink, query-var fallback).
	 */
	public function endpoint_url(): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( [ self::QUERY_VAR => '1' ], home_url( '/' ) );
		}

		return home_url( '/llms.txt' );
	}

	/**
	 * Flush rewrites once when the llms.txt toggle flips so the endpoint starts
	 * (or stops) resolving without a manual permalink re-save.
	 *
	 * @param array<string, mixed> $new_settings
	 * @param array<string, mixed> $old_settings
	 */
	public function maybe_flush( array $new_settings, array $old_settings ): void {
		$now = ! empty( $new_settings['aeo']['enabled'] );
		$was = ! empty( $old_settings['aeo']['enabled'] );
		if ( $now === $was ) {
			return;
		}

		if ( $now ) {
			$this->add_rewrite_rule();
		}
		flush_rewrite_rules( false );
	}
}
