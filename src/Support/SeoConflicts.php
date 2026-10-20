<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The SEO plugins that collide with this one when both are active.
 *
 * Kept apart from the migration sources in
 * {@see \Flexa\SeoAeo\Services\Migration\LegacyMapper} on purpose. Those two
 * lists answer different questions: "can we import your old data" and "is
 * something else writing the same tags right now". All in One SEO is the
 * clear case: it collides with this plugin, but there is nothing of its to
 * import.
 *
 * Each entry lists every main-file path the plugin has shipped under, because
 * folder names differ between the free and pro builds.
 */
final class SeoConflicts {
	/**
	 * id => label + candidate main files.
	 *
	 * @var array<string, array{label: string, files: list<string>}>
	 */
	private const PLUGINS = [
		'yoast'    => [
			'label' => 'Yoast SEO',
			'files' => [ 'wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php' ],
		],
		'rankmath' => [
			'label' => 'Rank Math',
			'files' => [ 'seo-by-rank-math/rank-math.php', 'seo-by-rank-math-pro/rank-math-pro.php' ],
		],
		'seopress' => [
			'label' => 'SEOPress',
			'files' => [ 'wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php', 'seopress/seopress.php' ],
		],
		'aioseo'   => [
			'label' => 'All in One SEO',
			'files' => [ 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ],
		],
	];

	/**
	 * Every known SEO plugin currently active, as id => label.
	 *
	 * @return array<string, string>
	 */
	public static function active(): array {
		$active = [];

		foreach ( self::PLUGINS as $id => $plugin ) {
			foreach ( $plugin['files'] as $file ) {
				if ( self::is_active( $file ) ) {
					$active[ $id ] = $plugin['label'];
					break;
				}
			}
		}

		return $active;
	}

	/**
	 * Whether a given id is active. Used by the onboarding detector, which
	 * works in migration-source ids.
	 */
	public static function is_plugin_active( string $id ): bool {
		return isset( self::active()[ $id ] );
	}

	/**
	 * A stable fingerprint of what is active right now, so a dismissal applies
	 * to the situation the user saw and a newly activated plugin raises the
	 * notice again instead of inheriting someone else's "don't show me this".
	 */
	public static function signature(): string {
		$ids = array_keys( self::active() );
		sort( $ids );

		return implode( ',', $ids );
	}

	private static function is_active( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}
}
