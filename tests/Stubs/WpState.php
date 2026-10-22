<?php
/**
 * Mutable state behind the WordPress function stubs.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

namespace Flexa\SeoAeo\Tests\Stubs;

/**
 * Everything the stubbed WordPress functions answer from. One static bag rather
 * than a mock object per function: the code under test calls free functions, so
 * there is nothing to inject, and a test reads better setting two fields than
 * wiring six expectations.
 */
final class WpState {
	/**
	 * Stored options, keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	public static array $options = [];

	/**
	 * Query vars, keyed by name. Anything unset answers ''.
	 *
	 * @var array<string, mixed>
	 */
	public static array $query_vars = [];

	/**
	 * `get_bloginfo()` answers, keyed by the show parameter.
	 *
	 * @var array<string, string>
	 */
	public static array $bloginfo = [
		'name'        => 'Northbank Press',
		'description' => '',
	];

	/**
	 * Registered post type slugs, as `get_post_types()` returns them.
	 *
	 * @var array<string, string>
	 */
	public static array $post_types = [];

	/**
	 * Registered taxonomy slugs, as `get_taxonomies()` returns them.
	 *
	 * @var array<string, string>
	 */
	public static array $taxonomies = [];

	/**
	 * Shortcode tags the request has registered. Empty is the interesting
	 * default: it is the state a description is built in when the plugin that
	 * owns a tag is deactivated.
	 *
	 * @var list<string>
	 */
	public static array $shortcodes = [];

	/**
	 * How many times each action has fired, for `did_action()`.
	 *
	 * @var array<string, int>
	 */
	public static array $did_action = [];

	/**
	 * Actions currently running, for `doing_action()`.
	 *
	 * @var array<string, bool>
	 */
	public static array $doing_action = [];

	/**
	 * Callbacks added with `add_filter()` / `add_action()`, keyed by hook and
	 * kept in insertion order within a priority.
	 *
	 * @var array<string, array<int, list<callable>>>
	 */
	public static array $hooks = [];

	/**
	 * Post IDs that `post_password_required()` answers true for.
	 *
	 * @var list<int>
	 */
	public static array $password_required = [];

	/**
	 * Posts returned by `get_posts()`, in call order. Each test that needs the
	 * function pushes one result.
	 *
	 * @var list<mixed>
	 */
	public static array $get_posts_results = [];

	/**
	 * The arguments every `get_posts()` call was made with, in call order, so a
	 * test can assert the shape of a query as well as its result.
	 *
	 * @var list<array<string, mixed>>
	 */
	public static array $get_posts_args = [];

	/**
	 * Plugin main files that `is_plugin_active()` answers true for.
	 *
	 * @var list<string>
	 */
	public static array $active_plugins = [];

	public static ?object $queried_object = null;

	public static bool $is_singular = false;

	public static bool $is_front_page = false;

	public static bool $is_home = false;

	/**
	 * `get_the_title()` and `get_permalink()` answers, keyed by post ID.
	 *
	 * @var array<int, string>
	 */
	public static array $post_titles = [];

	/**
	 * @var array<int, string>
	 */
	public static array $permalinks = [];

	public static bool $is_404 = false;

	public static string $search_query = '';

	public static string $home_url = 'https://example.test/';

	/**
	 * Reset everything to the state a fresh request starts in.
	 */
	public static function reset(): void {
		self::$options           = [];
		self::$query_vars        = [];
		self::$bloginfo          = [
			'name'        => 'Northbank Press',
			'description' => '',
		];
		self::$post_types        = [];
		self::$taxonomies        = [];
		self::$shortcodes        = [];
		self::$did_action        = [];
		self::$doing_action      = [];
		self::$hooks             = [];
		self::$password_required = [];
		self::$get_posts_results = [];
		self::$get_posts_args    = [];
		self::$active_plugins    = [];
		self::$queried_object    = null;
		self::$is_singular       = false;
		self::$is_front_page     = false;
		self::$is_home           = false;
		self::$post_titles       = [];
		self::$permalinks        = [];
		self::$is_404            = false;
		self::$search_query      = '';
		self::$home_url          = 'https://example.test/';
	}

	/**
	 * Mark an action as having fired, the way `did_action()` sees it.
	 */
	public static function fire( string $hook ): void {
		self::$did_action[ $hook ] = ( self::$did_action[ $hook ] ?? 0 ) + 1;
	}

	/**
	 * Run a hook's callbacks in priority order and return the filtered value.
	 *
	 * @param list<mixed> $args Arguments after the filtered value.
	 */
	public static function filter( string $hook, mixed $value, array $args = [] ): mixed {
		if ( ! isset( self::$hooks[ $hook ] ) ) {
			return $value;
		}

		$priorities = self::$hooks[ $hook ];
		ksort( $priorities );

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}

		return $value;
	}
}
