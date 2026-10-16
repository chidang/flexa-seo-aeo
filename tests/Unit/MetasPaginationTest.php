<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Services\Metas;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_Post;
use WP_Query;
use WP_Rewrite;
use WP_Term;

/**
 * Pagination: how a page number is read, whether the view really has that
 * page, and what canonical the page gets.
 *
 * This is the part of the plugin that has broken twice. Round 1 found a static
 * front page numbering `/page/99/` as a real page; the fix for it counted the
 * `<!--nextpage-->` markers by hand, which disagreed with core on two edge
 * cases, and round 3 replaced the hand count with `generate_postdata()`. Both
 * shapes are pinned below so a third attempt cannot reintroduce either.
 */
final class MetasPaginationTest extends TestCase {
	/* ---------------------------------------------------------------------
	 * Which page are we on.
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed> $vars     Query vars present on the request.
	 * @param int                  $expected The page number that must come out.
	 */
	#[DataProvider( 'page_number_cases' )]
	public function test_the_current_page_is_read_from_whichever_var_is_set( array $vars, int $expected ): void {
		WpState::$query_vars = $vars;

		$this->assertSame( $expected, $this->call_private( $this->bare( Metas::class ), 'current_page' ) );
	}

	/**
	 * `paged` counts pages of an archive, `page` counts the parts of a split
	 * post. Core sets one or the other, never both, and anything at or below
	 * zero means page one.
	 *
	 * @return array<string, array{array<string, mixed>, int}>
	 */
	public static function page_number_cases(): array {
		return [
			'nothing set'       => [ [], 1 ],
			'archive page 3'    => [ [ 'paged' => 3 ], 3 ],
			'split post part 4' => [ [ 'page' => 4 ], 4 ],
			'paged zero'        => [ [ 'paged' => 0 ], 1 ],
			'page zero'         => [ [ 'page' => 0 ], 1 ],
			'paged as a string' => [ [ 'paged' => '7' ], 7 ],
			'negative paged'    => [ [ 'paged' => -5 ], 1 ],
			'junk paged'        => [ [ 'paged' => 'abc' ], 1 ],
			'both set'          => [
				[
					'paged' => 2,
					'page'  => 5,
				],
				5,
			],
		];
	}

	/* ---------------------------------------------------------------------
	 * Does the view have that page at all.
	 * ------------------------------------------------------------------ */

	public function test_a_request_with_no_main_query_has_no_pages(): void {
		unset( $GLOBALS['wp_query'] );

		$this->assertFalse( $this->paginates_to( 1 ) );
	}

	public function test_a_main_query_that_is_not_a_wp_query_has_no_pages(): void {
		$GLOBALS['wp_query'] = new \stdClass();

		$this->assertFalse( $this->paginates_to( 1 ) );
	}

	public function test_an_archive_counts_pages_from_the_query(): void {
		$query                = new WP_Query();
		$query->max_num_pages = 3;
		$GLOBALS['wp_query']  = $query;

		$this->assertTrue( $this->paginates_to( 1 ) );
		$this->assertTrue( $this->paginates_to( 3 ) );
		$this->assertFalse( $this->paginates_to( 4 ), 'past the last page' );
		$this->assertFalse( $this->paginates_to( 99 ) );
	}

	public function test_an_archive_that_reports_no_pages_still_has_page_one(): void {
		$query                = new WP_Query();
		$query->max_num_pages = 0;
		$GLOBALS['wp_query']  = $query;

		$this->assertTrue( $this->paginates_to( 1 ), 'page 1 is the view itself' );
		$this->assertFalse( $this->paginates_to( 2 ) );
	}

	public function test_a_singular_view_whose_queried_object_is_not_a_post_has_no_pages(): void {
		$GLOBALS['wp_query']     = new WP_Query();
		WpState::$is_singular    = true;
		WpState::$queried_object = new WP_Term( [ 'term_id' => 9 ] );

		$this->assertFalse( $this->paginates_to( 1 ) );
	}

	public function test_a_split_post_takes_its_count_from_core(): void {
		$this->given_a_split_post( 2 );

		$this->assertTrue( $this->paginates_to( 1 ) );
		$this->assertTrue( $this->paginates_to( 2 ) );
		$this->assertFalse( $this->paginates_to( 3 ), 'core knows the post has two parts' );
	}

	/**
	 * The round-1 defect. A static front page answers 200 for every `/page/N/`
	 * with the same body, so numbering an arbitrary N hands a crawler an
	 * unbounded set of canonicals over one document.
	 */
	public function test_a_static_front_page_does_not_number_a_page_it_does_not_have(): void {
		$this->given_a_split_post( 1 );
		WpState::$is_front_page = true;

		$this->assertTrue( $this->paginates_to( 1 ) );
		$this->assertFalse( $this->paginates_to( 2 ) );
		$this->assertFalse( $this->paginates_to( 99 ) );
	}

	/**
	 * The round-2 defect, from the other side: the count has to come from
	 * `generate_postdata()`, which drops a marker that opens the content and
	 * runs the list through `content_pagination`. A hand count of the markers
	 * disagrees with the page the visitor is actually served.
	 */
	public function test_the_count_comes_from_core_and_not_from_counting_markers(): void {
		$body = "<!--nextpage-->Opening part.\n<!--nextpage-->Second part.";

		// Core drops the leading marker, so this body is two parts, not three.
		$this->given_a_split_post( 2, $body );

		$this->assertTrue( $this->paginates_to( 2 ) );
		$this->assertFalse( $this->paginates_to( 3 ), 'the marker that opens the content is not a page' );
	}

	public function test_a_filter_that_adds_a_part_makes_that_page_real(): void {
		// What `content_pagination` returning a longer list looks like from here.
		$this->given_a_split_post( 3, "One.\n<!--nextpage-->Two." );

		$this->assertTrue( $this->paginates_to( 3 ) );
		$this->assertFalse( $this->paginates_to( 4 ) );
	}

	public function test_a_post_core_refuses_to_paginate_has_no_pages(): void {
		$this->given_a_split_post( 1 );
		$GLOBALS['wp_query']->postdata = null; // generate_postdata() returns false.

		$this->assertFalse( $this->paginates_to( 1 ) );
		$this->assertFalse( $this->paginates_to( 2 ) );
	}

	public function test_a_post_core_reports_as_zero_parts_still_has_page_one(): void {
		$this->given_a_split_post( 0 );

		$this->assertTrue( $this->paginates_to( 1 ) );
		$this->assertFalse( $this->paginates_to( 2 ) );
	}

	/* ---------------------------------------------------------------------
	 * The canonical for page N.
	 * ------------------------------------------------------------------ */

	public function test_a_plain_permalink_site_numbers_with_a_query_argument(): void {
		WpState::$options['permalink_structure'] = '';

		$this->assertSame(
			'https://example.test/?p=12&page=2',
			$this->paged_canonical( 'https://example.test/?p=12', 2, singular: true )
		);
		$this->assertSame(
			'https://example.test/?cat=3&paged=2',
			$this->paged_canonical( 'https://example.test/?cat=3', 2, singular: false )
		);
	}

	public function test_a_base_that_already_carries_a_query_keeps_using_arguments(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';

		$this->assertSame(
			'https://example.test/shop/?orderby=price&paged=3',
			$this->paged_canonical( 'https://example.test/shop/?orderby=price', 3, singular: false )
		);
	}

	public function test_a_split_post_numbers_its_parts_directly(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';

		$this->assertSame(
			'https://example.test/long-read/2/',
			$this->paged_canonical( 'https://example.test/long-read/', 2, singular: true )
		);
	}

	public function test_a_static_front_page_numbers_under_the_pagination_base(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';

		$this->assertSame(
			'https://example.test/page/2/',
			$this->paged_canonical( 'https://example.test/', 2, singular: true, front: true )
		);
	}

	public function test_an_archive_numbers_under_the_pagination_base(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';

		$this->assertSame(
			'https://example.test/news/page/4/',
			$this->paged_canonical( 'https://example.test/news/', 4, singular: false )
		);
	}

	public function test_a_renamed_pagination_base_is_honoured(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';

		$rewrite                  = new WP_Rewrite();
		$rewrite->pagination_base = 'trang';
		$GLOBALS['wp_rewrite']    = $rewrite;

		$this->assertSame(
			'https://example.test/tin-tuc/trang/2/',
			$this->paged_canonical( 'https://example.test/tin-tuc/', 2, singular: false )
		);
	}

	public function test_a_missing_rewrite_object_falls_back_to_page(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';
		unset( $GLOBALS['wp_rewrite'] );

		$this->assertSame(
			'https://example.test/news/page/2/',
			$this->paged_canonical( 'https://example.test/news/', 2, singular: false )
		);
	}

	public function test_a_structure_without_a_trailing_slash_produces_none(): void {
		WpState::$options['permalink_structure'] = '/%postname%';

		$this->assertSame(
			'https://example.test/long-read/2',
			$this->paged_canonical( 'https://example.test/long-read/', 2, singular: true )
		);
		$this->assertSame(
			'https://example.test/news/page/2',
			$this->paged_canonical( 'https://example.test/news/', 2, singular: false )
		);
	}

	public function test_the_canonical_is_built_from_the_base_and_not_from_the_request(): void {
		WpState::$options['permalink_structure'] = '/%postname%/';
		WpState::$query_vars                     = [
			'utm_source' => 'newsletter',
			'paged'      => 2,
		];

		$url = $this->paged_canonical( 'https://example.test/news/', 2, singular: false );

		$this->assertStringNotContainsString( 'utm_', $url, 'a tracking parameter must not reach the tag' );
	}

	/* ---------------------------------------------------------------------
	 * Fixtures.
	 * ------------------------------------------------------------------ */

	/**
	 * A singular view of a post core reports as `$parts` pages.
	 */
	private function given_a_split_post( int $parts, string $content = 'Body.' ): void {
		$post = new WP_Post(
			[
				'ID'           => 12,
				'post_title'   => 'Long read',
				'post_content' => $content,
			]
		);

		$query                   = new WP_Query();
		$query->postdata         = [ 'numpages' => $parts ];
		$GLOBALS['wp_query']     = $query;
		WpState::$is_singular    = true;
		WpState::$queried_object = $post;
	}

	private function paginates_to( int $page ): bool {
		return (bool) $this->call_private( $this->bare( Metas::class ), 'paginates_to', [ $page ] );
	}

	private function paged_canonical( string $base, int $page, bool $singular, bool $front = false ): string {
		WpState::$is_singular   = $singular;
		WpState::$is_front_page = $front;

		return (string) $this->call_private( $this->bare( Metas::class ), 'paged_canonical', [ $base, $page ] );
	}
}
