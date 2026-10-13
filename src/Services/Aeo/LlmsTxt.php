<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Aeo;

use Flexa\SeoAeo\Services\Sitemap as SitemapService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;
use WP_Post_Type;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Pure builder for the `/llms.txt` document (see llmstxt.org): a curated,
 * markdown-flavoured map of the site that answer engines and coding agents read
 * to understand what's here without crawling every page. It reuses the same
 * enabled-post-type set (and per-post `noindex` exclusion) as the sitemap so the
 * two never advertise a different surface. Returns a string; no I/O, no headers.
 */
final class LlmsTxt {
	use SingletonTrait;

	/**
	 * Assemble the full llms.txt document from the AEO settings + content index.
	 */
	public function document(): string {
		$name = trim( (string) Settings::get_aeo( 'site_name' ) );
		if ( '' === $name ) {
			$name = (string) get_bloginfo( 'name' );
		}

		$summary = trim( (string) Settings::get_aeo( 'site_description' ) );
		if ( '' === $summary ) {
			$summary = trim( (string) get_bloginfo( 'description' ) );
		}

		$lines   = [];
		$lines[] = '# ' . $this->one_line( $name );
		$lines[] = '';
		if ( '' !== $summary ) {
			$lines[] = '> ' . $this->one_line( $summary );
			$lines[] = '';
		}
		$lines[] = home_url( '/' );
		$lines[] = '';

		foreach ( $this->sections() as $section ) {
			$lines[] = '## ' . $section['label'];
			foreach ( $section['items'] as $item ) {
				$note    = '' !== $item['note'] ? ': ' . $item['note'] : '';
				$lines[] = sprintf( '- [%s](%s)%s', $item['title'], $item['url'], $note );
			}
			$lines[] = '';
		}

		$document = rtrim( implode( "\n", $lines ) ) . "\n";

		/**
		 * Filters the fully-rendered llms.txt document.
		 *
		 * @param string $document
		 */
		return (string) apply_filters( 'flexa_seo_aeo/aeo/llms_txt', $document );
	}

	/**
	 * One markdown section per enabled post type, each listing its published,
	 * indexable entries. Empty sections are dropped.
	 *
	 * @return list<array{label: string, items: list<array{title: string, url: string, note: string}>}>
	 */
	private function sections(): array {
		$limit    = max( 1, (int) apply_filters( 'flexa_seo_aeo/aeo/llms_limit', 100 ) );
		$sections = [];

		foreach ( SitemapService::instance()->post_types() as $type ) {
			$object = get_post_type_object( $type );
			$label  = $object instanceof WP_Post_Type ? (string) $object->labels->name : $type;

			// The exclusion is the sitemap service's to build, not ours. This used
			// to carry a private copy of the same meta_query, which meant the two
			// surfaces could disagree about what is indexable and that /llms.txt
			// kept paying for the double postmeta join after the sitemap stopped.
			$query = new WP_Query(
				SitemapService::instance()->exclude_noindexed(
					[
						'post_type'              => $type,
						'post_status'            => 'publish',
						'posts_per_page'         => $limit,
						'orderby'                => 'modified',
						'order'                  => 'DESC',
						'ignore_sticky_posts'    => true,
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
					]
				)
			);

			$items = [];
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$items[] = [
					'title' => $this->one_line( (string) get_the_title( $post ) ),
					'url'   => (string) get_permalink( $post ),
					'note'  => $this->note( $post ),
				];
			}

			if ( [] !== $items ) {
				$sections[] = [
					'label' => $this->one_line( $label ),
					'items' => $items,
				];
			}
		}

		/**
		 * Filters the grouped llms.txt sections before rendering.
		 *
		 * @param list<array{label: string, items: list<array{title: string, url: string, note: string}>}> $sections
		 */
		return array_values( (array) apply_filters( 'flexa_seo_aeo/aeo/llms_sections', $sections ) );
	}

	/**
	 * A short, single-line note for a link — the manual excerpt when present,
	 * else a trimmed slice of the content. Empty string when nothing useful.
	 */
	private function note( WP_Post $post ): string {
		$raw = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$raw = wp_strip_all_tags( strip_shortcodes( $raw ) );
		$raw = $this->one_line( $raw );

		if ( mb_strlen( $raw ) <= 150 ) {
			return $raw;
		}

		$cut   = mb_substr( $raw, 0, 150 );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut ) . '…';
	}

	/**
	 * Collapse all whitespace to single spaces so a value can never break the
	 * line-oriented markdown structure.
	 */
	private function one_line( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}
}
