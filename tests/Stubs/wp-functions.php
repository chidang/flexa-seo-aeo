<?php
/**
 * The WordPress functions the code under test calls.
 *
 * Deliberately thin. Where core's real behaviour is intricate
 * (`sanitize_text_field`, `esc_url_raw`, `strip_shortcodes`) the stub covers the
 * shape the plugin relies on and nothing more, and the tests are written so that
 * no assertion turns on the difference. See tests/bootstrap.php.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

use Flexa\SeoAeo\Tests\Stubs\WpState;

// phpcs:disable

/* -------------------------------------------------------------------------
 * Options
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default_value = false ): mixed {
		$value = array_key_exists( $option, WpState::$options ) ? WpState::$options[ $option ] : $default_value;

		// Core runs `option_{$option}` on every read, which is what makes the
		// Settings memo observable: a filter added after the first read cannot
		// be seen until the memo is flushed.
		return WpState::filter( 'option_' . $option, $value );
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $option, mixed $value = '' ): bool {
		WpState::$options[ $option ] = $value;
		do_action( 'add_option_' . $option, $option, $value );

		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value ): bool {
		$old = WpState::$options[ $option ] ?? false;
		WpState::$options[ $option ] = $value;
		do_action( 'update_option_' . $option, $old, $value, $option );

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( WpState::$options[ $option ] );
		do_action( 'deleted_option', $option );

		return true;
	}
}

/* -------------------------------------------------------------------------
 * Hooks
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $accepted_args );
		WpState::$hooks[ $hook ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return WpState::filter( $hook, $value, $args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
		WpState::fire( $hook );
		WpState::$doing_action[ $hook ] = true;

		$priorities = WpState::$hooks[ $hook ] ?? [];
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callback( ...$args );
			}
		}

		WpState::$doing_action[ $hook ] = false;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( string $hook, mixed $callback = false ): bool {
		unset( $callback );

		return ! empty( WpState::$hooks[ $hook ] );
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( string $hook ): int {
		return WpState::$did_action[ $hook ] ?? 0;
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( ?string $hook = null ): bool {
		return true === ( WpState::$doing_action[ (string) $hook ] ?? false );
	}
}

/* -------------------------------------------------------------------------
 * Registries
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( mixed $args = [], string $output = 'names' ): array {
		unset( $args, $output );

		return WpState::$post_types;
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( mixed $args = [], string $output = 'names' ): array {
		unset( $args, $output );

		return WpState::$taxonomies;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = [] ): array {
		WpState::$get_posts_args[] = $args;

		return array_shift( WpState::$get_posts_results ) ?? [];
	}
}

/* -------------------------------------------------------------------------
 * Sanitizers and escapers
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		$str = strip_tags( $str );
		$str = (string) preg_replace( '/[\r\n\t ]+/', ' ', $str );

		return trim( $str );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		$str = strip_tags( $str );
		$str = str_replace( "\r\n", "\n", $str );

		return trim( $str );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		return in_array( $scheme, [ 'http', 'https', '' ], true ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );

		if ( $remove_breaks ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	/**
	 * Removes the tags listed in WpState::$shortcodes and leaves everything else
	 * alone, which is the half of core's behaviour that matters here: an
	 * unregistered tag survives this call and has to be dealt with downstream.
	 */
	function strip_shortcodes( string $content ): string {
		foreach ( WpState::$shortcodes as $tag ) {
			$quoted  = preg_quote( $tag, '~' );
			$content = (string) preg_replace( '~\[' . $quoted . '\b[^\]]*\](.*?)\[/' . $quoted . '\]~s', '', $content );
			$content = (string) preg_replace( '~\[/?' . $quoted . '\b[^\]]*\]~', '', $content );
		}

		return $content;
	}
}

/* -------------------------------------------------------------------------
 * Query and context
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( string $var, mixed $default_value = '' ): mixed {
		return WpState::$query_vars[ $var ] ?? $default_value;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
		unset( $filter );

		return WpState::$bloginfo[ $show ] ?? '';
	}
}

if ( ! function_exists( 'get_search_query' ) ) {
	function get_search_query( bool $escaped = true ): string {
		unset( $escaped );

		return WpState::$search_query;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( mixed $post_types = '' ): bool {
		unset( $post_types );

		return WpState::$is_singular;
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool {
		return WpState::$is_front_page;
	}
}

if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool {
		return WpState::$is_home;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( mixed $post = 0 ): string {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;

		return WpState::$post_titles[ $id ] ?? '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( mixed $post = 0 ): string|false {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;

		return WpState::$permalinks[ $id ] ?? false;
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool {
		return WpState::$is_404;
	}
}

if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object(): mixed {
		return WpState::$queried_object;
	}
}

if ( ! function_exists( 'post_password_required' ) ) {
	function post_password_required( mixed $post = null ): bool {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;

		return in_array( $id, WpState::$password_required, true );
	}
}

if ( ! function_exists( 'has_excerpt' ) ) {
	function has_excerpt( mixed $post = 0 ): bool {
		$excerpt = is_object( $post ) ? (string) ( $post->post_excerpt ?? '' ) : '';

		return '' !== trim( $excerpt );
	}
}

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return rtrim( WpState::$home_url, '/' ) . '/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'user_trailingslashit' ) ) {
	function user_trailingslashit( string $url, string $type_of_url = '' ): string {
		unset( $type_of_url );
		$structure = (string) ( WpState::$options['permalink_structure'] ?? '' );

		return str_ends_with( $structure, '/' ) ? trailingslashit( $url ) : untrailingslashit( $url );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( mixed ...$args ): string {
		[ $key, $value, $url ] = [ (string) $args[0], (string) $args[1], (string) $args[2] ];
		$glue                  = str_contains( $url, '?' ) ? '&' : '?';

		return $url . $glue . $key . '=' . rawurlencode( $value );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		unset( $domain );

		return $text;
	}
}
