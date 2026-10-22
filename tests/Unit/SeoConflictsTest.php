<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\SeoConflicts;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;

/**
 * Which active SEO plugins keep this one's robots directives off the page.
 *
 * Measured on 2026-10-22 with tests/Compat/run-robots.php: on a post set to
 * noindex, Rank Math 1.0.270 and All in One SEO 5.0.3 printed a robots tag
 * without noindex, SEOPress 10.1 printed a second tag saying index, and Yoast
 * 28.7 kept the noindex. The admin notice warns from these lists, so a plugin
 * moving between them changes what users are told.
 */
final class SeoConflictsTest extends TestCase {
	public function test_rank_math_and_aioseo_replace_the_robots_tag(): void {
		WpState::$active_plugins = [
			'seo-by-rank-math/rank-math.php',
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
		];

		$this->assertSame(
			[
				'rankmath' => 'Rank Math',
				'aioseo'   => 'All in One SEO',
			],
			SeoConflicts::robots_replaced()
		);
		$this->assertSame( [], SeoConflicts::robots_duplicated() );
	}

	public function test_seopress_duplicates_the_robots_tag(): void {
		WpState::$active_plugins = [ 'wp-seopress/seopress.php' ];

		$this->assertSame( [], SeoConflicts::robots_replaced() );
		$this->assertSame( [ 'seopress' => 'SEOPress' ], SeoConflicts::robots_duplicated() );
	}

	public function test_yoast_is_a_conflict_but_leaves_robots_alone(): void {
		WpState::$active_plugins = [ 'wordpress-seo/wp-seo.php' ];

		$this->assertSame( [ 'yoast' => 'Yoast SEO' ], SeoConflicts::active() );
		$this->assertSame( [], SeoConflicts::robots_replaced() );
		$this->assertSame( [], SeoConflicts::robots_duplicated() );
	}

	public function test_a_pro_build_counts_as_the_plugin(): void {
		WpState::$active_plugins = [ 'seo-by-rank-math-pro/rank-math-pro.php' ];

		$this->assertSame( [ 'rankmath' => 'Rank Math' ], SeoConflicts::robots_replaced() );
	}

	public function test_nothing_active_means_no_warning(): void {
		$this->assertSame( [], SeoConflicts::robots_replaced() );
		$this->assertSame( [], SeoConflicts::robots_duplicated() );
	}
}
