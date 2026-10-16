<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The typed schema: what a write is allowed to persist, and what a corrupt or
 * hand-edited option is coerced back to on the way out.
 *
 * The sanitizer is the only thing between the REST payload and the stored
 * option, so the cases that matter are the ones a client could get wrong on
 * purpose: an unknown key, a value of the wrong type, a separator that is not
 * on the list.
 */
final class SettingsSchemaTest extends TestCase {
	public function test_an_unknown_key_is_never_persisted(): void {
		$clean = Settings::sanitize(
			[
				'separator'     => '|',
				'evil_key'      => 'dropped',
				'option_update' => [ 'nested' => 'dropped' ],
			]
		);

		$this->assertSame( [ 'separator' => '|' ], $clean );
	}

	public function test_a_partial_payload_touches_only_the_keys_it_names(): void {
		$clean = Settings::sanitize( [ 'breadcrumbs' => true ] );

		$this->assertSame( [ 'breadcrumbs' => true ], $clean );
	}

	/**
	 * @param mixed $input    What the client sent.
	 * @param bool  $expected What it must be stored as.
	 */
	#[DataProvider( 'boolean_inputs' )]
	public function test_toggles_are_coerced_to_real_booleans( mixed $input, bool $expected ): void {
		$clean = Settings::sanitize( [ 'xml_sitemap' => $input ] );

		$this->assertSame( $expected, $clean['xml_sitemap'] );
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public static function boolean_inputs(): array {
		return [
			'true'         => [ true, true ],
			'false'        => [ false, false ],
			'string one'   => [ '1', true ],
			'string zero'  => [ '0', false ],
			'string true'  => [ 'true', true ],
			'string false' => [ 'false', false ],
			'string on'    => [ 'on', true ],
			'string off'   => [ 'off', false ],
			'string no'    => [ 'no', false ],
			'string yes'   => [ 'yes', true ],
			'empty string' => [ '', false ],
			'padded false' => [ '  FALSE  ', false ],
			'integer one'  => [ 1, true ],
			'integer zero' => [ 0, false ],
			'null'         => [ null, false ],
		];
	}

	#[DataProvider( 'allowed_separators' )]
	public function test_an_allowed_separator_is_kept( string $sep ): void {
		$clean = Settings::sanitize( [ 'separator' => $sep ] );

		$this->assertSame( $sep, $clean['separator'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function allowed_separators(): array {
		$out = [];
		foreach ( [ '-', '–', '—', '·', '•', '|', '/', '»', '>' ] as $sep ) {
			$out[ $sep ] = [ $sep ];
		}

		return $out;
	}

	#[DataProvider( 'rejected_separators' )]
	public function test_a_separator_off_the_list_falls_back_to_a_hyphen( mixed $input ): void {
		$clean = Settings::sanitize( [ 'separator' => $input ] );

		$this->assertSame( '-', $clean['separator'] );
	}

	/**
	 * A separator is interpolated into a regex downstream, so a value that is
	 * not on the list must never reach storage.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function rejected_separators(): array {
		return [
			'regex metacharacter' => [ '.*' ],
			'backreference'       => [ '\\1' ],
			'dollar'              => [ '$' ],
			'html'                => [ '<b>' ],
			'empty'               => [ '' ],
			'array'               => [ [ '-' ] ],
			'null'                => [ null ],
			'long string'         => [ '----------' ],
		];
	}

	public function test_a_twitter_card_type_off_the_list_falls_back(): void {
		$this->assertSame(
			'summary_large_image',
			Settings::sanitize( [ 'twitter_card_type' => 'player' ] )['twitter_card_type']
		);
		$this->assertSame(
			'summary',
			Settings::sanitize( [ 'twitter_card_type' => 'summary' ] )['twitter_card_type']
		);
	}

	public function test_a_knowledge_type_off_the_list_falls_back(): void {
		$this->assertSame(
			'organization',
			Settings::sanitize( [ 'knowledge_type' => 'robot' ] )['knowledge_type']
		);
		$this->assertSame(
			'person',
			Settings::sanitize( [ 'knowledge_type' => 'person' ] )['knowledge_type']
		);
	}

	#[DataProvider( 'indexnow_keys' )]
	public function test_the_indexnow_key_is_reduced_to_a_url_safe_token( mixed $input, string $expected ): void {
		$this->assertSame( $expected, Settings::sanitize( [ 'indexnow_key' => $input ] )['indexnow_key'] );
	}

	/**
	 * The key becomes a filename served at the site root, so anything that
	 * could escape the path has to go.
	 *
	 * @return array<string, array{mixed, string}>
	 */
	public static function indexnow_keys(): array {
		return [
			'plain token'    => [ 'abc123-DEF', 'abc123-DEF' ],
			'path traversal' => [ '../../etc/passwd', 'etcpasswd' ],
			'slashes'        => [ 'a/b\\c', 'abc' ],
			'dots'           => [ 'key.txt', 'keytxt' ],
			'spaces'         => [ 'a b c', 'abc' ],
			'null byte'      => [ "abc\0def", 'abcdef' ],
			'query string'   => [ 'abc?x=1', 'abcx1' ],
			'array'          => [ [ 'abc' ], '' ],
		];
	}

	public function test_a_post_type_list_drops_empty_and_duplicate_slugs(): void {
		WpState::$post_types = [
			'post' => 'post',
			'page' => 'page',
		];
		$this->given_init_finished();

		$clean = Settings::sanitize( [ 'sitemap_post_types' => [ 'post', 'post', '', 'page', '!!!' ] ] );

		$this->assertSame( [ 'post', 'page' ], $clean['sitemap_post_types'] );
	}

	public function test_a_post_type_list_that_is_not_a_list_becomes_empty(): void {
		$this->assertSame( [], Settings::sanitize( [ 'sitemap_post_types' => 'post' ] )['sitemap_post_types'] );
	}

	public function test_a_nested_aeo_payload_keeps_the_keys_it_does_not_name(): void {
		$this->given_init_finished();
		$this->given_settings(
			[
				'aeo' => [
					'enabled'          => true,
					'schema'           => true,
					'site_description' => 'Kept.',
				],
			]
		);

		$clean = Settings::sanitize( [ 'aeo' => [ 'commerce' => true ] ] );

		$this->assertTrue( $clean['aeo']['commerce'], 'the submitted toggle is applied' );
		$this->assertTrue( $clean['aeo']['enabled'], 'an untouched toggle survives' );
		$this->assertTrue( $clean['aeo']['schema'], 'an untouched toggle survives' );
		$this->assertSame( 'Kept.', $clean['aeo']['site_description'], 'untouched text survives' );
	}

	public function test_the_aeo_group_always_comes_back_in_its_full_shape(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'aeo' => [ 'enabled' => true ] ] );

		$aeo = Settings::get( 'aeo' );

		$this->assertSame( array_keys( Settings::aeo_defaults() ), array_keys( $aeo ) );
		$this->assertTrue( $aeo['enabled'] );
		$this->assertFalse( $aeo['schema'] );
	}

	public function test_a_corrupt_aeo_group_is_replaced_by_defaults(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'aeo' => 'not an array' ] );

		$this->assertSame( Settings::aeo_defaults(), Settings::get( 'aeo' ) );
	}

	public function test_get_aeo_reads_a_single_nested_key(): void {
		$this->given_init_finished();
		$this->given_settings( [ 'aeo' => [ 'site_name' => 'Northbank' ] ] );

		$this->assertSame( 'Northbank', Settings::get_aeo( 'site_name' ) );
		$this->assertFalse( Settings::get_aeo( 'enabled' ) );
	}

	public function test_every_toggle_defaults_off(): void {
		$this->given_init_finished();

		foreach ( Settings::defaults() as $key => $value ) {
			if ( is_bool( $value ) ) {
				$this->assertFalse( $value, $key . ' must default off' );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * The admin brand label.
	 * ------------------------------------------------------------------ */

	public function test_the_brand_name_defaults_to_flexa_seo(): void {
		$this->given_init_finished();

		$this->assertSame( 'Flexa SEO', Settings::brand_name() );
	}

	public function test_a_white_label_name_replaces_the_brand(): void {
		$this->given_init_finished();
		$this->given_settings(
			[
				'whitelabel'      => true,
				'whitelabel_name' => 'Acme SEO',
			]
		);

		$this->assertSame( 'Acme SEO', Settings::brand_name() );
	}

	public function test_a_white_label_name_is_ignored_while_the_toggle_is_off(): void {
		$this->given_init_finished();
		$this->given_settings(
			[
				'whitelabel'      => false,
				'whitelabel_name' => 'Acme SEO',
			]
		);

		$this->assertSame( 'Flexa SEO', Settings::brand_name() );
	}

	public function test_a_blank_white_label_name_falls_back_to_the_brand(): void {
		$this->given_init_finished();
		$this->given_settings(
			[
				'whitelabel'      => true,
				'whitelabel_name' => '   ',
			]
		);

		$this->assertSame( 'Flexa SEO', Settings::brand_name() );
	}
}
