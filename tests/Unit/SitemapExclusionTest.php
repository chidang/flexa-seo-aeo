<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Services\Sitemap;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;
use Flexa_Test_Wpdb;
use WP_Query;

/**
 * Keeping noindexed and password-protected posts out of everything the plugin
 * publishes: the XML sitemap, the HTML sitemap and llms.txt.
 *
 * `exclude_noindexed()` is the single point all three go through, which is why
 * it is worth pinning on its own. It has two modes. Up to a thousand flagged
 * posts the IDs are inlined as `post__not_in`; past that the list stops being
 * worth re-sending with every query and a `NOT EXISTS` subquery takes over.
 *
 * The subquery shape is deliberate and the tests below hold it in place. A
 * multi-key OR meta_query expressing the same thing cannot pool its clauses
 * onto one join, so each operator takes its own LEFT JOIN on postmeta. That
 * version timed out in production.
 */
final class SitemapExclusionTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wpdb'] = new Flexa_Test_Wpdb();
	}

	/* ---------------------------------------------------------------------
	 * Password-protected posts.
	 * ------------------------------------------------------------------ */

	public function test_password_protected_posts_are_excluded_by_default(): void {
		$args = $this->exclude( [ 'post_type' => 'post' ] );

		$this->assertArrayHasKey( 'has_password', $args );
		$this->assertFalse( $args['has_password'] );
	}

	public function test_a_filter_can_put_password_protected_posts_back(): void {
		add_filter( 'flexa_seo_aeo/sitemap/include_password_protected', static fn (): bool => true );

		$this->assertArrayNotHasKey( 'has_password', $this->exclude( [ 'post_type' => 'post' ] ) );
	}

	public function test_the_caller_arguments_are_carried_through_untouched(): void {
		$args = $this->exclude(
			[
				'post_type'      => [ 'post', 'page' ],
				'posts_per_page' => 500,
				'orderby'        => 'modified',
			]
		);

		$this->assertSame( [ 'post', 'page' ], $args['post_type'] );
		$this->assertSame( 500, $args['posts_per_page'] );
		$this->assertSame( 'modified', $args['orderby'] );
	}

	/* ---------------------------------------------------------------------
	 * Mode one: a short list inlined as post__not_in.
	 * ------------------------------------------------------------------ */

	public function test_a_short_flagged_list_is_inlined(): void {
		$sitemap = $this->given_flagged_ids( [ 7, 19, 204 ] );

		$args = $this->exclude( [ 'post_type' => 'post' ], $sitemap );

		$this->assertSame( [ 7, 19, 204 ], $args['post__not_in'] );
		$this->assertArrayNotHasKey( 'flexa_seo_aeo_exclude_noindex', $args, 'no subquery is needed' );
		$this->assertArrayNotHasKey( 'posts_where', WpState::$hooks, 'and no where filter is attached' );
	}

	public function test_an_empty_flagged_list_still_inlines(): void {
		$sitemap = $this->given_flagged_ids( [] );

		$args = $this->exclude( [ 'post_type' => 'post' ], $sitemap );

		$this->assertSame( [], $args['post__not_in'] );
		$this->assertArrayNotHasKey( 'posts_where', WpState::$hooks );
	}

	public function test_no_meta_query_is_ever_added_to_the_sitemap_query(): void {
		$sitemap = $this->given_flagged_ids( [ 7 ] );

		$this->assertArrayNotHasKey( 'meta_query', $this->exclude( [ 'post_type' => 'post' ], $sitemap ) );
	}

	/* ---------------------------------------------------------------------
	 * Mode two: the subquery.
	 * ------------------------------------------------------------------ */

	public function test_an_oversized_flagged_list_switches_to_the_subquery(): void {
		$sitemap = $this->given_flagged_ids( false );

		$args = $this->exclude( [ 'post_type' => 'post' ], $sitemap );

		$this->assertTrue( $args['flexa_seo_aeo_exclude_noindex'] );
		$this->assertArrayNotHasKey( 'post__not_in', $args, 'the list is too long to inline' );
		$this->assertArrayHasKey( 'posts_where', WpState::$hooks );
	}

	public function test_the_where_filter_is_attached_only_once(): void {
		$sitemap = $this->given_flagged_ids( false );

		$this->exclude( [ 'post_type' => 'post' ], $sitemap );
		$this->exclude( [ 'post_type' => 'page' ], $sitemap );
		$this->exclude( [ 'post_type' => 'product' ], $sitemap );

		$attached = 0;
		foreach ( WpState::$hooks['posts_where'] as $callbacks ) {
			$attached += count( $callbacks );
		}

		$this->assertSame( 1, $attached );
	}

	public function test_the_where_clause_is_one_not_exists_subquery(): void {
		$where = $this->where_for( [ 'flexa_seo_aeo_exclude_noindex' => true ] );

		$this->assertStringStartsWith( 'WHERE 1=1', $where, 'the incoming clause is kept' );
		$this->assertSame( 1, substr_count( $where, 'NOT EXISTS' ) );
		$this->assertSame( 1, substr_count( $where, 'SELECT' ), 'one subquery, not one per operator' );
		$this->assertStringNotContainsString( 'JOIN', $where, 'a join here is the shape that timed out' );
	}

	public function test_the_where_clause_tests_the_value_and_not_just_the_key(): void {
		$where = $this->where_for( [ 'flexa_seo_aeo_exclude_noindex' => true ] );

		$this->assertStringContainsString( "flexa_ni.meta_key = '_flexa_seo_aeo_noindex'", $where );
		$this->assertStringContainsString( "flexa_ni.meta_value = '1'", $where );
	}

	public function test_the_where_clause_is_scoped_to_the_posts_table(): void {
		$where = $this->where_for( [ 'flexa_seo_aeo_exclude_noindex' => true ] );

		$this->assertStringContainsString( 'flexa_ni.post_id = wp_posts.ID', $where );
		$this->assertStringContainsString( 'FROM wp_postmeta AS flexa_ni', $where );
	}

	public function test_a_query_without_the_flag_is_left_alone(): void {
		$this->assertSame( 'WHERE 1=1', $this->where_for( [ 'post_type' => 'post' ] ) );
		$this->assertSame( 'WHERE 1=1', $this->where_for( [ 'flexa_seo_aeo_exclude_noindex' => false ] ) );
	}

	/* ---------------------------------------------------------------------
	 * How the flagged set is detected.
	 * ------------------------------------------------------------------ */

	public function test_the_flagged_set_is_detected_with_a_single_key_lookup(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ [ 7, 19 ] ];

		$args = $this->exclude( [ 'post_type' => 'post' ] );

		$this->assertSame( [ 7, 19 ], $args['post__not_in'] );
		$this->assertCount( 1, WpState::$get_posts_args, 'one lookup, not one per post type' );

		$lookup = WpState::$get_posts_args[0];
		$this->assertSame( '_flexa_seo_aeo_noindex', $lookup['meta_key'] );
		$this->assertSame( '1', $lookup['meta_value'] );
		$this->assertArrayNotHasKey( 'meta_query', $lookup, 'a multi-key OR meta_query is what hung production' );
		$this->assertSame( 'ids', $lookup['fields'], 'only IDs are needed' );
		$this->assertTrue( $lookup['no_found_rows'] );
		$this->assertFalse( $lookup['update_post_meta_cache'] );
	}

	public function test_the_lookup_asks_for_exactly_one_row_past_the_cap(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ [] ];

		$this->exclude( [ 'post_type' => 'post' ] );

		$this->assertSame( 1001, WpState::$get_posts_args[0]['posts_per_page'] );
	}

	public function test_one_row_past_the_cap_flips_to_the_subquery(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ range( 1, 1001 ) ];

		$args = $this->exclude( [ 'post_type' => 'post' ] );

		$this->assertArrayNotHasKey( 'post__not_in', $args );
		$this->assertTrue( $args['flexa_seo_aeo_exclude_noindex'] );
	}

	public function test_a_list_exactly_at_the_cap_is_still_inlined(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ range( 1, 1000 ) ];

		$args = $this->exclude( [ 'post_type' => 'post' ] );

		$this->assertCount( 1000, $args['post__not_in'] );
		$this->assertArrayNotHasKey( 'flexa_seo_aeo_exclude_noindex', $args );
	}

	public function test_the_lookup_runs_once_however_many_documents_are_built(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ [ 7 ] ];

		$sitemap = $this->bare( Sitemap::class );
		$this->set_private( $sitemap, 'noindexed_ids', null );
		$this->set_private( $sitemap, 'where_filter_attached', false );

		for ( $i = 0; $i < 25; $i++ ) {
			$sitemap->exclude_noindexed( [ 'post_type' => 'post' ] );
		}

		$this->assertCount( 1, WpState::$get_posts_args, 'the detection lookup is memoised per request' );
	}

	public function test_no_lookup_runs_when_the_sitemap_has_no_post_types(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [] ] );

		$args = $this->exclude( [ 'post_type' => 'post' ] );

		$this->assertSame( [], WpState::$get_posts_args, 'nothing to look in' );
		$this->assertSame( [], $args['post__not_in'] );
	}

	public function test_ids_coming_back_as_strings_are_stored_as_integers(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'sitemap_post_types' => [ 'post' ] ] );
		WpState::$post_types        = [ 'post' => 'post' ];
		WpState::$get_posts_results = [ [ '7', '19' ] ];

		$this->assertSame( [ 7, 19 ], $this->exclude( [ 'post_type' => 'post' ] )['post__not_in'] );
	}

	/* ---------------------------------------------------------------------
	 * Fixtures.
	 * ------------------------------------------------------------------ */

	/**
	 * A service whose detection lookup has already resolved, so a test can
	 * drive either mode without going through `get_posts()`.
	 *
	 * @param list<int>|false $ids
	 */
	private function given_flagged_ids( array|false $ids ): Sitemap {
		$sitemap = $this->bare( Sitemap::class );
		$this->set_private( $sitemap, 'noindexed_ids', $ids );
		$this->set_private( $sitemap, 'where_filter_attached', false );

		return $sitemap;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function exclude( array $args, ?Sitemap $sitemap = null ): array {
		if ( null === $sitemap ) {
			$sitemap = $this->bare( Sitemap::class );
			$this->set_private( $sitemap, 'noindexed_ids', null );
			$this->set_private( $sitemap, 'where_filter_attached', false );
		}

		return $sitemap->exclude_noindexed( $args );
	}

	/**
	 * The WHERE clause the filter produces for a query carrying `$vars`.
	 *
	 * @param array<string, mixed> $vars
	 */
	private function where_for( array $vars ): string {
		return $this->bare( Sitemap::class )->filter_noindex_where( 'WHERE 1=1', new WP_Query( $vars ) );
	}
}
