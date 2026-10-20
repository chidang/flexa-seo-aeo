<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\ContentImages;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The image sitemap used to list the featured image only, so a post with a
 * dozen pictures in its body advertised one or none. These pin what is read
 * out of the stored content.
 */
final class ContentImagesTest extends TestCase {
	private const HOME = 'https://example.test/';

	/**
	 * @param string       $html     Stored post content.
	 * @param list<string> $expected Image URLs that must come out, in order.
	 */
	#[DataProvider( 'cases' )]
	public function test_images_are_read_from_stored_content( string $html, array $expected ): void {
		$this->assertSame( $expected, ContentImages::extract( $html, self::HOME ) );
	}

	/**
	 * @return array<string, array{string, list<string>}>
	 */
	public static function cases(): array {
		return [
			'no images'           => [ '<p>Just prose.</p>', [] ],
			'empty content'       => [ '', [] ],
			'image block'         => [
				'<!-- wp:image {"id":12} --><figure class="wp-block-image"><img src="https://example.test/wp-content/uploads/a.jpg" alt="" class="wp-image-12"/></figure><!-- /wp:image -->',
				[ 'https://example.test/wp-content/uploads/a.jpg' ],
			],
			'gallery keeps order' => [
				'<figure class="wp-block-gallery"><figure><img src="https://example.test/b.jpg"/></figure><figure><img src="https://example.test/c.jpg"/></figure></figure>',
				[ 'https://example.test/b.jpg', 'https://example.test/c.jpg' ],
			],
			'cover background'    => [
				'<div class="wp-block-cover"><img class="wp-block-cover__image-background" alt="" src="https://example.test/cover.jpg" data-object-fit="cover"/></div>',
				[ 'https://example.test/cover.jpg' ],
			],
			'single quotes'       => [ "<img alt='x' src='https://example.test/d.jpg'>", [ 'https://example.test/d.jpg' ] ],
			'uppercase tag'       => [ '<IMG SRC="https://example.test/e.jpg">', [ 'https://example.test/e.jpg' ] ],
			'duplicates collapse' => [
				'<img src="https://example.test/f.jpg"><img src="https://example.test/f.jpg">',
				[ 'https://example.test/f.jpg' ],
			],
			'entities decoded'    => [
				'<img src="https://example.test/g.jpg?w=800&amp;h=600">',
				[ 'https://example.test/g.jpg?w=800&h=600' ],
			],
			'root relative'       => [ '<img src="/wp-content/uploads/h.jpg">', [ 'https://example.test/wp-content/uploads/h.jpg' ] ],
			'protocol relative'   => [ '<img src="//cdn.example.net/i.jpg">', [ 'https://cdn.example.net/i.jpg' ] ],
			'other host kept'     => [ '<img src="https://cdn.example.net/j.jpg">', [ 'https://cdn.example.net/j.jpg' ] ],
			'data uri dropped'    => [ '<img src="data:image/gif;base64,R0lGOD">', [] ],
			'directory relative'  => [ '<img src="images/k.jpg">', [] ],
			'data-src is not src' => [ '<img data-src="https://example.test/l.jpg">', [] ],
			'srcset ignored'      => [
				'<img srcset="https://example.test/m-300.jpg 300w" src="https://example.test/m.jpg">',
				[ 'https://example.test/m.jpg' ],
			],
			'not an img tag'      => [ '<imgx src="https://example.test/n.jpg">', [] ],
		];
	}

	public function test_a_relative_path_takes_the_scheme_and_host_of_the_site(): void {
		$this->assertSame(
			[ 'http://woocommerce.local/wp-content/uploads/o.jpg', 'http://cdn.example.net/p.jpg' ],
			ContentImages::extract( '<img src="/wp-content/uploads/o.jpg"><img src="//cdn.example.net/p.jpg">', 'http://woocommerce.local/sub/' )
		);
	}
}
