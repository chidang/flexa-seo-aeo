<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services;

defined( 'ABSPATH' ) || exit;

/**
 * The fully-resolved `<head>` metadata for the current request. Pure data:
 * {@see Metas} builds it (all tokens already substituted, all values plain
 * text / URLs) and {@see \Flexa\SeoAeo\Actions\Front\Metas} escapes and prints
 * it. Keeping resolution and output apart makes the values unit-testable
 * without a request in flight.
 */
final class ResolvedMeta {
	/**
	 * @param list<string>          $robots  robots directives, e.g. ['index','follow']
	 * @param array<string, string> $og      property => content for Open Graph tags
	 * @param array<string, string> $twitter name => content for Twitter/X Card tags
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $description,
		public readonly string $canonical,
		public readonly array $robots,
		public readonly array $og,
		public readonly array $twitter,
	) {
	}

	public function robots_line(): string {
		return implode( ', ', $this->robots );
	}

	/**
	 * The same directives in the shape core's `wp_robots` filter expects:
	 * a flag becomes `directive => true`, and a valued hint such as
	 * `max-snippet:-1` splits into `'max-snippet' => '-1'`.
	 *
	 * @return array<string, string|true>
	 */
	public function robots_map(): array {
		$map = [];

		foreach ( $this->robots as $directive ) {
			$parts = explode( ':', $directive, 2 );
			$key   = trim( $parts[0] );

			if ( '' === $key ) {
				continue;
			}

			$map[ $key ] = isset( $parts[1] ) ? trim( $parts[1] ) : true;
		}

		return $map;
	}
}
