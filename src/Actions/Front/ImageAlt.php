<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Front;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Fills in missing `alt` text on images in post content at display time.
 *
 * An image with no alt is invisible to screen readers and to image search, and
 * on a large site the gaps are never going to be filled by hand. The fallback
 * order is deliberately conservative: the attachment's own alt field, then its
 * title, then the post title. The stored content is never modified, so turning
 * the setting off restores exactly what the author wrote.
 *
 * An `alt=""` that is already present is left alone. Empty alt is the correct,
 * documented way to mark a decorative image, and overwriting it would make a
 * page noisier for screen-reader users, not better.
 */
final class ImageAlt {
	use SingletonTrait;

	public function register(): void {
		add_action( 'wp', [ $this, 'maybe_hook' ] );
	}

	public function maybe_hook(): void {
		if ( ! (bool) Settings::get( 'image_seo_alt' ) ) {
			return;
		}

		// Priority 12, after LinkAttributes, so both see final markup.
		add_filter( 'the_content', [ $this, 'filter_content' ], 12 );
	}

	public function filter_content( string $content ): string {
		if ( '' === trim( $content ) || ! str_contains( $content, '<img' ) ) {
			return $content;
		}

		if ( ! preg_match_all( '/<img\s[^>]*>/i', $content, $matches ) ) {
			return $content;
		}

		$fallback = $this->post_title();

		foreach ( array_unique( $matches[0] ) as $tag ) {
			$rewritten = $this->rewrite_image( $tag, $fallback );
			if ( $rewritten !== $tag ) {
				$content = str_replace( $tag, $rewritten, $content );
			}
		}

		return $content;
	}

	/**
	 * Add an `alt` to one `<img>` tag, or hand it back untouched.
	 */
	private function rewrite_image( string $tag, string $fallback ): string {
		// Present already, including a deliberate alt="".
		if ( preg_match( '/\salt\s*=\s*("|\')/i', $tag ) ) {
			return $tag;
		}

		$alt = $this->alt_for( $tag, $fallback );
		if ( '' === $alt ) {
			return $tag;
		}

		return (string) preg_replace( '/^<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', $tag, 1 );
	}

	/**
	 * The attachment's alt, then its title, then the post title.
	 */
	private function alt_for( string $tag, string $fallback ): string {
		$attachment_id = $this->attachment_id( $tag );

		if ( $attachment_id > 0 ) {
			$meta = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( is_string( $meta ) && '' !== trim( $meta ) ) {
				return trim( $meta );
			}

			$title = get_the_title( $attachment_id );
			if ( '' !== trim( $title ) ) {
				return trim( $title );
			}
		}

		return $fallback;
	}

	/**
	 * The attachment id from the `wp-image-<id>` class core puts on inserted
	 * images. Zero when the image was not inserted by the editor.
	 */
	private function attachment_id( string $tag ): int {
		return preg_match( '/wp-image-(\d+)/i', $tag, $match ) ? (int) $match[1] : 0;
	}

	private function post_title(): string {
		$post = get_post();

		return $post instanceof WP_Post ? trim( wp_strip_all_tags( get_the_title( $post ) ) ) : '';
	}
}
