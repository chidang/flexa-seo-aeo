<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Services\Aeo;

use Flexa\SeoAeo\Services\Breadcrumbs as BreadcrumbsService;
use Flexa\SeoAeo\Services\Metas as MetasService;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the AEO JSON-LD `@graph` for the current request. Answer engines lean
 * hard on structured data to lift facts out of a page, so this emits Article /
 * WebPage nodes for content, a WebSite node with a SearchAction, an
 * Organization/Person publisher from the knowledge settings, and — the AEO
 * payoff — a FAQPage auto-detected from core `details` blocks. Pure data: it
 * returns nested arrays; JSON encoding + escaping happen in the output action.
 */
final class Schema {
	use SingletonTrait;

	/**
	 * Post meta holding the Q/A pairs detected for a post, stamped with a hash of
	 * the content they were read from. Detection walks the block tree and renders
	 * every details block, which is far more than a page view should repeat for a
	 * result that only moves when the content does. The stamp is what invalidates
	 * the entry, so no save hook is involved and an import, a revision rollback or
	 * a direct DB edit can never leave a stale answer on the page.
	 */
	private const FAQ_CACHE_META = '_flexa_seo_aeo_faq_cache';

	/**
	 * The full graph, or an empty array when there's nothing to emit.
	 *
	 * @return array<string, mixed>
	 */
	public function graph(): array {
		$nodes     = [];
		$publisher = $this->publisher();
		$pub_ref   = isset( $publisher['@id'] ) ? [ '@id' => (string) $publisher['@id'] ] : null;

		if ( is_front_page() ) {
			$nodes[] = $this->website( $pub_ref );
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$nodes[] = $this->article( $post, $pub_ref );

				$faq = $this->faq_page( $post );
				if ( [] !== $faq ) {
					$nodes[] = $faq;
				}

				$howto = $this->howto( $post );
				if ( [] !== $howto ) {
					$nodes[] = $howto;
				}
			}
		}

		$breadcrumbs = $this->breadcrumb_list();
		if ( [] !== $breadcrumbs ) {
			$nodes[] = $breadcrumbs;
		}

		if ( [] !== $nodes && [] !== $publisher ) {
			$nodes[] = $publisher;
		}

		/**
		 * Filters the JSON-LD graph nodes before they are wrapped and emitted.
		 *
		 * @param list<array<string, mixed>> $nodes
		 */
		$nodes = array_values( (array) apply_filters( 'flexa_seo_aeo/aeo/schema_nodes', $nodes ) );
		if ( [] === $nodes ) {
			return [];
		}

		return [
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		];
	}

	/**
	 * `BreadcrumbList` for the current view, built from the same trail the
	 * visible breadcrumbs render, so the markup and the structured data cannot
	 * describe different paths.
	 *
	 * The final crumb keeps its `name` but carries no `item`: it is the page
	 * being viewed, and Google's guidance is to leave the last URL off.
	 *
	 * @return array<string, mixed>
	 */
	private function breadcrumb_list(): array {
		if ( ! (bool) Settings::get( 'breadcrumbs' ) ) {
			return [];
		}

		$trail = BreadcrumbsService::instance()->trail();
		if ( count( $trail ) < 2 ) {
			return [];
		}

		$elements = [];
		$position = 1;

		foreach ( $trail as $crumb ) {
			$element = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $crumb['name'],
			];

			if ( '' !== $crumb['url'] ) {
				$element['item'] = $crumb['url'];
			}

			$elements[] = $element;
			++$position;
		}

		// Anchor the node on the page's canonical, which is the same URL the
		// other nodes on this page are keyed to.
		$canonical = MetasService::instance()->resolve()->canonical;

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => ( '' !== $canonical ? $canonical : home_url( '/' ) ) . '#breadcrumb',
			'itemListElement' => $elements,
		];
	}

	/**
	 * @param array{'@id': string}|null $pub_ref
	 * @return array<string, mixed>
	 */
	private function website( ?array $pub_ref ): array {
		$node = [
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => $this->site_name(),
			'description'     => $this->one_line( (string) get_bloginfo( 'description' ) ),
			'inLanguage'      => (string) get_bloginfo( 'language' ),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				],
				'query-input' => 'required name=search_term_string',
			],
		];

		if ( null !== $pub_ref ) {
			$node['publisher'] = $pub_ref;
		}

		return array_filter( $node, static fn( $v ): bool => '' !== $v && [] !== $v );
	}

	/**
	 * @param array{'@id': string}|null $pub_ref
	 * @return array<string, mixed>
	 */
	private function article( WP_Post $post, ?array $pub_ref ): array {
		$permalink = (string) get_permalink( $post );
		$type      = $this->article_type( $post );

		$node = [
			'@type'            => $type,
			'@id'              => $permalink . '#' . strtolower( $type ),
			'url'              => $permalink,
			'headline'         => $this->one_line( (string) get_the_title( $post ) ),
			'description'      => MetasService::instance()->resolve()->description,
			'inLanguage'       => (string) get_bloginfo( 'language' ),
			'mainEntityOfPage' => $permalink,
			'author'           => [
				'@type' => 'Person',
				'name'  => $this->one_line( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) ),
			],
		];

		$published = get_post_time( 'c', true, $post );
		if ( is_string( $published ) && '' !== $published ) {
			$node['datePublished'] = $published;
		}
		$modified = get_post_modified_time( 'c', true, $post );
		if ( is_string( $modified ) && '' !== $modified ) {
			$node['dateModified'] = $modified;
		}

		$image = $this->featured_image( $post );
		if ( '' !== $image ) {
			$node['image'] = [
				'@type' => 'ImageObject',
				'url'   => $image,
			];
		}

		if ( null !== $pub_ref ) {
			$node['publisher'] = $pub_ref;
		}

		if ( '' === $node['author']['name'] ) {
			unset( $node['author'] );
		}

		return $node;
	}

	/**
	 * Organization or Person node describing who publishes the site, from the
	 * knowledge-graph settings. Empty array when no name is resolvable.
	 *
	 * @return array<string, mixed>
	 */
	private function publisher(): array {
		$is_person = 'person' === (string) Settings::get( 'knowledge_type' );
		$name      = $this->one_line( (string) Settings::get( 'knowledge_name' ) );
		if ( '' === $name ) {
			$name = $this->site_name();
		}
		if ( '' === $name ) {
			return [];
		}

		$type = $is_person ? 'Person' : 'Organization';
		$node = [
			'@type' => $type,
			'@id'   => home_url( '/#' . strtolower( $type ) ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		];

		$logo = (string) Settings::get( 'og_default_image' );
		if ( '' !== $logo ) {
			$node['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $logo,
			];
			if ( ! $is_person ) {
				$node['image'] = $logo;
			}
		}

		return $node;
	}

	/**
	 * FAQPage node built from `core/details` blocks (each `<summary>` is a
	 * question, its body the answer) plus anything a filter injects.
	 *
	 * @return array<string, mixed>
	 */
	private function faq_page( WP_Post $post ): array {
		$items = $this->detect_faq( $post );

		/**
		 * Filters the FAQ question/answer pairs for a post.
		 *
		 * @param list<array{question: string, answer: string}> $items
		 * @param WP_Post                                        $post
		 */
		$items = (array) apply_filters( 'flexa_seo_aeo/aeo/faq', $items, $post );
		$items = $this->normalize_pairs( $items, 'question', 'answer' );
		if ( [] === $items ) {
			return [];
		}

		$entities = [];
		foreach ( $items as $qa ) {
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $qa['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $qa['answer'],
				],
			];
		}

		return [
			'@type'      => 'FAQPage',
			'@id'        => (string) get_permalink( $post ) . '#faq',
			'mainEntity' => $entities,
		];
	}

	/**
	 * HowTo node — filter-driven (no reliable auto-detection), so it stays empty
	 * until a theme/add-on supplies steps via `flexa_seo_aeo/aeo/howto_steps`.
	 *
	 * @return array<string, mixed>
	 */
	private function howto( WP_Post $post ): array {
		/**
		 * Filters the HowTo steps for a post.
		 *
		 * @param list<array{name: string, text: string}> $steps
		 * @param WP_Post                                  $post
		 */
		$steps = (array) apply_filters( 'flexa_seo_aeo/aeo/howto_steps', [], $post );
		$steps = $this->normalize_pairs( $steps, 'name', 'text' );
		if ( [] === $steps ) {
			return [];
		}

		$list = [];
		foreach ( $steps as $step ) {
			$list[] = [
				'@type' => 'HowToStep',
				'name'  => $step['name'],
				'text'  => $step['text'],
			];
		}

		return [
			'@type' => 'HowTo',
			'@id'   => (string) get_permalink( $post ) . '#howto',
			'name'  => $this->one_line( (string) get_the_title( $post ) ),
			'step'  => $list,
		];
	}

	/**
	 * Walk the post's block tree collecting core `details` blocks as Q/A pairs,
	 * reading {@see self::FAQ_CACHE_META} when it still matches the content.
	 *
	 * @return list<array{question: string, answer: string}>
	 */
	private function detect_faq( WP_Post $post ): array {
		// A protected post's Q/A pairs are the protected content, and FAQPage
		// publishes them verbatim and uncapped. Nothing to emit until the
		// visitor has the password.
		if ( post_password_required( $post ) ) {
			return [];
		}

		// Serialized block markup names every block in its comment delimiter, so
		// content without this substring cannot hold a details block. Checking it
		// first keeps the common page view clear of parse_blocks entirely, and
		// leaves the cache row below to the posts that actually have an FAQ.
		if ( ! function_exists( 'parse_blocks' ) || ! str_contains( $post->post_content, '<!-- wp:details' ) ) {
			return [];
		}

		$hash   = md5( $post->post_content );
		$cached = get_post_meta( $post->ID, self::FAQ_CACHE_META, true );

		if ( is_array( $cached ) && ( $cached['hash'] ?? '' ) === $hash && is_array( $cached['items'] ?? null ) ) {
			/** @var list<array{question: string, answer: string}> $items */
			$items = array_values( $cached['items'] );

			return $items;
		}

		$items = [];
		$this->collect_details( parse_blocks( $post->post_content ), $items );

		// A preview renders an unsaved revision, so its pairs describe markup the
		// public URL does not serve. Recompute next time rather than store them.
		if ( ! is_preview() ) {
			update_post_meta(
				$post->ID,
				self::FAQ_CACHE_META,
				[
					'hash'  => $hash,
					'items' => $items,
				]
			);
		}

		return $items;
	}

	/**
	 * @param array<int, mixed>                             $blocks
	 * @param list<array{question: string, answer: string}> $out
	 */
	private function collect_details( array $blocks, array &$out ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'core/details' === ( $block['blockName'] ?? '' ) ) {
				$qa = $this->details_to_qa( $block );
				if ( null !== $qa ) {
					$out[] = $qa;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_details( $block['innerBlocks'], $out );
			}
		}
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array{question: string, answer: string}|null
	 */
	private function details_to_qa( array $block ): ?array {
		$attrs   = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		$summary = isset( $attrs['summary'] ) && is_scalar( $attrs['summary'] ) ? (string) $attrs['summary'] : '';

		$rendered = (string) render_block( $block );
		if ( '' === $summary && preg_match( '/<summary[^>]*>(.*?)<\/summary>/is', $rendered, $m ) ) {
			$summary = $m[1];
		}

		$question = $this->one_line( wp_strip_all_tags( $summary ) );
		if ( '' === $question ) {
			return null;
		}

		$answer = (string) preg_replace( '/<summary[^>]*>.*?<\/summary>/is', '', $rendered );
		$answer = $this->one_line( wp_strip_all_tags( $answer ) );
		if ( '' === $answer ) {
			return null;
		}

		return [
			'question' => $question,
			'answer'   => $answer,
		];
	}

	/**
	 * Validate a filtered list of pairs down to the two string keys we need,
	 * dropping anything malformed or empty.
	 *
	 * @param array<int|string, mixed> $items
	 * @return list<array<string, string>>
	 */
	private function normalize_pairs( array $items, string $key_a, string $key_b ): array {
		$out = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$a = isset( $item[ $key_a ] ) && is_scalar( $item[ $key_a ] ) ? $this->one_line( (string) $item[ $key_a ] ) : '';
			$b = isset( $item[ $key_b ] ) && is_scalar( $item[ $key_b ] ) ? $this->one_line( (string) $item[ $key_b ] ) : '';
			if ( '' === $a || '' === $b ) {
				continue;
			}
			$out[] = [
				$key_a => $a,
				$key_b => $b,
			];
		}

		return $out;
	}

	private function article_type( WP_Post $post ): string {
		$type = get_post_type( $post );
		if ( 'post' === $type ) {
			return 'BlogPosting';
		}
		if ( 'page' === $type ) {
			return 'WebPage';
		}

		return 'Article';
	}

	private function site_name(): string {
		$name = $this->one_line( (string) Settings::get_aeo( 'site_name' ) );

		return '' !== $name ? $name : $this->one_line( (string) get_bloginfo( 'name' ) );
	}

	private function featured_image( WP_Post $post ): string {
		$thumb_id = get_post_thumbnail_id( $post );
		if ( ! $thumb_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );

		return is_string( $url ) ? $url : '';
	}

	private function one_line( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}
}
