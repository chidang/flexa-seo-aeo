<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the admin app needs to know about Flexa Cache, our page-cache
 * plugin. Flexa SEO builds title tags, meta, JSON-LD and sitemap entries while
 * WordPress is assembling a page. A page cache is what stops that work from
 * running again for the next visitor, which is why the suggestion lives here
 * rather than in the readme where nobody who already installed us would read it.
 *
 * It is deliberately quiet. Nothing is suggested when Flexa Cache is already
 * running, nor when the white-label setting is on: an agency hiding our
 * branding from its client does not want a second brand proposed in its place.
 */
final class CachePlugin {
	/** WordPress.org slug, which doubles as the install-link argument. */
	public const SLUG = 'flexa-cache';

	/** Plugin file relative to WP_PLUGIN_DIR. */
	private const FILE = 'flexa-cache/flexa-cache.php';

	/**
	 * Option recording that an administrator dismissed the dashboard card. A
	 * missing option means never dismissed, so this costs nothing on a fresh
	 * install. Not autoloaded: it is read on one admin screen and nowhere else.
	 */
	public const OPTION_KEY = 'flexa_seo_aeo_cache_hint_dismissed';

	/**
	 * The payload handed to the admin app through the `flexaSeoAeo` global, or
	 * null when nothing should be suggested at all. The Tools row honours
	 * `status` only; the dashboard card also honours `dismissed`, so hiding the
	 * card does not hide the quieter link.
	 *
	 * @return array{status: string, dismissed: bool, actionUrl: string, infoUrl: string}|null
	 */
	public static function hint(): ?array {
		$settings = Settings::all();
		if ( ! empty( $settings['whitelabel'] ) ) {
			return null;
		}

		$status = self::status();
		if ( 'active' === $status ) {
			return null;
		}

		return [
			'status'    => $status,
			'dismissed' => self::is_dismissed(),
			'actionUrl' => self::action_url( $status ),
			'infoUrl'   => 'https://wordpress.org/plugins/' . self::SLUG . '/',
		];
	}

	/**
	 * 'active', 'inactive' (installed but switched off) or 'missing'.
	 */
	public static function status(): string {
		self::load_plugin_api();

		if ( is_plugin_active( self::FILE ) ) {
			return 'active';
		}

		return file_exists( WP_PLUGIN_DIR . '/' . self::FILE ) ? 'inactive' : 'missing';
	}

	public static function is_dismissed(): bool {
		return (bool) get_option( self::OPTION_KEY, false );
	}

	public static function dismiss(): void {
		update_option( self::OPTION_KEY, true, false );
	}

	/**
	 * The one-click link, which only ever activates an already-installed copy.
	 * There is deliberately no install link: pulling a second plugin down from
	 * inside this one is more than a suggestion should do, so when Flexa Cache
	 * is absent this returns an empty string and the caller sends the reader to
	 * the WordPress.org listing instead. It is also empty when the user cannot
	 * activate plugins, the normal case for a site administrator on multisite.
	 */
	private static function action_url( string $status ): string {
		if ( 'inactive' !== $status || ! current_user_can( 'activate_plugins' ) ) {
			return '';
		}

		return wp_nonce_url(
			self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( self::FILE ) ),
			'activate-plugin_' . self::FILE
		);
	}

	/**
	 * is_plugin_active() lives in an admin include that is not loaded on every
	 * request, not even in wp-admin.
	 */
	private static function load_plugin_api(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
