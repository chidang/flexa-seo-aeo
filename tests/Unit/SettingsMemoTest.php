<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;

/**
 * The per-request memo in Settings::all().
 *
 * Three QA findings meet here. P-07: the option was re-coerced on every read,
 * dozens of times per request. R-02: memoising it meant a read before `init`
 * cached a post-type list that had been pruned against a registry which had not
 * filled up yet, so a CPT registered on `init:20` vanished from the sitemap.
 * R-07: a filter on the raw option added after the first read is invisible, and
 * the escape hatch is flush().
 */
final class SettingsMemoTest extends TestCase {
	public function test_the_option_is_read_once_per_request(): void {
		$reads = 0;
		add_filter(
			'option_' . Settings::OPTION_KEY,
			static function ( mixed $value ) use ( &$reads ): mixed {
				++$reads;

				return $value;
			}
		);

		$this->given_init_finished();
		WpState::$options[ Settings::OPTION_KEY ] = [ 'separator' => '|' ];

		for ( $i = 0; $i < 20; $i++ ) {
			Settings::get( 'separator' );
		}

		$this->assertSame( 1, $reads );
	}

	public function test_flush_makes_the_next_read_hit_the_option_again(): void {
		$this->given_init_finished();
		WpState::$options[ Settings::OPTION_KEY ] = [ 'separator' => '|' ];
		$this->assertSame( '|', Settings::get( 'separator' ) );

		WpState::$options[ Settings::OPTION_KEY ] = [ 'separator' => '·' ];
		$this->assertSame( '|', Settings::get( 'separator' ), 'the memo should still answer' );

		Settings::flush();
		$this->assertSame( '·', Settings::get( 'separator' ) );
	}

	public function test_a_stored_write_is_visible_to_the_next_read(): void {
		$this->given_init_finished();
		Settings::watch();

		WpState::$options[ Settings::OPTION_KEY ] = [ 'knowledge_name' => 'Before' ];
		$this->assertSame( 'Before', Settings::get( 'knowledge_name' ) );

		update_option( Settings::OPTION_KEY, [ 'knowledge_name' => 'After' ] );

		$this->assertSame( 'After', Settings::get( 'knowledge_name' ) );
	}

	public function test_deleting_the_option_drops_the_memo(): void {
		$this->given_init_finished();
		Settings::watch();

		WpState::$options[ Settings::OPTION_KEY ] = [ 'knowledge_name' => 'Stored' ];
		$this->assertSame( 'Stored', Settings::get( 'knowledge_name' ) );

		delete_option( Settings::OPTION_KEY );

		$this->assertSame( '', Settings::get( 'knowledge_name' ), 'back to the schema default' );
	}

	public function test_an_unrelated_deleted_option_leaves_the_memo_alone(): void {
		$this->given_init_finished();
		Settings::watch();

		WpState::$options[ Settings::OPTION_KEY ] = [ 'knowledge_name' => 'Stored' ];
		Settings::get( 'knowledge_name' );

		WpState::$options['something_else'] = 'x';
		delete_option( 'something_else' );

		$this->assertSame( 'Stored', Settings::get( 'knowledge_name' ) );
	}

	/**
	 * R-07, the documented caveat: a filter on the raw option added after the
	 * first read does not take effect until the memo is dropped.
	 */
	public function test_a_filter_added_after_the_first_read_needs_a_flush(): void {
		$this->given_init_finished();
		WpState::$options[ Settings::OPTION_KEY ] = [ 'knowledge_name' => 'Stored' ];

		$this->assertSame( 'Stored', Settings::get( 'knowledge_name' ) );

		add_filter(
			'option_' . Settings::OPTION_KEY,
			static function ( mixed $value ): mixed {
				$value['knowledge_name'] = 'Filtered';

				return $value;
			}
		);

		$this->assertSame( 'Stored', Settings::get( 'knowledge_name' ), 'invisible until flushed' );

		Settings::flush();

		$this->assertSame( 'Filtered', Settings::get( 'knowledge_name' ) );
	}

	/* ---------------------------------------------------------------------
	 * R-02: a value resolved before init must not be cached for the request.
	 * ------------------------------------------------------------------ */

	public function test_a_post_type_list_resolved_before_init_is_rebuilt_afterwards(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [
			'sitemap_post_types' => [ 'post', 'page', 'qa_late_cpt' ],
		];

		// A plugin on plugins_loaded reads a setting. The registry is empty, so
		// pruning has to be skipped rather than applied to nothing.
		$this->assertSame(
			[ 'post', 'page', 'qa_late_cpt' ],
			Settings::get( 'sitemap_post_types' ),
			'nothing is pruned before init'
		);

		// init runs; the late CPT registers.
		WpState::$post_types = [
			'post'        => 'post',
			'page'        => 'page',
			'qa_late_cpt' => 'qa_late_cpt',
		];
		$this->given_init_finished();

		$this->assertSame(
			[ 'post', 'page', 'qa_late_cpt' ],
			Settings::get( 'sitemap_post_types' ),
			'the early memo must not survive into the request'
		);
	}

	/**
	 * The same thing from the side where the two answers differ: the early read
	 * keeps a slug the registry has not seen, and the read after `init` must
	 * drop it. A memo that survives the boundary would still be serving the
	 * unpruned list.
	 */
	public function test_an_early_memo_is_not_served_after_init(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [
			'sitemap_post_types' => [ 'post', 'qa_never_registers' ],
		];

		$this->assertSame(
			[ 'post', 'qa_never_registers' ],
			Settings::get( 'sitemap_post_types' ),
			'nothing is pruned before init'
		);

		WpState::$post_types = [ 'post' => 'post' ];
		$this->given_init_finished();

		$this->assertSame(
			[ 'post' ],
			Settings::get( 'sitemap_post_types' ),
			'the slug is pruned once the registry is complete'
		);
	}

	public function test_the_early_memo_is_rebuilt_once_and_then_held(): void {
		$reads = 0;
		add_filter(
			'option_' . Settings::OPTION_KEY,
			static function ( mixed $value ) use ( &$reads ): mixed {
				++$reads;

				return $value;
			}
		);
		WpState::$options[ Settings::OPTION_KEY ] = [ 'sitemap_post_types' => [ 'post' ] ];

		Settings::get( 'separator' );
		$this->assertSame( 1, $reads, 'the early read' );

		WpState::$post_types = [ 'post' => 'post' ];
		$this->given_init_finished();

		Settings::get( 'separator' );
		$this->assertSame( 2, $reads, 'one rebuild once the registry is complete' );

		for ( $i = 0; $i < 10; $i++ ) {
			Settings::get( 'separator' );
		}
		$this->assertSame( 2, $reads, 'and no rebuild after that' );
	}

	public function test_a_slug_that_never_registers_is_dropped_after_init(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [
			'sitemap_post_types' => [ 'post', 'qa_never_registers' ],
		];
		WpState::$post_types                      = [
			'post' => 'post',
			'page' => 'page',
		];
		$this->given_init_finished();

		$this->assertSame( [ 'post' ], Settings::get( 'sitemap_post_types' ) );
	}

	/**
	 * Half way through `init` is the dangerous moment: the registry answers, it
	 * just answers incompletely, so pruning there would drop a type that is
	 * about to register.
	 */
	public function test_pruning_is_skipped_while_init_is_still_running(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [
			'sitemap_post_types' => [ 'post', 'qa_late_cpt' ],
		];
		WpState::$post_types                      = [ 'post' => 'post' ];

		WpState::fire( 'init' );
		WpState::$doing_action['init'] = true;

		$this->assertSame( [ 'post', 'qa_late_cpt' ], Settings::get( 'sitemap_post_types' ) );
	}

	public function test_taxonomies_are_pruned_the_same_way(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [
			'sitemap_taxonomies' => [ 'category', 'qa_gone' ],
		];
		WpState::$taxonomies                      = [
			'category' => 'category',
			'post_tag' => 'post_tag',
		];
		$this->given_init_finished();

		$this->assertSame( [ 'category' ], Settings::get( 'sitemap_taxonomies' ) );
	}

	public function test_an_empty_stored_list_is_left_empty(): void {
		WpState::$options[ Settings::OPTION_KEY ] = [ 'sitemap_post_types' => [] ];
		WpState::$post_types                      = [ 'post' => 'post' ];
		$this->given_init_finished();

		$this->assertSame( [], Settings::get( 'sitemap_post_types' ) );
	}

	public function test_a_corrupt_option_falls_back_to_defaults(): void {
		$this->given_init_finished();
		WpState::$options[ Settings::OPTION_KEY ] = 'not an array';

		$this->assertSame( '-', Settings::get( 'separator' ) );
		$this->assertSame( [ 'post', 'page' ], Settings::get( 'sitemap_post_types' ) );
	}

	public function test_an_unknown_key_reads_as_null(): void {
		$this->given_init_finished();

		$this->assertNull( Settings::get( 'no_such_setting' ) );
	}
}
