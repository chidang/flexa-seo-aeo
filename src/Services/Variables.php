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

		$replaced = preg_replace_callback(
			'/%%([a-z0-9_]+)%%/',
			static fn( array $m ): string => $tokens[ $m[1] ] ?? '',
			$subject
		);

		// Collapse the whitespace left behind by empty tokens (e.g. a missing
		// tagline around a separator) so we never emit "Title  -  ".
		$clean = preg_replace( '/\s{2,}/', ' ', (string) $replaced );
		$clean = trim( (string) $clean );

		$sep = trim( (string) Settings::get( 'separator' ) );
		if ( '' !== $sep ) {
			$quoted = preg_quote( $sep, '/' );

			// An empty token with a separator on either side leaves the two
			// adjacent: "Site - - Page 2" on a home template whose tagline is
			// blank. Collapse a run down to one. The lookbehind keeps the rule
			// off a separator character used inside a word, so a title like
			// "Re--run" survives a site whose separator is a hyphen.
			$clean = (string) preg_replace( '/(?<=^|\s)(?:' . $quoted . '\s*){2,}/u', $sep . ' ', $clean );

			// Trim a dangling leading/trailing separator left by an empty token.
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
