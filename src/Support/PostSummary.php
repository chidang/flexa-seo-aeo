<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a post into the one-line plain-text summary every public surface
 * quotes: the meta description, `og:description`, `twitter:description`, the
 * JSON-LD description and the `/llms.txt` note.
 *
 * It lives here because those five used to be built by two near-identical
 * private methods in two services, which is how they came to disagree about
 * password-protected posts: one of them leaked the body, the other did not.
 */
final class PostSummary {
	/**
	 * Block-level tags whose closing form is a word boundary.
	 * `wp_strip_all_tags()` removes them without leaving whitespace, so
	 * "<p>What is QA?</p><p>It is testing.</p>" collapses to
	 * "What is QA?It is testing." unless they are turned into spaces first.
	 */
	private const BLOCK_CLOSE = '~</(?:p|div|h[1-6]|li|dd|dt|td|th|tr|blockquote|section|article|aside|header|footer|figure|figcaption|pre|ul|ol|dl|table)\s*>|<(?:br|hr)\s*/?>~i';

	/**
	 * Anything shaped like a shortcode tag.
	 *
	 * `strip_shortcodes()` only removes the ones registered right now, and a
	 * description is built on requests where the plugin that owns the tag may
	 * not have loaded, or may have been deactivated with its shortcodes still
	 * sitting in old posts. What came out then was the raw source:
	 * `[contact-form-7 id="12"]` published as the meta description.
	 *
	 * The tag must open with a letter and run to at least two characters, so a
	 * `[1]` footnote and an `a[i]` index survive. A bracketed word in prose,
	 * `[sic]`, does not: there is nothing in the syntax to tell it apart from a
	 * tag, and Yoast and Rank Math make the same trade with a broader pattern.
	 */
	private const SHORTCODE_LIKE = '~\[/?[a-z][a-z0-9_-]+[^\]]*\]~i';

	/**
	 * The text to quote for a post, or an empty string when there is nothing
	 * safe to quote.
	 *
	 * A password-protected post returns an empty string rather than falling back
	 * to its manual excerpt: an author who put the secret in the body may well
	 * have put it in the excerpt too, and no description at all is a better
	 * outcome than a description that defeats the password.
	 *
	 * @param int $limit Maximum characters before truncating on a word boundary.
	 */
	public static function text( WP_Post $post, int $limit ): string {
		if ( post_password_required( $post ) ) {
			return '';
		}

		$raw = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;

		return self::truncate( self::condense( $raw ), $limit );
	}

	/**
	 * Squash arbitrary content down to a single line of plain text.
	 */
	public static function condense( string $raw ): string {
		$raw = strip_shortcodes( $raw );
		$raw = (string) preg_replace( self::SHORTCODE_LIKE, ' ', $raw );
		$raw = (string) preg_replace( self::BLOCK_CLOSE, ' ', $raw );
		$raw = wp_strip_all_tags( $raw );
		$raw = (string) preg_replace( '/\s+/', ' ', $raw );

		return trim( $raw );
	}

	/**
	 * Cut to `$limit` characters on the nearest earlier word boundary, with an
	 * ellipsis. Shorter text is returned untouched.
	 */
	public static function truncate( string $text, int $limit ): string {
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $limit );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut ) . '…';
	}
}
