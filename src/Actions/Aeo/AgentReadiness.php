<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Aeo;

use Flexa\SeoAeo\Services\Aeo\ContentMarkdown;
use Flexa\SeoAeo\Support\CacheHeaders;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Agent Readiness: content negotiation for AI crawlers. When `plain_text_export`
 * is on it serves a Markdown variant of any singular post at `?flexa-aeo=md`;
 * when `agent_readiness` is on it advertises that variant on the canonical page
 * via both an HTTP `Link` header and a `<link rel="alternate">` tag, so an agent
 * fetching the HTML page can discover the cheaper-to-ingest representation. No
 * rewrite rule is needed — it rides an existing URL as a query argument.
 */
final class AgentReadiness {
	use SingletonTrait;

	private const QUERY_VAR = 'flexa-aeo';

	public function register(): void {
		$export    = (bool) Settings::get_aeo( 'plain_text_export' );
		$advertise = (bool) Settings::get_aeo( 'agent_readiness' );
		if ( ! $export && ! $advertise ) {
			return;
		}

		add_filter( 'query_vars', [ $this, 'register_query_var' ] );

		if ( $export ) {
			add_action( 'template_redirect', [ $this, 'maybe_render' ], 0 );
		}

		if ( $advertise ) {
			add_action( 'template_redirect', [ $this, 'send_link_header' ], 1 );
			add_action( 'wp_head', [ $this, 'advertise' ], 2 );
		}
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function maybe_render(): void {
		if ( 'md' !== $this->requested_variant() || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Never leak content the visitor can't see anyway.
		if ( post_password_required( $post ) ) {
			return;
		}
		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
			return;
		}

		$markdown = ContentMarkdown::instance()->render( $post );
		$modified = (int) strtotime( $post->post_modified_gmt . ' GMT' );

		if ( CacheHeaders::serve( 'text/markdown', $markdown, max( 0, $modified ) ) ) {
			exit;
		}

		// Raw Markdown output, since escaping would corrupt the document.
		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function send_link_header(): void {
		if ( headers_sent() || ! is_singular() || 'md' === $this->requested_variant() ) {
			return;
		}

		$url = $this->markdown_url();
		if ( '' !== $url ) {
			header( sprintf( 'Link: <%s>; rel="alternate"; type="text/markdown"', $url ), false );
		}
	}

	public function advertise(): void {
		if ( ! is_singular() || 'md' === $this->requested_variant() ) {
			return;
		}

		$url = $this->markdown_url();
		if ( '' !== $url ) {
			printf( "<link rel=\"alternate\" type=\"text/markdown\" href=\"%s\" />\n", esc_url( $url ) );
		}
	}

	private function markdown_url(): string {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$permalink = (string) get_permalink( $post );

		return '' !== $permalink ? add_query_arg( self::QUERY_VAR, 'md', $permalink ) : '';
	}

	private function requested_variant(): string {
		return sanitize_key( (string) get_query_var( self::QUERY_VAR ) );
	}
}
