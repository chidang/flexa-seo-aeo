<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Pure translation layer from another SEO plugin's per-post meta to Flexa's
 * field shape. It takes a raw associative array of a post's legacy meta values
 * (already read from post meta by the {@see Migrator}) and returns Flexa fields
 * ready for {@see \Flexa\SeoAeo\Domain\PostMetaRepository::save()}.
 *
 * Zero WordPress calls — every method is static and side-effect free, so the
 * mapping (robots tri-state parsing, `%%token%%` conversion) is unit-testable
 * and phpstan-clean in isolation.
 */
final class LegacyMapper {
	/**
	 * Supported source plugins → human label.
	 *
	 * @var array<string, string>
	 */
	public const SOURCES = [
		'yoast'    => 'Yoast SEO',
		'rankmath' => 'Rank Math',
		'seopress' => 'SEOPress',
	];

	/**
	 * Flexa string field => legacy meta key, per source. Robots flags are
	 * handled separately in {@see self::robots()}.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const FIELD_KEYS = [
		'yoast'    => [
			'title'               => '_yoast_wpseo_title',
			'description'         => '_yoast_wpseo_metadesc',
			'canonical'           => '_yoast_wpseo_canonical',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'og_image'            => '_yoast_wpseo_opengraph-image',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
			'twitter_image'       => '_yoast_wpseo_twitter-image',
		],
		'rankmath' => [
			'title'               => 'rank_math_title',
			'description'         => 'rank_math_description',
			'canonical'           => 'rank_math_canonical_url',
			'og_title'            => 'rank_math_facebook_title',
			'og_description'      => 'rank_math_facebook_description',
			'og_image'            => 'rank_math_facebook_image',
			'twitter_title'       => 'rank_math_twitter_title',
			'twitter_description' => 'rank_math_twitter_description',
			'twitter_image'       => 'rank_math_twitter_image',
		],
		'seopress' => [
			'title'               => '_seopress_titles_title',
			'description'         => '_seopress_titles_desc',
			'canonical'           => '_seopress_robots_canonical',
			'og_title'            => '_seopress_social_fb_title',
			'og_description'      => '_seopress_social_fb_desc',
			'og_image'            => '_seopress_social_fb_img',
			'twitter_title'       => '_seopress_social_twitter_title',
			'twitter_description' => '_seopress_social_twitter_desc',
			'twitter_image'       => '_seopress_social_twitter_img',
		],
	];

	/**
	 * Extra legacy keys (robots) that must be read for a full migration but
	 * are not simple string fields.
	 *
	 * @var array<string, list<string>>
	 */
	private const ROBOTS_KEYS = [
		'yoast'    => [ '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow', '_yoast_wpseo_meta-robots-adv' ],
		'rankmath' => [ 'rank_math_robots' ],
		'seopress' => [ '_seopress_robots_index', '_seopress_robots_follow' ],
	];

	/**
	 * Legacy token name => Flexa token name. Delimiters are normalised to
	 * `%%name%%` first (Rank Math uses single `%name%`); only names that differ
	 * from Flexa's own vocabulary need an entry — the rest pass through, and an
	 * unknown token resolves to an empty string at render time (harmless).
	 *
	 * @var array<string, string>
	 */
	private const TOKEN_MAP = [
		'name'         => 'author',   // Yoast author display name.
		'excerpt_only' => 'excerpt',
		'seo_title'    => 'title',    // Rank Math self-reference.
		'term'         => 'term_title',
		'category'     => 'term_title',
	];

	/**
	 * Token renames that only hold for one source, checked before
	 * {@see self::TOKEN_MAP}. SEOPress already uses `%%token%%` delimiters but
	 * prefixes most names, so its vocabulary needs its own table.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const SOURCE_TOKEN_MAP = [
		'seopress' => [
			'post_title'         => 'title',
			'sitetitle'          => 'sitename',
			'post_excerpt'       => 'excerpt',
			'post_author'        => 'author',
			'_category_title'    => 'term_title',
			'tag_title'          => 'term_title',
			'search_keywords'    => 'searchphrase',
			'current_pagination' => 'page',
		],
	];

	/**
	 * @return list<string> the source ids this mapper understands
	 */
	public static function source_ids(): array {
		return array_keys( self::SOURCES );
	}

	public static function is_source( string $source ): bool {
		return isset( self::SOURCES[ $source ] );
	}

	public static function label( string $source ): string {
		return self::SOURCES[ $source ] ?? $source;
	}

	/**
	 * Every legacy meta key that must be read for a post before mapping.
	 *
	 * @return list<string>
	 */
	public static function legacy_keys( string $source ): array {
		$fields = array_values( self::FIELD_KEYS[ $source ] ?? [] );
		$robots = self::ROBOTS_KEYS[ $source ] ?? [];

		return array_values( array_unique( array_merge( $fields, $robots ) ) );
	}

	/**
	 * Keys used to detect whether a post carries any data from this source
	 * (an OR/EXISTS proxy — a post with any of these has something to migrate).
	 *
	 * @return list<string>
	 */
	public static function sentinel_keys( string $source ): array {
		$keys   = self::FIELD_KEYS[ $source ] ?? [];
		$fields = [];
		foreach ( [ 'title', 'description', 'canonical', 'og_title' ] as $field ) {
			if ( isset( $keys[ $field ] ) ) {
				$fields[] = $keys[ $field ];
			}
		}
		$robots = self::ROBOTS_KEYS[ $source ] ?? [];

		return array_values( array_unique( array_merge( $fields, $robots ) ) );
	}

	/**
	 * Map one post's raw legacy meta to Flexa fields. Only fields that carry a
	 * value are returned, so the caller can merge without clobbering.
	 *
	 * @param array<string, mixed> $meta legacy_key => raw post-meta value
	 * @return array<string, string|bool>
	 */
	public static function map( string $source, array $meta ): array {
		if ( ! self::is_source( $source ) ) {
			return [];
		}

		$out         = [];
		$verbatim    = [ 'canonical', 'og_image', 'twitter_image' ];
		$string_keys = self::FIELD_KEYS[ $source ] ?? [];

		foreach ( $string_keys as $field => $legacy_key ) {
			$raw = $meta[ $legacy_key ] ?? '';
			if ( ! is_scalar( $raw ) ) {
				continue;
			}
			$value = (string) $raw;
			if ( '' === $value ) {
				continue;
			}

			$out[ $field ] = in_array( $field, $verbatim, true )
				? $value
				: self::convert_tokens( $source, $value );
		}

		[ $noindex, $nofollow ] = self::robots( $source, $meta );
		if ( $noindex ) {
			$out['noindex'] = true;
		}
		if ( $nofollow ) {
			$out['nofollow'] = true;
		}

		return $out;
	}

	/**
	 * Normalise a legacy title/description template to Flexa's `%%token%%`
	 * vocabulary.
	 */
	private static function convert_tokens( string $source, string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		// Rank Math uses single-percent tokens, optionally with (args): fold
		// them to the double-percent form first, dropping any argument.
		if ( 'rankmath' === $source ) {
			$value = (string) preg_replace( '/%([a-z0-9_]+)(?:\([^)]*\))?%/i', '%%$1%%', $value );
		}

		$renames = ( self::SOURCE_TOKEN_MAP[ $source ] ?? [] ) + self::TOKEN_MAP;

		return (string) preg_replace_callback(
			'/%%([a-z0-9_]+)%%/i',
			static function ( array $matches ) use ( $renames ): string {
				$name   = strtolower( $matches[1] );
				$mapped = $renames[ $name ] ?? $name;

				return '' === $mapped ? '' : '%%' . $mapped . '%%';
			},
			$value
		);
	}

	/**
	 * Parse the source's robots settings into [ noindex, nofollow ].
	 *
	 * @param array<string, mixed> $meta
	 * @return array{0: bool, 1: bool}
	 */
	private static function robots( string $source, array $meta ): array {
		if ( 'yoast' === $source ) {
			// Yoast: meta-robots-noindex is a tri-state where '1' = noindex
			// (0 = default, 2 = index); nofollow is a plain '1' flag; the
			// "advanced" field may additionally list noindex/nofollow.
			$noindex_flag  = (string) ( $meta['_yoast_wpseo_meta-robots-noindex'] ?? '' );
			$nofollow_flag = (string) ( $meta['_yoast_wpseo_meta-robots-nofollow'] ?? '' );
			$advanced      = is_scalar( $meta['_yoast_wpseo_meta-robots-adv'] ?? '' )
				? (string) ( $meta['_yoast_wpseo_meta-robots-adv'] ?? '' )
				: '';

			$noindex  = '1' === $noindex_flag || str_contains( $advanced, 'noindex' );
			$nofollow = '1' === $nofollow_flag || str_contains( $advanced, 'nofollow' );

			return [ $noindex, $nofollow ];
		}

		// SEOPress stores each checkbox as 'yes' when ticked, and the boxes
		// are worded negatively: "Do not display this page in search engine
		// results" is `_seopress_robots_index`.
		if ( 'seopress' === $source ) {
			return [
				'yes' === ( $meta['_seopress_robots_index'] ?? '' ),
				'yes' === ( $meta['_seopress_robots_follow'] ?? '' ),
			];
		}

		// Rank Math stores rank_math_robots as an array of directive strings
		// (get_post_meta single already unserialises it).
		$robots = $meta['rank_math_robots'] ?? [];
		if ( is_string( $robots ) && '' !== $robots ) {
			$robots = [ $robots ];
		}
		$list = is_array( $robots ) ? $robots : [];

		return [ in_array( 'noindex', $list, true ), in_array( 'nofollow', $list, true ) ];
	}
}
