<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

use WP_Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * The post type behind the post type archive being viewed.
 *
 * `get_queried_object()` is the obvious source and usually right, but
 * WooCommerce swaps it for the Shop page on `/shop/` while still reporting
 * `is_post_type_archive()`, so a plain `instanceof WP_Post_Type` check comes
 * up empty on the busiest archive a store has. The query var is what survives
 * that, with the queried object as the fallback.
 */
final class ArchiveType {
	/**
	 * The post type object for the current archive, or null when this view is
	 * not a post type archive.
	 */
	public static function current(): ?WP_Post_Type {
		if ( ! is_post_type_archive() ) {
			return null;
		}

		$name = get_query_var( 'post_type' );
		if ( is_array( $name ) ) {
			$name = reset( $name );
		}

		if ( is_string( $name ) && '' !== $name ) {
			$type = get_post_type_object( $name );
			if ( $type instanceof WP_Post_Type ) {
				return $type;
			}
		}

		$queried = get_queried_object();

		return $queried instanceof WP_Post_Type ? $queried : null;
	}
}
