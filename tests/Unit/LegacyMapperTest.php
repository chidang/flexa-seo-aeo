<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Services\Migration\LegacyMapper;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Translating another plugin's per-post meta into Flexa fields. SEOPress was
 * read-fallback only, so a site leaving it lost its titles the moment it was
 * deactivated; these pin the write-side mapping that closes that.
 */
final class LegacyMapperTest extends TestCase {
	public function test_seopress_is_a_migration_source(): void {
		$this->assertTrue( LegacyMapper::is_source( 'seopress' ) );
		$this->assertSame( 'SEOPress', LegacyMapper::label( 'seopress' ) );
	}

	public function test_every_seopress_string_field_is_carried(): void {
		$mapped = LegacyMapper::map(
			'seopress',
			[
				'_seopress_titles_title'         => 'Custom title',
				'_seopress_titles_desc'          => 'Custom description',
				'_seopress_robots_canonical'     => 'https://example.test/canonical/',
				'_seopress_social_fb_title'      => 'FB title',
				'_seopress_social_fb_desc'       => 'FB description',
				'_seopress_social_fb_img'        => 'https://example.test/fb.jpg',
				'_seopress_social_twitter_title' => 'X title',
				'_seopress_social_twitter_desc'  => 'X description',
				'_seopress_social_twitter_img'   => 'https://example.test/x.jpg',
			]
		);

		$this->assertSame(
			[
				'title'               => 'Custom title',
				'description'         => 'Custom description',
				'canonical'           => 'https://example.test/canonical/',
				'og_title'            => 'FB title',
				'og_description'      => 'FB description',
				'og_image'            => 'https://example.test/fb.jpg',
				'twitter_title'       => 'X title',
				'twitter_description' => 'X description',
				'twitter_image'       => 'https://example.test/x.jpg',
			],
			$mapped
		);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, bool>  $expected
	 */
	#[DataProvider( 'seopress_robots_cases' )]
	public function test_seopress_robots_checkboxes_read_yes_as_ticked( array $meta, array $expected ): void {
		$this->assertSame( $expected, LegacyMapper::map( 'seopress', $meta ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>, array<string, bool>}>
	 */
	public static function seopress_robots_cases(): array {
		return [
			'noindex ticked'    => [ [ '_seopress_robots_index' => 'yes' ], [ 'noindex' => true ] ],
			'nofollow ticked'   => [ [ '_seopress_robots_follow' => 'yes' ], [ 'nofollow' => true ] ],
			'both ticked'       => [
				[
					'_seopress_robots_index'  => 'yes',
					'_seopress_robots_follow' => 'yes',
				],
				[
					'noindex'  => true,
					'nofollow' => true,
				],
			],
			'unticked is empty' => [ [ '_seopress_robots_index' => '' ], [] ],
			'anything but yes'  => [ [ '_seopress_robots_index' => '1' ], [] ],
		];
	}

	/**
	 * @param string $template SEOPress template.
	 * @param string $expected The same template in Flexa's vocabulary.
	 */
	#[DataProvider( 'seopress_token_cases' )]
	public function test_seopress_tokens_become_flexa_tokens( string $template, string $expected ): void {
		$this->assertSame( [ 'title' => $expected ], LegacyMapper::map( 'seopress', [ '_seopress_titles_title' => $template ] ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function seopress_token_cases(): array {
		return [
			'post title and site'  => [ '%%post_title%% %%sep%% %%sitetitle%%', '%%title%% %%sep%% %%sitename%%' ],
			'excerpt and author'   => [ '%%post_excerpt%% by %%post_author%%', '%%excerpt%% by %%author%%' ],
			'term titles'          => [ '%%_category_title%% / %%tag_title%% / %%term_title%%', '%%term_title%% / %%term_title%% / %%term_title%%' ],
			'search and page'      => [ '%%search_keywords%% %%current_pagination%%', '%%searchphrase%% %%page%%' ],
			'shared names pass'    => [ '%%sitename%% %%tagline%% %%currentyear%%', '%%sitename%% %%tagline%% %%currentyear%%' ],
			'unknown passes as is' => [ '%%wc_sku%%', '%%wc_sku%%' ],
		];
	}

	public function test_seopress_renames_do_not_leak_into_other_sources(): void {
		$this->assertSame(
			[ 'title' => '%%post_title%%' ],
			LegacyMapper::map( 'yoast', [ '_yoast_wpseo_title' => '%%post_title%%' ] )
		);
	}

	public function test_yoast_and_rank_math_keep_their_mapping(): void {
		$this->assertSame(
			[
				'title'   => '%%title%% %%sep%% %%author%%',
				'noindex' => true,
			],
			LegacyMapper::map(
				'yoast',
				[
					'_yoast_wpseo_title'               => '%%title%% %%sep%% %%name%%',
					'_yoast_wpseo_meta-robots-noindex' => '1',
				]
			)
		);

		$this->assertSame(
			[
				'title'    => '%%title%% %%sep%% %%sitename%%',
				'nofollow' => true,
			],
			LegacyMapper::map(
				'rankmath',
				[
					'rank_math_title'  => '%seo_title% %sep% %sitename%',
					'rank_math_robots' => [ 'nofollow' ],
				]
			)
		);
	}

	public function test_seopress_robots_keys_are_read_and_detected(): void {
		foreach ( [ '_seopress_robots_index', '_seopress_robots_follow', '_seopress_social_twitter_img' ] as $key ) {
			$this->assertContains( $key, LegacyMapper::legacy_keys( 'seopress' ) );
		}

		$this->assertContains( '_seopress_titles_title', LegacyMapper::sentinel_keys( 'seopress' ) );
		$this->assertContains( '_seopress_robots_index', LegacyMapper::sentinel_keys( 'seopress' ) );
	}
}
