<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\PostSummary;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_Post;

/**
 * The one-line summary every public surface quotes. Two defects found in QA
 * live here: a password-protected post leaking its body (0.5.6 security fix)
 * and an unregistered shortcode being published as the page's own words (S-02).
 */
final class PostSummaryTest extends TestCase {
	public function test_condense_collapses_markup_and_whitespace(): void {
		$this->assertSame(
			'What is QA? It is testing.',
			PostSummary::condense( "<p>What is QA?</p>\n<p>It is testing.</p>" )
		);
	}

	public function test_condense_keeps_words_closed_up_inside_a_sentence(): void {
		$this->assertSame(
			'A bold word and a link here.',
			PostSummary::condense( 'A <strong>bold</strong> word and a <a href="/x">link</a> here.' )
		);
	}

	public function test_condense_drops_a_shortcode_whose_plugin_is_not_loaded(): void {
		$this->assertSame( '', PostSummary::condense( '[contact-form-7 id="12"]' ) );
	}

	public function test_condense_keeps_prose_around_a_dead_shortcode(): void {
		$this->assertSame(
			'Real prose before. Real prose after.',
			PostSummary::condense( 'Real prose before. [contact-form-7 id="12"] Real prose after.' )
		);
	}

	public function test_condense_unwraps_a_page_builder_layout(): void {
		$this->assertSame(
			'Builder prose here.',
			PostSummary::condense( '[vc_row][vc_column]Builder prose here.[/vc_column][/vc_row]' )
		);
	}

	public function test_condense_lets_core_strip_a_registered_shortcode_first(): void {
		WpState::$shortcodes = [ 'gallery' ];

		$this->assertSame(
			'Before after.',
			PostSummary::condense( 'Before [gallery ids="1,2"]caption[/gallery] after.' )
		);
	}

	/**
	 * The pattern has to be narrow enough to leave prose alone. These are the
	 * bracket shapes real posts contain.
	 */
	#[DataProvider( 'bracketed_prose' )]
	public function test_condense_keeps_bracketed_prose( string $raw, string $expected ): void {
		$this->assertSame( $expected, PostSummary::condense( $raw ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function bracketed_prose(): array {
		return [
			'footnote marker'  => [ 'Footnote [1] here.', 'Footnote [1] here.' ],
			'array index'      => [ 'The value a[i] is read.', 'The value a[i] is read.' ],
			'bracketed phrase' => [ 'Pick [a or b] now.', 'Pick [a or b] now.' ],
			'bracketed year'   => [ 'Published [2026] already.', 'Published [2026] already.' ],
			'unclosed bracket' => [ 'An unclosed [ bracket.', 'An unclosed [ bracket.' ],
		];
	}

	/**
	 * The documented cost of the pattern: a bracketed word is indistinguishable
	 * from a shortcode tag. Pinned so a future widening of the regex is a
	 * deliberate decision rather than a surprise.
	 */
	#[DataProvider( 'accepted_collateral' )]
	public function test_condense_also_removes_a_bracketed_word( string $raw, string $expected ): void {
		$this->assertSame( $expected, PostSummary::condense( $raw ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function accepted_collateral(): array {
		return [
			'editorial sic' => [ 'Marked [sic] in the quote.', 'Marked in the quote.' ],
			'key name'      => [ 'Press [enter] to continue.', 'Press to continue.' ],
			'markdown link' => [ 'See [the docs](https://example.test/d) for more.', 'See (https://example.test/d) for more.' ],
		];
	}

	public function test_text_returns_nothing_for_a_password_protected_post(): void {
		$post = new WP_Post(
			[
				'ID'            => 7,
				'post_content'  => 'The secret is hunter2.',
				'post_excerpt'  => 'The secret is also here.',
				'post_password' => 'secret',
			]
		);

		WpState::$password_required = [ 7 ];

		$this->assertSame( '', PostSummary::text( $post, 160 ) );
	}

	public function test_text_prefers_a_manual_excerpt(): void {
		$post = new WP_Post(
			[
				'ID'           => 8,
				'post_content' => 'The body text.',
				'post_excerpt' => 'The hand-written excerpt.',
			]
		);

		$this->assertSame( 'The hand-written excerpt.', PostSummary::text( $post, 160 ) );
	}

	public function test_text_falls_back_to_the_body(): void {
		$post = new WP_Post(
			[
				'ID'           => 9,
				'post_content' => '<p>The body text.</p>',
			]
		);

		$this->assertSame( 'The body text.', PostSummary::text( $post, 160 ) );
	}

	public function test_truncate_cuts_on_a_word_boundary(): void {
		$this->assertSame( 'One two three…', PostSummary::truncate( 'One two three four five', 17 ) );
	}

	public function test_truncate_leaves_short_text_alone(): void {
		$this->assertSame( 'Short enough.', PostSummary::truncate( 'Short enough.', 160 ) );
	}

	public function test_truncate_counts_characters_not_bytes(): void {
		$this->assertSame( 'Đây là…', PostSummary::truncate( 'Đây là một câu tiếng Việt', 8 ) );
	}
}
