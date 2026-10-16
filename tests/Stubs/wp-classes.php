<?php
/**
 * The WordPress classes the code under test type-checks against.
 *
 * Only the members the plugin touches are declared. `WP_Query::generate_postdata()`
 * is the interesting one: `Metas::paginates_to()` asks core how many pages a post
 * splits into, so the stub lets a test say what core would answer without
 * reproducing core's pagination parser.
 *
 * @package Flexa\SeoAeo
 */

declare(strict_types=1);

// phpcs:disable

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_type = 'post';
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_password = '';
		public string $post_status = 'publish';
		public string $post_date_gmt = '2026-01-01 00:00:00';
		public string $post_modified_gmt = '2026-01-01 00:00:00';
		public int $post_parent = 0;
		public int $post_author = 1;

		/**
		 * @param array<string, mixed> $props
		 */
		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public int $max_num_pages = 1;
		public int $found_posts = 0;

		/**
		 * What `generate_postdata()` hands back, or null to return false the way
		 * core does for something that is not a post.
		 *
		 * @var array<string, mixed>|null
		 */
		public ?array $postdata = [ 'numpages' => 1 ];

		/**
		 * Query vars set on this query object.
		 *
		 * @var array<string, mixed>
		 */
		public array $query_vars = [];

		/**
		 * @param array<string, mixed> $args
		 */
		public function __construct( array $args = [] ) {
			$this->query_vars = $args;
		}

		public function get( string $key, mixed $default_value = '' ): mixed {
			return $this->query_vars[ $key ] ?? $default_value;
		}

		public function set( string $key, mixed $value ): void {
			$this->query_vars[ $key ] = $value;
		}

		/**
		 * @return array<string, mixed>|false
		 */
		public function generate_postdata( mixed $post ): array|false {
			unset( $post );

			return null === $this->postdata ? false : $this->postdata;
		}

		public function set_404(): void {
			\Flexa\SeoAeo\Tests\Stubs\WpState::$is_404 = true;
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id = 0;
		public string $name = '';
		public string $slug = '';
		public string $taxonomy = 'category';
		public int $parent = 0;
		public string $description = '';

		/**
		 * @param array<string, mixed> $props
		 */
		public function __construct( array $props = [] ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public int $ID = 0;
		public string $display_name = '';
		public string $description = '';
	}
}

if ( ! class_exists( 'WP_Post_Type' ) ) {
	class WP_Post_Type {
		public string $name = '';
		public object $labels;

		public function __construct( string $name = '', string $label = '' ) {
			$this->name   = $name;
			$this->labels = (object) [ 'name' => $label ];
		}
	}
}

if ( ! class_exists( 'WP_Rewrite' ) ) {
	class WP_Rewrite {
		public string $pagination_base = 'page';
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

/**
 * Just enough of `$wpdb` for the sitemap's noindex subquery: the table names it
 * interpolates and a `prepare()` that fills placeholders the way core does for
 * the two it uses. The SQL is asserted as a string, never executed.
 */
if ( ! class_exists( 'Flexa_Test_Wpdb' ) ) {
	class Flexa_Test_Wpdb {
		public string $posts    = 'wp_posts';
		public string $postmeta = 'wp_postmeta';
		public string $options  = 'wp_options';

		/**
		 * @param array<int, mixed> $args
		 */
		public function prepare( string $query, ...$args ): string {
			foreach ( $args as $arg ) {
				$replacement = is_int( $arg ) || is_float( $arg )
					? (string) $arg
					: "'" . str_replace( "'", "\\'", (string) $arg ) . "'";
				$query       = (string) preg_replace( '/%[sdf]/', $replacement, $query, 1 );
			}

			return $query;
		}
	}
}
