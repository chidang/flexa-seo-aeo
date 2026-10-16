<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services;

use Flexa\SeoAeo\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces `%%token%%` placeholders in title / description templates. Global
 * tokens (site name, tagline, separator, year, …) are resolved here; contextual
 * tokens (post title, term title, search phrase, …) are supplied by the caller
 * via $context and win over the globals.
 */
final class Variables {
	/**
	 * Stands in for a token that resolved to nothing, until the separator
	 * beside it has been cleared away. A private-use codepoint, so it cannot
	 * collide with anything a template or a post title contains.
	 */
	private const HOLE = "\u{E000}";

	/**
	 * @param array<string, string> $context contextual token => value overrides
	 */
	public static function replace( string $subject, array $context = [] ): string {
		if ( '' === $subject || ! str_contains( $subject, '%%' ) ) {
			return trim( $subject );
		}

		$tokens = array_merge( self::global_tokens(), $context );

		/**
		 * Filters the resolved token table before substitution so extensions can
		 * add their own `%%token%%` values.
		 *
		 * @param array<string, string> $tokens
		 * @param string                $subject
		 */
		$tokens = apply_filters( 'flexa_seo_aeo/variables/tokens', $tokens, $subject );

		// A token that resolves to nothing leaves a hole the surrounding
		// separators have to close over, and marking it is the only way to do
		// that without touching the text a token brought with it: a post
		// genuinely titled "A -- B" has to survive a site whose separator is a
		// hyphen.
		$replaced = preg_replace_callback(
			'/%%([a-z0-9_]+)%%/',
			static function ( array $m ) use ( $tokens ): string {
				$value = $tokens[ $m[1] ] ?? '';

				return '' !== $value ? $value : self::HOLE;
			},
			$subject
		);

		$clean  = (string) $replaced;
		$sep    = trim( (string) Settings::get( 'separator' ) );
		$quoted = preg_quote( $sep, '/' );

		if ( '' !== $sep ) {
			// The separator in front of a hole goes with it. That is where a
			// template puts it, and it is what used to leave "Site - - Page 2"
			// once a page suffix moved an empty tagline into the middle of the
			// string instead of the end.
			$clean = (string) preg_replace( '/\s*' . $quoted . '\s*' . self::HOLE . '/u', ' ', $clean );
		}

		$clean = str_replace( self::HOLE, '', $clean );

		// Collapse the whitespace left behind by empty tokens (e.g. a missing
		// tagline around a separator) so we never emit "Title  -  ".
		$clean = (string) preg_replace( '/\s{2,}/', ' ', $clean );
		$clean = trim( $clean );

		// Trim a separator left dangling at either end by a hole that had
		// nothing in front of it.
		if ( '' !== $sep ) {
			$clean = (string) preg_replace( '/^\s*' . $quoted . '\s*|\s*' . $quoted . '\s*$/u', '', $clean );
		}

		return trim( $clean );
	}

	/**
	 * @return array<string, string>
	 */
	private static function global_tokens(): array {
		$paged = (int) get_query_var( 'paged' );
		if ( 0 === $paged ) {
			$paged = (int) get_query_var( 'page' );
		}

		return [
			'sitename'     => (string) get_bloginfo( 'name' ),
			'sitedesc'     => (string) get_bloginfo( 'description' ),
			'tagline'      => (string) get_bloginfo( 'description' ),
			'sep'          => (string) Settings::get( 'separator' ),
			'currentyear'  => gmdate( 'Y' ),
			'currentmonth' => gmdate( 'F' ),
			'currentdate'  => gmdate( 'j F Y' ),
			'searchphrase' => (string) get_search_query(),
			'page'         => $paged > 1 ? (string) $paged : '',
		];
	}
}
