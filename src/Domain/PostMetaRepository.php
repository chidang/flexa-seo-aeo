<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Domain;

use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {@see PostMeta} to WordPress post meta. Also exposes the
 * fields to the block editor via register_post_meta() and provides a
 * best-effort migration fallback: if this plugin has no value for a field yet,
 * an equivalent key from Yoast / Rank Math / SEOPress is read instead, so a
 * site that switches to Flexa SEO keeps its existing SEO output.
 */
final class PostMetaRepository {
	use SingletonTrait;

	/**
	 * Legacy meta keys read as a fallback, per field, in priority order.
	 *
	 * @var array<string, list<string>>
	 */
	private const MIGRATION_KEYS = [
		'title'          => [ '_yoast_wpseo_title', 'rank_math_title', '_seopress_titles_title' ],
		'description'    => [ '_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc' ],
		'canonical'      => [ '_yoast_wpseo_canonical', 'rank_math_canonical_url', '_seopress_robots_canonical' ],
		'og_title'       => [ '_yoast_wpseo_opengraph-title', 'rank_math_facebook_title', '_seopress_social_fb_title' ],
		'og_description' => [ '_yoast_wpseo_opengraph-description', 'rank_math_facebook_description', '_seopress_social_fb_desc' ],
		'og_image'       => [ '_yoast_wpseo_opengraph-image', 'rank_math_facebook_image', '_seopress_social_fb_img' ],
	];

	/**
	 * Fields stored as booleans. False means "no override", which is written by
	 * removing the row, never by storing an empty one.
	 *
	 * @var list<string>
	 */
	private const BOOL_FIELDS = [ 'noindex', 'nofollow' ];

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_filter( 'update_post_metadata', [ $this, 'skip_falsy_flag' ], 10, 4 );
	}

	/**
	 * Keep an unchecked flag out of the database entirely.
	 *
	 * {@see self::save()} already deletes rather than stores a false, but the
	 * block editor writes registered meta straight through the REST meta
	 * endpoint, where core stores a false as an empty row. Those rows are not
	 * harmless: anything asking "which posts are flagged?" then has to tell a
	 * row meaning no from a row meaning yes, which is what pushed the sitemap
	 * query into joining postmeta twice (see Services\Sitemap::noindexed_ids()).
	 * Dropping the row on the way in keeps "a row exists" and "the flag is set"
	 * the same statement.
	 *
	 * @param  mixed  $check      Short-circuit value; null means "carry on".
	 * @param  int    $post_id    Post being written to.
	 * @param  string $meta_key   Meta key being written.
	 * @param  mixed  $meta_value Value being written.
	 * @return mixed  True once handled here, otherwise $check untouched.
	 */
	public function skip_falsy_flag( mixed $check, int $post_id, string $meta_key, mixed $meta_value ): mixed {
		if ( null !== $check || $meta_value ) {
			return $check;
		}

		foreach ( self::BOOL_FIELDS as $field ) {
			if ( PostMeta::META_KEYS[ $field ] === $meta_key ) {
				delete_post_meta( $post_id, $meta_key );

				return true;
			}
		}

		return $check;
	}

	/**
	 * Expose every field to the REST API / block editor. String fields are
	 * `string`; noindex/nofollow are `boolean`. Editing is gated per post.
	 */
	public function register_post_meta(): void {
		$auth = static function ( bool $allowed, string $meta_key, int $post_id ): bool {
			unset( $allowed, $meta_key );

			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( PostMeta::META_KEYS as $field => $meta_key ) {
			$is_bool = in_array( $field, self::BOOL_FIELDS, true );

			register_post_meta(
				'',
				$meta_key,
				[
					'type'              => $is_bool ? 'boolean' : 'string',
					'single'            => true,
					'default'           => $is_bool ? false : '',
					'show_in_rest'      => true,
					'sanitize_callback' => $is_bool
						? null
						: static fn( $value ): string => sanitize_text_field( (string) $value ),
					'auth_callback'     => $auth,
				]
			);
		}
	}

	public function get( int $post_id ): PostMeta {
		$data = [];

		foreach ( PostMeta::META_KEYS as $field => $meta_key ) {
			$stored = get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $stored && null !== $stored && false !== $stored ) {
				$data[ $field ] = $stored;
				continue;
			}

			$migrated = $this->read_migration_fallback( $post_id, $field );
			if ( null !== $migrated ) {
				$data[ $field ] = $migrated;
			}
		}

		return PostMeta::from_array( $data );
	}

	/**
	 * Persist a (possibly partial) payload. Empty string / false deletes the
	 * row so the post cleanly reverts to computed defaults rather than storing
	 * an empty override.
	 *
	 * @param array<string, mixed> $data
	 */
	public function save( int $post_id, array $data ): PostMeta {
		foreach ( PostMeta::META_KEYS as $field => $meta_key ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			$value = $this->sanitize_field( $field, $data[ $field ] );

			if ( '' === $value || false === $value ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}

		return $this->get( $post_id );
	}

	/**
	 * @return string|bool sanitized value; '' or false signals "clear it"
	 */
	private function sanitize_field( string $field, mixed $value ): string|bool {
		if ( in_array( $field, self::BOOL_FIELDS, true ) ) {
			return ! empty( $value ) && ! in_array( $value, [ '0', 'false', 'off', 'no' ], true );
		}

		if ( 'canonical' === $field || 'og_image' === $field || 'twitter_image' === $field ) {
			return esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ) );
		}

		if ( 'description' === $field || 'og_description' === $field || 'twitter_description' === $field ) {
			return sanitize_textarea_field( (string) ( is_scalar( $value ) ? $value : '' ) );
		}

		return sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Read the first non-empty legacy value for a field, if any.
	 */
	private function read_migration_fallback( int $post_id, string $field ): ?string {
		if ( ! apply_filters( 'flexa_seo_aeo/post_meta/read_legacy', true, $field, $post_id ) ) {
			return null;
		}

		foreach ( self::MIGRATION_KEYS[ $field ] ?? [] as $legacy_key ) {
			$value = get_post_meta( $post_id, $legacy_key, true );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return null;
	}

	/**
	 * Convenience: resolve the WP_Post currently being queried, or null.
	 */
	public function current_post(): ?WP_Post {
		$object = get_queried_object();

		return $object instanceof WP_Post ? $object : null;
	}
}
