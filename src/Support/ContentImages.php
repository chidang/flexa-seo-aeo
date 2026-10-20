<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Pulls the image URLs out of a post's stored content, for the image sitemap.
 *
 * It reads the raw `post_content` rather than the rendered page. A sub-sitemap
 * lists up to 2000 posts, and running `the_content` filters over each of them
 * would render 2000 pages to answer one request. Every core block that shows
 * an image (Image, Gallery, Cover, Media & Text) saves a real `<img>` tag in
 * its markup, as does the classic editor, so the stored HTML already carries
 * what the visitor sees. What it misses are images a shortcode or a dynamic
 * block only produces at render time; `flexa_seo_aeo/sitemap/post_images` is
 * the seam for those.
 *
 * Pure: no WordPress calls, so the parsing is testable on its own.
 */
final class ContentImages {
	/**
	 * Find the `src` of every `<img>` in the HTML, as absolute URLs, in the
	 * order they appear and without duplicates.
	 *
	 * A root-relative `/wp-content/…` path is resolved against the site's
	 * origin and a protocol-relative `//cdn…` one takes the site's scheme. A
	 * `data:` URI, or a path relative to the current directory, which has no
	 * single meaning in a sitemap, is left out.
	 *
	 * @param string $html     Stored post content.
	 * @param string $home_url The site's home URL, for resolving relative paths.
	 * @return list<string>
	 */
	public static function extract( string $html, string $home_url ): array {
		if ( '' === $html || false === stripos( $html, '<img' ) ) {
			return [];
		}

		if ( ! preg_match_all( '~<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1~is', $html, $matches ) ) {
			return [];
		}

		$origin = preg_match( '~^(https?)://[^/?#]+~i', $home_url, $m ) ? $m[0] : '';
		$scheme = '' !== $origin ? strtolower( $m[1] ) : 'https';
		$urls   = [];

		foreach ( $matches[2] as $raw ) {
			$url = trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			if ( str_starts_with( $url, '//' ) ) {
				$url = $scheme . ':' . $url;
			} elseif ( str_starts_with( $url, '/' ) ) {
				if ( '' === $origin ) {
					continue;
				}
				$url = $origin . $url;
			}

			if ( ! preg_match( '~^https?://[^/?#\s]+~i', $url ) ) {
				continue;
			}

			$urls[ $url ] = true;
		}

		return array_keys( $urls );
	}
}
