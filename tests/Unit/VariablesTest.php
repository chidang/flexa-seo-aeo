<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Services\Variables;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Token substitution in title and description templates.
 *
 * Two QA findings are pinned here. R-03: an empty token left the separator
 * beside it stranded, so a site with no tagline shipped "Site - - Page 2".
 * S-04: the first fix for that collapsed any spaced run of the separator, so a
 * post genuinely titled "A -- B" was rewritten to "A - B" on a hyphen site.
 * Both have to hold at once, which is the whole difficulty of this method.
 */
final class VariablesTest extends TestCase {
	/**
	 * Every separator the schema allows. A rule written as a regex has to be
	 * checked against all of them: `-` and `|` need quoting, `–` and `»` are
	 * multibyte.
	 *
	 * @return list<string>
	 */
	public static function separators(): array {
		return [ '-', '–', '—', '·', '•', '|', '/', '»', '>' ];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function separator_cases(): array {
		$out = [];
		foreach ( self::separators() as $sep ) {
			$out[ 'separator ' . $sep ] = [ $sep ];
		}

		return $out;
	}

	private function given_separator( string $sep ): void {
		$this->given_settings( [ 'separator' => $sep ] );
	}

	public function test_a_subject_without_tokens_is_returned_trimmed(): void {
		$this->assertSame( 'Plain title', Variables::replace( '  Plain title  ' ) );
	}

	public function test_global_tokens_resolve(): void {
		WpState::$bloginfo['name']        = 'Northbank Press';
		WpState::$bloginfo['description'] = 'Books and prints';
		$this->given_separator( '-' );

		$this->assertSame(
			'Northbank Press - Books and prints',
			Variables::replace( '%%sitename%% %%sep%% %%tagline%%' )
		);
	}

	public function test_context_wins_over_a_global_token(): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		$this->given_separator( '-' );

		$this->assertSame(
			'A post title - Northbank Press',
			Variables::replace( '%%title%% %%sep%% %%sitename%%', [ 'title' => 'A post title' ] )
		);
	}

	public function test_an_unknown_token_resolves_to_nothing(): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		$this->given_separator( '-' );

		$this->assertSame( 'Northbank Press', Variables::replace( '%%sitename%% %%sep%% %%nosuchtoken%%' ) );
	}

	public function test_the_page_token_is_empty_on_page_one(): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		WpState::$query_vars       = [ 'paged' => 1 ];
		$this->given_separator( '-' );

		$this->assertSame( 'Northbank Press', Variables::replace( '%%sitename%% %%sep%% %%page%%' ) );
	}

	public function test_the_page_token_reads_the_page_var_on_a_static_front_page(): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		WpState::$query_vars       = [ 'page' => 3 ];
		$this->given_separator( '-' );

		$this->assertSame( 'Northbank Press - 3', Variables::replace( '%%sitename%% %%sep%% %%page%%' ) );
	}

	public function test_extensions_can_add_a_token(): void {
		$this->given_separator( '-' );
		add_filter(
			'flexa_seo_aeo/variables/tokens',
			static function ( array $tokens ): array {
				$tokens['brand'] = 'Flexa';

				return $tokens;
			}
		);

		$this->assertSame( 'Flexa', Variables::replace( '%%brand%%' ) );
	}

	/* ---------------------------------------------------------------------
	 * R-03: an empty token must take its separator with it.
	 * ------------------------------------------------------------------ */

	#[DataProvider( 'separator_cases' )]
	public function test_an_empty_token_at_the_end_leaves_no_trailing_separator( string $sep ): void {
		WpState::$bloginfo['name']        = 'Northbank Press';
		WpState::$bloginfo['description'] = '';
		$this->given_separator( $sep );

		$this->assertSame( 'Northbank Press', Variables::replace( '%%sitename%% %%sep%% %%tagline%%' ) );
	}

	#[DataProvider( 'separator_cases' )]
	public function test_an_empty_token_at_the_front_leaves_no_leading_separator( string $sep ): void {
		WpState::$bloginfo['name']        = 'Northbank Press';
		WpState::$bloginfo['description'] = '';
		$this->given_separator( $sep );

		$this->assertSame( 'Northbank Press', Variables::replace( '%%tagline%% %%sep%% %%sitename%%' ) );
	}

	/**
	 * The shape R-03 was reported from: a page suffix moves the empty tagline
	 * into the middle of the string instead of the end.
	 */
	#[DataProvider( 'separator_cases' )]
	public function test_an_empty_token_in_the_middle_does_not_double_the_separator( string $sep ): void {
		WpState::$bloginfo['name']        = 'Northbank Press';
		WpState::$bloginfo['description'] = '';
		WpState::$query_vars              = [ 'paged' => 2 ];
		$this->given_separator( $sep );

		$this->assertSame(
			'Northbank Press ' . $sep . ' 2',
			Variables::replace( '%%sitename%% %%sep%% %%tagline%% %%sep%% %%page%%' )
		);
	}

	#[DataProvider( 'separator_cases' )]
	public function test_a_template_of_nothing_but_empty_tokens_resolves_to_nothing( string $sep ): void {
		WpState::$bloginfo['description'] = '';
		$this->given_separator( $sep );

		$this->assertSame( '', Variables::replace( '%%tagline%% %%sep%% %%tagline%% %%sep%% %%tagline%%' ) );
	}

	/* ---------------------------------------------------------------------
	 * S-04: real text must pass through byte for byte.
	 * ------------------------------------------------------------------ */

	/**
	 * A title containing a spaced run of the site's own separator. This is the
	 * S-04 regression: it used to come back collapsed to a single separator.
	 */
	#[DataProvider( 'separator_cases' )]
	public function test_a_doubled_separator_in_real_text_survives( string $sep ): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		$this->given_separator( $sep );

		$title = 'A ' . $sep . $sep . ' B';

		$this->assertSame(
			$title . ' ' . $sep . ' Northbank Press',
			Variables::replace( '%%title%% %%sep%% %%sitename%%', [ 'title' => $title ] )
		);
	}

	#[DataProvider( 'separator_cases' )]
	public function test_a_separator_inside_a_word_survives( string $sep ): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		$this->given_separator( $sep );

		$title = 'Re' . $sep . $sep . 'run the build';

		$this->assertSame(
			$title . ' ' . $sep . ' Northbank Press',
			Variables::replace( '%%title%% %%sep%% %%sitename%%', [ 'title' => $title ] )
		);
	}

	#[DataProvider( 'separator_cases' )]
	public function test_a_single_spaced_separator_in_real_text_survives( string $sep ): void {
		WpState::$bloginfo['name'] = 'Northbank Press';
		$this->given_separator( $sep );

		$title = 'A ' . $sep . ' B';

		$this->assertSame(
			$title . ' ' . $sep . ' Northbank Press',
			Variables::replace( '%%title%% %%sep%% %%sitename%%', [ 'title' => $title ] )
		);
	}

	/**
	 * The sentinel that marks an empty token is a private-use codepoint, and it
	 * must never reach the page whatever the input does.
	 */
	#[DataProvider( 'separator_cases' )]
	public function test_the_hole_sentinel_never_leaks( string $sep ): void {
		WpState::$bloginfo['description'] = '';
		$this->given_separator( $sep );

		$out = Variables::replace(
			'%%tagline%% %%sep%% %%title%% %%sep%% %%tagline%%',
			[ 'title' => "Contains \u{E000} already" ]
		);

		$this->assertStringNotContainsString( "\u{E000}", $out );
	}
}
