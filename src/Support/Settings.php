<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the `flexa_seo_aeo_settings` option: the typed
 * schema, defaults, type-coerced reads, and the sanitizer used on every write.
 * The REST controller, frontend meta/sitemap services, the AEO (llms.txt)
 * generator, and the CLI all go through here so the schema can never drift.
 */
final class Settings {
	public const OPTION_KEY = 'flexa_seo_aeo_settings';

	/**
	 * Module on/off toggles. Booleans default off so the plugin is inert until
	 * the user opts each feature in.
	 *
	 * @var list<string>
	 */
	private const BOOL_KEYS = [
		'titles_metas',        // Output <title> + meta description.
		'open_graph',          // Facebook/OG tags.
		'twitter_cards',       // X (Twitter) Card tags.
		'xml_sitemap',         // XML sitemap index + sub-sitemaps.
		'xml_sitemap_images',  // Include <image:image> entries.
		'html_sitemap',        // Human-readable HTML sitemap.
		'breadcrumbs',         // Breadcrumb trail + BreadcrumbList schema.
		'image_seo_alt',       // Auto-fill missing image alt text.
		'indexnow',            // Ping IndexNow on publish/update for instant indexing.
		'whitelabel',          // Replace the "Flexa SEO" branding in wp-admin.
		'strip_category_base',       // Remove /category/ from category archive URLs.
		'redirect_attachments',      // Redirect attachment pages to their parent post.
		'nofollow_external_links',   // Add rel="nofollow" to external links in content.
		'nofollow_image_links',      // Add rel="nofollow" to external image-file links.
		'new_window_external_links', // Add target="_blank" to external links in content.
	];

	/**
	 * Allowed title separators, keyed by the value we store.
	 *
	 * @var list<string>
	 */
	private const SEPARATORS = [ '-', '–', '—', '·', '•', '|', '/', '»', '>' ];

	/**
	 * @var list<string>
	 */
	private const TWITTER_CARD_TYPES = [ 'summary', 'summary_large_image' ];

	/**
	 * @var list<string>
	 */
	private const KNOWLEDGE_TYPES = [ 'organization', 'person' ];

	/**
	 * Defaults for the nested `aeo` group — the plugin's core differentiator.
	 * All inert by default; the AEO endpoints self-gate on `enabled`.
	 *
	 * @return array<string, bool|string>
	 */
	public static function aeo_defaults(): array {
		return [
			'enabled'           => false, // Serve /llms.txt.
			'agent_readiness'   => false, // Advertise plain-text/markdown variants to AI agents.
			'plain_text_export' => false, // Expose ?flexa-aeo=md plain-text rendering of content.
			'schema'            => false, // Emit AEO JSON-LD (Article/FAQPage/HowTo/WebSite).
			'commerce'          => false, // Emit WooCommerce Product/Offer JSON-LD + OG product tags.
			'site_name'         => '',    // Overrides the site title in llms.txt.
			'site_description'  => '',     // Short summary AI answer engines quote.
		];
	}

	/**
	 * The full default settings payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$defaults = [];
		foreach ( self::BOOL_KEYS as $key ) {
			$defaults[ $key ] = false;
		}

		$defaults['separator']          = '-';
		$defaults['home_title']         = '';
		$defaults['home_description']   = '';
		$defaults['twitter_card_type']  = 'summary_large_image';
		$defaults['og_default_image']   = '';
		$defaults['knowledge_type']     = 'organization';
		$defaults['knowledge_name']     = '';
		$defaults['sitemap_post_types'] = [ 'post', 'page' ];
		$defaults['sitemap_taxonomies'] = [ 'category', 'post_tag' ];
		$defaults['redirect_orphan_attachments'] = ''; // Fallback URL for attachments without a parent post.
		$defaults['robots_txt']         = '';   // Extra lines appended to the virtual robots.txt.
		$defaults['indexnow_key']       = '';   // Auto-generated on first IndexNow submission.
		$defaults['whitelabel_name']    = '';   // Overrides the admin menu / brand label when white-label is on.
		$defaults['aeo']                = self::aeo_defaults();

		return $defaults;
	}

	/**
	 * Stored settings merged over defaults, every value coerced back to its
	 * declared type so a hand-edited or corrupt option can never hand a
	 * consumer the wrong type.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return self::coerce( $stored );
	}

	/**
	 * Read a single setting with its default fallback.
	 */
	public static function get( string $key ): mixed {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * The admin-facing brand label. When white-label is on and a name is set,
	 * that name is used everywhere the plugin identifies itself in wp-admin
	 * (menu, page title, in-app brand strip); otherwise the default "Flexa SEO".
	 */
	public static function brand_name(): string {
		$all = self::all();
		if ( ! empty( $all['whitelabel'] ) ) {
			$name = is_string( $all['whitelabel_name'] ?? null ) ? trim( $all['whitelabel_name'] ) : '';
			if ( '' !== $name ) {
				return $name;
			}
		}

		return 'Flexa SEO';
	}

	/**
	 * Read a single value from the nested `aeo` group with its default fallback.
	 * The group is always coerced to its full shape, so this never returns null
	 * for a known key.
	 */
	public static function get_aeo( string $key ): mixed {
		$aeo = self::all()['aeo'];

		return is_array( $aeo ) ? ( $aeo[ $key ] ?? null ) : null;
	}

	/**
	 * Sanitize an incoming (possibly partial) payload against the schema.
	 * Unknown keys are dropped so arbitrary client input is never persisted.
	 *
	 * @param array<string, mixed> $incoming
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $incoming ): array {
		$clean = [];

		foreach ( self::BOOL_KEYS as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$clean[ $key ] = self::to_bool( $incoming[ $key ] );
			}
		}

		if ( array_key_exists( 'separator', $incoming ) ) {
			$clean['separator'] = self::pick( $incoming['separator'], self::SEPARATORS, '-' );
		}

		if ( array_key_exists( 'home_title', $incoming ) ) {
			$clean['home_title'] = sanitize_text_field( (string) $incoming['home_title'] );
		}

		if ( array_key_exists( 'home_description', $incoming ) ) {
			$clean['home_description'] = sanitize_text_field( (string) $incoming['home_description'] );
		}

		if ( array_key_exists( 'twitter_card_type', $incoming ) ) {
			$clean['twitter_card_type'] = self::pick( $incoming['twitter_card_type'], self::TWITTER_CARD_TYPES, 'summary_large_image' );
		}

		if ( array_key_exists( 'og_default_image', $incoming ) ) {
			$clean['og_default_image'] = esc_url_raw( (string) $incoming['og_default_image'] );
		}

		if ( array_key_exists( 'knowledge_type', $incoming ) ) {
			$clean['knowledge_type'] = self::pick( $incoming['knowledge_type'], self::KNOWLEDGE_TYPES, 'organization' );
		}

		if ( array_key_exists( 'knowledge_name', $incoming ) ) {
			$clean['knowledge_name'] = sanitize_text_field( (string) $incoming['knowledge_name'] );
		}

		if ( array_key_exists( 'sitemap_post_types', $incoming ) ) {
			$clean['sitemap_post_types'] = self::sanitize_post_types( $incoming['sitemap_post_types'] );
		}

		if ( array_key_exists( 'sitemap_taxonomies', $incoming ) ) {
			$clean['sitemap_taxonomies'] = self::sanitize_taxonomies( $incoming['sitemap_taxonomies'] );
		}

		if ( array_key_exists( 'redirect_orphan_attachments', $incoming ) ) {
			$clean['redirect_orphan_attachments'] = esc_url_raw( (string) $incoming['redirect_orphan_attachments'] );
		}

		if ( array_key_exists( 'robots_txt', $incoming ) ) {
			// Plain-text robots.txt directives only (standard SEO-plugin feature).
			// sanitize_textarea_field() strips any HTML/tags while keeping the
			// line breaks robots.txt needs; the value is later served as
			// text/plain via the core `robots_txt` filter (see Actions\RobotsTxt),
			// so it is not — and cannot become — executable CSS/JS/PHP.
			$clean['robots_txt'] = sanitize_textarea_field( (string) $incoming['robots_txt'] );
		}

		if ( array_key_exists( 'indexnow_key', $incoming ) ) {
			$clean['indexnow_key'] = self::sanitize_token( $incoming['indexnow_key'] );
		}

		if ( array_key_exists( 'whitelabel_name', $incoming ) ) {
			$clean['whitelabel_name'] = sanitize_text_field( (string) $incoming['whitelabel_name'] );
		}

		if ( array_key_exists( 'aeo', $incoming ) && is_array( $incoming['aeo'] ) ) {
			$clean['aeo'] = self::sanitize_aeo( $incoming['aeo'] );
		}

		return $clean;
	}

	/**
	 * Sanitize the nested `aeo` group, merging the (possibly partial) payload
	 * over the stored values so a screen that submits one toggle never wipes
	 * the rest — the outer merge in the REST controller is shallow and would
	 * otherwise replace the whole sub-array.
	 *
	 * @param array<string, mixed> $incoming
	 * @return array<string, bool|string>
	 */
	private static function sanitize_aeo( array $incoming ): array {
		$out = self::coerce_aeo( self::all()['aeo'] ?? [] );

		foreach ( [ 'enabled', 'agent_readiness', 'plain_text_export', 'schema', 'commerce' ] as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$out[ $key ] = self::to_bool( $incoming[ $key ] );
			}
		}

		if ( array_key_exists( 'site_name', $incoming ) ) {
			$out['site_name'] = sanitize_text_field( (string) $incoming['site_name'] );
		}

		if ( array_key_exists( 'site_description', $incoming ) ) {
			$out['site_description'] = sanitize_textarea_field( (string) $incoming['site_description'] );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array<string, mixed>
	 */
	private static function coerce( array $stored ): array {
		$out = self::defaults();

		foreach ( self::BOOL_KEYS as $key ) {
			if ( array_key_exists( $key, $stored ) ) {
				$out[ $key ] = self::to_bool( $stored[ $key ] );
			}
		}

		if ( isset( $stored['separator'] ) ) {
			$out['separator'] = self::pick( $stored['separator'], self::SEPARATORS, '-' );
		}
		if ( isset( $stored['home_title'] ) && is_string( $stored['home_title'] ) ) {
			$out['home_title'] = $stored['home_title'];
		}
		if ( isset( $stored['home_description'] ) && is_string( $stored['home_description'] ) ) {
			$out['home_description'] = $stored['home_description'];
		}
		if ( isset( $stored['twitter_card_type'] ) ) {
			$out['twitter_card_type'] = self::pick( $stored['twitter_card_type'], self::TWITTER_CARD_TYPES, 'summary_large_image' );
		}
		if ( isset( $stored['og_default_image'] ) && is_string( $stored['og_default_image'] ) ) {
			$out['og_default_image'] = $stored['og_default_image'];
		}
		if ( isset( $stored['knowledge_type'] ) ) {
			$out['knowledge_type'] = self::pick( $stored['knowledge_type'], self::KNOWLEDGE_TYPES, 'organization' );
		}
		if ( isset( $stored['knowledge_name'] ) && is_string( $stored['knowledge_name'] ) ) {
			$out['knowledge_name'] = $stored['knowledge_name'];
		}
		if ( isset( $stored['sitemap_post_types'] ) ) {
			$out['sitemap_post_types'] = self::sanitize_post_types( $stored['sitemap_post_types'] );
		}
		if ( isset( $stored['sitemap_taxonomies'] ) ) {
			$out['sitemap_taxonomies'] = self::sanitize_taxonomies( $stored['sitemap_taxonomies'] );
		}
		if ( isset( $stored['redirect_orphan_attachments'] ) && is_string( $stored['redirect_orphan_attachments'] ) ) {
			$out['redirect_orphan_attachments'] = $stored['redirect_orphan_attachments'];
		}
		if ( isset( $stored['robots_txt'] ) && is_string( $stored['robots_txt'] ) ) {
			$out['robots_txt'] = $stored['robots_txt'];
		}
		if ( isset( $stored['indexnow_key'] ) ) {
			$out['indexnow_key'] = self::sanitize_token( $stored['indexnow_key'] );
		}
		if ( isset( $stored['whitelabel_name'] ) && is_string( $stored['whitelabel_name'] ) ) {
			$out['whitelabel_name'] = $stored['whitelabel_name'];
		}

		$out['aeo'] = self::coerce_aeo( $stored['aeo'] ?? [] );

		return $out;
	}

	/**
	 * Coerce a stored `aeo` sub-array back to its declared types, filling any
	 * missing key from defaults so consumers always see the full shape.
	 *
	 * @param mixed $stored
	 * @return array<string, bool|string>
	 */
	private static function coerce_aeo( mixed $stored ): array {
		$out = self::aeo_defaults();
		if ( ! is_array( $stored ) ) {
			return $out;
		}

		foreach ( [ 'enabled', 'agent_readiness', 'plain_text_export', 'schema', 'commerce' ] as $key ) {
			if ( array_key_exists( $key, $stored ) ) {
				$out[ $key ] = self::to_bool( $stored[ $key ] );
			}
		}
		if ( isset( $stored['site_name'] ) && is_string( $stored['site_name'] ) ) {
			$out['site_name'] = $stored['site_name'];
		}
		if ( isset( $stored['site_description'] ) && is_string( $stored['site_description'] ) ) {
			$out['site_description'] = $stored['site_description'];
		}

		return $out;
	}

	/**
	 * Coerce a value to one of an allowed set, falling back to a default.
	 *
	 * @param mixed        $value
	 * @param list<string> $allowed
	 */
	private static function pick( mixed $value, array $allowed, string $fallback ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sitemap post types: sanitized, then checked against what is actually
	 * registered, so the stored value can never name a type the UI has no
	 * checkbox for.
	 *
	 * @param mixed $value
	 * @return list<string>
	 */
	private static function sanitize_post_types( mixed $value ): array {
		return self::registered_only( self::sanitize_keys( $value ), get_post_types() );
	}

	/**
	 * Sitemap taxonomies, same treatment as {@see self::sanitize_post_types()}.
	 *
	 * @param mixed $value
	 * @return list<string>
	 */
	private static function sanitize_taxonomies( mixed $value ): array {
		return self::registered_only( self::sanitize_keys( $value ), get_taxonomies() );
	}

	/**
	 * Drop the keys that name nothing real.
	 *
	 * Both registries only fill up during `init`, and `all()` runs earlier than
	 * that for anything reading a setting on `plugins_loaded`. Before `init`
	 * the list is handed back untouched, because at that point an unknown slug
	 * means "has not registered yet", not "does not exist", and dropping a
	 * site's custom post type from its own sitemap would be the worse bug.
	 *
	 * @param list<string>             $keys
	 * @param array<array-key, string> $registered Slugs from the WP registry.
	 * @return list<string>
	 */
	private static function registered_only( array $keys, array $registered ): array {
		if ( [] === $keys || ! did_action( 'init' ) ) {
			return $keys;
		}

		return array_values( array_intersect( $keys, array_values( $registered ) ) );
	}

	/**
	 * Sanitize a list of WordPress object keys (post type / taxonomy slugs).
	 *
	 * @param mixed $value
	 * @return list<string>
	 */
	private static function sanitize_keys( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$out = [];
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$item = sanitize_key( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize an opaque token (the IndexNow key) down to a URL/file-safe set.
	 */
	private static function sanitize_token( mixed $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return (string) preg_replace( '/[^a-zA-Z0-9-]/', '', $value );
	}

	/**
	 * Deterministic boolean coercion mirroring rest_sanitize_boolean()
	 * semantics. Kept local so the schema never depends on a templated WP stub
	 * that static analysis cannot resolve from a mixed value.
	 */
	private static function to_bool( mixed $value ): bool {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, [ 'false', '0', '', 'off', 'no' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
