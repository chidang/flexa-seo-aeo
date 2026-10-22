<?php
/**
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Unit;

use Flexa\SeoAeo\Admin\ConflictNotice;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use Flexa\SeoAeo\Tests\TestCase;

/**
 * What a dismissal of the conflict notice is stored as. Revision 2 added the
 * noindex warning, and a user who hid the notice for Rank Math before then
 * has to see it again, or they never learn that their noindex is ignored.
 */
final class ConflictNoticeTest extends TestCase {
	public function test_the_key_is_the_active_plugins_plus_the_revision(): void {
		WpState::$active_plugins = [ 'wordpress-seo/wp-seo.php', 'seo-by-rank-math/rank-math.php' ];

		$this->assertSame( 'rankmath,yoast@2', $this->key() );
	}

	public function test_a_dismissal_saved_before_revisions_no_longer_matches(): void {
		WpState::$active_plugins = [ 'seo-by-rank-math/rank-math.php' ];

		$this->assertNotSame( 'rankmath', $this->key() );
	}

	public function test_a_new_plugin_still_changes_the_key(): void {
		WpState::$active_plugins = [ 'wordpress-seo/wp-seo.php' ];
		$before                  = $this->key();

		WpState::$active_plugins[] = 'wp-seopress/seopress.php';

		$this->assertNotSame( $before, $this->key() );
	}

	private function key(): string {
		return $this->call_private( $this->bare( ConflictNotice::class ), 'dismissal_key' );
	}
}
