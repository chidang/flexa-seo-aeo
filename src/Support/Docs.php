<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Where the published documentation lives, in one place.
 *
 * Two things this plugin shows you are judgement calls rather than facts: the
 * weight on each readiness check, and the claim that another SEO plugin will
 * duplicate your head tags. Both are written up in full on the documentation
 * site, and the point of these links is to put that writing one click from the
 * score or the warning it explains, instead of leaving it somewhere the reader
 * has to go looking for.
 *
 * Nothing here is fetched at runtime. These are plain outbound links the user
 * chooses to follow, so no request leaves the site unless they click.
 */
final class Docs {
	private const BASE = 'https://flexa-seo-aeo.vercel.app/';

	/** The scoring rubric: every check's weight, thresholds and reasoning. */
	public static function rubric(): string {
		return self::url( '' );
	}

	/** The tested compatibility matrix for the other major SEO plugins. */
	public static function compatibility(): string {
		return self::url( 'compatibility' );
	}

	/**
	 * The links to hand a script, or null when there are none to show.
	 *
	 * A white-labelled install returns null: pointing somebody else's client at
	 * our documentation site defeats the whole point of the setting. Such a site
	 * can route the links to its own copy through the filter below instead of
	 * losing them.
	 *
	 * @return array{rubric: string, compatibility: string}|null
	 */
	public static function for_script(): ?array {
		if ( ! self::is_visible() ) {
			return null;
		}

		return [
			'rubric'        => self::rubric(),
			'compatibility' => self::compatibility(),
		];
	}

	public static function is_visible(): bool {
		return empty( Settings::all()['whitelabel'] );
	}

	private static function url( string $path ): string {
		/**
		 * Filters the base URL of the published documentation, so a white-label
		 * or intranet install can point the in-product links at its own copy.
		 *
		 * @param string $base Base URL, with a trailing slash.
		 */
		$base = (string) apply_filters( 'flexa_seo_aeo/docs/base_url', self::BASE );
		$base = '' !== $base ? trailingslashit( $base ) : self::BASE;

		return esc_url_raw( $base . ltrim( $path, '/' ) );
	}
}
