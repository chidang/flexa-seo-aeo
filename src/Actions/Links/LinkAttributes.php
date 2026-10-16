<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Links;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Adds rel/target attributes to links in post content at display time. Mirrors
 * the "Links" options every SEO plugin ships (Yoast, Rank Math, AIOSEO):
 *
 *  - Nofollow External Links      → rel="nofollow" on outbound links
 *  - Nofollow Image File Links    → rel="nofollow" on links to external images
 *  - Open External Links in a new tab → target="_blank" (+ rel="noopener")
 *
 * Applied through the `the_content` filter only, so the stored post content is
 * never modified — the attributes are injected as the content is rendered.
 */
final class LinkAttributes {
	use SingletonTrait;

	private const IMAGE_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif' ];

	private bool $nofollow_links  = false;
	private bool $nofollow_images = false;
	private bool $new_window      = false;

	public function register(): void {
		// Resolve on `wp` (after the query is set up, before content renders) so a
		// single settings read is shared across every the_content pass on the page.
		add_action( 'wp', [ $this, 'maybe_hook' ] );
	}

	public function maybe_hook(): void {
		$this->nofollow_links  = (bool) Settings::get( 'nofollow_external_links' );
		$this->nofollow_images = (bool) Settings::get( 'nofollow_image_links' );
		$this->new_window      = (bool) Settings::get( 'new_window_external_links' );

		if ( ! $this->nofollow_links && ! $this->nofollow_images && ! $this->new_window ) {
			return;
		}

		// Priority 11 so it runs after wpautop/shortcode expansion have produced
		// the final markup.
		add_filter( 'the_content', [ $this, 'filter_content' ], 11 );
	}

	public function filter_content( string $content ): string {
		if ( '' === trim( $content ) || ! str_contains( $content, '<a ' ) ) {
			return $content;
		}

		// Ignore anchors inside <script>/<style> so we never touch inline code.
		$scrubbed = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
		if ( ! preg_match_all( '/<a\s[^>]+>/i', $scrubbed, $matches ) ) {
			return $content;
		}

		foreach ( array_unique( $matches[0] ) as $tag ) {
			$rewritten = $this->rewrite_anchor( $tag );
			if ( $rewritten !== $tag ) {
				$content = str_replace( $tag, $rewritten, $content );
			}
		}

		return $content;
	}

	private function rewrite_anchor( string $tag ): string {
		if ( ! preg_match( '/href\s*=\s*("|\')(.*?)\1/i', $tag, $href_match ) ) {
			return $tag;
		}

		$href = trim( html_entity_decode( $href_match[2] ) );
		if ( '' === $href || ! $this->is_external( $href ) ) {
			return $tag;
		}

		$rel_tokens = [];
		if ( $this->nofollow_links || ( $this->nofollow_images && $this->is_image_url( $href ) ) ) {
			$rel_tokens[] = 'nofollow';
		}

		$new_tag = $tag;

		if ( $this->new_window ) {
			$new_tag      = $this->set_attribute( $new_tag, 'target', '_blank' );
			$rel_tokens[] = 'noopener';
		}

		if ( ! empty( $rel_tokens ) ) {
			$new_tag = $this->merge_rel( $new_tag, $rel_tokens );
		}

		return $new_tag;
	}

	/**
	 * Merge new rel tokens into an existing (or absent) rel attribute without
	 * duplicating tokens already present.
	 *
	 * @param list<string> $tokens
	 */
	private function merge_rel( string $tag, array $tokens ): string {
		if ( preg_match( '/rel\s*=\s*("|\')(.*?)\1/i', $tag, $rel_match ) ) {
			// preg_split() returns false only on a malformed pattern, which
			// this one is not, but the signature says it can.
			$existing = preg_split( '/\s+/', trim( $rel_match[2] ) );
			$merged   = array_values(
				array_unique( array_filter( array_merge( is_array( $existing ) ? $existing : [], $tokens ) ) )
			);

			return str_replace( $rel_match[0], 'rel="' . esc_attr( implode( ' ', $merged ) ) . '"', $tag );
		}

		$rel = esc_attr( implode( ' ', array_values( array_unique( $tokens ) ) ) );

		return preg_replace( '/^<a\b/i', '<a rel="' . $rel . '"', $tag, 1 ) ?? $tag;
	}

	/**
	 * Set (or replace) a single attribute on the opening anchor tag.
	 */
	private function set_attribute( string $tag, string $name, string $value ): string {
		$pattern = '/\s' . preg_quote( $name, '/' ) . '\s*=\s*("|\').*?\1/i';
		if ( preg_match( $pattern, $tag ) ) {
			return (string) preg_replace( $pattern, ' ' . $name . '="' . esc_attr( $value ) . '"', $tag, 1 );
		}

		return preg_replace( '/^<a\b/i', '<a ' . $name . '="' . esc_attr( $value ) . '"', $tag, 1 ) ?? $tag;
	}

	/**
	 * A link is external when it has a host that differs from the site host.
	 * Anchors (#), relative, and mailto:/tel: links are treated as internal.
	 */
	private function is_external( string $url ): bool {
		if ( '' === $url || str_starts_with( $url, '#' ) || str_starts_with( $url, '/' ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			// mailto:, tel:, javascript:, or a malformed URL — never "external".
			return false;
		}

		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = strtolower( preg_replace( '/^www\./', '', $host ) ?? $host );
		$home = strtolower( preg_replace( '/^www\./', '', is_string( $home ) ? $home : '' ) ?? '' );

		return $host !== $home;
	}

	private function is_image_url( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, self::IMAGE_EXTENSIONS, true );
	}
}
