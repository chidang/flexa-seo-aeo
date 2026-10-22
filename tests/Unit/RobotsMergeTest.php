<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Actions\Front\Metas;
use Flexa\SeoAeo\Services\ResolvedMeta;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * How the plugin's directives merge into core's `wp_robots` array.
 *
 * The 0.6.1 compatibility run showed that one robots tag can still carry the
 * wrong value, so these cases assert the effective directives, not whether a
 * tag exists. They cover only what Flexa's own filter does with the array it
 * is handed. What a competitor does after priority 20 is outside this filter
 * and is pinned by the fixtures under `tests/Compat/`.
 */
final class RobotsMergeTest extends TestCase {
	/** What the plugin resolves for an indexable post with default settings. */
	private const INDEXABLE = [ 'index', 'follow', 'max-snippet:-1', 'max-image-preview:large', 'max-video-preview:-1' ];

	protected function setUp(): void {
		parent::setUp();

		$this->given_init_finished();
		$this->given_settings( [ 'titles_metas' => true ] );
	}

	/**
	 * @param array<string, string|bool> $incoming What core (or an earlier filter) passed in.
	 * @param list<string>               $ours     What the plugin resolved for the request.
	 * @param string                     $expected The effective directives, sorted.
	 */
	#[DataProvider( 'merge_cases' )]
	public function test_the_merged_array_carries_the_expected_directives( array $incoming, array $ours, string $expected ): void {
		$this->assertSame( $expected, $this->effective( $this->merge( $incoming, $ours ) ) );
	}

	/**
	 * @return array<string, array{array<string, string|bool>, list<string>, string}>
	 */
	public static function merge_cases(): array {
		return [
			'indexable post, nothing from core'           => [
				[],
				self::INDEXABLE,
				'follow, index, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
			],
			// Core adds this at priority 10 on every public site.
			'indexable post, core image preview already set' => [
				[ 'max-image-preview' => 'large' ],
				self::INDEXABLE,
				'follow, index, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
			],
			'post set to noindex drops index and the preview hints' => [
				[ 'max-image-preview' => 'large' ],
				[ 'noindex', 'follow' ],
				'follow, noindex',
			],
			'post set to nofollow drops follow from core' => [
				[ 'follow' => true ],
				[ 'index', 'nofollow' ],
				'index, nofollow',
			],
			// Embeds and "Discourage search engines" reach the filter as core's noindex.
			'core noindex outranks an indexable post'     => [
				[
					'noindex'           => true,
					'max-image-preview' => 'large',
				],
				self::INDEXABLE,
				'follow, noindex',
			],
			'core nofollow outranks a followable post'    => [
				[ 'nofollow' => true ],
				[ 'index', 'follow' ],
				'index, nofollow',
			],
			'directives the plugin never sets are kept'   => [
				[
					'noarchive'   => true,
					'notranslate' => true,
				],
				[ 'index', 'follow' ],
				'follow, index, noarchive, notranslate',
			],
			// Not a latch: the later writer wins, and the plugin filters at 20.
			'the plugin value wins a valued hint'         => [
				[ 'max-snippet' => '50' ],
				[ 'index', 'follow', 'max-snippet:-1' ],
				'follow, index, max-snippet:-1',
			],
			'a false flag from core is not a refusal'     => [
				[
					'noindex'  => false,
					'nofollow' => false,
				],
				[ 'index', 'follow' ],
				'follow, index',
			],
		];
	}

	public function test_the_array_passes_through_untouched_when_the_module_is_off(): void {
		$this->given_settings( [ 'titles_metas' => false ] );

		$incoming = [
			'max-image-preview' => 'large',
			'noarchive'         => true,
		];

		$this->assertSame( $incoming, $this->merge( $incoming, [ 'noindex', 'nofollow' ] ) );
	}

	public function test_noindex_and_index_never_come_out_together(): void {
		$merged = $this->merge( [ 'index' => true ], [ 'noindex', 'follow' ] );

		$this->assertArrayNotHasKey( 'index', $merged );
		$this->assertTrue( $merged['noindex'] );
	}

	public function test_nofollow_and_follow_never_come_out_together(): void {
		$merged = $this->merge( [ 'nofollow' => true ], [ 'index', 'follow' ] );

		$this->assertArrayNotHasKey( 'follow', $merged );
		$this->assertTrue( $merged['nofollow'] );
	}

	/* ---------------------------------------------------------------------
	 * The compatibility fixture. The live runner compares real pages against
	 * it, so its Flexa-alone values must be what this filter really produces.
	 * ------------------------------------------------------------------ */

	public function test_every_fixture_case_names_a_configuration_with_assertions(): void {
		$fixture = $this->fixture();

		foreach ( $fixture['cases'] as $case ) {
			$this->assertArrayHasKey( $case['configuration'], $fixture['configurations'], $case['id'] );
			$this->assertArrayHasKey( $case['configuration'], $fixture['assertions'], $case['id'] );
		}
	}

	/**
	 * Flexa alone on a public site: core contributes its image preview hint at
	 * priority 10, then the plugin's filter runs on top of it.
	 */
	public function test_the_flexa_alone_cases_match_what_the_filter_produces(): void {
		$resolved = [
			'default'      => self::INDEXABLE,
			'post-noindex' => [ 'noindex', 'follow' ],
		];

		$checked = 0;

		foreach ( $this->fixture()['cases'] as $case ) {
			if ( null !== $case['competitor'] ) {
				continue;
			}

			$merged = $this->merge( [ 'max-image-preview' => 'large' ], $resolved[ $case['configuration'] ] );

			$this->assertSame( $case['exact'], $this->effective( $merged ), $case['id'] );
			++$checked;
		}

		$this->assertSame( 2, $checked, 'both Flexa-alone cases should be in the fixture' );
	}

	/**
	 * @return array{configurations: array<string, mixed>, assertions: array<string, mixed>, cases: list<array<string, mixed>>}
	 */
	private function fixture(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local fixture, read without WordPress loaded.
		$json = (string) file_get_contents( dirname( __DIR__ ) . '/Compat/fixtures/robots.json' );

		return json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
	}

	/**
	 * Run the filter with a resolved meta already in place, so the test does
	 * not depend on how the service builds one.
	 *
	 * @param array<string, string|bool> $incoming
	 * @param list<string>               $ours
	 * @return array<string, string|bool|int>
	 */
	private function merge( array $incoming, array $ours ): array {
		$metas = $this->bare( Metas::class );
		$this->set_private( $metas, 'resolved', new ResolvedMeta( '', '', '', $ours, [], [] ) );

		return $metas->filter_robots( $incoming );
	}

	/**
	 * The array as `wp_robots()` would print it, sorted so the assertion reads
	 * the same whatever order the keys were merged in. Search engines ignore
	 * the order too.
	 *
	 * @param array<string, string|bool|int> $robots
	 */
	private function effective( array $robots ): string {
		$directives = [];

		foreach ( $robots as $key => $value ) {
			if ( true === $value ) {
				$directives[] = $key;
			} elseif ( is_string( $value ) || is_int( $value ) ) {
				$directives[] = $key . ':' . $value;
			}
		}

		sort( $directives );

		return implode( ', ', $directives );
	}
}
