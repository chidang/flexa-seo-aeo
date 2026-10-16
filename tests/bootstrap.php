<?php
/**
 * PHPUnit bootstrap.
 *
 * These are unit tests: WordPress is never loaded. The functions the code under
 * test calls are declared in Stubs/wp-functions.php and answer from
 * {@see \Flexa\SeoAeo\Tests\Stubs\WpState}, which every test resets.
 *
 * The stubs are a test fixture, not an emulator of WordPress. A test must never
 * assert something whose outcome depends on how faithful a stub is: assert on
 * the plugin's own branching, coercion and string handling, and let the
 * integration side of QA cover whatever core actually does.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/fake-wp/' );

require_once __DIR__ . '/phpstan-constants.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/WpState.php';
require_once __DIR__ . '/Stubs/wp-classes.php';
require_once __DIR__ . '/Stubs/wp-functions.php';
