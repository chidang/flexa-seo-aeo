<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Migration;

use Flexa\SeoAeo\Domain\PostMeta;
use Flexa\SeoAeo\Domain\PostMetaRepository;
use Flexa\SeoAeo\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk-imports per-post SEO meta from another plugin (Yoast SEO, Rank Math,
 * SEOPress) into Flexa's `_flexa_seo_aeo_*` keys. This is the write-side counterpart to
 * the read fallback in {@see PostMetaRepository}: the fallback keeps output
 * working while both plugins coexist; this copies the data across so the site
 * can deactivate the old plugin.
 *
 * Work is done in offset-paged batches (the REST/UI loop calls one batch at a
 * time) so a large site never trips a request timeout. Because each batch reads
 * the *legacy* keys and writes *Flexa* keys, the detection query is stable
 * across batches — the found-post count does not shift as we migrate.
 */
final class Migrator {
	use SingletonTrait;

	private const MAX_BATCH     = 100;
	private const DEFAULT_BATCH = 50;

	/**
	 * Availability + counts for every supported source, for the UI to render.
	 *
	 * @return list<array{id: string, label: string, count: int, available: bool}>
	 */
	public function sources(): array {
		$out = [];
		foreach ( LegacyMapper::source_ids() as $source ) {
			$count = $this->count( $source );
			$out[] = [
				'id'        => $source,
				'label'     => LegacyMapper::label( $source ),
				'count'     => $count,
				'available' => $count > 0,
			];
		}

		return $out;
	}

	/**
	 * Migrate one batch of posts from a source.
	 *
	 * @return array{source: string, total: int, processed: int, migrated: int, skipped: int, next_offset: int, done: bool}
	 */
	public function migrate( string $source, bool $overwrite, int $offset, int $batch ): array {
		$offset = max( 0, $offset );
		$batch  = $batch > 0 ? min( $batch, self::MAX_BATCH ) : self::DEFAULT_BATCH;

		$result = [
			'source'      => $source,
			'total'       => 0,
			'processed'   => 0,
			'migrated'    => 0,
			'skipped'     => 0,
			'next_offset' => $offset,
			'done'        => true,
		];

		if ( ! LegacyMapper::is_source( $source ) ) {
			return $result;
		}

		$ids   = $this->detect_ids( $source, $offset, $batch );
		$total = $this->count( $source );
		$keys  = LegacyMapper::legacy_keys( $source );
		$repo  = PostMetaRepository::instance();

		$migrated = 0;
		$skipped  = 0;

		foreach ( $ids as $post_id ) {
			$raw = [];
			foreach ( $keys as $legacy_key ) {
				$raw[ $legacy_key ] = get_post_meta( $post_id, $legacy_key, true );
			}

			$mapped = LegacyMapper::map( $source, $raw );
			if ( [] === $mapped ) {
				++$skipped;
				continue;
			}

			$payload = $overwrite ? $mapped : $this->only_missing( $post_id, $mapped );
			if ( [] === $payload ) {
				++$skipped;
				continue;
			}

			$repo->save( $post_id, $payload );
			++$migrated;
		}

		$processed = count( $ids );
		$next      = $offset + $processed;
		$done      = 0 === $processed || $next >= $total;

		$result = [
			'source'      => $source,
			'total'       => $total,
			'processed'   => $processed,
			'migrated'    => $migrated,
			'skipped'     => $skipped,
			'next_offset' => $next,
			'done'        => $done,
		];

		/**
		 * Fires after each migration batch completes.
		 *
		 * @param string                                                                                              $source
		 * @param array{source: string, total: int, processed: int, migrated: int, skipped: int, next_offset: int, done: bool} $result
		 */
		do_action( 'flexa_seo_aeo/migration/batch', $source, $result );

		return $result;
	}

	/**
	 * Drop fields the post already has a Flexa value for, so a non-overwrite
	 * run never clobbers manual edits.
	 *
	 * @param array<string, string|bool> $mapped
	 * @return array<string, string|bool>
	 */
	private function only_missing( int $post_id, array $mapped ): array {
		$out = [];
		foreach ( $mapped as $field => $value ) {
			$meta_key = PostMeta::META_KEYS[ $field ] ?? null;
			if ( null === $meta_key ) {
				continue;
			}

			$existing = get_post_meta( $post_id, $meta_key, true );
			if ( '' === $existing || null === $existing || false === $existing ) {
				$out[ $field ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Count posts carrying any of the source's sentinel meta keys.
	 *
	 * A single indexed scan of `postmeta.meta_key` with one JOIN and `DISTINCT`.
	 * The previous WP_Query approach OR'd an `EXISTS` clause per key, which
	 * WP_Query expands into one self-JOIN on `wp_postmeta` per key; combined with
	 * `SQL_CALC_FOUND_ROWS` + `GROUP BY`, that produced a Cartesian blow-up that
	 * could pin a MySQL/PHP-FPM worker for many minutes on a large site (and, with
	 * a small pool, take the whole site down with 504s). This form never joins the
	 * meta table more than once.
	 */
	private function count( string $source ): int {
		$keys  = LegacyMapper::sentinel_keys( $source );
		$types = $this->post_types();
		if ( [] === $keys || [] === $types ) {
			return 0;
		}

		global $wpdb;
		$key_ph  = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$type_ph = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		$sql = $wpdb->prepare(
			"SELECT COUNT( DISTINCT pm.post_id )
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key IN ( {$key_ph} )
			 AND p.post_type IN ( {$type_ph} )
			 AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
			...array_merge( $keys, $types )
		);

		$count = (int) $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery

		return $count;
	}

	/**
	 * The ascending post IDs (one batch) that carry data from the source. Same
	 * single-JOIN `meta_key IN (…)` scan as {@see self::count()}, paged with
	 * LIMIT/OFFSET so a batch is always bounded.
	 *
	 * @return list<int>
	 */
	private function detect_ids( string $source, int $offset, int $limit ): array {
		$keys  = LegacyMapper::sentinel_keys( $source );
		$types = $this->post_types();
		if ( [] === $keys || [] === $types ) {
			return [];
		}

		global $wpdb;
		$key_ph  = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$type_ph = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$args    = array_merge( $keys, $types, [ $limit, $offset ] );

		// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key IN ( {$key_ph} )
			 AND p.post_type IN ( {$type_ph} )
			 AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			 ORDER BY pm.post_id ASC
			 LIMIT %d OFFSET %d",
			...$args
		);

		$ids = $wpdb->get_col( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery

		return array_map( 'intval', $ids );
	}

	/**
	 * Public post types are where SEO meta lives.
	 *
	 * @return list<string>
	 */
	private function post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );

		return array_values( $types );
	}
}
