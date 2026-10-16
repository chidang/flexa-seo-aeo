<?php
/**
 * Shared base for the unit tests.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests;

use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Tests\Stubs\WpState;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionClass;

/**
 * Resets everything that survives between tests: the stub state, the Settings
 * per-request memo, and the globals the services read.
 */
abstract class TestCase extends PhpUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		WpState::reset();
		Settings::flush();

		unset( $GLOBALS['wp_query'], $GLOBALS['wp_rewrite'], $GLOBALS['wpdb'] );
	}

	protected function tearDown(): void {
		WpState::reset();
		Settings::flush();

		unset( $GLOBALS['wp_query'], $GLOBALS['wp_rewrite'], $GLOBALS['wpdb'] );

		parent::tearDown();
	}

	/**
	 * Store a settings payload the way the option really holds it: whatever the
	 * caller passes, merged over nothing, so a test can set one key and leave
	 * the rest to the schema defaults.
	 *
	 * @param array<string, mixed> $settings
	 */
	protected function given_settings( array $settings ): void {
		WpState::$options[ Settings::OPTION_KEY ] = $settings;
		Settings::flush();
	}

	/**
	 * Put the request past `init`, which is where most of the plugin runs.
	 */
	protected function given_init_finished(): void {
		WpState::fire( 'init' );
		WpState::$doing_action['init'] = false;
	}

	/**
	 * Build an instance without running the constructor, for a service whose
	 * constructor reaches for collaborators the test does not need.
	 */
	protected function bare( string $class_name ): object {
		return ( new ReflectionClass( $class_name ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Call a private or protected method.
	 *
	 * @param array<int, mixed> $args
	 */
	protected function call_private( object $target, string $method, array $args = [] ): mixed {
		// No setAccessible() call: the plugin requires PHP 8.2, where private
		// members are already reachable through reflection.
		$m = ( new ReflectionClass( $target ) )->getMethod( $method );

		return $m->invokeArgs( $target, $args );
	}

	/**
	 * Write a private or protected property.
	 */
	protected function set_private( object $target, string $property, mixed $value ): void {
		( new ReflectionClass( $target ) )->getProperty( $property )->setValue( $target, $value );
	}
}
