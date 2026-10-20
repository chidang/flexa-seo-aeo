<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Services\Breadcrumbs;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;

/**
 * The trail on the posts page. QA found `/blog/` shipping no JSON-LD at all:
 * the trail had no `is_home()` branch, so it was a lone Home crumb and was
 * dropped, which left the graph empty and the publisher with it.
 */
final class BreadcrumbsTest extends TestCase {
	private const BLOG_ID = 42;

	protected function setUp(): void {
		parent::setUp();

		WpState::$options['page_for_posts']    = self::BLOG_ID;
		WpState::$post_titles[ self::BLOG_ID ] = 'Blog';
		WpState::$permalinks[ self::BLOG_ID ]  = 'https://example.test/blog/';
	}

	public function test_the_posts_page_gets_home_then_the_page_itself(): void {
		WpState::$is_home = true;

		$this->assertSame(
			[
				[
					'name' => 'Home',
					'url'  => 'https://example.test/',
				],
				[
					'name' => 'Blog',
					'url'  => '',
				],
			],
			$this->trail()
		);
	}

	public function test_a_blog_index_on_the_front_page_has_no_trail(): void {
		WpState::$is_home       = true;
		WpState::$is_front_page = true;

		$this->assertSame( [], $this->trail() );
	}

	public function test_no_posts_page_set_means_no_trail(): void {
		WpState::$is_home                   = true;
		WpState::$options['page_for_posts'] = 0;

		$this->assertSame( [], $this->trail() );
	}

	public function test_an_untitled_posts_page_gets_no_trail_rather_than_a_blank_crumb(): void {
		WpState::$is_home                      = true;
		WpState::$post_titles[ self::BLOG_ID ] = '  ';

		$this->assertSame( [], $this->trail() );
	}

	/**
	 * @return list<array{name: string, url: string}>
	 */
	private function trail(): array {
		return $this->bare( Breadcrumbs::class )->trail();
	}
}
