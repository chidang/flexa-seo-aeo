<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Cache validators for the plugin's own public documents: `/llms.txt`, the XML
 * sitemaps and the Markdown variant of a post.
 *
 * All three are machine-fetched, often by crawlers that re-request the same URL
 * on a schedule, and all three were answering 200 with a full body every time.
 * The validator is a hash of the body the caller has already built, so it can
 * never disagree with what would be sent; `Last-Modified` is advisory on top of
 * it, for clients and CDNs that prefer a date.
 */
final class CacheHeaders {
	/**
	 * How long a crawler or CDN may reuse the response without revalidating.
	 */
	private const MAX_AGE = HOUR_IN_SECONDS;

	/**
	 * Send the headers for a public document and report whether the client
	 * already has it.
	 *
	 * @param string $content_type Media type, without the charset.
	 * @param string $body         The exact bytes that would be sent.
	 * @param int    $modified     Unix timestamp of the newest thing in the body,
	 *                             or 0 when there is nothing meaningful to date.
	 * @return bool True when a 304 was sent, so the caller must exit without
	 *              printing the body.
	 */
	public static function serve( string $content_type, string $body, int $modified ): bool {
		if ( headers_sent() ) {
			return false;
		}

		$etag = '"' . md5( $body ) . '"';

		/**
		 * Filters how long the plugin's public documents may be cached, in
		 * seconds. Return 0 to force revalidation on every request.
		 *
		 * @param int    $max_age      Lifetime in seconds.
		 * @param string $content_type The document's media type.
		 */
		$max_age = (int) apply_filters( 'flexa_seo_aeo/cache_headers/max_age', self::MAX_AGE, $content_type );

		header( 'Content-Type: ' . $content_type . '; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'ETag: ' . $etag );
		header(
			$max_age > 0
				? 'Cache-Control: public, max-age=' . $max_age
				: 'Cache-Control: public, max-age=0, must-revalidate'
		);

		if ( $modified > 0 ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );
		}

		if ( ! self::client_has( $etag, $modified ) ) {
			return false;
		}

		status_header( 304 );

		return true;
	}

	/**
	 * The newest date in a set of sitemap entries, as a Unix timestamp.
	 *
	 * @param list<array{loc: string, lastmod: string}> $entries
	 */
	public static function newest_lastmod( array $entries ): int {
		$newest = 0;
		foreach ( $entries as $entry ) {
			if ( '' === $entry['lastmod'] ) {
				continue;
			}

			$stamp = strtotime( $entry['lastmod'] );
			if ( false !== $stamp && $stamp > $newest ) {
				$newest = $stamp;
			}
		}

		return $newest;
	}

	/**
	 * Whether the request's validators already match what we would send.
	 *
	 * `If-None-Match` wins outright when present, per RFC 7232 section 3.3: a
	 * date is the weaker signal and the ETag is exact.
	 */
	private static function client_has( string $etag, int $modified ): bool {
		$sent_etag = self::server_value( 'HTTP_IF_NONE_MATCH' );
		if ( '' !== $sent_etag ) {
			foreach ( explode( ',', $sent_etag ) as $candidate ) {
				$candidate = trim( $candidate );
				if ( '*' === $candidate ) {
					return true;
				}

				// A weak validator is still a match for our purposes: the body
				// we would send is byte-identical to the one that produced it.
				if ( ltrim( $candidate, 'W/' ) === ltrim( $etag, 'W/' ) ) {
					return true;
				}
			}

			return false;
		}

		if ( 0 === $modified ) {
			return false;
		}

		$since = self::server_value( 'HTTP_IF_MODIFIED_SINCE' );
		if ( '' === $since ) {
			return false;
		}

		$stamp = strtotime( $since );

		return false !== $stamp && $modified <= $stamp;
	}

	private static function server_value( string $key ): string {
		if ( ! isset( $_SERVER[ $key ] ) ) {
			return '';
		}

		return trim( sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) );
	}
}
