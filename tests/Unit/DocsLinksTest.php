<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\Docs;
use Flexa\SeoAeo\Tests\TestCase;

/**
 * The in-product links to the published rubric and compatibility matrix.
 *
 * A reviewer asked for the scoring rubric and the compatibility matrix to be
 * published, which they now are. The links are what connects a score or a
 * conflict warning in wp-admin to the writing that justifies it, so three
 * things are worth holding down: the paths, the white-label gate, and the
 * filter a self-hosted copy would use.
 */
final class DocsLinksTest extends TestCase {
	public function test_the_two_pages_have_the_urls_the_readme_advertises(): void {
		$this->given_settings( [] );

		$this->assertSame( 'https://flexa-seo-aeo.vercel.app/', Docs::rubric() );
		$this->assertSame( 'https://flexa-seo-aeo.vercel.app/compatibility', Docs::compatibility() );
	}

	public function test_a_white_labelled_install_is_handed_no_links(): void {
		$this->given_settings( [ 'whitelabel' => true ] );

		$this->assertFalse( Docs::is_visible() );
		$this->assertNull( Docs::for_script(), 'the UI drops the links when this is null' );
	}

	public function test_an_ordinary_install_is_handed_both(): void {
		$this->given_settings( [] );

		$this->assertSame(
			[
				'rubric'        => 'https://flexa-seo-aeo.vercel.app/',
				'compatibility' => 'https://flexa-seo-aeo.vercel.app/compatibility',
			],
			Docs::for_script()
		);
	}

	public function test_a_site_can_point_the_links_at_its_own_copy(): void {
		$this->given_settings( [] );
		add_filter(
			'flexa_seo_aeo/docs/base_url',
			static fn (): string => 'https://docs.example.com/flexa'
		);

		$this->assertSame( 'https://docs.example.com/flexa/', Docs::rubric() );
		$this->assertSame( 'https://docs.example.com/flexa/compatibility', Docs::compatibility() );
	}

	public function test_a_filter_that_empties_the_base_url_falls_back_instead_of_linking_to_nowhere(): void {
		$this->given_settings( [] );
		add_filter( 'flexa_seo_aeo/docs/base_url', static fn (): string => '' );

		$this->assertSame( 'https://flexa-seo-aeo.vercel.app/compatibility', Docs::compatibility() );
	}

	public function test_a_javascript_base_url_is_refused(): void {
		$this->given_settings( [] );
		add_filter(
			'flexa_seo_aeo/docs/base_url',
			static fn (): string => 'javascript:alert(1)/'
		);

		$this->assertSame( '', Docs::rubric(), 'esc_url_raw drops the scheme entirely' );
	}
}
